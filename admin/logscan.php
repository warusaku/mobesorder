<?php
/**
 * logscan.php - ログファイルスキャナー
 * 
 * /logs ディレクトリ内の.logファイルをスキャンし、JSONフォーマットで返します
 */

// セキュリティ対策: 直接アクセスを制限するためのセッションチェック
session_start();
if (!isset($_SESSION['auth_user'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// 設定
$rootPath = realpath(__DIR__ . '/..');
$logDir = $rootPath . '/logs';
$response = [
    'success' => false,
    'files' => [],
    'timestamp' => time(),
    'error' => ''
];

try {
    // ディレクトリが存在するか確認
    if (!file_exists($logDir) || !is_dir($logDir)) {
        throw new Exception("ログディレクトリが存在しません: {$logDir}");
    }
    
    // ディレクトリの読み取り権限を確認
    if (!is_readable($logDir)) {
        throw new Exception("ログディレクトリの読み取り権限がありません: {$logDir}");
    }
    
    // .logファイルをスキャン
    $logFiles = [];
    $dir = new DirectoryIterator($logDir);
    
    foreach ($dir as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getExtension() === 'log') {
            $logFiles[] = [
                'name' => $fileInfo->getFilename(),
                'path' => str_replace($rootPath, '', $fileInfo->getPathname()), // 相対パス
                'size' => $fileInfo->getSize(),
                'size_formatted' => formatFileSize($fileInfo->getSize()),
                'mtime' => $fileInfo->getMTime(),
                'mtime_formatted' => date('Y-m-d H:i:s', $fileInfo->getMTime())
            ];
        }
    }
    
    // 最終更新日時で降順ソート
    usort($logFiles, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    
    $response['success'] = true;
    $response['files'] = $logFiles;
    
    // ファイル内容も取得するモード
    if (isset($_GET['file']) && !empty($_GET['file'])) {
        $requestedFile = basename($_GET['file']); // セキュリティ対策
        $filePath = $logDir . '/' . $requestedFile;
        
        if (file_exists($filePath) && is_readable($filePath) && is_file($filePath)) {
            // ファイルの拡張子を確認（.logファイルのみ許可）
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($ext !== 'log') {
                throw new Exception("無効なファイルタイプです");
            }
            
            // ファイルサイズを確認（大きすぎるファイルは処理しない）
            if (filesize($filePath) > 5 * 1024 * 1024) { // 5MB制限
                $content = "ファイルが大きすぎます (上限: 5MB)。先頭100KBを表示します。\n\n";
                $content .= file_get_contents($filePath, false, null, 0, 100 * 1024);
            } else {
                $content = file_get_contents($filePath);
            }
            
            $response['file_content'] = $content;
            $response['file_name'] = $requestedFile;
        } else {
            throw new Exception("要求されたファイルが見つかりません");
        }
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

// レスポンスのJSONヘッダーを設定
header('Content-Type: application/json');
echo json_encode($response);
exit;

/**
 * ファイルサイズを人間が読みやすい形式にフォーマット
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
} 