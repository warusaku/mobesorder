<?php
/**
 * WebhookHandler クラス
 * 
 * 汎用のWebhook通知を送信するハンドラークラス。
 * 任意のエンドポイントにHTTPリクエストを送信することで外部システムへの通知を行います。
 */
class WebhookHandler {
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
     * Webhook通知を送信する
     * 
     * @param string $message メッセージ本文
     * @param array $options 追加オプション（画像含めるか、詳細情報含めるかなど）
     * @return bool 送信成功フラグ
     */
    public function send($message, $options = []) {
        try {
            // 設定チェック
            if (!isset($this->config['endpoint_url']) || empty($this->config['endpoint_url'])) {
                throw new Exception("Webhook エンドポイントURLが設定されていません");
            }
            
            $endpoint_url = $this->config['endpoint_url'];
            
            // メソッドの決定（デフォルトはPOST）
            $method = isset($this->config['method']) ? strtoupper($this->config['method']) : 'POST';
            
            // 認証情報の取得
            $auth_type = isset($this->config['auth_type']) ? $this->config['auth_type'] : null;
            $auth_username = isset($this->config['auth_username']) ? $this->config['auth_username'] : null;
            $auth_password = isset($this->config['auth_password']) ? $this->config['auth_password'] : null;
            $auth_token = isset($this->config['auth_token']) ? $this->config['auth_token'] : null;
            
            // カスタムヘッダーの取得
            $custom_headers = isset($this->config['headers']) ? $this->config['headers'] : [];
            
            // ペイロードフォーマットの決定（デフォルトはJSON）
            $payload_format = isset($this->config['payload_format']) ? strtolower($this->config['payload_format']) : 'json';
            
            // リクエストの準備
            $payload = [
                'message' => $message,
                'timestamp' => date('c'),
                'source' => 'rtsp_ocr_system'
            ];
            
            // 判定結果データの追加
            if (!empty($options['include_details']) && isset($options['determination_result'])) {
                $result = $options['determination_result'];
                $payload['determination_result'] = $result;
                
                // さらに基本情報を上位レベルに展開（フラット化）
                $payload['ocr_text'] = $result['ocr_text'];
                $payload['camera_id'] = $result['camera_id'];
                $payload['area_id'] = $result['area_id'];
                $payload['display_type'] = $result['display_type'];
                $payload['display_level'] = $result['display_level'];
                $payload['result_id'] = $result['result_id'];
                
                if (isset($result['determination_type'])) {
                    $payload['determination_type'] = $result['determination_type'];
                }
                
                if (isset($result['numerical_value'])) {
                    $payload['numerical_value'] = $result['numerical_value'];
                }
                
                if (isset($result['model_id'])) {
                    $payload['model_id'] = $result['model_id'];
                }
                
                if (isset($result['is_threshold_alert']) && $result['is_threshold_alert']) {
                    $payload['is_threshold_alert'] = true;
                }
            }
            
            // 画像URLの追加（画像が含まれる場合）
            if (!empty($options['include_image']) && isset($options['determination_result']['image_path'])) {
                $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                $image_url = $base_url . $options['determination_result']['image_path'];
                $payload['image_url'] = $image_url;
            }
            
            // 追加のカスタムフィールドを含める
            if (isset($this->config['custom_fields']) && is_array($this->config['custom_fields'])) {
                foreach ($this->config['custom_fields'] as $key => $value) {
                    $payload[$key] = $value;
                }
            }
            
            // ペイロードフォーマットに応じたデータ変換
            $request_data = '';
            $content_type = '';
            
            switch ($payload_format) {
                case 'json':
                    $request_data = json_encode($payload);
                    $content_type = 'application/json';
                    break;
                    
                case 'form':
                    $request_data = http_build_query($payload);
                    $content_type = 'application/x-www-form-urlencoded';
                    break;
                    
                case 'xml':
                    $xml = new SimpleXMLElement('<notification></notification>');
                    $this->arrayToXml($payload, $xml);
                    $request_data = $xml->asXML();
                    $content_type = 'application/xml';
                    break;
                    
                default:
                    throw new Exception("不明なペイロードフォーマット: $payload_format");
            }
            
            // ヘッダーの準備
            $headers = ["Content-Type: $content_type"];
            
            // 認証ヘッダーの追加
            if ($auth_type) {
                switch ($auth_type) {
                    case 'basic':
                        if ($auth_username && $auth_password) {
                            $headers[] = 'Authorization: Basic ' . base64_encode("$auth_username:$auth_password");
                        }
                        break;
                        
                    case 'bearer':
                        if ($auth_token) {
                            $headers[] = 'Authorization: Bearer ' . $auth_token;
                        }
                        break;
                        
                    case 'api_key':
                        if (isset($this->config['api_key_name']) && isset($this->config['api_key_value'])) {
                            if (isset($this->config['api_key_in']) && $this->config['api_key_in'] === 'header') {
                                $headers[] = $this->config['api_key_name'] . ': ' . $this->config['api_key_value'];
                            } elseif (isset($this->config['api_key_in']) && $this->config['api_key_in'] === 'query') {
                                // クエリパラメータに追加
                                $separator = (strpos($endpoint_url, '?') !== false) ? '&' : '?';
                                $endpoint_url .= $separator . urlencode($this->config['api_key_name']) . '=' . urlencode($this->config['api_key_value']);
                            }
                        }
                        break;
                }
            }
            
            // カスタムヘッダーの追加
            foreach ($custom_headers as $name => $value) {
                $headers[] = "$name: $value";
            }
            
            // cURLを使用してHTTPリクエストを送信
            $ch = curl_init($endpoint_url);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
            } elseif ($method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            } elseif ($method === 'PATCH') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
            } else {
                // GETリクエストの場合、ペイロードはクエリパラメータに変換
                if ($method === 'GET' && $payload_format === 'form') {
                    $separator = (strpos($endpoint_url, '?') !== false) ? '&' : '?';
                    $endpoint_url .= $separator . $request_data;
                    curl_setopt($ch, CURLOPT_URL, $endpoint_url);
                }
            }
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // タイムアウト設定
            $timeout = isset($this->config['timeout']) ? intval($this->config['timeout']) : 30;
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            
            // SSLの検証設定
            $verify_ssl = isset($this->config['verify_ssl']) ? boolval($this->config['verify_ssl']) : true;
            if (!$verify_ssl) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            $this->response_data = "HTTP Code: $http_code, Response: $response";
            
