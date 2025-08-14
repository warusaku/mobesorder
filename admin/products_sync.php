<?php
/**
 * 商品同期管理ユーティリティ
 * 
 * このスクリプトは、Squareからの商品データ同期を管理するための
 * 管理者向けインターフェースを提供します。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';
require_once $rootPath . '/api/lib/SquareService.php';
require_once $rootPath . '/api/lib/ProductService.php';

// セッション開始
session_start();

// ログファイル関連の定数
define('LOG_FILE_PATH', __DIR__ . '/../logs/products_sync.log');
define('MAX_LOG_SIZE', 307200); // 300KB
define('RETENTION_PERCENT', 20); // ログローテーション時に残す割合

// ログ関数
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    
    // より詳細なログ情報を取得（呼び出し元関数名、ファイル名、行番号）
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
    $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
    $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
    
    // メッセージにコンテキスト情報を追加
    $formattedMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
    
    // ログディレクトリの確認・作成
    $logDir = dirname(LOG_FILE_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ログローテーションのチェック
    checkLogRotation();
    
    // ログファイルに書き込み
    file_put_contents(LOG_FILE_PATH, $formattedMessage, FILE_APPEND);
    
    // Utils::logにも記録（既存の動作を維持）
    if (class_exists('Utils')) {
    Utils::log($message, $level, 'ProductSyncManager');
    }
    
    // デバッグモードが有効な場合はエラーログにも記録（開発時のトラブルシューティング用）
    if ($level === 'ERROR' || $level === 'WARNING') {
        error_log("ProductSyncManager: [$level] $message");
    }
}

/**
 * ログファイルのサイズをチェックし、必要に応じてローテーションを実行
 */
function checkLogRotation() {
    if (!file_exists(LOG_FILE_PATH)) {
        // ログファイルが存在しない場合は作成
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] [INFO] 新規ログファイル作成\n";
        file_put_contents(LOG_FILE_PATH, $message);
        return;
    }
    
    $fileSize = filesize(LOG_FILE_PATH);
    
    // サイズが上限を超えている場合
    if ($fileSize > MAX_LOG_SIZE) {
        // ファイルのメタデータを取得
        $timestamp = date('Y-m-d H:i:s');
        $fileSizeKB = round($fileSize / 1024, 2);
        $maxSizeKB = round(MAX_LOG_SIZE / 1024, 2);
        $retentionKB = round(MAX_LOG_SIZE * (RETENTION_PERCENT / 100) / 1024, 2);
        
        // ファイルの内容を読み込む
        $content = file_get_contents(LOG_FILE_PATH);
        
        // 20%分のデータを残す位置を計算
        $keepSize = floor($fileSize * (RETENTION_PERCENT / 100));
        $position = $fileSize - $keepSize;
        
        // ファイルの最初の行区切りを見つける
        $newLinePos = strpos($content, "\n", $position);
        if ($newLinePos !== false) {
            $position = $newLinePos + 1;
        }
        
        // 指定位置以降のデータだけを抽出
        $newContent = substr($content, $position);
        
        // ローテーション情報を追加（より詳細な情報を含む）
        $rotationMessage = "[$timestamp] [INFO] ログローテーション実行:\n" . 
                          "- 元ファイルサイズ: {$fileSizeKB}KB\n" . 
                          "- 上限サイズ: {$maxSizeKB}KB\n" . 
                          "- 保持サイズ: {$retentionKB}KB (" . RETENTION_PERCENT . "%)\n" . 
                          "- 行数削減: 全体 " . substr_count($content, "\n") . " 行から " . substr_count($newContent, "\n") . " 行に\n";
        
        // 新しい内容をファイルに書き込み
        file_put_contents(LOG_FILE_PATH, $rotationMessage . $newContent);
        
        // ローテーション情報をエラーログにも記録
        error_log("ProductSyncManager: ログローテーション実行 - サイズ: {$fileSizeKB}KB → {$retentionKB}KB");
    }
}

// ユーザー認証情報を読み込み
$userAuthFile = $rootPath . '/admin/user.json';
$users = [];

if (file_exists($userAuthFile)) {
    $jsonContent = file_get_contents($userAuthFile);
    $authData = json_decode($jsonContent, true);
    if (isset($authData['user'])) {
        $users = $authData['user'];
    }
} else {
    // ユーザーファイルが存在しない場合はデフォルト作成
    $defaultUsers = [
        'user' => [
            'fabula' => 'fg12345@',
            'admin' => 'admin12345@'
        ]
    ];
    file_put_contents($userAuthFile, json_encode($defaultUsers, JSON_PRETTY_PRINT));
    $users = $defaultUsers['user'];
    logMessage("ユーザー認証ファイルが見つからないため、デフォルトユーザーで作成しました", 'WARNING');
}

