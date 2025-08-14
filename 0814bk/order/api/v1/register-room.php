<?php
/**
 * 部屋情報登録API
 * LINEユーザーIDと部屋番号を受け取り、line_room_linksテーブルに登録する
 * 
 * @param string line_user_id LINE UserID
 * @param string room_number 部屋番号
 * @param string user_name ユーザー名
 * @return JSON レスポンス
 */

// デバッグモード
define('DEBUG', false);

// 必要なファイルの読み込み
require_once '../../../config/database.php';
require_once '../../../api/lib/Utils.php';

// CORSヘッダー
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');

// ログ出力関数
function logMessage($message, $level = 'INFO') {
    if (function_exists('Utils::log')) {
        Utils::log($message, $level, 'RoomReg');
    } else {
        if (DEBUG) {
            error_log("[RoomReg] $level: $message");
        }
    }
}

// エラーレスポンス生成
function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// 成功レスポンス生成
function respondWithSuccess($data = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        'success' => true
    ], $data));
    exit;
}

// メイン処理
try {
    // リクエストメソッドの確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respondWithError('不正なリクエストメソッドです', 405);
    }
    
    // パラメータの取得
    $lineUserId = isset($_POST['line_user_id']) ? trim($_POST['line_user_id']) : null;
    $roomNumber = isset($_POST['room_number']) ? trim($_POST['room_number']) : null;
    $userName = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';
    
    // バリデーション
    if (!$lineUserId) {
        respondWithError('LINE UserIDが指定されていません');
    }
    
    if (!$roomNumber) {
        respondWithError('部屋番号が指定されていません');
    }
    
    logMessage("部屋情報登録: LINE UserID = $lineUserId, 部屋番号 = $roomNumber, ユーザー名 = $userName");
    
    // データベース接続
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        logMessage("データベース接続エラー: " . $db->connect_error, 'ERROR');
        respondWithError('データベース接続エラー', 500);
    }
    
    // 文字セットをUTF-8に設定
    $db->set_charset('utf8mb4');
    
    // トランザクション開始
    $db->begin_transaction();
    
    try {
        // 既存の登録があれば非アクティブ化
        $deactivateQuery = "UPDATE line_room_links SET is_active = 0 WHERE line_user_id = ?";
        $deactivateStmt = $db->prepare($deactivateQuery);
        
        if (!$deactivateStmt) {
            throw new Exception("クエリ準備エラー: " . $db->error);
        }
        
        $deactivateStmt->bind_param('s', $lineUserId);
        
        if (!$deactivateStmt->execute()) {
            throw new Exception("クエリ実行エラー: " . $deactivateStmt->error);
        }
        
        $deactivateStmt->close();
        
        // 新しい登録を追加
        $insertQuery = "INSERT INTO line_room_links (line_user_id, room_number, user_name, check_in_date, is_active, created_at) 
                        VALUES (?, ?, ?, CURDATE(), 1, NOW())";
        
        $insertStmt = $db->prepare($insertQuery);
        
        if (!$insertStmt) {
            throw new Exception("クエリ準備エラー: " . $db->error);
        }
        
        $insertStmt->bind_param('sss', $lineUserId, $roomNumber, $userName);
        
        if (!$insertStmt->execute()) {
            throw new Exception("クエリ実行エラー: " . $insertStmt->error);
        }
        
        $newId = $db->insert_id;
        $insertStmt->close();
        
        // トランザクションをコミット
        $db->commit();
        
        logMessage("部屋情報登録成功: ID = $newId, LINE UserID = $lineUserId, 部屋番号 = $roomNumber");
        
        // 成功レスポンス
        respondWithSuccess([
            'id' => $newId,
            'message' => '部屋情報の登録が完了しました'
        ]);
        
    } catch (Exception $e) {
        // トランザクションをロールバック
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // 例外処理
    logMessage("例外発生: " . $e->getMessage(), 'ERROR');
    respondWithError('システムエラーが発生しました: ' . $e->getMessage(), 500);
}
?> 