<?php
/**
 * 注文編集API
 * バージョン: 1.0.2
 * ファイル説明: sales_monitor 画面からの Ajax での注文編集リクエストを受け付けるエンドポイント。
 * 必須パラメータ: order_id, items (JSON array)
 * items フォーマット: [ {detail_id:int, quantity:int, delete:bool} ]
 * 返却: JSON { success, new_total, removed, message }
 * 更新履歴: 
 * - 2025-05-31 パス修正
 * - 2025-01-31 エラーハンドリング改善
 */

// エラー出力を一時的に有効化（デバッグ用）
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // HTTP method check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    // JSON body parsing
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON');
    }

    $orderId = (int)($data['order_id'] ?? 0);
    $items   = $data['items'] ?? [];

    if ($orderId <= 0 || !is_array($items)) {
        throw new Exception('パラメータが不正です');
    }

    // 設定ファイルとデータベース接続を先に読み込み
    $configPath = __DIR__ . '/../../api/config/config.php';
    $dbPath = __DIR__ . '/../../api/lib/Database.php';
    $utilsPath = __DIR__ . '/../../api/lib/Utils.php';
    $servicePath = __DIR__ . '/../../api/lib/OrderService_Edit.php';
    
    // ファイルの存在確認
    if (!file_exists($configPath)) {
        throw new Exception('Config file not found: ' . $configPath);
    }
    if (!file_exists($dbPath)) {
        throw new Exception('Database file not found: ' . $dbPath);
    }
    if (!file_exists($utilsPath)) {
        throw new Exception('Utils file not found: ' . $utilsPath);
    }
    if (!file_exists($servicePath)) {
        throw new Exception('Service file not found: ' . $servicePath);
    }
    
    require_once $configPath;
    require_once $utilsPath;  // Utilsを先に読み込む
    require_once $dbPath;
    require_once $servicePath;
    
    // サービス呼び出し
    $service = new OrderService_Edit();
    $result  = $service->editOrder($orderId, $items);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
} catch (Exception $e) {
    error_log('edit_order.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 