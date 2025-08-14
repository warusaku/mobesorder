<?php
/**
 * LINE ID と部屋連携状態を確認するAPI
 * line_room_linksテーブルからユーザーの連携状況を取得
 */

// エラー表示を有効化
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 共通システム設定ファイルの読み込み
require_once '../../../api/config/config.php';

// ログファイル名を設定
$logFile = '../../../logs/check-room-link.log';

// 起動ログを出力
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "][INFO] API起動: check-room-link.php\n", FILE_APPEND);

/**
 * ログメッセージを記録する関数
 * @param string $message ログメッセージ
 * @param string $level ログレベル（INFO/WARN/ERROR）
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    
    // ログディレクトリがなければ作成
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ログローテーション処理
    rotateLogIfNeeded($logFile);
    
    // タイムスタンプ付きでログを記録
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp][$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

/**
 * ログファイルのローテーションを行う関数
 * @param string $logFile ログファイルのパス
 */
function rotateLogIfNeeded($logFile) {
    if (!file_exists($logFile)) {
        return;
    }
    
    $maxSize = 300 * 1024; // 300KB
    $fileSize = filesize($logFile);
    
    if ($fileSize >= $maxSize) {
        // ファイルサイズが上限を超えた場合、20%程度を残して削除
        $content = file_get_contents($logFile);
        $keepRatio = 0.2; // 20%を保持
        $newContent = substr($content, (int)($fileSize * (1 - $keepRatio)));
        file_put_contents($logFile, $newContent);
        
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp][INFO] ログファイルをローテーション実行\n";
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }
}

// レスポンスはJSON形式
header('Content-Type: application/json');

// LINE IDパラメータの確認
if (!isset($_GET['line_user_id'])) {
    logMessage("エラー: line_user_idパラメータがありません", 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'LINE IDが指定されていません'
    ]);
    exit;
}

$lineUserId = $_GET['line_user_id'];
logMessage("部屋連携確認リクエスト: LINE_ID=" . substr($lineUserId, 0, 5) . "...", 'INFO');

try {
    // データベース設定確認ログ
    logMessage("DBホスト: " . (defined('DB_HOST') ? DB_HOST : 'undefined'), 'INFO');
    logMessage("DBユーザー: " . (defined('DB_USER') ? DB_USER : 'undefined'), 'INFO');
    logMessage("DB名: " . (defined('DB_NAME') ? DB_NAME : 'undefined'), 'INFO');
    
    // データベース接続
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        logMessage("データベース接続エラー: " . $conn->connect_error, 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'データベース接続エラー: ' . $conn->connect_error
        ]);
        exit;
    }
    
    logMessage("データベース接続成功", 'INFO');
    
    // LINE IDで部屋連携を検索
    $sql = "SELECT * FROM line_room_links WHERE line_user_id = ?";
    logMessage("SQL実行: " . $sql, 'INFO');
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logMessage("プリペアステートメントエラー: " . $conn->error, 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => 'SQLエラー: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("s", $lineUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // 連携情報あり
        $row = $result->fetch_assoc();
        
        // 結果をログに出力
        logMessage("部屋連携あり: room_number=" . $row['room_number'] . ", is_active=" . $row['is_active'], 'INFO');
        
        // レスポンス作成
        echo json_encode([
            'success' => true,
            'is_linked' => true,
            'room_info' => [
                'room_number' => $row['room_number'],
                'user_name' => $row['user_name'],
                'check_in_date' => $row['check_in_date'],
                'check_out_date' => $row['check_out_date'],
                'is_active' => (int)$row['is_active'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ]
        ]);
    } else {
        // 連携情報なし
        logMessage("部屋連携なし: LINE_ID=" . substr($lineUserId, 0, 5) . "...", 'WARN');
        echo json_encode([
            'success' => true,
            'is_linked' => false,
            'message' => '部屋連携がありません。登録が必要です。'
        ]);
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    logMessage("処理エラー: " . $e->getMessage(), 'ERROR');
    logMessage("エラースタック: " . $e->getTraceAsString(), 'ERROR');
    echo json_encode([
        'success' => false,
        'message' => 'エラーが発生しました: ' . $e->getMessage()
    ]);
}
?> 