<?php
/**
 * order_History_registrer.php
 * LINE の room_number をキーに注文履歴を返却する API
 * 
 * パラメータ:
 *   room_number (GET) 必須
 * 
 * レスポンス(JSON)
 *  {
 *    success: true,
 *    orders: [
 *      {
 *        order_id: 1,
 *        created_at: "2025-05-12 11:00",
 *        total: 1200,
 *        items: [
 *          { product_name:"コーヒー", unit_price:400, quantity:2, subtotal:800 }
 *        ]
 *      }
 *    ]
 *  }
 */

// ---------- ログ設定 ----------
$rootDir = realpath(__DIR__ . '/../../..');
$logDir  = $rootDir . '/logs';
$logFile = $logDir . '/order_History_registrer.log';
$maxSize = 307200; // 300KB
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMsg($msg, $level = 'INFO') {
    global $logFile, $maxSize;
    // ローテーション
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        $content  = file_get_contents($logFile);
        $keepSize = intval($maxSize * 0.2);
        $content  = substr($content, -$keepSize);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] [INFO] ログローテーション\n" . $content);
    }
    $line = "[" . date('Y-m-d H:i:s') . "] [$level] $msg\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$roomNumber = isset($_GET['room_number']) ? trim($_GET['room_number']) : '';
if ($roomNumber === '') {
    logMsg('room_number が指定されていません', 'ERROR');
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'room_number required']);
    exit;
}

require_once $rootDir . '/api/config/config.php';
require_once $rootDir . '/api/lib/Database.php';

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    logMsg('DB接続失敗: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'db connection error']);
    exit;
}

try {
    logMsg("履歴取得開始 room_number=$roomNumber");
    // orders テーブルから room_number で取得
    $orders = $db->select("SELECT id, created_at FROM orders WHERE room_number = ? ORDER BY created_at DESC", [$roomNumber]);
    $result = [];
    foreach ($orders as $o) {
        $orderId = $o['id'];
        // 明細取得
        $items = $db->select("SELECT product_name, unit_price, quantity, subtotal FROM order_details WHERE order_id = ?", [$orderId]);
        // 整数化
        foreach ($items as &$it) {
            $it['unit_price'] = intval($it['unit_price']);
            $it['quantity']   = intval($it['quantity']);
            $it['subtotal']   = intval($it['subtotal']);
        }
        unset($it);
        // 合計計算
        $total = array_reduce($items, function($sum,$it){return $sum + $it['subtotal'];},0);
        $result[] = [
            'order_id'   => intval($orderId),
            'created_at' => $o['created_at'],
            'total'      => intval($total),
            'items'      => $items
        ];
    }
    logMsg('取得件数: ' . count($result));
    echo json_encode(['success'=>true,'orders'=>$result]);
} catch (Exception $e) {
    logMsg('クエリ実行エラー: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'query error']);
} 