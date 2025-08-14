<?php
/**
 * register_Sentinel.php
 * 
 * LINE ログイン完了後にフロントエンドが呼び出し、
 * line_user_id からユーザー状態（部屋リンク・未払い有無など）を返すエンドポイント。
 * JSON で結果を返却し、処理詳細は logs/register_Sentinel.log へ出力します。
 * 
 * ログ仕様:
 *   ・logs ディレクトリ直下に同名 .log を出力
 *   ・300KB 超えで 20% を残すローテーション
 */

require_once dirname(__DIR__, 2) . '/order/php/log_helper.php'; // LogHelper を再利用
require_once dirname(__DIR__, 2) . '/api/config/config.php';    // DB 定数(DB_HOST 等)

header('Content-Type: application/json; charset=utf-8');

$logFile = LogHelper::getLogFileNameFromPhp(__FILE__);

// --- ヘルパー : JSON レスポンス & ログ出力 ----------------------------
function jsonResponse(array $data, int $httpStatus = 200)
{
    http_response_code($httpStatus);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// --- パラメータ取得 ----------------------------------------------------
$lineUserId = $_POST['line_user_id'] ?? $_GET['line_user_id'] ?? '';
if (empty($lineUserId)) {
    LogHelper::warn('line_user_id が未指定でリクエストされました', $logFile);
    jsonResponse(['success' => false, 'message' => 'line_user_id is required.'], 400);
}

// --- DB 接続 -----------------------------------------------------------
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    LogHelper::error('DB接続失敗: ' . $e->getMessage(), $logFile);
    jsonResponse(['success' => false, 'message' => 'DB connection failed'], 500);
}

LogHelper::info("処理開始: line_user_id={$lineUserId}", $logFile);

$result = [
    'success'           => true,
    'line_user_id'      => $lineUserId,
    'has_active_room'   => false,
    'room_number'       => null,
    'has_active_session'=> false,
    'order_session_id'  => null,
    'square_item_id'    => null,
    'unpaid_total'      => 0,
];

try {
    // 1) line_room_links からアクティブルーム取得
    $sql = 'SELECT room_number FROM line_room_links WHERE line_user_id = :uid AND is_active = 1 LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $lineUserId]);
    $rowCountRoom = $stmt->rowCount();
    LogHelper::debug("line_room_links ヒット件数: {$rowCountRoom}", $logFile);
    $row = $stmt->fetch();
    if ($row) {
        $result['has_active_room'] = true;
        $result['room_number'] = $row['room_number'];
        LogHelper::info("アクティブな部屋リンクを検出: room_number={$row['room_number']}", $logFile);

        // 2) order_sessions チェック
        $sql2 = 'SELECT id, square_item_id FROM order_sessions WHERE room_number = :room AND is_active = 1 LIMIT 1';
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([':room' => $row['room_number']]);
        LogHelper::debug('order_sessions SQL: room_number=' . $row['room_number'], $logFile);
        $sess = $stmt2->fetch();
        if ($sess) {
            $result['has_active_session'] = true;
            $result['order_session_id']  = (int)$sess['id'];
            $result['square_item_id']    = $sess['square_item_id'];
            LogHelper::info("アクティブセッション検出: order_session_id={$sess['id']}, square_item_id={$sess['square_item_id']}", $logFile);

            // 3) orders テーブルで未払い合計取得
            $sql3 = 'SELECT COALESCE(SUM(total_amount),0) AS total
                     FROM orders
                     WHERE order_session_id = :sid
                       AND order_status <> \'COMPLETED\'';
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([':sid' => $sess['id']]);
            LogHelper::debug('orders 未払い集計 SQL: session_id=' . $sess['id'], $logFile);
            $sum = $stmt3->fetchColumn();
            $result['unpaid_total'] = (int)$sum;
            LogHelper::info("未払い(OPEN)合計取得: {$sum} 円", $logFile);
        } else {
            LogHelper::info('アクティブな order_session は存在しません', $logFile);
        }
    } else {
        LogHelper::info('アクティブな部屋リンクは存在しません', $logFile);
    }

    LogHelper::debug('最終レスポンス: ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $logFile);
    jsonResponse($result);
} catch (Exception $ex) {
    LogHelper::error('処理中例外: ' . $ex->getMessage(), $logFile);
    jsonResponse(['success' => false, 'message' => 'internal error'], 500);
} 