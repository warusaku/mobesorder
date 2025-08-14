<?php
/**
 * 手動注文追加API
 * バージョン: 2.0.0
 * ファイル説明: 管理者による手動注文追加エンドポイント（OrderService使用版）
 * 必須パラメータ: session_id, room_number, items
 * 返却: JSON { success, order_id, message }
 * 更新履歴: 
 * - 2025-01-31 初版作成
 * - 2025-01-31 OrderServiceを使用するように変更
 */

// エラー出力設定
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ログファイル設定
define('LOG_DIR', __DIR__ . '/../../logs');
define('LOG_FILE', LOG_DIR . '/add_manual_order.php.log');
define('MAX_LOG_SIZE', 300 * 1024); // 300KB
define('LOG_RETENTION_PERCENT', 0.2); // 20%

/**
 * ログ記録関数（プロジェクトルール準拠）
 */
function writeLog($message, $level = 'INFO') {
    // ログディレクトリが存在しない場合は作成
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    // タイムスタンプ（JST）
    date_default_timezone_set('Asia/Tokyo');
    $timestamp = date('Y-m-d H:i:s');
    
    // ログエントリ
    $logEntry = sprintf("[%s] [%s] %s\n", $timestamp, $level, $message);
    
    // ログローテーション
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > MAX_LOG_SIZE) {
        $content = file_get_contents(LOG_FILE);
        $lines = explode("\n", $content);
        $keepLines = max(1, (int)(count($lines) * LOG_RETENTION_PERCENT));
        $newContent = implode("\n", array_slice($lines, -$keepLines));
        file_put_contents(LOG_FILE, $newContent);
    }
    
    // ログ書き込み
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json; charset=utf-8');

try {
    writeLog("=== 手動注文追加API開始 ===");
    
    // HTTP method check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        writeLog("不正なHTTPメソッド: " . $_SERVER['REQUEST_METHOD'], 'ERROR');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }
    
    // セッションチェック
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['auth_user'])) {
        writeLog("未認証アクセス", 'ERROR');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '認証が必要です']);
        exit;
    }
    
    $adminUser = $_SESSION['auth_user'];
    writeLog("管理者: {$adminUser}");
    
    // JSON body parsing
    $raw = file_get_contents('php://input');
    writeLog("受信データ: " . substr($raw, 0, 1000)); // 最初の1000文字のみ
    
    $data = json_decode($raw, true);
    
    if (!is_array($data)) {
        writeLog("JSONパースエラー", 'ERROR');
        throw new Exception('Invalid JSON');
    }
    
    $sessionId = $data['session_id'] ?? '';
    $roomNumber = $data['room_number'] ?? '';
    $items = $data['items'] ?? [];
    $memo = $data['memo'] ?? '';
    
    writeLog("パラメータ - SessionID: {$sessionId}, Room: {$roomNumber}, Items: " . count($items));
    
    if (empty($roomNumber) || empty($items)) {
        writeLog("必須パラメータ不足", 'ERROR');
        throw new Exception('必須パラメータが不足しています');
    }
    
    // OrderServiceを使用するため、設定ファイルを読み込み
    $rootPath = realpath(__DIR__ . '/../..');
    require_once $rootPath . '/api/config/config.php';
    require_once $rootPath . '/api/lib/OrderService.php';
    
    // OrderServiceインスタンスを作成
    $orderService = new OrderService();
    writeLog("OrderService初期化成功");
    
    // 商品データを整形（OrderServiceが期待する形式に変換）
    $formattedItems = [];
    foreach ($items as $item) {
        $squareItemId = $item['square_item_id'] ?? null;
        $itemType = $item['type'] ?? 'product';
        
        // カスタム商品（金額修正商品）の場合
        if ($itemType === 'custom' || empty($squareItemId)) {
            $squareItemId = 'mobes_order_999999';
        }
        
        $formattedItem = [
            'square_item_id' => $squareItemId,
            'name' => $item['name'] ?? '不明な商品',
            'price' => floatval($item['price'] ?? 0),
            'quantity' => intval($item['quantity'] ?? 1),
            'note' => '' // 個別商品のメモ（今回は使用しない）
        ];
        
        $formattedItems[] = $formattedItem;
        writeLog("商品追加: " . json_encode($formattedItem));
    }
    
    // メモに管理者情報を追加
    $fullMemo = $memo;
    if (!empty($fullMemo)) {
        $fullMemo .= "\n";
    }
    $fullMemo .= "[手動追加: {$adminUser}]";
    
    // OrderServiceを使って注文を作成
    writeLog("OrderService::createOrder実行開始");
    $result = $orderService->createOrder(
        $roomNumber,
        $formattedItems,
        $adminUser . ' (手動)',  // guestName
        $fullMemo,               // note
        ''                       // lineUserId（手動注文なので空）
    );
    
    if ($result === false) {
        writeLog("OrderService::createOrder失敗", 'ERROR');
        throw new Exception('注文の作成に失敗しました');
    }
    
    writeLog("OrderService::createOrder成功: " . json_encode($result));
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'order_id' => $result['id'] ?? 'unknown',
        'message' => '注文を追加しました'
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    
    writeLog("=== 手動注文追加API正常終了 ===");
    
} catch (Exception $e) {
    writeLog("エラー発生: " . $e->getMessage(), 'ERROR');
    writeLog("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
    error_log('add_manual_order.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    writeLog("=== 手動注文追加APIエラー終了 ===", 'ERROR');
} 