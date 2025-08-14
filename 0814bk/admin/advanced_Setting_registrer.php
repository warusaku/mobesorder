<?php
/**
 * advanced_Setting_registrer.php
 *
 * api/config/config.php に定義されている define() 定数を読み込み / 更新するエンドポイント
 * 
 * GET  : 全定数または ?key=DB_HOST のような単一取得
 * POST : JSON で {"DB_HOST":"xxxxx", ...} を送り、該当 define 行を書き換える
 * 
 * ログは logs/advanced_Setting_registrer.log に出力（300KB ローテーション / 20% 残し）
 */
 
// ===== パス設定 =====
$rootPath   = realpath(__DIR__ . '/..');
$configFile = $rootPath . '/api/config/config.php';
$logDir     = $rootPath . '/logs';
$logFile    = $logDir . '/advanced_Setting_registrer.log';
$maxLogSize = 307200; // 300KB
 
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
 
// ---------- ログ関数 ----------
if (!function_exists('advLog')) {
    function advLog($msg, $level = 'INFO') {
        global $logFile, $maxLogSize;
        // ローテーション
        if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
            $content   = file_get_contents($logFile);
            $keepSize  = intval($maxLogSize * 0.2);
            $newContent= substr($content, -$keepSize);
            file_put_contents($logFile, "--- ログローテーション " . date('Y-m-d H:i:s') . " ---\n" . $newContent);
        }
        $line = '[' . date('Y-m-d H:i:s') . "] [$level] $msg\n";
        file_put_contents($logFile, $line, FILE_APPEND);
    }
}
 
// ---------- 共通レスポンス ----------
function respondJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
 
// ---------- config.php を読み取って連想配列に ----------
function extractConfigArray($file) {
    $constants = [];
    $content = file($file, FILE_IGNORE_NEW_LINES);
    foreach ($content as $line) {
        if (preg_match("/^\s*define\(\s*'([A-Z0-9_]+)'\s*,\s*'([^']*)'\s*\)\s*;/", $line, $m)) {
            $constants[$m[1]] = $m[2];
        }
    }
    return $constants;
}
 
// ---------- 定数を書き換える ----------
function updateConfigFile($file, $newValues) {
    $content = file($file);
    $updated = false;
    for ($i = 0; $i < count($content); $i++) {
        if (preg_match("/^\s*define\(\s*'([A-Z0-9_]+)'\s*,\s*'([^']*)'\s*\)\s*;/", $content[$i], $m)) {
            $key = $m[1];
            if (array_key_exists($key, $newValues)) {
                $newVal = addslashes($newValues[$key]);
                $content[$i] = "define('$key', '$newVal');\n";
                $updated = true;
            }
        }
    }
    if ($updated) {
        return file_put_contents($file, implode('', $content)) !== false;
    }
    return false;
}
 
// ========== リクエスト処理 ==========
$method = $_SERVER['REQUEST_METHOD'];
advLog("REQUEST $method " . $_SERVER['REQUEST_URI']);
 
if ($method === 'GET') {
    $settings = extractConfigArray($configFile);
    if (isset($_GET['key'])) {
        $key = $_GET['key'];
        if (isset($settings[$key])) {
            respondJson(['success'=>true,'key'=>$key,'value'=>$settings[$key]]);
        }
        respondJson(['success'=>false,'message'=>'設定が見つかりません'],404);
    }
    respondJson(['success'=>true,'settings'=>$settings]);
}
 
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
        advLog('無効なJSON受信','ERROR');
        respondJson(['success'=>false,'message'=>'Invalid JSON'],400);
    }
    $result = updateConfigFile($configFile, $input);
    if ($result) {
        advLog('設定を更新: ' . implode(',', array_keys($input)));
        respondJson(['success'=>true,'message'=>'設定を更新しました']);
    }
    respondJson(['success'=>false,'message'=>'更新対象がありませんでした']);
}
 
respondJson(['success'=>false,'message'=>'Method Not Allowed'],405);
?> 