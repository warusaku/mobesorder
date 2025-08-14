<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>LacisMobileOrder デバッグテスト</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .code { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        .section { margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>LacisMobileOrder システムデバッグテスト</h1>
    <p>このページはシステムの状態と構成をチェックするためのものです。</p>

    <div class="section">
        <h2>1. PHPバージョンとシステム情報</h2>
        <table>
            <tr><th>項目</th><th>値</th></tr>
            <tr><td>PHPバージョン</td><td><?php echo phpversion(); ?></td></tr>
            <tr><td>サーバーソフトウェア</td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td></tr>
            <tr><td>リクエスト時間</td><td><?php echo date('Y-m-d H:i:s'); ?></td></tr>
            <tr><td>サーバーIP</td><td><?php echo $_SERVER['SERVER_ADDR'] ?? 'Unknown'; ?></td></tr>
            <tr><td>ホスト名</td><td><?php echo gethostname(); ?></td></tr>
            <tr><td>ドキュメントルート</td><td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td></tr>
            <tr><td>現在のスクリプトパス</td><td><?php echo __FILE__; ?></td></tr>
            <tr><td>カレントディレクトリ</td><td><?php echo getcwd(); ?></td></tr>
        </table>
    </div>

    <div class="section">
        <h2>2. 設定ファイルの読み込み</h2>
        <?php
        $configFile = __DIR__ . '/api/config/config.php';
        if (file_exists($configFile)) {
            echo "<p class='success'>設定ファイルが存在します: " . $configFile . "</p>";
            
            // 既存の定数を保存
            $originals = get_defined_constants(true)['user'] ?? [];
            
            // 設定ファイルを読み込み
            require_once $configFile;
            
            // 新しく追加された定数を取得
            $new = array_diff_key(get_defined_constants(true)['user'] ?? [], $originals);
            
            echo "<p>読み込まれた設定値（機密情報は非表示）:</p>";
            echo "<table>";
            echo "<tr><th>設定名</th><th>設定値</th></tr>";
            
            foreach ($new as $key => $value) {
                // パスワードやトークンなどの機密情報は隠す
                $display = $value;
                if (strpos($key, 'PASS') !== false || 
                    strpos($key, 'TOKEN') !== false || 
                    strpos($key, 'SECRET') !== false ||
                    strpos($key, 'KEY') !== false) {
                    $display = "********";
                }
                
                echo "<tr><td>$key</td><td>" . htmlspecialchars($display) . "</td></tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p class='error'>設定ファイルが見つかりません: " . $configFile . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. ディレクトリ構造と権限チェック</h2>
        <?php
        $dirsToCheck = [
            'logs' => __DIR__ . '/logs',
            'api' => __DIR__ . '/api',
            'api/lib' => __DIR__ . '/api/lib',
            'api/config' => __DIR__ . '/api/config',
            'api/v1' => __DIR__ . '/api/v1',
            'api/v1/products' => __DIR__ . '/api/v1/products',
        ];
        
        echo "<table>";
        echo "<tr><th>ディレクトリ</th><th>存在</th><th>読み取り</th><th>書き込み</th><th>実行</th><th>パーミッション</th><th>所有者</th></tr>";
        
        foreach ($dirsToCheck as $name => $path) {
            $exists = is_dir($path);
            $readable = $exists && is_readable($path);
            $writable = $exists && is_writable($path);
            $executable = $exists && is_executable($path);
            
            // パーミッションと所有者情報を取得
            $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
            $owner = $exists ? (function_exists('posix_getpwuid') ? 
                      posix_getpwuid(fileowner($path))['name'] : 
                      fileowner($path)) : 'N/A';
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($name) . "</td>";
            echo "<td class='" . ($exists ? "success" : "error") . "'>" . ($exists ? "はい" : "いいえ") . "</td>";
            echo "<td class='" . ($readable ? "success" : "error") . "'>" . ($readable ? "はい" : "いいえ") . "</td>";
            echo "<td class='" . ($writable ? "success" : "error") . "'>" . ($writable ? "はい" : "いいえ") . "</td>";
            echo "<td class='" . ($executable ? "success" : "error") . "'>" . ($executable ? "はい" : "いいえ") . "</td>";
            echo "<td>" . $perms . "</td>";
            echo "<td>" . $owner . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        ?>
    </div>
    
    <div class="section">
        <h2>4. ログファイルのテスト</h2>
        <?php
        $logDir = __DIR__ . '/logs';
        $testLogFile = $logDir . '/test_' . date('YmdHis') . '.log';
        
        // ログディレクトリが存在しない場合は作成を試みる
        if (!is_dir($logDir)) {
            $mkdirResult = @mkdir($logDir, 0755, true);
            echo $mkdirResult ? 
                "<p class='success'>ログディレクトリを作成しました: $logDir</p>" : 
                "<p class='error'>ログディレクトリの作成に失敗しました: $logDir</p>";
        }
        
        // ディレクトリが存在するか確認
        if (is_dir($logDir)) {
            echo "<p>ログディレクトリが存在します: $logDir</p>";
            
            // 書き込み権限をチェック
            if (is_writable($logDir)) {
                echo "<p class='success'>ログディレクトリに書き込み権限があります</p>";
                
                // テストログファイルに書き込みを試みる
                $testMessage = "[" . date('Y-m-d H:i:s') . "] [TEST] テストログメッセージ\n";
                $writeResult = @file_put_contents($testLogFile, $testMessage);
                
                if ($writeResult !== false) {
                    echo "<p class='success'>テストログファイルに書き込みに成功しました: $testLogFile</p>";
                    echo "<p>ログ内容: " . htmlspecialchars($testMessage) . "</p>";
                    
                    // ファイルを読み込んでみる
                    $readResult = @file_get_contents($testLogFile);
                    if ($readResult !== false) {
                        echo "<p class='success'>テストログファイルを読み込みました</p>";
                    } else {
                        echo "<p class='error'>テストログファイルの読み込みに失敗しました</p>";
                    }
                } else {
                    echo "<p class='error'>テストログファイルへの書き込みに失敗しました: $testLogFile</p>";
                }
            } else {
                echo "<p class='error'>ログディレクトリに書き込み権限がありません</p>";
            }
            
            // logs ディレクトリの内容を表示
            echo "<h3>ログディレクトリの内容:</h3>";
            if ($handle = opendir($logDir)) {
                echo "<ul>";
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $fullPath = $logDir . '/' . $entry;
                        $size = file_exists($fullPath) ? filesize($fullPath) : 0;
                        $modified = file_exists($fullPath) ? date("Y-m-d H:i:s", filemtime($fullPath)) : 'Unknown';
                        echo "<li>" . htmlspecialchars($entry) . " (" . $size . " bytes, " . $modified . ")</li>";
                    }
                }
                echo "</ul>";
                closedir($handle);
            } else {
                echo "<p class='error'>ディレクトリを開けませんでした</p>";
            }
        } else {
            echo "<p class='error'>ログディレクトリが存在しません: $logDir</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. Categories APIのテスト</h2>
        <?php
        $categoriesApiUrl = '/api/v1/products/categories.php';
        $fullApiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . $categoriesApiUrl;
        
        echo "<p>APIエンドポイント: " . htmlspecialchars($fullApiUrl) . "</p>";
        
        // ファイルが存在するか確認
        $apiFile = __DIR__ . $categoriesApiUrl;
        if (file_exists($apiFile)) {
            echo "<p class='success'>APIファイルが存在します: " . $apiFile . "</p>";
        } else {
            echo "<p class='error'>APIファイルが見つかりません: " . $apiFile . "</p>";
        }
        
        // 実際にAPIにリクエスト（必要に応じて有効化）
        /*
        echo "<h3>APIレスポンス:</h3>";
        try {
            // このコードはJavaScriptで実行する方が望ましい
            $ch = curl_init($fullApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "<p>HTTPステータスコード: " . $httpCode . "</p>";
            echo "<pre class='code'>" . htmlspecialchars($response) . "</pre>";
        } catch (Exception $e) {
            echo "<p class='error'>APIリクエストエラー: " . $e->getMessage() . "</p>";
        }
        */
        ?>
        
        <h3>JavaScriptでAPIをテスト:</h3>
        <button id="testApiBtn">APIをテスト</button>
        <div id="apiResult" class="code" style="margin-top: 10px; height: 200px; overflow: auto;"></div>
        
        <script>
        document.getElementById('testApiBtn').addEventListener('click', function() {
            var resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = "APIリクエスト中...";
            
            fetch('<?php echo $categoriesApiUrl; ?>')
                .then(response => {
                    resultDiv.innerHTML = "HTTPステータス: " + response.status + " " + response.statusText + "<br>";
                    return response.text().then(text => {
                        // レスポンスがJSONかどうかチェック
                        try {
                            const json = JSON.parse(text);
                            resultDiv.innerHTML += "レスポンス (JSON):<br><pre>" + 
                                JSON.stringify(json, null, 2) + "</pre>";
                        } catch (e) {
                            // JSONではない場合はテキストとして表示
                            resultDiv.innerHTML += "レスポンス (Text):<br><pre>" + text + "</pre>";
                        }
                    });
                })
                .catch(error => {
                    resultDiv.innerHTML = "エラー: " + error;
                });
        });
        </script>
    </div>
    
    <div class="section">
        <h2>6. PHPエラーログ</h2>
        <?php
        $errorLogFile = ini_get('error_log');
        echo "<p>PHPエラーログファイル: " . ($errorLogFile ? htmlspecialchars($errorLogFile) : '設定されていません') . "</p>";
        
        if ($errorLogFile && file_exists($errorLogFile)) {
            echo "<p class='success'>エラーログファイルが存在します</p>";
            
            // ファイルサイズを取得
            $logSize = filesize($errorLogFile);
            echo "<p>ファイルサイズ: " . number_format($logSize) . " バイト</p>";
            
            // 最後の更新日時
            $lastModified = filemtime($errorLogFile);
            echo "<p>最終更新日時: " . date('Y-m-d H:i:s', $lastModified) . "</p>";
            
            // ファイルの末尾を表示（大きすぎる場合は一部のみ）
            if ($logSize > 0) {
                echo "<h3>最新のエラーログ（最大1000行）:</h3>";
                
                // 最大1000行を表示
                $maxLines = 1000;
                $lines = [];
                
                // ファイルを開いて末尾から読み込む
                $file = new SplFileObject($errorLogFile, 'r');
                $file->seek(PHP_INT_MAX); // 最終行に移動
                $totalLines = $file->key(); // 総行数
                
                // 表示する行数を計算
                $linesToShow = min($maxLines, $totalLines);
                $startLine = max(0, $totalLines - $linesToShow);
                
                // 指定行数分だけ読み込む
                $file->seek($startLine);
                for ($i = 0; $i < $linesToShow && !$file->eof(); $i++) {
                    $lines[] = $file->current();
                    $file->next();
                }
                
                echo "<pre class='code' style='max-height: 400px;'>";
                foreach ($lines as $line) {
                    echo htmlspecialchars($line);
                }
                echo "</pre>";
            } else {
                echo "<p>エラーログは空です</p>";
            }
        } else {
            echo "<p class='warning'>エラーログファイルが見つからないか、アクセスできません</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>7. PHPの設定情報</h2>
        <table>
            <tr><th>設定項目</th><th>値</th></tr>
            <tr><td>max_execution_time</td><td><?php echo ini_get('max_execution_time'); ?> 秒</td></tr>
            <tr><td>memory_limit</td><td><?php echo ini_get('memory_limit'); ?></td></tr>
            <tr><td>post_max_size</td><td><?php echo ini_get('post_max_size'); ?></td></tr>
            <tr><td>upload_max_filesize</td><td><?php echo ini_get('upload_max_filesize'); ?></td></tr>
            <tr><td>display_errors</td><td><?php echo ini_get('display_errors') ? 'On' : 'Off'; ?></td></tr>
            <tr><td>log_errors</td><td><?php echo ini_get('log_errors') ? 'On' : 'Off'; ?></td></tr>
            <tr><td>error_reporting</td><td><?php echo error_reporting(); ?></td></tr>
            <tr><td>date.timezone</td><td><?php echo ini_get('date.timezone'); ?></td></tr>
            <tr><td>default_charset</td><td><?php echo ini_get('default_charset'); ?></td></tr>
            <tr><td>エラーログパス</td><td><?php echo ini_get('error_log'); ?></td></tr>
        </table>
    </div>
</body>
</html> 