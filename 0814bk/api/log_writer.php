<?php
/**
 * ログ書き込みAPI
 * フロントエンドからのログリクエストを処理し、指定されたファイルにログを出力します
 * ログファイルのローテーション機能も含みます
 */

// クロスオリジンリクエストを許可
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト（プリフライト）への対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// リクエストヘッダーチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POSTメソッドのみ許可されています']);
    exit;
}

// リクエストボディの取得
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// パラメータのバリデーション
if (!isset($data['message']) || empty($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'メッセージが指定されていません']);
    exit;
}

// ログファイル名の決定
$logFileName = isset($data['file']) && !empty($data['file']) ? 
    basename($data['file']) : 'default.log';

// 絶対パスでのログディレクトリ
$logDirectory = dirname(__DIR__) . '/logs';

// ディレクトリ存在チェック
if (!is_dir($logDirectory)) {
    if (!mkdir($logDirectory, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'ログディレクトリの作成に失敗しました']);
        exit;
    }
}

// 完全なログファイルパス
$logFilePath = $logDirectory . '/' . $logFileName;

// ログメッセージの作成
$timestamp = date('Y-m-d H:i:s');
$logType = isset($data['type']) ? strtoupper($data['type']) : 'INFO';
$message = $data['message'];
$logLine = "[{$timestamp}] [{$logType}] {$message}" . PHP_EOL;

// ローテーション処理: ファイルサイズを確認
$maxSize = 300 * 1024; // 300KB
$reservePercent = 20; // 20%を残す

if (file_exists($logFilePath) && filesize($logFilePath) > $maxSize) {
    rotateLogFileFast($logFilePath, $reservePercent);
}

// ログの書き込み
if (!file_put_contents($logFilePath, $logLine, FILE_APPEND)) {
    http_response_code(500);
    echo json_encode(['error' => 'ログの書き込みに失敗しました']);
    exit;
}

// 成功レスポンス
http_response_code(200);
echo json_encode(['success' => true]);
exit;

/**
 * ログファイルをローテーションする関数
 * 
 * @param string $filePath ログファイルのパス
 * @param int $reservePercent 残す割合（%）
 * @return bool 成功したかどうか
 */
function rotateLogFileFast($filePath, $reservePercent) {
    // 末尾（reservePercent%）のみを残す高速ローテーション
    $fp = fopen($filePath, 'c+');
    if (!$fp) return false;
    $fileSize = filesize($filePath);
    if ($fileSize === false || $fileSize === 0) { fclose($fp); return true; }

    $keepBytes = max(1024, (int)($fileSize * ($reservePercent / 100)));
    if ($keepBytes >= $fileSize) { fclose($fp); return true; }

    // ファイル末尾から保持サイズ分だけ読み取る
    fseek($fp, -$keepBytes, SEEK_END);
    $data = fread($fp, $keepBytes);

    if ($data === false) { fclose($fp); return false; }

    // ファイルをトランケートして先頭に書き戻す
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $data);

    // ローテーションメッセージを追記
    $rotationMsg = "[" . date('Y-m-d H:i:s') . "] [SYSTEM] ログローテーション実行: " .
                   $fileSize . "B → " . strlen($data) . "B" . PHP_EOL;
    fwrite($fp, $rotationMsg);
    fclose($fp);
    return true;
} 