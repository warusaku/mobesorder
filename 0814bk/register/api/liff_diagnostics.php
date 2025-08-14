<?php
/**
 * LIFF診断情報API
 * Version: 1.0.0
 * 
 * クライアント側のLIFF認証問題を診断するための情報を収集・分析
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// POSTデータの取得
$input = json_decode(file_get_contents('php://input'), true);

// 診断情報の収集
$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [
        'php_version' => PHP_VERSION,
        'session_id' => session_id(),
        'session_status' => session_status(),
        'redirect_count' => $_SESSION['liff_redirect_count_2007363986-nMAv6J8w'] ?? 0,
        'last_redirect_time' => $_SESSION['liff_redirect_count_2007363986-nMAv6J8w_time'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? null,
        'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_enabled' => !empty($_COOKIE),
        'session_cookie' => isset($_COOKIE[session_name()])
    ],
    'client' => $input['client'] ?? [],
    'errors' => $input['errors'] ?? []
];

// 診断結果の分析
$analysis = [];
$recommendations = [];

// UserAgent分析
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isLineApp = strpos($ua, 'Line/') !== false;
$isAndroid = strpos($ua, 'Android') !== false;
$isIOS = strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false;
$isWebView = strpos($ua, 'wv') !== false || strpos($ua, 'WebView') !== false;

if (!$isLineApp) {
    $analysis[] = 'LINEアプリ外からのアクセス';
    $recommendations[] = 'LINEアプリ内で開く必要があります';
}

if ($isAndroid && $isWebView) {
    $analysis[] = 'Android WebViewからのアクセス';
    if (preg_match('/Chrome\/([0-9]+)/', $ua, $matches)) {
        $chromeVersion = (int)$matches[1];
        if ($chromeVersion < 90) {
            $recommendations[] = 'Android System WebViewを最新版にアップデートしてください';
        }
    }
}

// リダイレクト回数チェック
if ($diagnostics['server']['redirect_count'] >= 2) {
    $analysis[] = 'リダイレクト上限に到達';
    $recommendations[] = '5分待ってから再度アクセスしてください';
}

// セッション問題チェック
if (!$diagnostics['server']['session_cookie']) {
    $analysis[] = 'セッションクッキーが保存されていません';
    $recommendations[] = 'Cookieを有効にしてください';
    $recommendations[] = 'プライベートブラウジングモードを無効にしてください';
}

// クライアント側エラーの分析
if (!empty($input['errors'])) {
    foreach ($input['errors'] as $error) {
        if (strpos($error, 'SDK') !== false) {
            $analysis[] = 'LIFF SDK読み込みエラー';
            $recommendations[] = 'ネットワーク接続を確認してください';
        }
        if (strpos($error, 'timeout') !== false || strpos($error, 'タイムアウト') !== false) {
            $analysis[] = 'タイムアウトエラー';
            $recommendations[] = 'ネットワーク速度を確認してください';
            $recommendations[] = 'Wi-Fi接続を試してください';
        }
        if (strpos($error, 'storage') !== false || strpos($error, 'ストレージ') !== false) {
            $analysis[] = 'ストレージアクセスエラー';
            $recommendations[] = 'プライベートブラウジングモードを無効にしてください';
            $recommendations[] = 'ブラウザのキャッシュをクリアしてください';
        }
    }
}

// ログファイルに記録
$logDir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/liff_diagnostics.log';
$logEntry = date('Y-m-d H:i:s') . ' - ' . json_encode([
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'ua' => substr($ua, 0, 100),
    'analysis' => $analysis,
    'client_errors' => $input['errors'] ?? []
], JSON_UNESCAPED_UNICODE) . PHP_EOL;

// ログファイルサイズチェック（300KB制限）
if (file_exists($logFile) && filesize($logFile) > 307200) {
    // 古いログを削除
    file_put_contents($logFile, '');
}

file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// レスポンス
$response = [
    'success' => true,
    'diagnostics' => $diagnostics,
    'analysis' => $analysis,
    'recommendations' => array_unique($recommendations),
    'should_retry' => count($analysis) === 0 || 
                     ($diagnostics['server']['redirect_count'] < 2 && !in_array('リダイレクト上限に到達', $analysis))
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?> 