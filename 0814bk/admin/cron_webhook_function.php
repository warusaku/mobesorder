<?php
/**
 * cron_webhook_function.php
 * 
 * Lolipopサーバー上でcronによって実行されるWebhook処理スクリプト
 * webhook_eventsテーブルに登録された未処理イベントを読み取り、通知を送信します
 * 
 * 使用方法: cron設定で定期的に実行する（例: 5分ごとに実行する設定）
 */

// エラー表示設定（本番環境では必要に応じて変更）
ini_set('display_errors', 0);
error_reporting(E_ALL);

// スクリプト最大実行時間（秒）
set_time_limit(300);

// 基本設定
define('MAX_EVENTS_PER_RUN', 50); // 1回の実行で処理する最大イベント数
define('LOG_FILE', __DIR__ . '/../logs/webhook_cron.log'); // ログファイルパス

// ルートパスを設定
$rootPath = realpath(__DIR__ . '/..');

// 必要なファイルを読み込み
require_once $rootPath . '/includes/config.php'; // DB設定など
require_once $rootPath . '/includes/db_connection.php'; // DBコネクション
require_once $rootPath . '/admin/webhook_function.php'; // WebhookManagerクラス

// ログ関数
function writeLog($message, $level = 'INFO') {
    $logDir = dirname(LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $formattedMessage, FILE_APPEND);
}

// スクリプト実行開始ログ
writeLog("Webhook処理バッチ開始");

