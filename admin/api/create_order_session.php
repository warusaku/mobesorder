<?php
/**
 * オーダーセッション作成API
 * バージョン: 1.0.0
 * ファイル説明: 手動で新しいオーダーセッションを作成するエンドポイント
 * 必須パラメータ: room_number
 * 返却: JSON { success, session_id, message }
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
    
    // セッションチェック
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['auth_user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '認証が必要です']);
        exit;
    }
    
    // JSON body parsing
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (!is_array($data)) {
        throw new Exception('Invalid JSON');
    }
    
    $roomNumber = $data['room_number'] ?? '';
    $adminUser = $_SESSION['auth_user'];
    
    if (empty($roomNumber)) {
        throw new Exception('部屋番号が指定されていません');
    }
    
    // 設定ファイルとデータベース接続
    $configPath = __DIR__ . '/../../api/config/config.php';
    $utilsPath = __DIR__ . '/../../api/lib/Utils.php';
    $dbPath = __DIR__ . '/../../api/lib/Database.php';
    
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
    
    require_once $configPath;
    require_once $utilsPath;
    require_once $dbPath;
    
    $db = Database::getInstance();
    
    // トランザクション開始
    $db->beginTransaction();
    
    try {
        // 既存のアクティブなセッションがないか確認
        $existingSession = $db->selectOne(
            "SELECT id FROM order_sessions WHERE room_number = ? AND is_active = 1",
            [$roomNumber]
        );
        
        if ($existingSession) {
            throw new Exception("部屋番号 {$roomNumber} には既にアクティブなセッションが存在します");
        }
        
        // セッションIDを生成（21文字）
        $sessionId = generateSessionId();
        
        // 新しいセッションを作成
        $sql = "INSERT INTO order_sessions (id, room_number, square_item_id, is_active, opened_at) 
                VALUES (?, ?, NULL, 1, NOW())";
        
        $db->execute($sql, [$sessionId, $roomNumber]);
        
        $db->commit();
        
        // ログを記録
        Utils::log("order_session.log", "Order session created: {$sessionId} for room {$roomNumber} by {$adminUser}");
        
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'message' => '新しいセッションを作成しました'
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('create_order_session.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * セッションIDを生成（21文字）
 * フォーマット: PREFIX_TIMESTAMP_RANDOM (合計21文字)
 */
function generateSessionId() {
    $prefix = 'OS'; // Order Session
    $timestamp = substr(time(), -8); // 最後の8桁
    $random = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 11);
    return $prefix . $timestamp . $random;
} 