<?php
// デバッグ用テストスクリプト
// 統合テストの注文作成部分のみを実行して詳細ログを出力

// メモリ制限を引き上げる - 最適化後も一応維持
ini_set('memory_limit', '256M');

// ログレベルを最大に設定（定義されていない場合のみ）
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', 'DEBUG');
}

if (!defined('LOG_FILE')) {
    define('LOG_FILE', __DIR__ . '/../api/logs/debug_test.log');
}

// エラー表示を最大に
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイル読み込み
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/lib/Database.php';
require_once __DIR__ . '/../api/lib/Utils.php';
require_once __DIR__ . '/../api/lib/SquareService.php';
require_once __DIR__ . '/../api/lib/OrderService.php';
require_once __DIR__ . '/../api/lib/ProductService.php';
require_once __DIR__ . '/../api/lib/LineService.php';
require_once __DIR__ . '/../api/lib/RoomTicketService.php';

// ユーティリティ関数
function log_debug($message) {
    Utils::log($message, 'DEBUG', 'DebugTest');
    echo $message . "\n";
}

// テスト前にログファイルをクリア
if (file_exists(LOG_FILE)) {
    unlink(LOG_FILE);
}

log_debug("=== 最適化されたデバッグテスト開始 ===");

// テストパラメータ
$roomNumber = '101';
$testMode = 'sandbox';

// メモリ使用量計測用関数
function getMemoryUsage() {
    return round(memory_get_usage() / 1024 / 1024, 2) . ' MB';
}