// 認証処理
$isLoggedIn = false;
$loginError = '';

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['auth_user']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ログインフォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($users[$username]) && is_array($users[$username]) && $users[$username][0] === $password) {
        $_SESSION['auth_user'] = $username;
        $_SESSION['auth_token'] = $users[$username][1]; // トークンを保存
        logMessage("ユーザー '{$username}' がログインしました");
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'ユーザー名またはパスワードが正しくありません';
        logMessage("ログイン失敗: ユーザー '{$username}'", 'WARNING');
    }
}

// ログイン状態チェック
if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_token']) && array_key_exists($_SESSION['auth_user'], $users)) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['auth_user'];
    $authToken = $_SESSION['auth_token'];
} else {
    $isLoggedIn = false;
}

// データベース接続
$db = Database::getInstance();

// アクション処理（ログイン済みの場合のみ）
$actionMessage = '';
$actionError = '';

if ($isLoggedIn) {
    // 商品同期実行処理
    if (isset($_GET['action']) && $_GET['action'] === 'sync') {
        try {
            logMessage("商品同期処理を開始します: ユーザー '$currentUser' からの手動実行");
            
            // ProductServiceを直接使用するように変更
            require_once $rootPath . '/api/lib/ProductService.php';
            logMessage("ProductServiceを初期化します");
            $productService = new ProductService();
            
            // 同期処理を実行
            logMessage("ProductService経由で商品同期を実行します");
            $startTime = microtime(true);
            $syncResult = $productService->processProductSync();
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            logMessage("商品同期処理が完了しました: 実行時間: ${executionTime}秒");
            
            // 同期結果の詳細をログに記録
            if (isset($syncResult['stats'])) {
                logMessage(
                    "同期結果統計: 追加=" . ($syncResult['stats']['added'] ?? 0) . 
                    ", 更新=" . ($syncResult['stats']['updated'] ?? 0) . 
                    ", 無効化=" . ($syncResult['stats']['disabled'] ?? 0) . 
                    ", エラー=" . ($syncResult['stats']['errors'] ?? 0)
                );
            }
            
            // 画像URL更新処理も実行
            if ($syncResult['success']) {
                logMessage("画像URL更新処理を開始します");
                $imageUpdateResult = $productService->updateImageUrls(100);
                logMessage(
                    "画像URL更新完了: 処理=" . ($imageUpdateResult['processed'] ?? 0) . 
                    ", 更新=" . ($imageUpdateResult['updated'] ?? 0) . 
                    ", エラー=" . ($imageUpdateResult['errors'] ?? 0)
                );
                
                // 結果を整形
                $response = json_encode([
                    'success' => true,
                    'message' => '商品同期と画像URL更新が完了しました',
                    'stats' => $syncResult['stats'] ?? [],
                    'product_sync' => $syncResult,
                    'image_update' => $imageUpdateResult
                ]);
            } else {
                // 同期処理が失敗した場合
                logMessage("商品同期処理が失敗しました: " . ($syncResult['message'] ?? '不明なエラー'), 'ERROR');
                $response = json_encode($syncResult);
            }
                
                // レスポンスJSONを保存（詳細表示用）
                $_SESSION['sync_response_json'] = $response;
                
            // 以下の既存のレスポンス処理部分はそのまま維持
            $result = json_decode($response, true);
            
            $actionMessage = '';
            $syncDetails = [];
            
                    // 同期が成功した場合
            if (isset($result['success']) && $result['success']) {
                logMessage("同期APIは成功を返しました");
                    
                    // 統計情報を適切なソースから取得
                    $stats = null;
                    
                    // 新構造: direct stats
                    if (isset($result['stats'])) {
                        $stats = $result['stats'];
                        $syncDetails[] = '✅ 同期API: 基本統計形式';
                    logMessage("統計情報形式: 基本統計形式");
                    } 
                    // 旧構造: nested stats
                    else if (isset($result['products']) && isset($result['products']['stats'])) {
                        $stats = $result['products']['stats'];
                        $syncDetails[] = '✅ 同期API: products.stats形式';
                    logMessage("統計情報形式: products.stats形式");
                    }
                    // product_sync構造
                    else if (isset($result['product_sync']) && isset($result['product_sync']['stats'])) {
                        $stats = $result['product_sync']['stats'];
                        $syncDetails[] = '✅ 同期API: product_sync.stats形式';
                    logMessage("統計情報形式: product_sync.stats形式");
                    }
                    else {
                        $stats = ['added' => 0, 'updated' => 0, 'errors' => 0];
                        $syncDetails[] = '⚠️ 同期API: 統計情報なし';
                    logMessage("統計情報形式: なし（デフォルト値を使用）", 'WARNING');
                    }
                
                // 統計情報の詳細をログに記録
                logMessage("同期統計: 追加 " . ($stats['added'] ?? 0) . '件, 更新 ' . ($stats['updated'] ?? 0) . '件, エラー ' . ($stats['errors'] ?? 0) . '件');
                    
                    $actionMessage = '商品同期を実行しました: 追加 ' . ($stats['added'] ?? 0) . '件, 更新 ' . ($stats['updated'] ?? 0) . '件, エラー ' . ($stats['errors'] ?? 0) . '件';
                    
                    // Phase 1: 商品同期の詳細
                    $syncDetails[] = '<strong>Phase 1: 商品データ同期 (Square → products テーブル)</strong>';
                    $syncDetails[] = '✅ 追加: ' . ($stats['added'] ?? 0) . '件';
                    $syncDetails[] = '✅ 更新: ' . ($stats['updated'] ?? 0) . '件';
                    $syncDetails[] = '✅ エラー: ' . ($stats['errors'] ?? 0) . '件';
                
                // 追加・更新された商品の詳細をログに記録（存在する場合）
                if (isset($result['added_items']) && is_array($result['added_items']) && count($result['added_items']) > 0) {
                    logMessage("追加された商品: " . implode(', ', array_slice($result['added_items'], 0, 10)) . 
                              (count($result['added_items']) > 10 ? ' 他 ' . (count($result['added_items']) - 10) . '件' : ''));
                }
                
                if (isset($result['updated_items']) && is_array($result['updated_items']) && count($result['updated_items']) > 0) {
                    logMessage("更新された商品: " . implode(', ', array_slice($result['updated_items'], 0, 10)) . 
                              (count($result['updated_items']) > 10 ? ' 他 ' . (count($result['updated_items']) - 10) . '件' : ''));
                }
                
                // エラー情報があれば記録
                if (isset($result['error_items']) && is_array($result['error_items']) && count($result['error_items']) > 0) {
                    logMessage("処理中にエラーが発生した商品: " . json_encode($result['error_items'], JSON_UNESCAPED_UNICODE), 'WARNING');
                }
                    
                    // Phase 2: カテゴリ同期があれば表示
                    if (isset($result['categories'])) {
                        $syncDetails[] = '<strong>Phase 2: カテゴリ同期 (Square → category_descripter テーブル)</strong>';
                    logMessage("カテゴリ同期フェーズを開始");
                        
                        if ($result['categories']['success']) {
                        $catStats = $result['categories']['stats'] ?? [];
                            $syncDetails[] = '✅ カテゴリ同期成功: ' . 
                            '作成 ' . ($catStats['created'] ?? 0) . '件, ' . 
                            '更新 ' . ($catStats['updated'] ?? 0) . '件, ' . 
                            'スキップ ' . ($catStats['skipped'] ?? 0) . '件, ' . 
                            'エラー ' . ($catStats['errors'] ?? 0) . '件';
                        
                        logMessage("カテゴリ同期成功: 作成 " . ($catStats['created'] ?? 0) . 
                                 "件, 更新 " . ($catStats['updated'] ?? 0) . 
                                 "件, スキップ " . ($catStats['skipped'] ?? 0) . 
                                 "件, エラー " . ($catStats['errors'] ?? 0) . "件");
                            
                            $actionMessage .= '<br>カテゴリ同期も実行しました。';
                        
                        // カテゴリの詳細をログに記録
                        if (isset($result['categories']['details']) && !empty($result['categories']['details'])) {
                            logMessage("カテゴリ同期詳細: " . json_encode($result['categories']['details'], JSON_UNESCAPED_UNICODE));
                        }
                        } else {
                        $errorMsg = $result['categories']['message'] ?? '不明なエラー';
                        $syncDetails[] = '❌ カテゴリ同期失敗: ' . $errorMsg;
                        logMessage("カテゴリ同期失敗: $errorMsg", 'ERROR');
                        }
                } else {
                    logMessage("カテゴリ同期情報はレスポンスに含まれていません");
                    }
                    
                    // Phase 3: 画像URL更新があれば表示
                    if (isset($result['image_update'])) {
                        $syncDetails[] = '<strong>Phase 3: 商品画像URL更新</strong>';
                    logMessage("画像URL更新フェーズを開始");
                        
                        if (is_array($result['image_update'])) {
                            $totalUpdated = isset($result['image_update']['updated']) ? $result['image_update']['updated'] : 0;
                            $totalProcessed = isset($result['image_update']['processed']) ? $result['image_update']['processed'] : 0;
                            
                            $syncDetails[] = '✅ 画像URL更新成功: ' . 
                                '処理済 ' . $totalProcessed . '件, ' . 
                                '更新 ' . $totalUpdated . '件';
                        
                        logMessage("画像URL更新: 処理済 $totalProcessed 件, 更新 $totalUpdated 件");
                        
                        // 画像更新の詳細をログに記録
                        if (isset($result['image_update']['details']) && !empty($result['image_update']['details'])) {
                            $previewDetails = array_slice($result['image_update']['details'], 0, 5);
                            logMessage("画像URL更新詳細(サンプル): " . json_encode($previewDetails, JSON_UNESCAPED_UNICODE) . 
                                     (count($result['image_update']['details']) > 5 ? ' 他 ' . (count($result['image_update']['details']) - 5) . '件' : ''));
                        }
                        } else {
                            $syncDetails[] = '✅ 画像URL更新処理完了';
                        logMessage("画像URL更新処理完了（詳細なし）");
                        }
                    } else {
                        // 画像URL更新情報がない場合でも表示（エラーなどの場合）
                        $syncDetails[] = '<strong>Phase 3: 商品画像URL更新</strong>';
                        $syncDetails[] = '✅ 画像URL更新: 処理対象なし (0.00s)';
                    logMessage("画像URL更新フェーズ: 処理対象なし");
                    }
                    
                    // オプション: 全体的な完了メッセージ
                    $syncDetails[] = '<strong>同期処理が完了しました。(' . date('Y-m-d H:i:s') . ')</strong>';
                logMessage("商品同期処理が完了しました。実行時間: ${executionTime}秒");
                    
                } else {
                    // 同期が失敗した場合
                    $errorMessage = isset($result['message']) ? $result['message'] : '不明なエラー';
                    $syncDetails[] = '❌ <strong>同期処理が失敗しました</strong>';
                    $syncDetails[] = '❌ エラー: ' . $errorMessage;
                
                logMessage("同期API処理に失敗: $errorMessage", 'ERROR');
                
                // エラー詳細があれば記録
                if (isset($result['errors']) && !empty($result['errors'])) {
                    if (is_array($result['errors'])) {
                        logMessage("エラー詳細: " . json_encode($result['errors'], JSON_UNESCAPED_UNICODE), 'ERROR');
                    } else {
                        logMessage("エラー詳細: " . $result['errors'], 'ERROR');
                    }
                }
                    
                    throw new Exception('同期に失敗しました: ' . $errorMessage);
                }
                
                // 同期詳細をセッションに保存（画面に表示するため）
                $_SESSION['sync_details'] = $syncDetails;
            logMessage("同期詳細を表示用にセッションに保存しました");
                
        } catch (Exception $e) {
            $actionError = '同期実行エラー: ' . $e->getMessage();
            logMessage("同期実行エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
        }
    }
}

// 商品同期実行処理のコードを維持し、以下のように同期間隔関連コードを削除

// CRON設定の定数
$cronCommand = "curl -s 'https://test-mijeos.but.jp/fgsquare/api/sync/sync_products.php?token=". (isset($authToken) ? $authToken : 'XXXXX') ."' > /dev/null";

// 同期ステータスを取得
$syncStatus = null;
if ($isLoggedIn) {
    try {
        $syncStatus = $db->selectOne(
            "SELECT * FROM sync_status WHERE provider = ? AND table_name = ? ORDER BY last_sync_time DESC LIMIT 1",
            ['square', 'products']
        );
    } catch (Exception $e) {
        logMessage("同期ステータス取得エラー: " . $e->getMessage(), 'WARNING');
    }
}

// ロリポップの設定URL
$lolipopCronUrl = "https://user.lolipop.jp/cron/";

// 商品件数を取得
$productCount = 0;
if ($isLoggedIn) {
    try {
        $countData = $db->selectOne("SELECT COUNT(*) as count FROM products");
        if ($countData) {
            $productCount = $countData['count'];
        }
    } catch (Exception $e) {
        logMessage("商品件数取得エラー: " . $e->getMessage(), 'WARNING');
    }
}

// ===== 共通ヘッダー読込 =====
$pageTitle = '商品同期管理';
require_once __DIR__.'/inc/admin_header.php';

// 未ログイン時は共通ヘッダー側のログインフォームのみ表示して終了
if (!$isLoggedIn) {
    require_once __DIR__.'/inc/admin_footer.php';
    return;
}

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品同期管理 - FG Square</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* スピナーのスタイル */
        .loading-spinner {
            display: none;
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #007bff;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 同期ステップ表示 */
        .sync-steps {
            margin-top: 15px;
            text-align: left;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .sync-step {
            padding: 5px 0;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .sync-step.active {
            color: #0366d6;
            font-weight: bold;
        }
        
        .sync-step.complete {
            color: #28a745;
        }
        
        /* 同期ログ表示エリア */
        .sync-log {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .sync-log h4 {
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .sync-time {
            font-size: 12px;
            color: #6c757d;
            font-weight: normal;
        }
        
        .log-header, .log-footer {
            background-color: #e9ecef;
            padding: 8px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .log-sequence {
            background-color: #f1f3f5;
            padding: 8px;
            border-radius: 4px;
            margin: 10px 0;
            font-style: italic;
            text-align: center;
        }
        
        .log-section {
            margin: 15px 0;
        }
        
        .log-section-header {
            font-weight: bold;
            margin: 10px 0;
            padding: 5px;
            background-color: #e9ecef;
            border-left: 3px solid #0366d6;
        }
        
        .log-entry {
            padding: 3px 5px;
            margin: 3px 0;
            border-radius: 2px;
        }
        
        .log-entry.success {
            color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .log-entry.warning {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .log-entry.error {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .log-entry.section-header {
            margin-top: 15px;
            font-weight: bold;
        }
        
        .processing-time {
            font-size: 12px;
            color: #6c757d;
            margin-left: 5px;
        }
        
        .sync-log strong {
            color: #0366d6;
        }
        
        /* 統計カードのスタイル */
        .stat-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card.added {
            border-left: 4px solid #28a745;
        }
        
        .stat-card.updated {
            border-left: 4px solid #0366d6;
        }
        
        .stat-card.errors {
            border-left: 4px solid #dc3545;
        }
        
        .stat-card.no-errors {
            border-left: 4px solid #28a745;
        }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .stat-card.added .stat-icon {
            color: #28a745;
        }
        
        .stat-card.updated .stat-icon {
            color: #0366d6;
        }
        
        .stat-card.errors .stat-icon {
            color: #dc3545;
        }
        
        .stat-card.no-errors .stat-icon {
            color: #28a745;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }
        
        /* フェーズタイムラインのスタイル */
        .execution-phases {
            margin: 20px 0;
        }
        
        .phase-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 0 20px;
        }
        
        .phase-timeline:before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e9ecef;
            z-index: 1;
        }
        
        .phase-item {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .phase-icon {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border: 2px solid #0366d6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            color: #0366d6;
            font-size: 18px;
        }
        
        .phase-name {
            font-size: 12px;
            color: #495057;
            max-width: 80px;
            margin: 0 auto;
        }
        
        /* ログ詳細セクションのスタイル */
        .log-item-header {
            margin: 12px 0 4px 0;
            font-weight: 500;
            color: #212529;
            border-bottom: 1px dashed #dee2e6;
            padding-bottom: 4px;
        }
        
        .log-item-header strong {
            color: #0366d6;
        }
        
        .log-item-detail {
            margin: 2px 0 2px 15px;
            font-family: monospace;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .log-item-detail.changed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #155724;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ヘッダーは admin_header.php で出力済み -->
        
        <?php if (!$isLoggedIn): ?>
        <!-- ログインフォーム -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-form">
                    <div class="card-header">
                        管理者ログイン
                    </div>
                    <div class="card-body">
                        <?php if ($loginError): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($loginError); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">ユーザー名</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">パスワード</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">ログイン</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- アクションメッセージ -->
        <div id="action-messages">
            <?php if ($actionMessage): ?>
            <div class="alert alert-success">
                <?php echo $actionMessage; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($actionError): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($actionError); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 同期中のローディングスピナー -->
        <div id="loading-spinner" class="loading-spinner">
            <div class="spinner"></div>
            <p id="sync-status-text">同期処理実行中... しばらくお待ちください</p>
        </div>
        
        <!-- 概要情報 -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        商品データ概要
                    </div>
                    <div class="card-body">
                        <p><strong>登録商品数:</strong> <?php echo number_format($productCount); ?> 件</p>
                        <?php if (isset($syncStatus)): ?>
                        <p><strong>最終同期日時:</strong> <?php echo htmlspecialchars($syncStatus['last_sync_time']); ?></p>
                        <p>
                            <strong>ステータス:</strong> 
                            <span class="status-badge <?php echo $syncStatus['status'] === 'success' ? 'success' : ($syncStatus['status'] === 'warning' ? 'warning' : 'error'); ?>">
                                <?php echo htmlspecialchars($syncStatus['status']); ?>
                            </span>
                        </p>
                        <?php endif; ?>
                        
                        <a href="?action=sync" id="sync-button" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat"></i> 商品同期を実行する
                        </a>
                        
                        <!-- 同期説明 -->
                        <div class="alert alert-info mt-3">
                            <h5><i class="bi bi-info-circle"></i> 同期処理の説明</h5>
                            <p>
                                <strong>この同期処理は何をするのですか？</strong><br>
                                Square からすべての商品情報を取得し、データベースの <code>products</code> テーブルに反映します。
                                Square側の変更（新商品追加、価格・説明変更など）がデータベースに反映されます。
                            </p>
                            <p>
                                <strong>既存のデータはどうなりますか？</strong><br>
                                既存の商品は更新されます。削除された商品は非表示になります。完全な上書き処理ではなく、差分更新です。
                            </p>
                        </div>
                        
                        <!-- 同期ログ表示エリア（改善版） -->
                        <?php if (isset($_SESSION['sync_response_json'])): ?>
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-list-check"></i> 同期処理結果
                                    <span class="text-muted small ms-2"><?php echo date('Y-m-d H:i:s'); ?></span>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#syncLogCollapse">
                                    詳細を表示/非表示
                                </button>
                            </div>
                            <div class="collapse show" id="syncLogCollapse">
                                <div class="card-body">
                                        <?php
                                    // JSONデータを解析
                                    $syncData = json_decode($_SESSION['sync_response_json'], true);
                                    
                                    // 統計情報を抽出
                                    $stats = [];
                                    if (isset($syncData['stats'])) {
                                        $stats = $syncData['stats'];
                                    } elseif (isset($syncData['product_sync']) && isset($syncData['product_sync']['stats'])) {
                                        $stats = $syncData['product_sync']['stats'];
                                    } elseif (isset($syncData['products']) && isset($syncData['products']['stats'])) {
                                        $stats = $syncData['products']['stats'];
                                    }
                                    
                                    $added = $stats['added'] ?? 0;
                                    $updated = $stats['updated'] ?? 0;
                                    $disabled = $stats['disabled'] ?? 0;
                                    $errors = $stats['errors'] ?? 0;
                                    
                                    // 同期ステータスを判定
                                    $isSuccess = (isset($syncData['success']) && $syncData['success']) || 
                                                 (isset($syncData['product_sync']['success']) && $syncData['product_sync']['success']);
                                    ?>
                                    
                                    <!-- 実行サマリー：シンプルで分かりやすい表示 -->
                                    <div class="alert <?php echo $isSuccess ? 'alert-success' : 'alert-danger'; ?> mb-4">
                                        <h5>
                                            <i class="bi <?php echo $isSuccess ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?>"></i> 
                                            同期結果: <?php echo $isSuccess ? '成功' : '失敗'; ?>
                                        </h5>
                                        <?php if (!$isSuccess && isset($syncData['message'])): ?>
                                            <p class="mb-0"><?php echo htmlspecialchars($syncData['message']); ?></p>
                                        <?php endif; ?>
                                                </div>
                                    
                                    <!-- 実行統計：シンプルな表形式 -->
                                    <div class="mb-4">
                                        <h5><i class="bi bi-pie-chart"></i> 同期統計情報</h5>
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>追加</th>
                                                    <th>更新</th>
                                                    <th>非表示化</th>
                                                    <th>エラー</th>
                                                    <th>合計処理商品数</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="text-success fw-bold"><?php echo $added; ?> 件</td>
                                                    <td class="text-primary fw-bold"><?php echo $updated; ?> 件</td>
                                                    <td class="text-warning fw-bold"><?php echo $disabled; ?> 件</td>
                                                    <td class="<?php echo $errors > 0 ? 'text-danger' : 'text-success'; ?> fw-bold"><?php echo $errors; ?> 件</td>
                                                    <td class="fw-bold"><?php echo ($added + $updated + $disabled); ?> 件</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- 更新された商品の詳細（最大10件） -->
                                            <?php 
                                    $updatedItems = [];
                                    if (isset($syncData['updated_items'])) {
                                        $updatedItems = $syncData['updated_items'];
                                    } elseif (isset($syncData['product_sync']['updated_items'])) {
                                        $updatedItems = $syncData['product_sync']['updated_items'];
                                    }
                                    
                                    if (!empty($updatedItems)): 
                                    ?>
                                    <div class="mb-4">
                                        <h5><i class="bi bi-pencil-square"></i> 更新された商品（最大10件）</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>商品名</th>
                                                        <th>商品ID</th>
                                                        <th>カテゴリ</th>
                                                        <th>価格</th>
                                                        <th>更新内容</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($updatedItems as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['name'] ?? 'N/A'); ?></td>
                                                        <td><small><?php echo htmlspecialchars($item['square_item_id'] ?? 'N/A'); ?></small></td>
                                                        <td><?php echo htmlspecialchars($item['category_name'] ?? $item['category'] ?? 'N/A'); ?></td>
                                                        <td><?php echo isset($item['price']) ? number_format($item['price']) : 'N/A'; ?></td>
                                                        <td>
                                                            <?php
                                                            $changes = [];
                                                            if (isset($item['old_price']) && isset($item['new_price']) && $item['old_price'] != $item['new_price']) {
                                                                $changes[] = '価格変更';
                                                            }
                                                            if (isset($item['old_description']) && isset($item['new_description']) && $item['old_description'] !== $item['new_description']) {
                                                                $changes[] = '説明変更';
                                                            }
                                                            if (isset($item['old_category']) && isset($item['new_category']) && $item['old_category'] !== $item['new_category']) {
                                                                $changes[] = 'カテゴリ変更';
                                                            }
                                                            if (isset($item['old_image_url']) && isset($item['new_image_url']) && $item['old_image_url'] !== $item['new_image_url']) {
                                                                $changes[] = '画像更新';
                                                            }
                                                            echo !empty($changes) ? implode(', ', $changes) : '基本情報更新';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                                </div>
                                            </div>
                                    <?php endif; ?>
                                    
                                    <!-- 新規追加された商品の詳細（最大10件） -->
                                    <?php 
                                    $addedItems = [];
                                    if (isset($syncData['added_items'])) {
                                        $addedItems = $syncData['added_items'];
                                    } elseif (isset($syncData['product_sync']['added_items'])) {
                                        $addedItems = $syncData['product_sync']['added_items'];
                                    }
                                    
                                    if (!empty($addedItems)): 
                                    ?>
                                    <div class="mb-4">
                                        <h5><i class="bi bi-plus-circle"></i> 新規追加された商品（最大10件）</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>商品名</th>
                                                        <th>商品ID</th>
                                                        <th>カテゴリ</th>
                                                        <th>価格</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($addedItems as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['name'] ?? 'N/A'); ?></td>
                                                        <td><small><?php echo htmlspecialchars($item['square_item_id'] ?? 'N/A'); ?></small></td>
                                                        <td><?php echo htmlspecialchars($item['category_name'] ?? $item['category'] ?? 'N/A'); ?></td>
                                                        <td><?php echo isset($item['price']) ? number_format($item['price']) : 'N/A'; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                                </div>
                                            </div>
                                    <?php endif; ?>
                                    
                                    <!-- 非表示化された商品（あれば表示） -->
                                    <?php if ($disabled > 0): ?>
                                    <div class="mb-4">
                                        <h5><i class="bi bi-eye-slash"></i> 非表示化された商品</h5>
                                        <div class="alert alert-warning">
                                            <p>合計 <?php echo $disabled; ?> 件の商品がSquareに存在しないため非表示化されました。</p>
                                            <p class="mb-0 small">これらはSquareから削除された商品、またはAPI経由で取得できなかった商品です。</p>
                                                    </div>
                                                </div>
                                    <?php endif; ?>

                                    <!-- エラー情報（あれば表示） -->
                                        <?php 
                                    $errorItems = [];
                                    if (isset($syncData['error_items'])) {
                                        $errorItems = $syncData['error_items'];
                                    } elseif (isset($syncData['product_sync']['error_items'])) {
                                        $errorItems = $syncData['product_sync']['error_items'];
                                    }
                                    
                                    if (!empty($errorItems) || $errors > 0): 
                                    ?>
                                    <div class="mb-4">
                                        <h5><i class="bi bi-exclamation-triangle"></i> エラー情報</h5>
                                        <?php if (!empty($errorItems)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>商品名</th>
                                                        <th>商品ID</th>
                                                        <th>エラー内容</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($errorItems as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['name'] ?? 'N/A'); ?></td>
                                                        <td><small><?php echo htmlspecialchars($item['square_item_id'] ?? 'N/A'); ?></small></td>
                                                        <td class="text-danger"><?php echo htmlspecialchars($item['error_message'] ?? '不明なエラー'); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                    </div>
                                        <?php else: ?>
                                        <div class="alert alert-danger">
                                            <p class="mb-0">処理中に <?php echo $errors; ?> 件のエラーが発生しました。詳細情報はログファイルを確認してください。</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- 画像URL更新情報 -->
                                    <?php if (isset($syncData['image_update'])): ?>
                                    <div class="mb-4">
                                        <h5><i class="bi bi-image"></i> 画像URL更新情報</h5>
                                        <div class="alert alert-info">
                                            <p>処理商品数: <?php echo $syncData['image_update']['processed'] ?? 0; ?> 件</p>
                                            <p class="mb-0">更新商品数: <?php echo $syncData['image_update']['updated'] ?? 0; ?> 件</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- デバッグ情報：トラブルシューティング用（通常は非表示） -->
                                    <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                                    <div class="mt-4 border-top pt-3">
                                        <details>
                                            <summary class="text-muted">デバッグ情報を表示（開発者向け）</summary>
                                            <div class="mt-2">
                                                <pre class="bg-light p-3 small" style="max-height: 300px; overflow: auto;"><?php echo htmlspecialchars($_SESSION['sync_response_json']); ?></pre>
                                    </div>
                                        </details>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        <h4>自動同期設定</h4>
                        
                        <div class="alert alert-info">
                            <strong>CRON設定情報</strong><br>
                            <p><strong>実行間隔:</strong> */10 * * * * (10分ごと)</p>
                            <p><strong>実行コマンド:</strong> <code><?php echo htmlspecialchars($cronCommand); ?></code></p>
                            <p>Lolipopの <a href="<?php echo htmlspecialchars($lolipopCronUrl); ?>" target="_blank">CRON設定ページ</a> で確認できます。</p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>【重要】CRON設定について</strong><br>
                            商品同期は10分間隔でLolipopサーバー側のCRON設定により自動実行されています。この設定を変更する場合はサーバー管理者に依頼してください。
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="js/admin.js"></script>
    <script>
        // 同期ボタンのクリックイベント
        document.addEventListener('DOMContentLoaded', function() {
            const syncButton = document.getElementById('sync-button');
            const loadingSpinner = document.getElementById('loading-spinner');
            const actionMessages = document.getElementById('action-messages');
            const syncStatusText = document.getElementById('sync-status-text');
            const syncSteps = document.querySelectorAll('.sync-step');
            const syncLogDetails = document.getElementById('sync-log-details');
            
            if (syncButton) {
                syncButton.addEventListener('click', function(e) {
                    // エラーメッセージをクリア
                    actionMessages.innerHTML = '';
                    
                    // スピナーを表示
                    loadingSpinner.style.display = 'block';
                    
                    // 同期ボタンを無効化
                    syncButton.disabled = true;
                    syncButton.classList.add('disabled');
                    syncButton.innerHTML = '<i class="bi bi-hourglass-split"></i> 同期処理実行中...';
                    
                    // 同期ステップを順番に表示（視覚的なフィードバックのため）
                    let stepIndex = 0;
                    syncSteps[stepIndex].classList.add('active');
                    
                    const stepInterval = setInterval(function() {
                        if (stepIndex < syncSteps.length - 1) {
                            syncSteps[stepIndex].classList.remove('active');
                            syncSteps[stepIndex].classList.add('complete');
                            stepIndex++;
                            syncSteps[stepIndex].classList.add('active');
                            
                            // ステータステキストを更新
                            syncStatusText.textContent = '同期処理実行中... ' + 
                                Math.round((stepIndex / (syncSteps.length - 1)) * 100) + '% 完了';
                        } else {
                            clearInterval(stepInterval);
                        }
                    }, 1500); // 1.5秒ごとに次のステップに進む
                    
                    // 実際のリダイレクトを許可（フォームの通常の動作）
                    // ページ遷移によりスピナーが表示され続ける
                });
            }
            
            // URLにaction=syncがある場合、ページ読み込み後に自動的にスピナーを非表示
            if (window.location.href.indexOf('action=sync') !== -1) {
                // スピナーが表示されている場合は非表示に
                if (loadingSpinner) {
                    // すべてのステップを完了状態に
                    syncSteps.forEach(step => {
                        step.classList.add('complete');
                    });
                    
                    // 操作が完了したことを示すために少し遅延させる
                    setTimeout(function() {
                        loadingSpinner.style.display = 'none';
                        if (syncButton) {
                            syncButton.disabled = false;
                            syncButton.classList.remove('disabled');
                            syncButton.innerHTML = '<i class="bi bi-arrow-repeat"></i> 商品同期を実行する';
                        }
                        
                        // 同期完了後、レスポンスデータがあれば解析して詳細表示
                        try {
                            const syncLogDataElement = document.getElementById('sync-log-data');
                            if (syncLogDataElement && syncLogDataElement.textContent) {
                                const syncData = JSON.parse(syncLogDataElement.textContent);
                                if (syncData) {
                                    // 詳細表示関数を呼び出し
                                    displayLog(syncData);
                                }
                            }
                        } catch (e) {
                            console.error('同期データの解析エラー:', e);
                        }
                    }, 500);
                }
            }
        });

        // ログ情報を表示する関数
        function displayLog(data) {
            // displayLog関数は不要になります。新しいUIでは直接PHPから表示するようにしました。
            console.log('同期データ受信:', data);
            
            // 同期ボタンを再び有効化する
            const syncButton = document.getElementById('sync-button');
            if (syncButton) {
                syncButton.disabled = false;
                syncButton.classList.remove('disabled');
                syncButton.innerHTML = '<i class="bi bi-arrow-repeat"></i> 商品同期を実行する';
            }
        }
    </script>
</body>
</html> 
<?php require_once __DIR__.'/inc/admin_footer.php'; ?> 