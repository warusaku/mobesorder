<?php
/**
 * SlackHandler クラス
 * 
 * Slack Webhookを使用して通知を送信するハンドラークラス。
 */
class SlackHandler {
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
    }
    
    /**
     * Slack Webhookに通知を送信する
     * 
     * @param string $message メッセージ本文
     * @param array $options 追加オプション（画像含めるか、詳細情報含めるかなど）
     * @return bool 送信成功フラグ
     */
    public function send($message, $options = []) {
        try {
            // 設定チェック
            if (!isset($this->config['webhook_url']) || empty($this->config['webhook_url'])) {
                throw new Exception("Slack webhook URLが設定されていません");
            }
            
            $webhook_url = $this->config['webhook_url'];
            
            // チャンネル名を取得（設定されていれば）
            $channel = isset($this->config['channel']) ? $this->config['channel'] : null;
            
            // メッセージの準備
            $payload = [
                'text' => $message
            ];
            
            // チャンネルが設定されていれば追加
            if ($channel) {
                $payload['channel'] = $channel;
            }
            
            // ユーザー名と絵文字設定
            if (isset($this->config['username'])) {
                $payload['username'] = $this->config['username'];
            }
            
            if (isset($this->config['icon_emoji'])) {
                $payload['icon_emoji'] = $this->config['icon_emoji'];
            } elseif (isset($this->config['icon_url'])) {
                $payload['icon_url'] = $this->config['icon_url'];
            }
            
            // 添付ファイル（Attachments）の作成
            $attachments = [];
            
            // 判定結果の詳細情報を添付する
            if (!empty($options['include_details']) && isset($options['determination_result'])) {
                $result = $options['determination_result'];
                
                // 表示レベルに応じた色を設定
                $color = $this->getColorForDisplayLevel($result['display_level']);
                
                // フィールドの作成
                $fields = [];
                
                // 基本情報のフィールド
                $fields[] = [
                    'title' => 'カメラID',
                    'value' => $result['camera_id'],
                    'short' => true
                ];
                
                $fields[] = [
                    'title' => 'エリアID',
                    'value' => $result['area_id'],
                    'short' => true
                ];
                
                // OCR情報
                $fields[] = [
                    'title' => 'OCRテキスト',
                    'value' => !empty($result['ocr_text']) ? $result['ocr_text'] : '(空)',
                    'short' => true
                ];
                
                // 数値がある場合
                if (isset($result['numerical_value']) && $result['numerical_value'] !== null) {
                    $fields[] = [
                        'title' => '数値',
                        'value' => $result['numerical_value'],
                        'short' => true
                    ];
                }
                
                // 表示/判定情報
                $fields[] = [
                    'title' => '表示種別',
                    'value' => $result['display_type'],
                    'short' => true
                ];
                
                $fields[] = [
                    'title' => '表示レベル',
                    'value' => $result['display_level'],
                    'short' => true
                ];
                
                if (isset($result['determination_type']) && $result['determination_type'] !== null) {
                    $fields[] = [
                        'title' => '判定種別',
                        'value' => $result['determination_type'],
                        'short' => true
                    ];
                }
                
                // 閾値情報（該当する場合）
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
                    
                    $fields[] = [
                        'title' => '閾値情報',
                        'value' => $threshold_info,
                        'short' => false
                    ];
                }
                
                // 時刻情報
                $fields[] = [
                    'title' => '取得時刻',
                    'value' => $result['capture_time'],
                    'short' => false
                ];
                
                // Attachmentの作成
                $attachment = [
                    'fallback' => "OCR判定結果: {$result['ocr_text']}",
                    'color' => $color,
                    'title' => '判定結果詳細',
                    'fields' => $fields,
                    'footer' => "判定結果ID: {$result['result_id']}",
                    'ts' => strtotime($result['capture_time'])
                ];
                
                // 画像の追加（画像が含まれる場合）
                if (!empty($options['include_image']) && isset($result['image_path'])) {
                    $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                    $image_url = $base_url . $result['image_path'];
                    $attachment['image_url'] = $image_url;
                }
                
                $attachments[] = $attachment;
                $payload['attachments'] = $attachments;
            }
            
            // ブロックスタイルでのメッセージ構築（Slack API の現代的な方法）
            if (!empty($options['include_details']) && empty($attachments)) {
                $blocks = [];
                
                // セクションブロック (メインメッセージ)
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message
                    ]
                ];
                
                // 区切り線
                $blocks[] = [
                    'type' => 'divider'
                ];
                
                // 詳細情報セクション (判定結果がある場合)
                if (isset($options['determination_result'])) {
                    $result = $options['determination_result'];
                    
                    $detail_text = "*OCRテキスト:* " . (!empty($result['ocr_text']) ? $result['ocr_text'] : '(空)') . "\n";
                    $detail_text .= "*カメラID:* {$result['camera_id']} | *エリアID:* {$result['area_id']}\n";
                    $detail_text .= "*表示種別:* {$result['display_type']} | *表示レベル:* {$result['display_level']}\n";
                    
                    if (isset($result['determination_type']) && $result['determination_type'] !== null) {
                        $detail_text .= "*判定種別:* {$result['determination_type']}\n";
                    }
                    
                    $blocks[] = [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $detail_text
                        ]
                    ];
                    
                    // 画像の追加（画像が含まれる場合）
                    if (!empty($options['include_image']) && isset($result['image_path'])) {
                        $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                        $image_url = $base_url . $result['image_path'];
                        
                        $blocks[] = [
                            'type' => 'image',
                            'title' => [
                                'type' => 'plain_text',
                                'text' => 'OCR画像'
                            ],
                            'image_url' => $image_url,
                            'alt_text' => 'OCR対象画像'
                        ];
                    }
                }
                
                $payload['blocks'] = $blocks;
            }
            
            // JSONにエンコード
            $json_payload = json_encode($payload);
            
            // cURLを使用してHTTPリクエストを送信
            $ch = curl_init($webhook_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_payload)
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            $this->response_data = "HTTP Code: $http_code, Response: $response";
            
            if (curl_errno($ch)) {
                $this->last_error = "cURLエラー: " . curl_error($ch);
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            // 成功判定（Slackは成功時に「ok」を返す）
            if ($http_code == 200 && $response == 'ok') {
                return true;
            } else {
                $this->last_error = "Slack API エラー: HTTPコード $http_code, レスポンス: $response";
                return false;
            }
            
        } catch (Exception $e) {
            $this->last_error = "Slack通知送信エラー: " . $e->getMessage();
            return false;
        }
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
    
    /**
     * 表示レベルに応じた色コードを取得する
     * 
     * @param string $display_level 表示レベル
     * @return string カラーコード（Slack形式）
     */
    private function getColorForDisplayLevel($display_level) {
        switch ($display_level) {
            case 'CRITICAL':
                return '#FF0000'; // 赤
            case 'HIGH':
                return '#FFA500'; // オレンジ
            case 'MEDIUM':
                return '#FFFF00'; // 黄色
            case 'LOW':
                return '#00FF00'; // 緑
            case 'INFO':
                return '#0000FF'; // 青
            default:
                return '#808080'; // グレー
        }
    }
} 
 
 
 
 