            if (curl_errno($ch)) {
                $this->last_error = "cURLエラー: " . curl_error($ch);
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            // 成功判定（HTTPコード2xx）
            if ($http_code >= 200 && $http_code < 300) {
                return true;
            } else {
                $this->last_error = "Webhook送信エラー: HTTPコード $http_code, レスポンス: $response";
                return false;
            }
            
        } catch (Exception $e) {
            $this->last_error = "Webhook通知送信エラー: " . $e->getMessage();
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
     * 配列データをXML形式に変換する（再帰的）
     * 
     * @param array $data 変換対象の配列
     * @param SimpleXMLElement $xml XMLオブジェクト
     */
    private function arrayToXml($data, &$xml) {
        foreach ($data as $key => $value) {
            // 数値キーの場合は「item」タグを使用
            if (is_numeric($key)) {
                $key = 'item';
            }
            
            // 特殊文字をエスケープ
            $key = preg_replace('/[^a-z0-9_-]/i', '_', $key);
            
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                // nullや真偽値の処理
                if ($value === null) {
                    $xml->addChild($key, '');
                } elseif (is_bool($value)) {
                    $xml->addChild($key, ($value ? 'true' : 'false'));
                } else {
                    // 文字列に変換して追加
                    $xml->addChild($key, htmlspecialchars((string)$value));
                }
            }
        }
    }
} 
 
 
 
 