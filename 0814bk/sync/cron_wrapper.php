<?php
/**
 * Lolipop cron用ラッパースクリプト
 * 
 * このスクリプトは、Lolipopのcron設定から呼び出され、
 * トークン認証付きで商品同期APIを実行します。
 * 
 * 実行形式: /home/users/2/but.jp-test-mijeos/web/fgsquare/api/sync/cron_wrapper.php
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/../../');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Utils.php';

// ログ関数
function logCronMessage($message, $level = 'INFO') {
    global $rootPath;
    
    $logDir = $rootPath . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/cron_sync.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Utils::logが利用可能ならそちらも使用
    if (class_exists('Utils') && method_exists('Utils', 'log')) {
        Utils::log($message, $level, 'cron_wrapper');
    }
}

try {
    logCronMessage('ラッパースクリプト開始');
    
    // 認証トークンを設定ファイルから取得（定数定義がある場合）
    $token = defined('SYNC_TOKEN') ? SYNC_TOKEN : '';
    
    if (empty($token)) {
        // トークンが設定されていない場合、フォールバックとして一般的な管理者トークンを探す
        // admin.jsonからトークンを探す
        $adminFile = $rootPath . '/admin/user.json';
        if (file_exists($adminFile)) {
            $adminConfig = json_decode(file_get_contents($adminFile), true);
            if (isset($adminConfig['user']['fabula'][1])) {
                $token = $adminConfig['user']['fabula'][1];
            } elseif (isset($adminConfig['user']['admin'][1])) {
                $token = $adminConfig['user']['admin'][1];
            }
        }
        
        // それでもトークンがない場合はエラー
        if (empty($token)) {
            throw new Exception('認証トークンが設定されていません');
        }
    }
    
    // 正しいURLを直接指定（絶対パス）
    $syncUrl = 'http://test-mijeos.but.jp/fgsquare/api/sync/sync_products.php?token=' . urlencode($token);
    
    logCronMessage('同期API呼び出し: ' . $syncUrl);
    
    // curlを使用してAPIを呼び出す
    $ch = curl_init($syncUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5分タイムアウト
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 自己署名証明書対応
    
    // 実行
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    // 実行結果の確認
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $executionTime = round(($endTime - $startTime), 2);
    
    if ($httpCode === 200) {
        // 成功
        $jsonResponse = json_decode($response, true);
        
        if (is_array($jsonResponse) && isset($jsonResponse['success'])) {
            if ($jsonResponse['success']) {
                // 成功メッセージをログに記録
                $stats = [];
                
                // stats情報の取得（複数の構造に対応）
                if (isset($jsonResponse['stats'])) {
                    $stats = $jsonResponse['stats'];
                } else if (isset($jsonResponse['products']) && isset($jsonResponse['products']['stats'])) {
                    $stats = $jsonResponse['products']['stats'];
                } else if (isset($jsonResponse['product_sync']) && isset($jsonResponse['product_sync']['stats'])) {
                    $stats = $jsonResponse['product_sync']['stats'];
                }
                
                $statsText = '';
                if (!empty($stats)) {
                    $statsText = '追加:' . ($stats['added'] ?? 0) . '件, 更新:' . ($stats['updated'] ?? 0) . '件, エラー:' . ($stats['errors'] ?? 0) . '件';
                }
                
                logCronMessage('同期完了 - 処理時間:' . $executionTime . '秒 - ' . $statsText);
            } else {
                // 同期APIからエラーが返された
                $errorMessage = isset($jsonResponse['message']) ? $jsonResponse['message'] : '不明なエラー';
                logCronMessage('同期API実行エラー: ' . $errorMessage, 'ERROR');
            }
        } else {
            // JSONではない、または不正な形式
            logCronMessage('同期APIからの応答が不正: ' . substr($response, 0, 200), 'ERROR');
        }
    } else {
        // HTTP エラー - 詳細情報
        logCronMessage('同期API HTTP エラー: ' . $httpCode . ' - エラー: ' . $error, 'ERROR');
        logCronMessage('リクエストURL: ' . $syncUrl, 'ERROR');
        logCronMessage('レスポンス (先頭500文字): ' . substr($response, 0, 500), 'ERROR');
    }
    
    // ログファイルにも記録
    $logDir = $rootPath . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/cron_response_' . date('Ymd') . '.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - HTTP: {$httpCode}\n{$response}\n\n", FILE_APPEND);
    
    logCronMessage('ラッパースクリプト終了');
    
} catch (Exception $e) {
    logCronMessage('予期せぬエラー: ' . $e->getMessage(), 'ERROR');
    logCronMessage('スタックトレース: ' . $e->getTraceAsString(), 'ERROR');
} 