try {
    log_debug("メモリ使用量（初期）: " . getMemoryUsage());
    
    // 1. データベース接続テスト
    log_debug("1. データベース接続テスト");
    $db = Database::getInstance();
    log_debug("データベース接続情報: " . DB_HOST . ", " . DB_NAME);
    
    try {
        $result = $db->selectOne("SELECT 1 AS connection_test");
        log_debug("データベース接続成功");
        
        // テーブル一覧確認
        $tables = $db->select("SHOW TABLES");
        log_debug("存在するテーブル数: " . count($tables));
        
        // room_ticketsテーブルの存在確認
        $roomTicketsExists = false;
        foreach ($tables as $table) {
            $tableName = reset($table);
            if ($tableName === 'room_tickets') {
                $roomTicketsExists = true;
                break;
            }
        }
        
        if (!$roomTicketsExists) {
            log_debug("警告: room_ticketsテーブルが存在しません");
            throw new Exception("テスト環境のデータベース構成が不完全です");
        }
        
    } catch (Exception $dbEx) {
        log_debug("データベース確認中のエラー: " . $dbEx->getMessage());
        log_debug("Note: テストにはデータベース接続が必要です。接続情報を確認してください。");
        throw $dbEx;
    }
    
    log_debug("メモリ使用量（DB接続後）: " . getMemoryUsage());
    
    // 2. 特定の商品を1つ取得（カタログ全体を取得せず、限定的な取得方法を使用）
    log_debug("2. テスト用商品の取得");
    $squareService = new SquareService();
    
    // テスト商品IDを指定（固定アイテムID）
    $testItemId = 'IV7TSDH5FQ7V6TBJXS32OLK6'; // テスト用の商品ID（実際の環境に合わせて変更してください）
    $testPrice = 1000; // 商品価格（テスト用）
    $testName = 'テスト商品'; // 商品名（テスト用）
    
    // ProductServiceを使用して商品詳細を取得（カタログ全体ではなく単一商品）
    $productService = new ProductService();
    $testProduct = $productService->getProductBySquareId($testItemId);
    
    if ($testProduct) {
        log_debug("テスト商品が見つかりました: " . json_encode($testProduct));
        $testItemId = $testProduct['square_item_id'];
        $testPrice = $testProduct['price'];
        $testName = $testProduct['name'];
    } else {
        // 商品が見つからない場合はテスト用データで続行
        log_debug("指定された商品が見つかりません。テスト用データで続行します");
    }
    
    // 3. テスト注文データ準備
    log_debug("3. テスト注文データ準備");
    $orderData = [
        'room_number' => $roomNumber,
        'guest_name' => 'テストユーザー',
        'items' => [
            [
                'square_item_id' => $testItemId,
                'name' => $testName,
                'price' => $testPrice,
                'quantity' => 1,
                'note' => 'テスト注文アイテム'
            ]
        ],
        'note' => 'テスト用注文データ'
    ];
    log_debug("注文データ: " . json_encode($orderData));
    
    log_debug("メモリ使用量（注文データ準備後）: " . getMemoryUsage());
    
    // 4. RoomTicketService動作確認
    log_debug("4. RoomTicketService動作確認");
    $roomTicketService = new RoomTicketService();
    
    // 既存のチケットを確認（存在する場合は削除してクリーンな状態でテスト）
    $existingTicket = $roomTicketService->getRoomTicketByRoomNumber($roomNumber);
    if ($existingTicket) {
        log_debug("既存のチケットが見つかりました。テスト前にクリーンアップします");
        try {
            $db->execute("DELETE FROM room_tickets WHERE room_number = ?", [$roomNumber]);
            log_debug("チケットを削除しました");
        } catch (Exception $e) {
            log_debug("チケット削除中のエラー: " . $e->getMessage());
        }
    }
    
    // 5. 注文処理実行
    log_debug("5. OrderService.createOrderを実行");
    try {
        $orderService = new OrderService();
        
        log_debug("注文処理開始...");
        $startTime = microtime(true);
        $orderResult = $orderService->createOrder(
            $orderData['room_number'],
            $orderData['items'],
            $orderData['guest_name'],
            $orderData['note'] ?? ''
        );
        $endTime = microtime(true);
        $processingTime = round(($endTime - $startTime) * 1000);
        log_debug("注文処理時間: {$processingTime}ms");
        log_debug("メモリ使用量（注文処理後）: " . getMemoryUsage());
        
        // 6. 結果確認
        if (is_array($orderResult) && isset($orderResult['id'])) {
            log_debug("6. 注文作成成功: " . json_encode($orderResult));
            
            // 作成された注文の詳細を確認
            $createdOrder = $orderService->getOrder($orderResult['id']);
            if ($createdOrder) {
                log_debug("作成された注文の詳細: " . json_encode([
                    'id' => $createdOrder['id'],
                    'room_number' => $createdOrder['room_number'],
                    'square_order_id' => $createdOrder['square_order_id'],
                    'total_amount' => $createdOrder['total_amount'],
                    'items_count' => count($createdOrder['items'])
                ]));
            }
        } else {
            log_debug("6. 注文作成失敗: " . ($orderResult === false ? "処理中にエラーが発生" : json_encode($orderResult)));
            
            // エラーの詳細を確認
            log_debug("エラー詳細確認...");
            
            // room_ticketsテーブルを確認
            $ticketsAfter = $db->select("SELECT * FROM room_tickets WHERE room_number = ?", [$roomNumber]);
            log_debug("room_ticketsテーブル状態: " . json_encode($ticketsAfter));
            
            // エラーログテーブルを確認
            try {
                $tableExists = $db->selectOne("SHOW TABLES LIKE 'system_logs'");
                if ($tableExists) {
                    $recentLogs = $db->select("SELECT * FROM system_logs WHERE level = 'ERROR' ORDER BY id DESC LIMIT 3");
                    if ($recentLogs) {
                        log_debug("最近のエラーログ: " . json_encode($recentLogs));
                    }
                } else {
                    log_debug("system_logsテーブルが存在しません");
                }
            } catch (Exception $logEx) {
                log_debug("エラーログ取得中のエラー: " . $logEx->getMessage());
            }
        }
    } catch (Exception $orderEx) {
        log_debug("注文処理中の例外: " . $orderEx->getMessage());
        log_debug("スタックトレース: " . $orderEx->getTraceAsString());
    }
    
} catch (Exception $e) {
    log_debug("エラー発生: " . $e->getMessage());
    log_debug("スタックトレース: " . $e->getTraceAsString());
}

log_debug("最終メモリ使用量: " . getMemoryUsage());
log_debug("=== デバッグテスト終了 ==="); 