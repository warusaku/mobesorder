<?php
/**
 * ユーザー強制削除API
 * 
 * LINE登録ユーザーを強制的に削除（非アクティブ化）するためのAPIエンドポイント
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// セッション開始
session_start();

// ログファイルの設定
$logDir = $rootPath . '/logs';
$logFile = $logDir . '/user_deactivate.log';

// ログディレクトリの存在確認と作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// ログ関数
function writeLog($message, $level = 'INFO') {
    global $logFile;
    
    // ファイルサイズをチェック
    if (file_exists($logFile) && filesize($logFile) > 204800) { // 200KB
        // ファイルを削除して新規作成
        unlink($logFile);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// JSONリクエストの取得
$inputData = json_decode(file_get_contents('php://input'), true);

// レスポンスの初期化
$response = [
    'success' => false,
    'message' => '不明なエラーが発生しました'
];

// ログイン状態チェック
if (!isset($_SESSION['auth_user'])) {
    writeLog("未認証のアクセス", "ERROR");
    $response['message'] = '認証されていません';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// データベース接続
try {
    $db = Database::getInstance();
    writeLog("データベース接続成功");
} catch (Exception $e) {
    writeLog("データベース接続エラー: " . $e->getMessage(), 'ERROR');
    $response['message'] = 'データベース接続エラー';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 入力チェック
if (empty($inputData['id'])) {
    writeLog("ユーザーIDが指定されていません", "ERROR");
    $response['message'] = 'ユーザーIDが必要です';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

try {
    // 指定されたユーザーの存在確認
    $userId = $inputData['id'];
    $roomNumber = $inputData['room_number'] ?? '';
    
    $userData = $db->selectOne("SELECT * FROM line_room_links WHERE id = ?", [$userId]);
    
    if (!$userData) {
        writeLog("指定されたユーザーIDが見つかりません: $userId", "ERROR");
        $response['message'] = '指定されたユーザーが見つかりません';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // ユーザーの非アクティブ化
    $result = $db->execute(
        "UPDATE line_room_links SET is_active = 0 WHERE id = ?",
        [$userId]
    );
    
    if ($result) {
        writeLog("ユーザーを非アクティブ化しました: ID=$userId, 部屋番号={$userData['room_number']}, LINE ID={$userData['line_user_id']}", "INFO");
        $response = [
            'success' => true,
            'message' => 'ユーザーを強制削除しました'
        ];
    } else {
        writeLog("ユーザーの非アクティブ化に失敗しました: ID=$userId", "ERROR");
        $response['message'] = 'データベース更新エラー';
    }
} catch (Exception $e) {
    writeLog("ユーザー非アクティブ化処理エラー: " . $e->getMessage(), 'ERROR');
    $response['message'] = 'エラー: ' . $e->getMessage();
}

// JSONレスポンス
header('Content-Type: application/json');
echo json_encode($response); 