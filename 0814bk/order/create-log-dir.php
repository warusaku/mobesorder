<?php
/**
 * ログディレクトリを作成するスクリプト
 * このスクリプトは注文システムが初めて実行される前に手動で実行することが推奨されます
 */

// ログディレクトリパス
$logsDir = __DIR__ . '/../logs';

// ログディレクトリが存在しない場合は作成
if (!file_exists($logsDir)) {
    if (mkdir($logsDir, 0755, true)) {
        echo "ログディレクトリを作成しました: " . $logsDir . "\n";
    } else {
        die("ログディレクトリの作成に失敗しました: " . $logsDir . "\n");
    }
} else {
    echo "ログディレクトリは既に存在します: " . $logsDir . "\n";
}

// ログディレクトリの権限を確認
$perms = substr(sprintf('%o', fileperms($logsDir)), -4);
if ($perms !== '0755') {
    if (chmod($logsDir, 0755)) {
        echo "ログディレクトリの権限を0755に設定しました\n";
    } else {
        echo "警告: ログディレクトリの権限を変更できませんでした\n";
    }
}

// .htaccessファイルの作成（ログディレクトリへの直接アクセスを防止）
$htaccessFile = $logsDir . '/.htaccess';
$htaccessContent = "# ログディレクトリへの直接アクセスを防止\nDeny from all\n";

if (!file_exists($htaccessFile)) {
    if (file_put_contents($htaccessFile, $htaccessContent)) {
        echo ".htaccessファイルを作成しました\n";
    } else {
        echo "警告: .htaccessファイルの作成に失敗しました\n";
    }
} else {
    echo ".htaccessファイルは既に存在します\n";
}

echo "セットアップが完了しました\n"; 