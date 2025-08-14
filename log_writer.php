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
    rotateLogFile($logFilePath, $maxSize, $reservePercent);
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
 * @param int $maxSize 最大サイズ（バイト）
 * @param int $reservePercent 残す割合（%）
 * @return bool 成功したかどうか
 */
function rotateLogFile($filePath, $maxSize, $reservePercent) {
    // ファイルが存在しない場合は何もしない
    if (!file_exists($filePath)) {
        return false;
    }
    
    // ファイルサイズを取得
    $fileSize = filesize($filePath);
    
    // 最大サイズを超えていない場合は何もしない
    if ($fileSize <= $maxSize) {
        return true;
    }
    
    // ファイル内容を読み込む
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        return false;
    }
    
    // 行に分割
    $lines = explode(PHP_EOL, $contents);
    
    // 残す行数を計算（全体の20%）
    $linesToKeep = max(1, intval(count($lines) * ($reservePercent / 100)));
    
    // 残す行を抽出（最新のものから）
    $newLines = array_slice($lines, -$linesToKeep);
    
    // ファイルに書き戻す
    $rotationMsg = "[" . date('Y-m-d H:i:s') . "] [SYSTEM] ログローテーション実行: " . 
                   count($lines) . "行 → " . count($newLines) . "行" . PHP_EOL;
    
    $newContent = implode(PHP_EOL, $newLines) . PHP_EOL . $rotationMsg;
    return file_put_contents($filePath, $newContent) !== false;
} 