try {
    // データベース接続
    $db = getDbConnection();
    
    // WebhookManagerのインスタンス化（管理画面設定を使用）
    $webhookManager = new WebhookManager();
    
    // 未処理のwebhook_eventsを取得
    $stmt = $db->prepare("
        SELECT id, event_id, event_type, payload, created_at
        FROM webhook_events
        WHERE processed = 0
        ORDER BY created_at ASC
        LIMIT :limit
    ");
    
    // processed カラムがない場合は作成する
    try {
        $checkColumnStmt = $db->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'webhook_events'
            AND COLUMN_NAME = 'processed'
        ");
        $checkColumnStmt->execute();
        
        if ($checkColumnStmt->rowCount() === 0) {
            // processed カラムが存在しない場合は追加
            $alterTableStmt = $db->prepare("
                ALTER TABLE webhook_events
                ADD COLUMN processed TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN processed_at TIMESTAMP NULL DEFAULT NULL
            ");
            $alterTableStmt->execute();
            writeLog("webhook_events テーブルに processed カラムを追加しました", "INFO");
        }
    } catch (PDOException $e) {
        writeLog("テーブル構造チェック中にエラーが発生しました: " . $e->getMessage(), "ERROR");
    }
    
    $stmt->bindValue(':limit', MAX_EVENTS_PER_RUN, PDO::PARAM_INT);
    $stmt->execute();
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $eventCount = count($events);
    
    writeLog("処理対象イベント: {$eventCount}件");
    
    if ($eventCount === 0) {
        writeLog("処理対象のイベントはありません");
        exit(0);
    }
    
    // webhookの設定を取得して確認
    $webhooks = $webhookManager->getWebhooks();
    if (empty($webhooks)) {
        writeLog("有効なWebhook設定がありません", "WARNING");
        exit(0);
    }
    
    $activeWebhookCount = 0;
    foreach ($webhooks as $webhook) {
        if (!empty($webhook['enabled']) && $webhook['enabled'] === true) {
            $activeWebhookCount++;
        }
    }
    
    writeLog("有効なWebhook設定数: {$activeWebhookCount}");
    
    if ($activeWebhookCount === 0) {
        writeLog("有効なWebhook設定がありません", "WARNING");
        exit(0);
    }
    
    // イベントごとに処理
    foreach ($events as $event) {
        try {
            writeLog("イベント処理開始: ID={$event['id']}, Type={$event['event_type']}");
            
            // ペイロードがJSON形式の場合はデコード
            $payload = $event['payload'] ?? '{}';
            $payloadData = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                writeLog("JSONデコードエラー: " . json_last_error_msg(), "ERROR");
                $payloadData = [];
            }
            
            // イベントタイプに応じた処理
            $processed = false;
            
            switch ($event['event_type']) {
                case 'order_notification':
                    // 注文通知
                    if (isset($payloadData['order_id']) || isset($payloadData['id'])) {
                        // order_idが設定されていない場合はidを使用
                        if (!isset($payloadData['order_id']) && isset($payloadData['id'])) {
                            $payloadData['order_id'] = $payloadData['id'];
                        }
                        
                        // 通知を送信
                        $result = $webhookManager->sendOrderNotification($payloadData);
                        writeLog("注文通知送信結果: " . json_encode($result));
                        $processed = $result['success'];
                    } else {
                        writeLog("注文IDが不明のためスキップします", "WARNING");
                    }
                    break;
                    
                case 'inventory_alert':
                    // 在庫アラート
                    if (isset($payloadData['product_name'])) {
                        $message = "在庫アラート: {$payloadData['product_name']}";
                        
                        if (isset($payloadData['stock_quantity'])) {
                            $message .= " 残り{$payloadData['stock_quantity']}個";
                        }
                        
                        // カスタムテンプレートデータ
                        $alertData = [
                            'title' => '在庫アラート',
                            'description' => $message,
                            'color' => '15548997', // 赤色
                            'fields' => [
                                [
                                    'name' => '商品名',
                                    'value' => $payloadData['product_name'],
                                    'inline' => true
                                ],
                                [
                                    'name' => '在庫数',
                                    'value' => $payloadData['stock_quantity'] ?? '不明',
                                    'inline' => true
                                ]
                            ],
                            'footer' => [
                                'text' => 'FG Square 在庫管理'
                            ]
                        ];
                        
                        // 各Webhookに送信
                        $successCount = 0;
                        foreach ($webhooks as $webhook) {
                            if (!isset($webhook['enabled']) || $webhook['enabled'] !== true) {
                                continue;
                            }
                            
                            $url = $webhook['url'];
                            $discordPayload = $webhookManager->formatDiscordWebhook($alertData);
                            $result = $webhookManager->sendWebhook($url, $discordPayload);
                            
                            if ($result['success']) {
                                $successCount++;
                            }
                        }
                        
                        writeLog("在庫アラート送信結果: 成功 {$successCount}件");
                        $processed = ($successCount > 0);
                    } else {
                        writeLog("商品名が不明のためスキップします", "WARNING");
                    }
                    break;
                    
                case 'system_alert':
                    // システムアラート
                    $message = $payloadData['message'] ?? "システムアラート（詳細不明）";
                    $level = $payloadData['level'] ?? "WARNING";
                    
                    // カスタムテンプレートデータ
                    $alertData = [
                        'title' => 'システムアラート',
                        'description' => $message,
                        'color' => ($level === 'ERROR' || $level === 'CRITICAL') ? '15548997' : '16776960', // 赤または黄色
                        'fields' => [
                            [
                                'name' => 'レベル',
                                'value' => $level,
                                'inline' => true
                            ],
                            [
                                'name' => '時刻',
                                'value' => date('Y-m-d H:i:s'),
                                'inline' => true
                            ]
                        ],
                        'footer' => [
                            'text' => 'FG Square システム監視'
                        ]
                    ];
                    
                    // 詳細情報があれば追加
                    if (!empty($payloadData['details'])) {
                        $details = is_array($payloadData['details']) 
                            ? json_encode($payloadData['details'], JSON_UNESCAPED_UNICODE)
                            : $payloadData['details'];
                            
                        $alertData['fields'][] = [
                            'name' => '詳細情報',
                            'value' => $details,
                            'inline' => false
                        ];
                    }
                    
                    // 各Webhookに送信
                    $successCount = 0;
                    foreach ($webhooks as $webhook) {
                        if (!isset($webhook['enabled']) || $webhook['enabled'] !== true) {
                            continue;
                        }
                        
                        $url = $webhook['url'];
                        $discordPayload = $webhookManager->formatDiscordWebhook($alertData);
                        $result = $webhookManager->sendWebhook($url, $discordPayload);
                        
                        if ($result['success']) {
                            $successCount++;
                        }
                    }
                    
                    writeLog("システムアラート送信結果: 成功 {$successCount}件");
                    $processed = ($successCount > 0);
                    break;
                    
                default:
                    // その他のイベント
                    $message = "イベント通知: {$event['event_type']}";
                    
                    // カスタムテンプレートデータ
                    $alertData = [
                        'title' => 'イベント通知',
                        'description' => $message,
                        'color' => '3447003', // 青色
                        'fields' => [
                            [
                                'name' => 'イベントタイプ',
                                'value' => $event['event_type'],
                                'inline' => true
                            ],
                            [
                                'name' => '時刻',
                                'value' => date('Y-m-d H:i:s'),
                                'inline' => true
                            ]
                        ],
                        'footer' => [
                            'text' => 'FG Square イベント通知'
                        ]
                    ];
                    
                    // ペイロードの内容を追加
                    if (!empty($payloadData)) {
                        $payloadFormatted = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        if (strlen($payloadFormatted) > 1000) {
                            $payloadFormatted = substr($payloadFormatted, 0, 997) . '...';
                        }
                        
                        $alertData['fields'][] = [
                            'name' => 'ペイロード',
                            'value' => "```json\n" . $payloadFormatted . "\n```",
                            'inline' => false
                        ];
                    }
                    
                    // 各Webhookに送信
                    $successCount = 0;
                    foreach ($webhooks as $webhook) {
                        if (!isset($webhook['enabled']) || $webhook['enabled'] !== true) {
                            continue;
                        }
                        
                        $url = $webhook['url'];
                        $discordPayload = $webhookManager->formatDiscordWebhook($alertData);
                        $result = $webhookManager->sendWebhook($url, $discordPayload);
                        
                        if ($result['success']) {
                            $successCount++;
                        }
                    }
                    
                    writeLog("一般通知送信結果: 成功 {$successCount}件");
                    $processed = ($successCount > 0);
                    break;
            }
            
            // 処理済みフラグを更新
            $updateStmt = $db->prepare("
                UPDATE webhook_events
                SET processed = 1, processed_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->bindValue(':id', $event['id'], PDO::PARAM_INT);
            $updateStmt->execute();
            
            writeLog("イベント処理完了: ID={$event['id']} " . ($processed ? "(通知成功)" : "(通知失敗)"));
            
        } catch (Exception $e) {
            writeLog("イベント処理エラー (ID={$event['id']}): " . $e->getMessage(), "ERROR");
        }
    }
    
    writeLog("Webhook処理バッチ正常終了 (処理件数: {$eventCount})");
    
} catch (Exception $e) {
    writeLog("クリティカルエラー: " . $e->getMessage(), "ERROR");
    exit(1);
} 