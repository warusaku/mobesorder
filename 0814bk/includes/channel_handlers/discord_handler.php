<?php
/**
 * DiscordHandler クラス
 * 
 * Discord Webhookを使用して通知を送信するハンドラークラス。
 */
class DiscordHandler {
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
     * Discord Webhookに通知を送信する
     * 
     * @param string $message メッセージ本文
     * @param array $options 追加オプション（画像含めるか、詳細情報含めるかなど）
     * @return bool 送信成功フラグ
     */
    public function send($message, $options = []) {
        try {
            // 設定チェック
            if (!isset($this->config['webhook_url']) || empty($this->config['webhook_url'])) {
                throw new Exception("Discord webhook URLが設定されていません");
            }
            
            $webhook_url = $this->config['webhook_url'];
            
            // メッセージの準備
            $payload = [
                'content' => $message
            ];
            
            // ユーザー名と画像設定
            if (isset($this->config['username'])) {
                $payload['username'] = $this->config['username'];
            }
            
            if (isset($this->config['avatar_url'])) {
                $payload['avatar_url'] = $this->config['avatar_url'];
            }
            
            // 添付ファイル（Embeds）の作成
            $embeds = [];
            
            // 判定結果の詳細情報を添付する
            if (!empty($options['include_details']) && isset($options['determination_result'])) {
                $result = $options['determination_result'];
                
                // 表示レベルに応じた色を設定（Discordは10進数で色を指定）
                $color = $this->getColorForDisplayLevel($result['display_level']);
                
                // 基本情報のフィールド
                $fields = [];
                
                // カメラとエリア情報
                $fields[] = [
                    'name' => 'カメラID',
                    'value' => $result['camera_id'],
                    'inline' => true
                ];
                
                $fields[] = [
                    'name' => 'エリアID',
                    'value' => $result['area_id'],
                    'inline' => true
                ];
                
                // OCR情報
                $fields[] = [
                    'name' => 'OCRテキスト',
                    'value' => !empty($result['ocr_text']) ? $result['ocr_text'] : '(空)',
                    'inline' => true
                ];
                
                // 数値がある場合
                if (isset($result['numerical_value']) && $result['numerical_value'] !== null) {
                    $fields[] = [
                        'name' => '数値',
                        'value' => (string)$result['numerical_value'],
                        'inline' => true
                    ];
                }
                
                // 表示/判定情報
                $fields[] = [
                    'name' => '表示種別',
                    'value' => $result['display_type'],
                    'inline' => true
                ];
                
                $fields[] = [
                    'name' => '表示レベル',
                    'value' => $result['display_level'],
                    'inline' => true
                ];
                
                if (isset($result['determination_type']) && $result['determination_type'] !== null) {
                    $fields[] = [
                        'name' => '判定種別',
                        'value' => $result['determination_type'],
                        'inline' => true
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
                        'name' => '閾値情報',
                        'value' => $threshold_info,
                        'inline' => false
                    ];
                }
                
                // 時刻情報
                $fields[] = [
                    'name' => '取得時刻',
                    'value' => $result['capture_time'],
                    'inline' => false
                ];
                
                // Embedの作成
                $embed = [
                    'title' => '判定結果詳細',
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => [
                        'text' => "判定結果ID: {$result['result_id']}"
                    ],
                    'timestamp' => date('c', strtotime($result['capture_time']))
                ];
                
                // 画像の追加（画像が含まれる場合）
                if (!empty($options['include_image']) && isset($result['image_path'])) {
                    $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                    $image_url = $base_url . $result['image_path'];
                    
                    // 画像URLをEmbedに追加
                    $embed['image'] = [
                        'url' => $image_url
                    ];
                }
                
                $embeds[] = $embed;
                $payload['embeds'] = $embeds;
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
            
            // 成功判定（Discordは成功時に200または204を返す）
            if ($http_code == 200 || $http_code == 204) {
                return true;
            } else {
                $this->last_error = "Discord API エラー: HTTPコード $http_code, レスポンス: $response";
                return false;
            }
            
        } catch (Exception $e) {
            $this->last_error = "Discord通知送信エラー: " . $e->getMessage();
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
     * Discordは10進数の整数値で色を指定する
     * 
     * @param string $display_level 表示レベル
     * @return int カラーコード（Discord形式）
     */
    private function getColorForDisplayLevel($display_level) {
        switch ($display_level) {
            case 'CRITICAL':
                return 15158332; // 赤 (#FF0000)
            case 'HIGH':
                return 16753920; // オレンジ (#FFA500)
            case 'MEDIUM':
                return 16776960; // 黄色 (#FFFF00)
            case 'LOW':
                return 65280;    // 緑 (#00FF00)
            case 'INFO':
                return 255;      // 青 (#0000FF)
            default:
                return 8421504;  // グレー (#808080)
        }
    }
    
    /**
     * 16進数のカラーコードを10進数に変換する
     * 
     * @param string $hex 16進数カラーコード（例: #FF0000）
     * @return int 10進数カラー値
     */
    private function hexToDecimal($hex) {
        $hex = ltrim($hex, '#');
        return hexdec($hex);
    }
    
    /**
     * ログ出力
     * 
     * @param string $message ログメッセージ
     */
    private function logger($message) {
        error_log("[DiscordHandler] " . $message);
    }
} 
 
 
 
 