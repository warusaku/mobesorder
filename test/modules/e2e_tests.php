<?php
/**
 * RTSP_Reader Test Framework - E2E Tests Module
 * 
 * エンドツーエンドテストモジュール
 */

class E2eTestsModule {
    private $logger;
    private $db;
    private $discordWebhookUrl = 'https://discord.com/api/webhooks/1367685703193985034/JFMQhjn2s002akKm9Ndw1DhO2WO-kkwyQAAW-rJy9LMSvH2bAaK0D-gkhfkB3fgtCMBQ';
    private $localServerApiBase = 'http://192.168.3.57/dev/RTSPserver/api';
    private $testTimeout = 300; // 5分
    
    /**
     * コンストラクタ
     *
     * @param TestLogger $logger ロガーインスタンス
     * @param PDO $db データベース接続
     */
    public function __construct($logger, $db = null) {
        $this->logger = $logger;
        $this->db = $db;
    }
    
    /**
     * Discord Webhookテスト
     * 
     * @description Discordへのテスト通知を送信します
     * @return array テスト結果
     */
    public function testDiscordWebhook() {
        $this->logger->info("Discord Webhookテスト開始");
        
        // テスト用通知ペイロード
        $payload = [
            'content' => '[テスト通知] RTSP_Readerテストモジュールからの自動テスト: ' . date('Y-m-d H:i:s'),
            'embeds' => [
                [
                    'title' => 'WebhookテストV1.0',
                    'description' => 'これはRTSP_Readerテストモジュールからの自動テスト通知です。本番環境では表示されません。',
                    'color' => 0x00ff00,
                    'fields' => [
                        [
                            'name' => 'テスト種別',
                            'value' => 'Discord Webhook送信テスト',
                            'inline' => true
                        ],
                        [
                            'name' => '送信時刻',
                            'value' => date('Y-m-d H:i:s'),
                            'inline' => true
                        ]
                    ],
                    'footer' => [
                        'text' => 'RTSP_Reader E2Eテストシステム'
                    ]
                ]
            ]
        ];
        
        try {
            $ch = curl_init($this->discordWebhookUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->logger->info("Discord通知送信成功", ['http_code' => $httpCode]);
                
                return [
                    'status' => 'success',
                    'message' => 'Discord通知が正常に送信されました',
                    'details' => [
                        'http_code' => $httpCode,
                        'response' => $response,
                        'sent_payload' => $payload
                    ]
                ];
            } else {
                $this->logger->error("Discord通知送信失敗", ['http_code' => $httpCode, 'error' => $error]);
                
                return [
                    'status' => 'failed',
                    'message' => 'Discord通知の送信に失敗しました',
                    'details' => [
                        'http_code' => $httpCode,
                        'curl_error' => $error,
                        'response' => $response,
                        'sent_payload' => $payload
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->logger->error("Discord通知テストエラー", ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'message' => "テスト実行中にエラーが発生しました: " . $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * データベース同期テスト
     * 
     * @description データベース双方向同期機能をテストします
     * @return array テスト結果
     */
    public function testDatabaseSynchronization() {
        $this->logger->info("データベース同期テスト開始");
        
        if (!$this->db) {
            return [
                'status' => 'error',
                'message' => 'データベース接続が確立されていません',
                'details' => []
            ];
        }
        
        // テスト用一意のIDを生成
        $testId = 'test_' . uniqid();
        
        // テスト用データ
        $testData = [
            'test_id' => $testId,
            'name' => 'PubSubテストデータ',
            'value' => rand(1000, 9999),
            'is_test_data' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            // テスト用データをsync_changesテーブルに直接挿入
            $query = "
                INSERT INTO sync_changes 
                (table_name, record_id, change_type, origin, priority, data_snapshot) 
                VALUES 
                ('test_pubsub', :record_id, 'INSERT', 'CLOUD', 5, :data_snapshot)
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'record_id' => $testData['test_id'],
                'data_snapshot' => json_encode($testData)
            ]);
            
            $this->logger->info("テストデータ挿入完了", ['test_id' => $testId]);
            
            // 同期状態を監視
            $startTime = time();
            $checkInterval = 5; // 5秒ごとにチェック
            $details = [];
            $syncStatus = null;
            
            while (time() - $startTime < $this->testTimeout) {
                // sync_changesテーブルのステータスを確認
                $query = "
                    SELECT 
                        id, sync_status, sync_message, last_sync_attempt, retry_count
                    FROM 
                        sync_changes 
                    WHERE 
                        record_id = :test_id
                        AND table_name = 'test_pubsub'
                    ORDER BY
                        id DESC
                    LIMIT 1
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute(['test_id' => $testId]);
                $syncRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$syncRecord) {
                    $details[] = "警告: 同期レコードが見つかりません。テストIDを確認してください。";
                    sleep($checkInterval);
                    continue;
                }
                
                $details[] = "同期ステータス: {$syncRecord['sync_status']}, 時刻: " . date('H:i:s');
                $syncStatus = $syncRecord['sync_status'];
                
                if ($syncRecord['sync_status'] === 'SYNCED') {
                    // クリーンアップ
                    $this->cleanupTestData($testId);
                    
                    return [
                        'status' => 'success',
                        'message' => '同期が正常に完了しました',
                        'details' => [
                            'sync_record' => $syncRecord,
                            'elapsed_time' => time() - $startTime,
                            'log' => $details
                        ]
                    ];
                } elseif ($syncRecord['sync_status'] === 'ERROR') {
                    // クリーンアップ
                    $this->cleanupTestData($testId);
                    
                    return [
                        'status' => 'failed',
                        'message' => '同期中にエラーが発生しました: ' . $syncRecord['sync_message'],
                        'details' => [
                            'sync_record' => $syncRecord,
                            'elapsed_time' => time() - $startTime,
                            'log' => $details
                        ]
                    ];
                }
                
                sleep($checkInterval);
            }
            
            // クリーンアップ
            $this->cleanupTestData($testId);
            
            // タイムアウト
            return [
                'status' => 'failed',
                'message' => '同期がタイムアウトしました。制限時間内に同期が完了しませんでした。',
                'details' => [
                    'timeout' => $this->testTimeout,
                    'elapsed_time' => time() - $startTime,
                    'last_sync_status' => $syncStatus,
                    'log' => $details
                ]
            ];
        } catch (Exception $e) {
            // クリーンアップ
            $this->cleanupTestData($testId);
            
            $this->logger->error("データベース同期テストエラー", ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'message' => "テスト実行中にエラーが発生しました: " . $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'test_id' => $testId
                ]
            ];
        }
    }
    
    /**
     * ローカルサーバー接続性テスト
     * 
     * @description ローカルサーバーのAPIエンドポイントへの接続をテストします
     * @return array テスト結果
     */
    public function testLocalServerConnectivity() {
        $this->logger->info("ローカルサーバー接続性テスト開始");
        
        try {
            // ローカルサーバーのステータスAPIをテスト
            $endpoint = $this->localServerApiBase . '/status.php';
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $responseData = json_decode($response, true);
                
                $this->logger->info("ローカルサーバー接続成功", ['http_code' => $httpCode]);
                
                return [
                    'status' => 'success',
                    'message' => 'ローカルサーバーへの接続に成功しました',
                    'details' => [
                        'http_code' => $httpCode,
                        'response' => $responseData,
                        'endpoint' => $endpoint
                    ]
                ];
            } else {
                $this->logger->error("ローカルサーバー接続失敗", ['http_code' => $httpCode, 'error' => $error]);
                
                return [
                    'status' => 'failed',
                    'message' => 'ローカルサーバーへの接続に失敗しました',
                    'details' => [
                        'http_code' => $httpCode,
                        'curl_error' => $error,
                        'response' => $response,
                        'endpoint' => $endpoint
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->logger->error("ローカルサーバー接続テストエラー", ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'message' => "テスト実行中にエラーが発生しました: " . $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * テストデータのクリーンアップ
     *
     * @param string $testId テストID
     */
    private function cleanupTestData($testId) {
        try {
            // sync_changesテーブルからテストデータを削除
            $query = "
                DELETE FROM sync_changes 
                WHERE record_id = :test_id AND table_name = 'test_pubsub'
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['test_id' => $testId]);
            
            $this->logger->info("テストデータクリーンアップ完了", ['test_id' => $testId]);
        } catch (Exception $e) {
            $this->logger->error("テストデータクリーンアップエラー", ['error' => $e->getMessage()]);
        }
    }
} 
 
 
 
 