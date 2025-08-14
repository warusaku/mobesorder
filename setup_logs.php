<?php
/**
 * ログディレクトリのセットアップスクリプト
 * 
 * このスクリプトは以下を行います：
 * 1. logsディレクトリが存在しない場合は作成
 * 2. 適切な権限を設定
 * 3. 必要な初期ログファイルを作成
 * 4. Square SDK依存関係の修正
 */

// ログディレクトリのパス
$logDir = __DIR__ . '/logs';
$certDir = __DIR__ . '/certificates';

// ステータスメッセージ出力関数
function output($message, $isError = false) {
    echo ($isError ? "\033[31m" : "\033[32m") . $message . "\033[0m\n";
}

// 1. ログディレクトリの確認と作成
if (!is_dir($logDir)) {
    output("ログディレクトリが存在しません。作成します: {$logDir}");
    if (mkdir($logDir, 0775, true)) {
        output("ログディレクトリを作成しました");
    } else {
        output("ログディレクトリの作成に失敗しました", true);
        exit(1);
    }
} else {
    output("ログディレクトリは既に存在します: {$logDir}");
}

// 2. ログディレクトリの権限を設定
$currentPerms = substr(sprintf('%o', fileperms($logDir)), -4);
output("現在のログディレクトリの権限: {$currentPerms}");

if (!chmod($logDir, 0775)) {
    output("ログディレクトリの権限設定に失敗しました", true);
} else {
    output("ログディレクトリの権限を 0775 に設定しました");
}

// 3. 証明書ディレクトリの確認と作成
if (!is_dir($certDir)) {
    output("証明書ディレクトリが存在しません。作成します: {$certDir}");
    if (mkdir($certDir, 0755, true)) {
        output("証明書ディレクトリを作成しました");
    } else {
        output("証明書ディレクトリの作成に失敗しました", true);
    }
} else {
    output("証明書ディレクトリは既に存在します: {$certDir}");
}

// 4. 証明書ディレクトリの権限を設定
$currentPerms = substr(sprintf('%o', fileperms($certDir)), -4);
output("現在の証明書ディレクトリの権限: {$currentPerms}");

if (!chmod($certDir, 0755)) {
    output("証明書ディレクトリの権限設定に失敗しました", true);
} else {
    output("証明書ディレクトリの権限を 0755 に設定しました");
}

// 5. Square SDK依存関係の修正
$nonEmptyParamFile = __DIR__ . '/api/vendor/apimatic/core-interfaces/src/Core/Request/NonEmptyParamInterface.php';
if (file_exists($nonEmptyParamFile)) {
    output("Square SDK依存関係ファイルを修正します: {$nonEmptyParamFile}");
    $content = file_get_contents($nonEmptyParamFile);
    
    // 正しいインポート文の追加
    if (strpos($content, 'use CoreInterfaces\Core\Request\ParamInterface;') === false) {
        $content = str_replace(
            'namespace CoreInterfaces\Core\Request;',
            "namespace CoreInterfaces\Core\Request;\n\nuse CoreInterfaces\Core\Request\ParamInterface;",
            $content
        );
        
        if (file_put_contents($nonEmptyParamFile, $content)) {
            output("依存関係ファイルを修正しました");
        } else {
            output("依存関係ファイルの修正に失敗しました", true);
        }
    } else {
        output("依存関係ファイルは既に修正されています");
    }
} else {
    output("依存関係ファイルが見つかりません: {$nonEmptyParamFile}", true);
}

// 6. 必須ログファイルの作成
$logFiles = [
    'RoomTicketService.log',
    'SquareService.log',
    'OrderService.log',
    'system_logs.log',
    'orders_api_error.log',
    'error.log'
];

foreach ($logFiles as $logFile) {
    $fullPath = $logDir . '/' . $logFile;
    
    if (!file_exists($fullPath)) {
        $timestamp = date('Y-m-d H:i:s');
        $initialContent = "[{$timestamp}] INFO: ログファイル初期化\n";
        
        if (file_put_contents($fullPath, $initialContent)) {
            output("ログファイルを作成しました: {$logFile}");
            chmod($fullPath, 0664);
        } else {
            output("ログファイルの作成に失敗しました: {$logFile}", true);
        }
    } else {
        output("ログファイルは既に存在します: {$logFile}");
        chmod($fullPath, 0664);
    }
}

// 7. ログファイルのローテーションスクリプトの配置
$rotateScriptContent = <<<'EOT'
<?php
/**
 * ログローテーションスクリプト
 * 
 * 定期的に実行して、ログファイルのサイズを管理します。
 * cron job として設定することを推奨: 
 * 0 0 * * * php /path/to/this/script.php
 */

$logDir = __DIR__;
$maxFileSize = 5 * 1024 * 1024; // 5MB
$maxBackups = 5;

// ログファイル一覧
$logFiles = glob($logDir . '/*.log');

foreach ($logFiles as $logFile) {
    if (filesize($logFile) > $maxFileSize) {
        $baseName = basename($logFile);
        $backupName = $logDir . '/' . $baseName . '.' . date('Ymd_His');
        
        // 古いバックアップファイルを削除
        $oldBackups = glob($logDir . '/' . $baseName . '.*');
        usort($oldBackups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        while (count($oldBackups) >= $maxBackups) {
            $fileToDelete = array_shift($oldBackups);
            unlink($fileToDelete);
            echo "古いログファイルを削除: " . basename($fileToDelete) . "\n";
        }
        
        // 現在のファイルをバックアップとして移動
        rename($logFile, $backupName);
        
        // 新しい空のログファイルを作成
        $timestamp = date('Y-m-d H:i:s');
        $initialContent = "[{$timestamp}] INFO: ログファイルをローテーションしました。前回のファイル: " . basename($backupName) . "\n";
        file_put_contents($logFile, $initialContent);
        chmod($logFile, 0664);
        
        echo "ログローテーション完了: {$baseName} -> " . basename($backupName) . "\n";
    }
}

echo "ログローテーション処理を完了しました: " . date('Y-m-d H:i:s') . "\n";
EOT;

$rotateScriptPath = $logDir . '/rotate_logs.php';
if (!file_exists($rotateScriptPath) || md5_file($rotateScriptPath) != md5($rotateScriptContent)) {
    if (file_put_contents($rotateScriptPath, $rotateScriptContent)) {
        output("ログローテーションスクリプトを作成/更新しました");
        chmod($rotateScriptPath, 0755);
    } else {
        output("ログローテーションスクリプトの作成に失敗しました", true);
    }
} else {
    output("ログローテーションスクリプトは既に最新です");
}

// 8. サマリー
output("\n===== セットアップ完了 =====");
output("ログディレクトリ: {$logDir}");
output("証明書ディレクトリ: {$certDir}");
output("作成されたログファイル: " . count($logFiles));
output("ログローテーションスクリプト: {$rotateScriptPath}");
output("Square SDK依存関係の修正: " . (file_exists($nonEmptyParamFile) ? "完了" : "失敗"));
output("\nLolipopサーバーへアップロードし、ウェブページからsetup_logs.phpを実行してください。");
output("URLからアクセス: http://test-mijeos.but.jp/fgsquare/setup_logs.php");
output("==================\n"); 