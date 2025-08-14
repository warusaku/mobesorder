<?php
/**
 * LineHandler クラス
 * 
 * LINE Messaging APIを使用して通知を送信するハンドラークラス。
 */
class LineHandler {
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
     * LINE通知を送信する
     * 
     * @param string $message メッセージ本文
     * @param array $options 追加オプション（画像含めるか、詳細情報含めるかなど）
     * @return bool 送信成功フラグ
     */
    public function send($message, $options = []) {
        try {
            // 設定チェック
            if (!isset($this->config['access_token']) || empty($this->config['access_token'])) {
                throw new Exception("LINE アクセストークンが設定されていません");
            }
            
            if (!isset($this->config['to']) || empty($this->config['to'])) {
                throw new Exception("LINE 送信先IDが設定されていません");
            }
            
            $access_token = $this->config['access_token'];
            $to = $this->config['to'];
            
            // LINE Messaging API エンドポイント
            $api_url = 'https://api.line.me/v2/bot/message/push';
            
            // メッセージの準備
            $messages = [];
            
            // メインメッセージを追加
            $messages[] = [
                'type' => 'text',
                'text' => $message
            ];
            
            // 判定結果の詳細情報をFlexメッセージとして追加
            if (!empty($options['include_details']) && isset($options['determination_result'])) {
                $result = $options['determination_result'];
                
                // 色の設定（表示レベルに応じて）
                $color = $this->getColorForDisplayLevel($result['display_level']);
                
                // Flexメッセージの構築
                $flex_message = [
                    'type' => 'flex',
                    'altText' => '判定結果詳細',
                    'contents' => [
                        'type' => 'bubble',
                        'header' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '判定結果詳細',
                                    'weight' => 'bold',
                                    'color' => '#FFFFFF',
                                    'size' => 'md'
                                ]
                            ],
                            'backgroundColor' => $color,
                            'paddingAll' => '8px'
                        ],
                        'body' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => []
                        ],
                        'footer' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => "判定結果ID: {$result['result_id']}",
                                    'size' => 'xs',
                                    'color' => '#AAAAAA'
                                ]
                            ]
                        ]
                    ]
                ];
                
                // 本文要素の作成
                $body_contents = [];
                
                // OCRテキスト
                $body_contents[] = [
                    'type' => 'text',
                    'text' => 'OCRテキスト',
                    'weight' => 'bold',
                    'size' => 'sm'
                ];
                $body_contents[] = [
                    'type' => 'text',
                    'text' => !empty($result['ocr_text']) ? $result['ocr_text'] : '(空)',
                    'size' => 'sm',
                    'wrap' => true,
                    'margin' => 'sm'
                ];
                $body_contents[] = [
                    'type' => 'separator',
                    'margin' => 'sm'
                ];
                
                // カメラ情報
                $body_contents[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => 'カメラID',
                                    'size' => 'sm',
                                    'color' => '#555555'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $result['camera_id'],
                                    'size' => 'sm'
                                ]
                            ],
                            'flex' => 1
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => 'エリアID',
                                    'size' => 'sm',
                                    'color' => '#555555'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $result['area_id'],
                                    'size' => 'sm'
                                ]
                            ],
                            'flex' => 1
                        ]
                    ],
                    'margin' => 'md'
                ];
                
                // 表示/判定情報
                $body_contents[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '表示種別',
                                    'size' => 'sm',
                                    'color' => '#555555'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $result['display_type'],
                                    'size' => 'sm'
                                ]
                            ],
                            'flex' => 1
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => '表示レベル',
                                    'size' => 'sm',
                                    'color' => '#555555'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $result['display_level'],
                                    'size' => 'sm'
                                ]
                            ],
                            'flex' => 1
                        ]
                    ],
                    'margin' => 'md'
                ];
                
                // 判定種別（存在する場合）
                if (isset($result['determination_type']) && $result['determination_type'] !== null) {
                    $body_contents[] = [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '判定種別',
                                'size' => 'sm',
                                'color' => '#555555'
                            ],
                            [
                                'type' => 'text',
                                'text' => $result['determination_type'],
                                'size' => 'sm'
                            ]
                        ],
                        'margin' => 'md'
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
                    
                    $body_contents[] = [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '閾値情報',
                                'size' => 'sm',
                                'color' => '#555555'
                            ],
                            [
                                'type' => 'text',
                                'text' => $threshold_info,
                                'size' => 'sm',
                                'wrap' => true
                            ]
                        ],
                        'margin' => 'md'
                    ];
                }
                
                // 時刻情報
                $body_contents[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '取得時刻',
                            'size' => 'sm',
                            'color' => '#555555'
                        ],
                        [
                            'type' => 'text',
                            'text' => $result['capture_time'],
                            'size' => 'sm'
                        ]
                    ],
                    'margin' => 'md'
                ];
                
                // 本文要素をFlexメッセージに追加
                $flex_message['contents']['body']['contents'] = $body_contents;
                
                // Flexメッセージをメッセージリストに追加
                $messages[] = $flex_message;
            }
            
            // 画像メッセージの追加（画像が含まれる場合）
            if (!empty($options['include_image']) && isset($options['determination_result']['image_path'])) {
                $base_url = 'http://test-mijeos.but.jp/RTSP_reader';
                $image_url = $base_url . $options['determination_result']['image_path'];
                
                // LINE Messaging APIの制約上、HTTPS URLが必要
                if (strpos($image_url, 'https://') === 0) {
                    $messages[] = [
                        'type' => 'image',
                        'originalContentUrl' => $image_url,
                        'previewImageUrl' => $image_url
                    ];
                } else {
                    $this->logger("警告: 画像URLはHTTPSである必要があります: $image_url");
                }
            }
            
            // リクエストデータ構築
            $post_data = [
                'to' => $to,
                'messages' => $messages
            ];
            
            // JSONにエンコード
            $json_data = json_encode($post_data);
            
            // cURLを使用してHTTPリクエストを送信
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            $this->response_data = "HTTP Code: $http_code, Response: $response";
            
            if (curl_errno($ch)) {
                $this->last_error = "cURLエラー: " . curl_error($ch);
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            
            // 成功判定（HTTPコード200）
            if ($http_code == 200) {
                return true;
            } else {
                $this->last_error = "LINE API エラー: HTTPコード $http_code, レスポンス: $response";
                return false;
            }
            
        } catch (Exception $e) {
            $this->last_error = "LINE通知送信エラー: " . $e->getMessage();
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
     * @return string カラーコード（RGB形式）
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
    
    /**
     * ログ出力
     * 
     * @param string $message ログメッセージ
     */
    private function logger($message) {
        error_log("[LineHandler] " . $message);
    }
} 
 
 
 
 