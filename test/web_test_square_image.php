<?php
/**
 * Square APIから画像URLを取得するテストスクリプト（シンプル版）
 * 
 * 実行方法: ブラウザでアクセス
 * 認証: 簡易的なパスワード保護
 * ログ: /logs/web_test_square_image.log に詳細なログを出力
 */

// エラー表示と最大実行時間の設定
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300); // 5分

// 簡易認証
$password = 'square_test'; // テスト用パスワード

// セッション開始
session_start();

// ログディレクトリ確認と作成
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/web_test_square_image.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// ログ関数定義
function log_message($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] " . $message . "\n";
    
    // 画面表示用
    echo $logMessage;
    flush(); // 出力をすぐに送信
    
    // ファイルにログを書き込む
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ログイン済みかチェック、またはログイン処理
$loggedIn = false;
$error = '';

if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
        $loggedIn = true;
        log_message("ログイン成功: " . $_SERVER['REMOTE_ADDR']);
    } else {
        $error = 'パスワードが正しくありません';
        log_message("ログイン失敗 (パスワード不一致): " . $_SERVER['REMOTE_ADDR']);
    }
} elseif (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $loggedIn = true;
}

// 実行フラグ
$executeTest = $loggedIn && isset($_GET['execute']);

// HTMLヘッダー出力
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Square API 画像URL取得テスト</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .login-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0069d9;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            overflow: auto;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            white-space: pre-wrap;
            max-height: 600px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        hr {
            margin: 20px 0;
            border: 0;
            border-top: 1px solid #eee;
        }
        .image-preview {
            margin-top: 20px;
            text-align: center;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
    </style>
</head>
<body>
    <h1>Square API 画像URL取得テスト</h1>
    
    <?php if (!$loggedIn): ?>
        <!-- ログインフォーム -->
        <div class="login-form">
            <h2>認証</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="password">パスワード:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">ログイン</button>
            </form>
        </div>
    <?php else: ?>
        <!-- 認証済み：テスト実行ボタン表示 -->
        <?php if (!$executeTest): ?>
            <div class="alert alert-info">
                注意: このテストを実行すると、Square APIに対して複数のリクエストが発行されます。
                APIレート制限やデータ利用に影響する可能性があります。
            </div>
            <p>このテストは、Square APIから商品画像URLを取得するための最適な方法を検証します。</p>
            <p><strong>目的:</strong> 安定して画像URLを取得できる方法を見つけ、Webサイトでの画像表示を改善する</p>
            <p>準備ができたら「テスト実行」ボタンをクリックしてください。</p>
            <a href="?execute=1" class="btn">テスト実行</a>
            
            <hr>
            <h3>ログファイル</h3>
            <p>すべてのテスト結果は以下のファイルに記録されます：</p>
            <code><?php echo htmlspecialchars($logFile); ?></code>
        <?php else: ?>
            <!-- テスト実行結果 -->
            <a href="?" class="btn">テスト再実行</a>
            <hr>
            <h2>テスト結果</h2>
            <pre><?php
                // 開始時刻を記録
                $startTime = microtime(true);
                
                // ここからテスト実行コード
                try {
                    log_message("========== テスト開始 ==========");
                    log_message("PHP バージョン: " . PHP_VERSION);
                    log_message("実行時間制限: " . ini_get('max_execution_time') . "秒");
                    log_message("リクエスト元IPアドレス: " . $_SERVER['REMOTE_ADDR']);
                    
                    // ステップ1: 必要なファイルを読み込む
                    log_message("必要なファイルを読み込み中...");
                    
                    // 可能性のあるパスを試す
                    $possiblePaths = [
                        '../api/init.php',              // libなしのパス
                        '../api/lib/init.php',           // 従来のパス
                        dirname(__DIR__) . '/api/init.php',  // 絶対パスでlibなし
                        dirname(__DIR__) . '/api/lib/init.php'  // 絶対パスで従来のパス
                    ];
                    
                    $initFile = null;
                    foreach ($possiblePaths as $path) {
                        log_message("パスを試行中: " . $path . " (存在: " . (file_exists($path) ? "はい" : "いいえ") . ")");
                        if (file_exists($path)) {
                            log_message("✅ 初期化ファイルを発見: " . $path);
                            $initFile = $path;
                            break;
                        }
                    }
                    
                    if (!$initFile) {
                        log_message("❌ 初期化ファイルが見つかりません。以下のパスを試行しました:");
                        foreach ($possiblePaths as $path) {
                            log_message("  - " . $path);
                        }
                        
                        // サーバーの実際のパス構造を確認するための情報
                        log_message("\nデバッグ情報:");
                        log_message("現在のディレクトリ: " . getcwd());
                        log_message("スクリプトのパス: " . __FILE__);
                        log_message("スクリプトのディレクトリ: " . __DIR__);
                        
                        // ディレクトリ内のファイル一覧を確認
                        log_message("\n親ディレクトリ内のファイル一覧:");
                        $parentDir = dirname(__DIR__);
                        log_message("親ディレクトリ: " . $parentDir);
                        
                        if (is_dir($parentDir)) {
                            $files = scandir($parentDir);
                            foreach ($files as $file) {
                                log_message(" - " . $file);
                            }
                            
                            // apiディレクトリの中身も確認
                            $apiDir = $parentDir . '/api';
                            if (is_dir($apiDir)) {
                                log_message("\n/api ディレクトリ内のファイル一覧:");
                                $apiFiles = scandir($apiDir);
                                foreach ($apiFiles as $file) {
                                    log_message(" - " . $file);
                                }
                            } else {
                                log_message("apiディレクトリが見つかりません");
                            }
                        } else {
                            log_message("親ディレクトリにアクセスできません");
                        }
                        
                        throw new Exception("初期化ファイルが見つかりません。ファイル構造を確認してください。");
                    }
                    
                    // init.phpを読み込む
                    require_once $initFile;
                    log_message("初期化ファイル読み込み完了");
                    
                    // SquareServiceクラスの確認
                    log_message("\nSquareServiceクラスの確認:");
                    if (!class_exists('SquareService')) {
                        throw new Exception("SquareServiceクラスが見つかりません");
                    }
                    
                    log_message("✅ SquareServiceクラスが存在します");
                    
                    // SquareServiceクラスのメソッド一覧を表示
                    $methods = get_class_methods('SquareService');
                    log_message("SquareServiceクラスのメソッド一覧:");
                    foreach ($methods as $method) {
                        log_message(" - " . $method);
                    }
                    
                    // ステップ2: SquareServiceインスタンスの作成
                    log_message("Square クライアントの初期化...");
                    $squareService = new SquareService();
                    
                    // クラスのプロパティが非公開のため、利用可能なメソッドを使用する
                    log_message("Square APIの接続をテスト中...");
                    try {
                        // testConnectionメソッドがあれば接続テスト
                        $connectionInfo = $squareService->testConnection();
                        log_message("✅ Square APIに接続成功しました");
                        log_message("接続情報: " . json_encode($connectionInfo));
                    } catch (Exception $e) {
                        log_message("⚠️ 接続テストでエラーが発生しましたが、処理を続行します: " . $e->getMessage());
                    }
                    
                    // ステップ3: 商品リスト取得（getItemsメソッドを使用）
                    log_message("\n👉 テスト1: 商品データの取得");
                    try {
                        // 商品一覧を取得（最大10件）
                        $items = $squareService->getItems(true, 10); // 生のオブジェクトを取得
                        log_message("✅ 成功: " . count($items) . "件の商品を取得");
                        
                        if (count($items) > 0) {
                            // 商品情報のテーブルヘッダーを表示
                            log_message("\n商品一覧:");
                            log_message(str_pad("ID", 20) . " | " . str_pad("名前", 30) . " | 画像ID");
                            log_message(str_repeat("-", 80));
                            
                            // 画像付き商品を探す
                            $testImageId = null;
                            
                            foreach ($items as $index => $item) {
                                $itemId = $item->getId();
                                $itemData = $item->getItemData();
                                $itemName = $itemData ? $itemData->getName() : 'Unknown';
                                
                                // getImageIds()メソッドがあれば使用
                                $imageIds = [];
                                if ($itemData && method_exists($itemData, 'getImageIds') && $itemData->getImageIds()) {
                                    $imageIds = $itemData->getImageIds();
                                }
                                
                                // 商品情報をテーブル行として表示
                                log_message(
                                    str_pad(substr($itemId, 0, 18), 20) . " | " . 
                                    str_pad(substr($itemName, 0, 28), 30) . " | " . 
                                    ($imageIds ? implode(", ", $imageIds) : "なし")
                                );
                                
                                // 最初に見つかった画像IDをテスト用として使用
                                if (!$testImageId && $imageIds && count($imageIds) > 0) {
                                    $testImageId = $imageIds[0];
                                    log_message("✅ テスト用画像IDとして選択: $testImageId");
                                }
                            }
                            
                            // 画像取得テスト
                            if ($testImageId) {
                                log_message("\n👉 テスト2: 画像URLの取得");
                                log_message("画像ID: $testImageId を使用");
                                
                                // getImageByIdメソッドを使用して画像を取得
                                $startTime = microtime(true);
                                $imageObject = $squareService->getImageById($testImageId);
                                $endTime = microtime(true);
                                
                                log_message("API呼び出し時間: " . round(($endTime - $startTime) * 1000) . "ms");
                                
                                if ($imageObject) {
                                    log_message("✅ 成功: 画像オブジェクトを取得");
                                    
                                    // ImageDataからURLを取得
                                    if ($imageObject->getType() === 'IMAGE') {
                                        $imageData = $imageObject->getImageData();
                                        if ($imageData && $imageData->getUrl()) {
                                            $imageUrl = $imageData->getUrl();
                                            log_message("✅ 成功: 画像URL = " . $imageUrl);
                                            
                                            // 取得した画像URLを表示
                                            echo '</pre>';
                                            echo '<h3>取得した画像URL:</h3>';
                                            echo '<code>' . htmlspecialchars($imageUrl) . '</code>';
                                            
                                            echo '<div class="image-preview">';
                                            echo '<h3>画像プレビュー:</h3>';
                                            echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="Square商品画像">';
                                            echo '</div>';
                                            
                                            echo '<pre>';
                                        } else {
                                            log_message("❌ 失敗: 画像URLが見つかりません");
                                        }
                                    } else {
                                        log_message("❌ 失敗: 取得したオブジェクトは画像ではありません (Type: " . $imageObject->getType() . ")");
                                    }
                                } else {
                                    log_message("❌ 失敗: 画像オブジェクトを取得できませんでした");
                                }
                            } else {
                                log_message("❌ 画像IDが見つかりませんでした。画像URL取得テストをスキップします。");
                            }
                        } else {
                            log_message("❌ 商品が見つかりませんでした");
                        }
                    } catch (Exception $e) {
                        log_message("❌ 例外が発生しました: " . $e->getMessage());
                        if (method_exists($e, 'getTraceAsString')) {
                            log_message("スタックトレース: " . $e->getTraceAsString());
                        }
                    }
                    
                    log_message("\n推奨される実装方法:");
                    log_message("Square APIから画像URLを取得するには、以下のメソッドを使用してください:");
                    log_message("- SquareService::getImageById(画像ID) - 単一の画像を取得");
                    log_message("- 商品の画像IDは、商品データのgetImageIds()メソッドで取得できます");
                    
                    // 実行時間計測
                    $endTime = microtime(true);
                    $executionTime = $endTime - $startTime;
                    log_message("\n総実行時間: " . round($executionTime, 2) . "秒");
                    
                    log_message("========== テスト完了 ==========");
                    
                } catch (Exception $e) {
                    log_message("❌ テスト全体の実行中に例外が発生しました: " . $e->getMessage());
                    if (method_exists($e, 'getTraceAsString')) {
                        log_message("スタックトレース: " . $e->getTraceAsString());
                    }
                } catch (Error $e) {
                    log_message("❌ PHPエラーが発生しました: " . $e->getMessage());
                    if (method_exists($e, 'getTraceAsString')) {
                        log_message("スタックトレース: " . $e->getTraceAsString());
                    }
                }
            ?></pre>
            
            <hr>
            <p><a href="?" class="btn">テスト再実行</a></p>
            
            <h3>ログファイル</h3>
            <p>テスト実行の詳細ログは以下のファイルに記録されています：</p>
            <code><?php echo htmlspecialchars($logFile); ?></code>
            
            <h3>実装サンプル (PHP)</h3>
            <pre>/**
 * Square商品IDから画像URLを取得する (最適化版)
 * 
 * @param string $squareItemId Square商品ID
 * @return string 画像URL (取得できない場合は空文字)
 */
public function getImageUrlForItem($squareItemId) {
    try {
        // APIクライアント取得
        $catalogApi = $this->squareService->getSquareClient()->getCatalogApi();
        
        // 関連オブジェクト含めて1回のリクエストで取得
        $response = $catalogApi->retrieveCatalogObject($squareItemId, true);
        
        if (!$response->isSuccess()) {
            return '';
        }
        
        $object = $response->getResult()->getObject();
        $relatedObjects = $response->getResult()->getRelatedObjects();
        
        // 画像IDの確認
        if (!$object || !$object->getItemData() || !$object->getItemData()->getImageIds()) {
            return '';
        }
        
        $imageIds = $object->getItemData()->getImageIds();
        if (empty($imageIds)) {
            return '';
        }
        
        $firstImageId = $imageIds[0];
        
        // 関連オブジェクトから画像URLを検索
        if ($relatedObjects) {
            foreach ($relatedObjects as $relObj) {
                if ($relObj->getType() === 'IMAGE' && $relObj->getId() === $firstImageId) {
                    $imageData = $relObj->getImageData();
                    if ($imageData && $imageData->getUrl()) {
                        return $imageData->getUrl();
                    }
                }
            }
        }
        
        // 関連オブジェクトで見つからない場合は直接取得を試行
        try {
            $imageResponse = $catalogApi->retrieveCatalogObject($firstImageId);
            
            if ($imageResponse->isSuccess()) {
                $imageObj = $imageResponse->getResult()->getObject();
                
                if ($imageObj->getType() === 'IMAGE' && $imageObj->getImageData() && $imageObj->getImageData()->getUrl()) {
                    return $imageObj->getImageData()->getUrl();
                }
            }
        } catch (Exception $e) {
            // 直接取得失敗時は空文字を返す
        }
        
        return '';
    } catch (Exception $e) {
        // エラーログを記録
        error_log("Square画像URL取得エラー: " . $e->getMessage());
        return '';
    }
}</pre>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html> 