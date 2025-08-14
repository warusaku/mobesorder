<?php
// File: rotate_log.php
// Description: php.logのサイズ制限（500KB）を超えたら古い行を削除

$logPath = __DIR__ . '/php.log';
$maxSize = 500 * 1024;  // 500KB

// ログファイルの存在チェック
if (!file_exists($logPath)) {
    // ログファイルが存在しない場合は空ファイルを作成
    file_put_contents($logPath, "");
    exit;
}

// ファイルサイズチェック
$fileSize = filesize($logPath);
if ($fileSize <= $maxSize) {
    // サイズ制限以下なら何もしない
    exit;
}

// ログファイルの内容を読み込む
$lines = file($logPath);
$totalLines = count($lines);

// 最新の2000行（または全行数の半分）を保持する
$keepLines = min(2000, intval($totalLines / 2));
if ($keepLines < 100) {
    // 最低100行は保持する
    $keepLines = min(100, $totalLines);
}

// 最新の行を残して古い行を削除
$newContent = array_slice($lines, -$keepLines);

// ファイルに書き戻す
file_put_contents($logPath, implode('', $newContent));

// 完了時のタイムスタンプ
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logPath, "{$timestamp} [SYSTEM] Log rotated: {$totalLines} lines -> {$keepLines} lines\n", FILE_APPEND);

exit;
?> 