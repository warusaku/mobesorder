<?php
// File: test_heartbeat.php
// Description: ハートビート送信テストスクリプト

// ログ設定
$logFile = __DIR__ . '/../logs/php.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

function log_message($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [HEARTBEAT_TEST] $msg" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 設定
$securecode = "rtsp_test";  // セキュリティコード（変更して使用）
$security_key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
$lacis_id = filter_input(INPUT_GET, 'lacis_id', FILTER_SANITIZE_STRING);
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?: 'form';
$heartbeat_endpoint = "/api/heartbeat.php";
$with_image = isset($_GET['with_image']) ? filter_var($_GET['with_image'], FILTER_VALIDATE_BOOLEAN) : false;

// セキュリティチェック
$security_passed = ($security_key === $securecode);

// 応答を送信する関数
function send_response($status, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// セキュリティチェックが必要なアクションのリスト
$secured_actions = ['send', 'receive'];

// アクションがセキュリティ保護されていて、キーが無効な場合
if (in_array($action, $secured_actions) && !$security_passed) {
    log_message("セキュリティ違反: 不正なキー ($action)");
    send_response('error', 'セキュリティコードが不正です', null);
}

// ログへの記録
log_message("ハートビートテスト実行: アクション=$action, LacisID=" . ($lacis_id ?: '未指定'));

// アクションに基づく処理
switch ($action) {
    case 'form':
        // テストフォームを表示
        show_test_form();
        break;
        
    case 'send':
        // ハートビートを送信
        if (!$lacis_id) {
            send_response('error', 'Lacis IDが指定されていません', null);
        }
        
        send_heartbeat($lacis_id, $with_image);
        break;
        
    case 'receive':
        // ハートビートを受信（サーバー側での動作をシミュレート）
        if (!$lacis_id) {
            send_response('error', 'Lacis IDが指定されていません', null);
        }
        
        receive_heartbeat($lacis_id);
        break;
        
    default:
        send_response('error', '不明なアクション: ' . $action, null);
}

// フォーム表示
function show_test_form() {
    global $securecode;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>ハートビート送信テスト</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h2 { color: #333; }
            form { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            label { display: block; margin: 10px 0 5px; }
            input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
            select { padding: 8px; }
            input[type="submit"] { background: #4CAF50; color: white; border: none; padding: 10px 15px; margin-top: 15px; cursor: pointer; }
            .note { color: #666; font-size: 0.8em; margin-top: 5px; }
            #result { margin-top: 20px; padding: 10px; background: #f0f0f0; white-space: pre-wrap; overflow: auto; max-height: 300px; }
            .tabs { display: flex; border-bottom: 1px solid #ccc; margin-bottom: 15px; }
            .tab { padding: 8px 15px; cursor: pointer; background: #eee; margin-right: 5px; border-radius: 5px 5px 0 0; }
            .tab.active { background: #4CAF50; color: white; }
            .file-upload { margin: 10px 0; }
            .checkbox-container { margin: 10px 0; }
        </style>
    </head>
    <body>
        <h2>ハートビート送信テスト</h2>
        
        <div class="tabs">
            <div class="tab active" data-tab="send">送信テスト</div>
            <div class="tab" data-tab="receive">受信シミュレーション</div>
        </div>
        
        <!-- 送信テストフォーム -->
        <form id="sendForm" method="get" enctype="multipart/form-data">
            <h3>ハートビート送信テスト</h3>
            <input type="hidden" name="action" value="send">
            
            <label for="lacis_id">Lacis ID:</label>
            <input type="text" id="lacis_id" name="lacis_id" placeholder="例: LACIS00123" required>
            
            <label for="key">セキュリティコード:</label>
            <input type="text" id="key" name="key" placeholder="セキュリティコードを入力" value="<?php echo $securecode; ?>" required>
            
            <div class="checkbox-container">
                <input type="checkbox" id="with_image" name="with_image" value="1">
                <label for="with_image" style="display: inline;">画像を含める</label>
            </div>
            
            <div class="file-upload" id="imageUploadDiv" style="display: none;">
                <label for="test_image">テスト画像:</label>
                <input type="file" id="test_image" name="test_image" accept="image/*">
                <p class="note">※画像を含める場合、このテスト用画像が送信されます</p>
            </div>
            
            <input type="submit" value="ハートビート送信">
        </form>
        
        <!-- 受信シミュレーションフォーム -->
        <form id="receiveForm" method="get" style="display: none;">
            <h3>ハートビート受信シミュレーション（サーバー側）</h3>
            <input type="hidden" name="action" value="receive">
            
            <label for="receive_lacis_id">Lacis ID:</label>
            <input type="text" id="receive_lacis_id" name="lacis_id" placeholder="例: LACIS00123" required>
            
            <label for="receive_key">セキュリティコード:</label>
            <input type="text" id="receive_key" name="key" placeholder="セキュリティコードを入力" value="<?php echo $securecode; ?>" required>
            
            <input type="submit" value="受信シミュレーション実行">
            <p class="note">※このテストはサーバー側でのハートビート受信処理をシミュレートします</p>
        </form>
        
        <!-- 結果表示エリア -->
        <div id="result"></div>
        
        <script>
            // タブ切り替え
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    // アクティブタブの切り替え
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // フォームの表示切り替え
                    const tabName = this.getAttribute('data-tab');
                    document.getElementById('sendForm').style.display = tabName === 'send' ? 'block' : 'none';
                    document.getElementById('receiveForm').style.display = tabName === 'receive' ? 'block' : 'none';
                });
            });
            
            // 画像含めるチェックボックスの制御
            document.getElementById('with_image').addEventListener('change', function() {
                document.getElementById('imageUploadDiv').style.display = this.checked ? 'block' : 'none';
            });
            
            // 送信フォームのサブミット
            document.getElementById('sendForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const withImage = document.getElementById('with_image').checked;
                
                // 画像ファイルの追加
                if (withImage) {
                    const fileInput = document.getElementById('test_image');
                    if (fileInput.files.length > 0) {
                        formData.append('image', fileInput.files[0]);
                    } else {
                        alert('画像を含める場合は、ファイルを選択してください。');
                        return;
                    }
                }
                
                // 結果表示領域をクリア
                document.getElementById('result').innerHTML = '送信中...';
                
                // GETパラメータの作成
                const params = new URLSearchParams();
                params.append('action', formData.get('action'));
                params.append('lacis_id', formData.get('lacis_id'));
                params.append('key', formData.get('key'));
                params.append('with_image', withImage ? '1' : '0');
                
                // リクエスト送信
                fetch('?' + params.toString(), {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    document.getElementById('result').innerHTML = 'エラーが発生しました: ' + error;
                });
            });
            
            // 受信フォームのサブミット
            document.getElementById('receiveForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                // 結果表示領域をクリア
                document.getElementById('result').innerHTML = '処理中...';
                
                // GETパラメータの作成
                const params = new URLSearchParams();
                params.append('action', formData.get('action'));
                params.append('lacis_id', formData.get('lacis_id'));
                params.append('key', formData.get('key'));
                
                // リクエスト送信
                fetch('?' + params.toString(), {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    document.getElementById('result').innerHTML = 'エラーが発生しました: ' + error;
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ハートビートを送信
function send_heartbeat($lacis_id, $with_image = false) {
    global $heartbeat_endpoint;
    
    try {
        log_message("ハートビート送信開始: LacisID=$lacis_id, 画像含む=" . ($with_image ? 'はい' : 'いいえ'));
        
        // 送信データの準備
        $heartbeat_data = [
            'lacis_id' => $lacis_id,
            'version' => '1.0.0',
            'timestamp' => time(),
            'uptime' => get_system_uptime(),
            'memory_usage' => get_memory_usage(),
            'hostname' => gethostname(),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ];
        
        // サーバーのURL
        $server_url = get_server_url() . $heartbeat_endpoint;
        
        // cURLセッションの初期化
        $ch = curl_init();
        
        if ($with_image) {
            // 画像を含むマルチパートリクエスト
            $test_image_path = __DIR__ . '/sample_image.jpg';
            
            // テスト用の画像がなければ作成
            if (!file_exists($test_image_path)) {
                create_test_image($test_image_path);
            }
            
            $boundary = uniqid();
            $delimiter = '-------------' . $boundary;
            
            $post_data = build_multipart_data($delimiter, $heartbeat_data, $test_image_path);
            
            curl_setopt($ch, CURLOPT_URL, $server_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: multipart/form-data; boundary=" . $delimiter,
                "Content-Length: " . strlen($post_data)
            ]);
        } else {
            // 通常のJSONリクエスト
            curl_setopt($ch, CURLOPT_URL, $server_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($heartbeat_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        // タイムアウト設定
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // リクエスト実行
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURLエラー: $error");
        }
        
        log_message("ハートビート送信完了: HTTP " . $info['http_code']);
        
        // レスポンスのパース
        $response_data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSONパースエラー: " . json_last_error_msg());
        }
        
        send_response('success', "ハートビート送信完了", [
            'lacis_id' => $lacis_id,
            'with_image' => $with_image,
            'server_response' => $response_data,
            'http_code' => $info['http_code'],
            'total_time' => $info['total_time']
        ]);
        
    } catch (Exception $e) {
        log_message("エラー: " . $e->getMessage());
        send_response('error', $e->getMessage(), null);
    }
}

// ハートビート受信処理（サーバー側をシミュレート）
function receive_heartbeat($lacis_id) {
    try {
        log_message("ハートビート受信シミュレーション: LacisID=$lacis_id");
        
        // データベース接続（実際の環境に合わせて調整）
        $db_file = __DIR__ . '/../db/simulation.db'; // SQLiteを使用
        $db_dir = dirname($db_file);
        
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0777, true);
        }
        
        $db = new SQLite3($db_file);
        
        // テーブルがなければ作成
        $db->exec("CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lacis_id TEXT NOT NULL UNIQUE,
            last_heartbeat INTEGER,
            online INTEGER DEFAULT 0,
            config_version INTEGER DEFAULT 1,
            last_updated TEXT
        )");
        
        // デバイス情報の取得または作成
        $stmt = $db->prepare("SELECT * FROM devices WHERE lacis_id = :lacis_id");
        $stmt->bindValue(':lacis_id', $lacis_id, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        $device = $result->fetchArray(SQLITE3_ASSOC);
        $current_time = time();
        $config_changed = false;
        
        if (!$device) {
            // 新規デバイス
            $stmt = $db->prepare("INSERT INTO devices (lacis_id, last_heartbeat, online, config_version, last_updated) 
                                 VALUES (:lacis_id, :timestamp, 1, 1, :updated)");
            $stmt->bindValue(':lacis_id', $lacis_id, SQLITE3_TEXT);
            $stmt->bindValue(':timestamp', $current_time, SQLITE3_INTEGER);
            $stmt->bindValue(':updated', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            
            log_message("新規デバイス登録: $lacis_id");
            $config_changed = true;
        } else {
            // 既存デバイスの更新
            // ランダムに設定変更をシミュレート
            $config_changed = (rand(0, 5) === 0); // 20%の確率で設定変更
            $config_version = $device['config_version'];
            
            if ($config_changed) {
                $config_version++;
                log_message("設定変更シミュレーション: $lacis_id (バージョン $config_version)");
            }
            
            $stmt = $db->prepare("UPDATE devices SET 
                                last_heartbeat = :timestamp, 
                                online = 1, 
                                config_version = :version,
                                last_updated = :updated
                                WHERE lacis_id = :lacis_id");
            $stmt->bindValue(':timestamp', $current_time, SQLITE3_INTEGER);
            $stmt->bindValue(':version', $config_version, SQLITE3_INTEGER);
            $stmt->bindValue(':updated', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->bindValue(':lacis_id', $lacis_id, SQLITE3_TEXT);
            $stmt->execute();
            
            log_message("既存デバイス更新: $lacis_id");
        }
        
        // 設定データのシミュレーション
        $config_data = null;
        if ($config_changed) {
            // 設定変更があった場合のサンプルデータ
            $config_data = [
                'detection_mode' => rand(0, 1) ? 'area' : 'aruco',
                'threshold' => rand(30, 90),
                'interval' => rand(1, 10) * 5,
                'area_settings' => [
                    'points' => [
                        [rand(0, 100), rand(0, 100)],
                        [rand(100, 200), rand(0, 100)],
                        [rand(100, 200), rand(100, 200)],
                        [rand(0, 100), rand(100, 200)]
                    ]
                ]
            ];
        }
        
        $db->close();
        
        // レスポンスの準備
        $response = [
            'status' => 'success',
            'message' => 'ハートビート受信処理完了',
            'data' => [
                'lacis_id' => $lacis_id,
                'timestamp' => $current_time,
                'config_changed' => $config_changed
            ]
        ];
        
        if ($config_data) {
            $response['data']['config'] = $config_data;
        }
        
        log_message("ハートビート受信シミュレーション完了");
        send_response('success', "ハートビート受信処理完了", $response['data']);
        
    } catch (Exception $e) {
        log_message("エラー: " . $e->getMessage());
        send_response('error', $e->getMessage(), null);
    }
}

// サーバーのURLを取得
function get_server_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base_dir = substr($script_dir, 0, strrpos($script_dir, '/test'));
    
    return "$protocol://$host$base_dir";
}

// マルチパートデータの構築
function build_multipart_data($delimiter, $fields, $image_path) {
    $data = '';
    $eol = "\r\n";
    
    // フィールドの追加
    foreach ($fields as $name => $content) {
        $data .= "--" . $delimiter . $eol;
        $data .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
        $data .= $content . $eol;
    }
    
    // 画像の追加
    $data .= "--" . $delimiter . $eol;
    $data .= 'Content-Disposition: form-data; name="image"; filename="' . basename($image_path) . '"' . $eol;
    $data .= 'Content-Type: image/jpeg' . $eol . $eol;
    $data .= file_get_contents($image_path) . $eol;
    
    // 終了区切り
    $data .= "--" . $delimiter . "--" . $eol;
    
    return $data;
}

// テスト画像の作成
function create_test_image($path) {
    $width = 640;
    $height = 480;
    
    $image = imagecreatetruecolor($width, $height);
    
    // 背景色
    $bg_color = imagecolorallocate($image, 240, 240, 240);
    imagefill($image, 0, 0, $bg_color);
    
    // 枠線
    $border_color = imagecolorallocate($image, 200, 200, 200);
    imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);
    
    // テキスト
    $text_color = imagecolorallocate($image, 50, 50, 50);
    $text = "テスト画像 - " . date('Y-m-d H:i:s');
    imagestring($image, 5, 20, 20, $text, $text_color);
    
    // テスト用マーカー
    $marker_color = imagecolorallocate($image, 255, 0, 0);
    imagefilledrectangle($image, 100, 100, 200, 200, $marker_color);
    
    // 画像の保存
    imagejpeg($image, $path, 90);
    imagedestroy($image);
    
    return true;
}

// システムのアップタイム取得
function get_system_uptime() {
    if (PHP_OS === 'Linux') {
        $uptime = shell_exec('cat /proc/uptime');
        $uptime = explode(' ', $uptime)[0];
        return (int)$uptime;
    } else {
        // 他のOSの場合、ダミー値を返す
        return rand(3600, 86400); // 1時間〜1日
    }
}

// メモリ使用量取得
function get_memory_usage() {
    return [
        'used' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
}
?> 