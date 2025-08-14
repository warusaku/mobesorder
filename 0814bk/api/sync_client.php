#!/usr/bin/env php
<?php
/**
 * sync_client.php - RTSPリーダーシステム用設定同期クライアント
 * 
 * クラウドサーバーから設定を取得し、ローカルの設定ファイルを更新します。
 * cron/update_config.shから呼び出され、LacisIDを引数として受け取ります。
 * 
 * 使用方法: php sync_client.php lacis_id=LACIS001
 */

// スクリプトのディレクトリパスを取得
$script_dir = dirname(__FILE__);
$project_root = dirname($script_dir);

// ログファイルの設定
$log_file = $project_root . '/logs/sync.log';

// 設定ディレクトリ
$config_dir = $project_root . '/config';
$data_dir = $project_root . '/data';

// 必要なディレクトリを作成
if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

/**
 * ログにメッセージを記録する関数
 * 
 * @param string $message ログに記録するメッセージ
 * @return void
 */
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// 引数からLacisIDを取得
$lacis_id = 'LACIS_DEFAULT';
$args = $_SERVER['argv'];
if (count($args) > 1) {
    foreach ($args as $arg) {
        if (strpos($arg, 'lacis_id=') === 0) {
            $lacis_id = substr($arg, 9); // 'lacis_id='の長さが9
            break;
        }
    }
}

log_message("設定同期開始: LacisID=$lacis_id");

// クラウドサーバーの設定
$sync_url = 'https://example.com/rtsp_reader/api/config.php';
$security_key = 'rtsp_test'; // セキュリティキー

// 設定ファイルのパス
$config_file_path = $config_dir . '/device_config.json';
$temp_config_path = $data_dir . '/temp_config.json';

// 最終同期時間を取得（ファイルがあれば）
$last_sync_file = $data_dir . '/last_sync.txt';
$last_sync_time = file_exists($last_sync_file) ? trim(file_get_contents($last_sync_file)) : '0';

// CURLが利用可能か確認
if (!function_exists('curl_init')) {
    log_message("エラー: CURLがインストールされていません。設定の同期ができません。");
    exit(1);
}

// クラウドサーバーに接続して設定を取得
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $sync_url . '?lacis_id=' . urlencode($lacis_id) . '&key=' . urlencode($security_key) . '&last_sync=' . urlencode($last_sync_time));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // タイムアウト30秒

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// レスポンスの確認
if ($http_code != 200) {
    log_message("エラー: サーバーに接続できません。HTTPステータスコード: $http_code");
    exit(1);
}

// 一時ファイルに保存
file_put_contents($temp_config_path, $response);

// JSONの検証
$config_data = json_decode($response, true);
if ($config_data === null && json_last_error() !== JSON_ERROR_NONE) {
    log_message("エラー: 無効なJSON形式です: " . json_last_error_msg());
    unlink($temp_config_path);
    exit(1);
}

// 設定に変更があるか確認
$has_changes = true;
if (file_exists($config_file_path)) {
    $existing_config = file_get_contents($config_file_path);
    $existing_data = json_decode($existing_config, true);
    
    // バージョンや最終更新日時を比較
    if (isset($existing_data['version']) && isset($config_data['version'])) {
        if ($existing_data['version'] === $config_data['version']) {
            $has_changes = false;
        }
    } elseif ($existing_config === $response) {
        $has_changes = false;
    }
}

// 変更がなければそのまま終了
if (!$has_changes) {
    log_message("変更なし: 設定ファイルは最新です");
    // 最終同期時間を更新
    file_put_contents($last_sync_file, date('Y-m-d H:i:s'));
    unlink($temp_config_path);
    exit(0);
}

// 変更があれば設定ファイルを更新
log_message("設定ファイルに変更があります。更新を適用します");

// 既存のファイルがあればバックアップ
if (file_exists($config_file_path)) {
    $backup_path = $config_file_path . '.bak.' . date('YmdHis');
    copy($config_file_path, $backup_path);
    log_message("既存の設定ファイルをバックアップしました: " . basename($backup_path));
}

// 新しい設定を適用
rename($temp_config_path, $config_file_path);
log_message("新しい設定を適用しました: " . basename($config_file_path));

// 最終同期時間を更新
file_put_contents($last_sync_file, date('Y-m-d H:i:s'));

// 設定内容のサマリーをログに記録
$config_summary = [];
if (isset($config_data['version'])) {
    $config_summary[] = "バージョン: " . $config_data['version'];
}
if (isset($config_data['last_modified'])) {
    $config_summary[] = "最終更新: " . $config_data['last_modified'];
}
if (!empty($config_summary)) {
    log_message("設定情報: " . implode(", ", $config_summary));
}

// 設定変更完了フラグを設定
file_put_contents($data_dir . '/config_updated.txt', date('Y-m-d H:i:s'));

log_message("設定同期完了");
exit(0); 