<?php
/**
 * File: setup_directories.php
 * Description: RTSP_Readerシステムの必要ディレクトリ作成スクリプト
 * ハートビート時の画像保存や各種機能に必要なディレクトリを自動作成します
 */

// 定数定義
define('SCRIPT_DIR', __DIR__);

// ロギング用関数
function log_message($message) {
    echo date('Y-m-d H:i:s') . " - $message\n";
}

// バナー表示
echo "=================================================\n";
echo " RTSP_Reader ディレクトリセットアップユーティリティ\n";
echo "=================================================\n\n";

// 作成するディレクトリリスト
$directories = [
    // 画像保存用ディレクトリ
    SCRIPT_DIR . '/images',
    SCRIPT_DIR . '/images/devices',
    SCRIPT_DIR . '/latestimages',
    
    // ログディレクトリ
    SCRIPT_DIR . '/logs',
    
    // その他必要なディレクトリ
    SCRIPT_DIR . '/configs',
    SCRIPT_DIR . '/backup'
];

// ディレクトリ作成処理
$created_count = 0;
$existed_count = 0;
$error_count = 0;

foreach ($directories as $dir) {
    try {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                log_message("✅ 作成しました: $dir");
                $created_count++;
            } else {
                log_message("❌ 作成失敗: $dir");
                $error_count++;
            }
        } else {
            // 既存ディレクトリの権限確認と修正
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            if ($perms != "0755") {
                log_message("🔧 権限を修正: $dir ($perms → 0755)");
                chmod($dir, 0755);
            }
            log_message("ℹ️ 既に存在: $dir");
            $existed_count++;
        }
    } catch (Exception $e) {
        log_message("❌ エラー ($dir): " . $e->getMessage());
        $error_count++;
    }
}

// .htaccessファイルの作成
$image_access_file = SCRIPT_DIR . '/images/.htaccess';
if (!file_exists($image_access_file)) {
    $htaccess_content = <<<EOT
<IfModule mod_headers.c>
    Header set Cache-Control "max-age=86400, public"
</IfModule>
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 day"
    ExpiresByType image/png "access plus 1 day"
</IfModule>
EOT;
    
    if (file_put_contents($image_access_file, $htaccess_content)) {
        log_message("✅ 画像キャッシュ設定を作成しました: $image_access_file");
    } else {
        log_message("❌ 画像キャッシュ設定の作成に失敗しました");
    }
}

// ログディレクトリに空のログファイルを作成
$log_file = SCRIPT_DIR . '/logs/php.log';
if (!file_exists($log_file)) {
    if (file_put_contents($log_file, "")) {
        log_message("✅ ログファイルを作成しました: $log_file");
        chmod($log_file, 0666); // 書き込み権限を付与
    } else {
        log_message("❌ ログファイルの作成に失敗しました");
    }
}

// 結果表示
echo "\n=================================================\n";
echo "セットアップ完了:\n";
echo " - 作成: $created_count\n";
echo " - 既存: $existed_count\n";
echo " - エラー: $error_count\n";
echo "=================================================\n";

// ディレクトリツリーの表示
echo "\nディレクトリ構造:\n";
function print_directory_tree($dir, $prefix = '') {
    $files = scandir($dir);
    $files = array_filter($files, function($file) {
        return !in_array($file, ['.', '..']);
    });
    
    $count = count($files);
    $i = 0;
    
    foreach ($files as $file) {
        $i++;
        $isLast = ($i == $count);
        $path = $dir . '/' . $file;
        
        echo $prefix . ($isLast ? '└── ' : '├── ') . $file . "\n";
        
        if (is_dir($path)) {
            print_directory_tree(
                $path, 
                $prefix . ($isLast ? '    ' : '│   ')
            );
        }
    }
}

print_directory_tree(SCRIPT_DIR, ''); 