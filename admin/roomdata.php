<?php
/**
 * 部屋データ管理API
 * 部屋情報の取得と利用状況の確認を行うAPIエンドポイント
 */

// 出力バッファリングを開始
ob_start();

// エラー表示設定
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ログファイルの設定
$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/roomdata.log';

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

// エラーハンドラー
function handleError($errno, $errstr, $errfile, $errline) {
    writeLog("Error: [$errno] $errstr in $errfile on line $errline", 'ERROR');
    return true;
}
set_error_handler('handleError');

try {
    // 設定ファイルを読み込み
    $rootPath = realpath(__DIR__ . '/../..');
    require_once $rootPath . '/api/config/config.php';
    require_once $rootPath . '/api/lib/Utils.php';
    
    // CORSヘッダー設定
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // OPTIONSリクエストの場合は終了
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        ob_end_clean();
        exit(0);
    }
    
    // GETリクエストのみ許可
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('不正なリクエストメソッド');
    }
    
    // 管理者認証
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        writeLog("認証エラー: トークンが指定されていません", 'WARNING');
        throw new Exception('認証エラー');
    }
    
    // セッションからの認証トークンを使用
    session_start();
    if (!isset($_SESSION['auth_token']) || $_GET['token'] !== $_SESSION['auth_token']) {
        writeLog("認証エラー: トークンが一致しません " . substr($_GET['token'], 0, 3) . "... vs " . (isset($_SESSION['auth_token']) ? substr($_SESSION['auth_token'], 0, 3) . "..." : "未設定"), 'WARNING');
        throw new Exception('認証エラー');
    }
    
    writeLog("部屋データAPI呼び出し: " . json_encode($_GET));
    
    // データベース接続
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 部屋データ取得
    $activeOnly = isset($_GET['active']) && $_GET['active'] == '1';
    
    $sql = "SELECT * FROM roomdatasettings";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY room_number";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("部屋データ取得: " . count($rooms) . "件");
    
    // 利用状況も含める場合
    if (isset($_GET['usage']) && $_GET['usage'] == '1') {
        $sql = "SELECT r.room_number, l.user_name, l.check_in_date, l.check_out_date, l.is_active 
                FROM roomdatasettings r
                LEFT JOIN line_room_links l ON r.room_number = l.room_number
                ORDER BY r.room_number";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $usageData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        writeLog("利用状況データ取得: " . count($usageData) . "件");
        
        // バッファをクリアしてレスポンスを返す
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'usage' => $usageData
        ]);
    } else {
        // バッファをクリアしてレスポンスを返す
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'rooms' => $rooms
        ]);
    }
    
} catch (Exception $e) {
    writeLog("エラー発生: " . $e->getMessage(), 'ERROR');
    
    // バッファをクリアしてエラーレスポンスを返す
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 