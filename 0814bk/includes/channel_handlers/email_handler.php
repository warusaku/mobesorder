<?php
/**
 * EmailHandler クラス
 * 
 * 電子メール通知を送信するハンドラークラス。
 * PHPMailerを使用してメール送信を行います。
 */
class EmailHandler {
    private $config;
    private $last_error;
    private $response_data;
    
    /**
     * コンストラクタ
     * 
     * @param string|array $config チャネル設定（JSON文字列または配列）
     */
    public function __construct($config) {
        if (is_string($config)) {
            $this->config = json_decode($config, true);
        } else {
            $this->config = $config;
        }
        
        $this->last_error = null;
        $this->response_data = null;
        
        // PHPMailerのクラスをロード
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    }
    
    /**
     * メール通知を送信する
     * 
     * @param string $message メッセージ本文
     * @param array $options 追加オプション（画像含めるか、詳細情報含めるかなど）
     * @return bool 送信成功フラグ
     */
    public function send($message, $options = []) {
        try {
            // 設定チェック
            if (!isset($this->config['smtp_host']) || empty($this->config['smtp_host'])) {
                throw new Exception("SMTP ホストが設定されていません");
            }
            
            if (!isset($this->config['recipients']) || empty($this->config['recipients'])) {
                throw new Exception("メール受信者が設定されていません");
            }
            
            // PHPMailerのインスタンス化
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTPサーバー設定
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = isset($this->config['smtp_auth']) ? (bool)$this->config['smtp_auth'] : true;
            
            if ($mail->SMTPAuth) {
                $mail->Username = $this->config['smtp_username'] ?? '';
                $mail->Password = $this->config['smtp_password'] ?? '';
            }
            
            // TLS/SSL設定
            if (isset($this->config['smtp_secure']) && $this->config['smtp_secure'] !== '') {
                $mail->SMTPSecure = $this->config['smtp_secure']; // tls or ssl
            }
            
            // ポート設定
            $mail->Port = $this->config['smtp_port'] ?? 25;
            
            // デバッグレベル設定
            $mail->SMTPDebug = 0; // デバッグ出力を無効化
            
            // 送信者設定
            $from_email = $this->config['from_email'] ?? 'rtsp-ocr@example.com';
            $from_name = $this->config['from_name'] ?? 'RTSP OCR System';
            $mail->setFrom($from_email, $from_name);
            
            // 返信先設定
            if (isset($this->config['reply_to']) && !empty($this->config['reply_to'])) {
                $mail->addReplyTo($this->config['reply_to'], $this->config['reply_to_name'] ?? '');
            }
            
            // 受信者の設定
            if (is_array($this->config['recipients'])) {
                foreach ($this->config['recipients'] as $recipient) {
                    if (is_array($recipient)) {
                        $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
                    } else {
                        $mail->addAddress($recipient);
                    }
                }
            } else {
                $recipients = explode(',', $this->config['recipients']);
                foreach ($recipients as $recipient) {
                    $mail->addAddress(trim($recipient));
                }
            }
            
            // CC設定
            if (isset($this->config['cc']) && !empty($this->config['cc'])) {
                if (is_array($this->config['cc'])) {
                    foreach ($this->config['cc'] as $cc) {
                        if (is_array($cc)) {
                            $mail->addCC($cc['email'], $cc['name'] ?? '');
                        } else {
                            $mail->addCC($cc);
                        }
                    }
                } else {
                    $ccs = explode(',', $this->config['cc']);
                    foreach ($ccs as $cc) {
                        $mail->addCC(trim($cc));
                    }
                }
            }
            
            // BCC設定
            if (isset($this->config['bcc']) && !empty($this->config['bcc'])) {
                if (is_array($this->config['bcc'])) {
                    foreach ($this->config['bcc'] as $bcc) {
                        if (is_array($bcc)) {
                            $mail->addBCC($bcc['email'], $bcc['name'] ?? '');
                        } else {
                            $mail->addBCC($bcc);
                        }
                    }
                } else {
                    $bccs = explode(',', $this->config['bcc']);
                    foreach ($bccs as $bcc) {
                        $mail->addBCC(trim($bcc));
                    }
                }
            }
            
            // 文字セット設定
            $mail->CharSet = $this->config['charset'] ?? 'UTF-8';
            
            // メール形式設定（HTMLまたはテキスト）
            $use_html = isset($this->config['use_html']) ? (bool)$this->config['use_html'] : true;
            
            // 件名の設定
            $subject = $this->config['subject_prefix'] ?? 'RTSP OCR ';
            if (isset($options['determination_result'])) {
                $result = $options['determination_result'];
                $level = $result['display_level'] ?? 'INFO';
                $subject .= "[{$level}] ";
                
                if (isset($result['ocr_text']) && !empty($result['ocr_text'])) {
                    $subject .= $result['ocr_text'];
                } else {
                    $subject .= 'OCR結果通知';
                }
            } else {
                $subject .= '通知';
            }
            
            $mail->Subject = $subject;
            
            // メール本文の生成
            $body = '';
            
            if ($use_html) {
                $mail->isHTML(true);
                $body = $this->generateHtmlContent($message, $options);
            } else {
                $mail->isHTML(false);
                $body = $this->generateTextContent($message, $options);
            }
            
            $mail->Body = $body;
            
            // テキスト版も設定（HTMLメールの場合）
            if ($use_html) {
                $mail->AltBody = $this->generateTextContent($message, $options);
            }
            
            // 画像の添付（画像が含まれる場合）
            if (!empty($options['include_image']) && isset($options['determination_result']['image_path'])) {
                $image_path = $_SERVER['DOCUMENT_ROOT'] . $options['determination_result']['image_path'];
                
                if (file_exists($image_path)) {
                    $mail->addAttachment($image_path, 'ocr_image.jpg');
                }
            }
            
            // メールの送信
            $result = $mail->send();
            $this->response_data = "メール送信結果: " . ($result ? '成功' : '失敗');
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = "メール送信エラー: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * HTML形式のメール本文を生成する
     * 
     * @param string $message メッセージ本文
     * @param array $options オプション
     * @return string HTML形式のメール本文
     */
    private function generateHtmlContent($message, $options) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { max-height: 60px; }
        .footer { border-top: 1px solid #ddd; padding-top: 10px; margin-top: 20px; font-size: 12px; color: #777; }
        .message { margin-bottom: 20px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.details th { background-color: #f5f5f5; text-align: left; padding: 8px; border: 1px solid #ddd; }
        table.details td { padding: 8px; border: 1px solid #ddd; }
        .status-critical { color: #d9534f; font-weight: bold; }
        .status-high { color: #f0ad4e; font-weight: bold; }
        .status-medium { color: #f0ad4e; }
        .status-low { color: #5cb85c; }
        .status-info { color: #5bc0de; }
        .image-container { margin-top: 20px; }
        .image-container img { max-width: 100%; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>RTSP OCR システム通知</h2>
        </div>
        <div class="message">' . nl2br(htmlspecialchars($message)) . '</div>';
        
        // 判定結果の詳細情報を追加
        if (!empty($options['include_details']) && isset($options['determination_result'])) {
            $result = $options['determination_result'];
            
            $display_level = $result['display_level'] ?? 'INFO';
            $level_class = 'status-' . strtolower($display_level);
            
            $html .= '
        <h3>判定結果詳細</h3>
        <table class="details">
            <tr>
                <th>項目</th>
                <th>値</th>
            </tr>
            <tr>
                <th>OCRテキスト</th>
                <td>' . htmlspecialchars($result['ocr_text'] ?? '(空)') . '</td>
            </tr>
            <tr>
                <th>カメラID</th>
                <td>' . htmlspecialchars($result['camera_id'] ?? '') . '</td>
            </tr>
            <tr>
                <th>エリアID</th>
                <td>' . htmlspecialchars($result['area_id'] ?? '') . '</td>
            </tr>';
            
            if (isset($result['numerical_value']) && $result['numerical_value'] !== null) {
                $html .= '
            <tr>
                <th>数値</th>
                <td>' . htmlspecialchars($result['numerical_value']) . '</td>
            </tr>';
            }
            
            $html .= '
            <tr>
                <th>表示種別</th>
                <td>' . htmlspecialchars($result['display_type'] ?? '') . '</td>
            </tr>
            <tr>
                <th>表示レベル</th>
                <td class="' . $level_class . '">' . htmlspecialchars($display_level) . '</td>
            </tr>';
            
            if (isset($result['determination_type']) && $result['determination_type'] !== null) {
                $html .= '
            <tr>
                <th>判定種別</th>
                <td>' . htmlspecialchars($result['determination_type']) . '</td>
            </tr>';
            }
            
            if (isset($result['is_threshold_alert']) && $result['is_threshold_alert']) {
                $threshold_info = '';
                
                if (isset($result['threshold_min']) && $result['threshold_min'] !== null) {
                    $threshold_info .= "最小: {$result['threshold_min']} ";
                }
                
                if (isset($result['threshold_max']) && $result['threshold_max'] !== null) {
                    $threshold_info .= "最大: {$result['threshold_max']} ";
                }
                
                if (isset($result['condition_logic']) && $result['condition_logic'] !== null) {
                    $threshold_info .= "ロジック: {$result['condition_logic']}";
                }
                
                $html .= '
            <tr>
                <th>閾値情報</th>
                <td>' . htmlspecialchars($threshold_info) . '</td>
            </tr>';
            }
            
            $html .= '
            <tr>
                <th>取得時刻</th>
                <td>' . htmlspecialchars($result['capture_time'] ?? '') . '</td>
            </tr>
            <tr>
                <th>結果ID</th>
                <td>' . htmlspecialchars($result['result_id'] ?? '') . '</td>
            </tr>
        </table>';
            
            // 画像の埋め込み
            if (!empty($options['include_image']) && isset($result['image_path'])) {
                $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                $image_url = $base_url . $result['image_path'];
                
                $html .= '
        <div class="image-container">
            <h3>OCR対象画像</h3>
            <img src="' . htmlspecialchars($image_url) . '" alt="OCR対象画像">
        </div>';
            }
        }
        
        $html .= '
        <div class="footer">
            <p>このメールはRTSP OCR監視システムから自動送信されています。</p>
            <p>&copy; ' . date('Y') . ' MIJEOS - LACIS Project</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * テキスト形式のメール本文を生成する
     * 
     * @param string $message メッセージ本文
     * @param array $options オプション
     * @return string テキスト形式のメール本文
     */
    private function generateTextContent($message, $options) {
        $text = "RTSP OCR システム通知\n";
        $text .= "====================\n\n";
        $text .= $message . "\n\n";
        
        // 判定結果の詳細情報を追加
        if (!empty($options['include_details']) && isset($options['determination_result'])) {
            $result = $options['determination_result'];
            
            $text .= "判定結果詳細\n";
            $text .= "====================\n";
            $text .= "OCRテキスト: " . ($result['ocr_text'] ?? '(空)') . "\n";
            $text .= "カメラID: " . ($result['camera_id'] ?? '') . "\n";
            $text .= "エリアID: " . ($result['area_id'] ?? '') . "\n";
            
            if (isset($result['numerical_value']) && $result['numerical_value'] !== null) {
                $text .= "数値: " . $result['numerical_value'] . "\n";
            }
            
            $text .= "表示種別: " . ($result['display_type'] ?? '') . "\n";
            $text .= "表示レベル: " . ($result['display_level'] ?? 'INFO') . "\n";
            
            if (isset($result['determination_type']) && $result['determination_type'] !== null) {
                $text .= "判定種別: " . $result['determination_type'] . "\n";
            }
            
            if (isset($result['is_threshold_alert']) && $result['is_threshold_alert']) {
                $text .= "閾値情報: ";
                
                if (isset($result['threshold_min']) && $result['threshold_min'] !== null) {
                    $text .= "最小: {$result['threshold_min']} ";
                }
                
                if (isset($result['threshold_max']) && $result['threshold_max'] !== null) {
                    $text .= "最大: {$result['threshold_max']} ";
                }
                
                if (isset($result['condition_logic']) && $result['condition_logic'] !== null) {
                    $text .= "ロジック: {$result['condition_logic']}";
                }
                
                $text .= "\n";
            }
            
            $text .= "取得時刻: " . ($result['capture_time'] ?? '') . "\n";
            $text .= "結果ID: " . ($result['result_id'] ?? '') . "\n\n";
            
            // 画像URLを追加
            if (!empty($options['include_image']) && isset($result['image_path'])) {
                $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                $image_url = $base_url . $result['image_path'];
                
                $text .= "OCR対象画像: " . $image_url . "\n\n";
            }
        }
        
        $text .= "====================\n";
        $text .= "このメールはRTSP OCR監視システムから自動送信されています。\n";
        $text .= "© " . date('Y') . " MIJEOS - LACIS Project";
        
        return $text;
    }
    
    /**
     * 最後のエラーメッセージを取得する
     * 
     * @return string|null エラーメッセージ
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * レスポンスデータを取得する
     * 
     * @return string|null レスポンスデータ
     */
    public function getResponseData() {
        return $this->response_data;
    }
} 
 
 
 
 