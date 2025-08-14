<?php
// File: test_area_api.php
// Description: 台形補正用エリア指定APIテスト

// ログ設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [AREA_API_TEST] $msg" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 設定
$securecode = "rtsp_test";  // セキュリティコード（変更して使用）
$security_key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
$lacis_id = filter_input(INPUT_GET, 'lacis_id', FILTER_SANITIZE_STRING);
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?: 'form';

// データベース接続情報（必要に応じて調整）
require_once __DIR__ . '/../dbconfig.php';

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
$secured_actions = ['get', 'update', 'save'];

// アクションがセキュリティ保護されていて、キーが無効な場合
if (in_array($action, $secured_actions) && !$security_passed) {
    log_message("セキュリティ違反: 不正なキー ($action)");
    send_response('error', 'セキュリティコードが不正です', null);
}

// ログへの記録
log_message("エリアAPIテスト実行: アクション=$action, LacisID=" . ($lacis_id ?: '未指定'));

// アクションに基づく処理
switch ($action) {
    case 'form':
        // テストフォームを表示
        show_test_form();
        break;
        
    case 'get':
        // エリア設定を取得
        if (!$lacis_id) {
            send_response('error', 'Lacis IDが指定されていません', null);
        }
        
        get_area_settings($lacis_id);
        break;
        
    case 'update':
        // エリア設定を更新（テスト用）
        if (!$lacis_id) {
            send_response('error', 'Lacis IDが指定されていません', null);
        }
        
        // POSTデータからエリア座標を取得
        $area_data = isset($_POST['area_data']) ? $_POST['area_data'] : null;
        if (!$area_data) {
            send_response('error', 'エリアデータが指定されていません', null);
        }
        
        update_area_settings($lacis_id, $area_data);
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
        <title>エリア指定APIテスト</title>
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
            #canvas-container { position: relative; margin-top: 20px; }
            canvas { border: 1px solid #ddd; }
            #result { margin-top: 20px; padding: 10px; background: #f0f0f0; white-space: pre-wrap; }
        </style>
    </head>
    <body>
        <h2>エリア指定APIテスト</h2>
        
        <!-- 設定取得フォーム -->
        <form id="getForm" method="get">
            <h3>エリア設定取得</h3>
            <input type="hidden" name="action" value="get">
            
            <label for="lacis_id">Lacis ID:</label>
            <input type="text" id="lacis_id" name="lacis_id" placeholder="例: LACIS00123" required>
            
            <label for="key">セキュリティコード:</label>
            <input type="text" id="key" name="key" placeholder="セキュリティコードを入力" value="<?php echo $securecode; ?>" required>
            
            <input type="submit" value="設定取得">
        </form>
        
        <!-- 設定更新フォーム -->
        <form id="updateForm" method="post" action="?action=update">
            <h3>エリア設定更新（テスト）</h3>
            
            <label for="update_lacis_id">Lacis ID:</label>
            <input type="text" id="update_lacis_id" name="lacis_id" placeholder="例: LACIS00123" required>
            
            <label for="update_key">セキュリティコード:</label>
            <input type="text" id="update_key" name="key" placeholder="セキュリティコードを入力" value="<?php echo $securecode; ?>" required>
            
            <label for="area_data">エリアデータ (JSON):</label>
            <textarea id="area_data" name="area_data" rows="5" style="width: 100%;" placeholder='{"points": [[0,0], [100,0], [100,100], [0,100]]}'></textarea>
            
            <input type="submit" value="設定更新">
        </form>
        
        <!-- キャンバスエリア -->
        <div id="canvas-container">
            <h3>エリアビジュアライザー</h3>
            <p>現在の設定をビジュアル化します。または新しい設定を描画できます。</p>
            <canvas id="areaCanvas" width="640" height="480"></canvas>
            <div>
                <button id="clearCanvas">クリア</button>
                <button id="generateJSON">JSONに変換</button>
            </div>
        </div>
        
        <!-- 結果表示エリア -->
        <div id="result"></div>
        
        <script>
            // キャンバス操作用JavaScript
            const canvas = document.getElementById('areaCanvas');
            const ctx = canvas.getContext('2d');
            const points = [];
            let isDragging = false;
            let activePointIndex = -1;
            
            // キャンバスをクリア
            function clearCanvas() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                points.length = 0;
                drawPoints();
            }
            
            // ポイントを描画
            function drawPoints() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // 線を描画
                if (points.length > 1) {
                    ctx.beginPath();
                    ctx.moveTo(points[0].x, points[0].y);
                    for (let i = 1; i < points.length; i++) {
                        ctx.lineTo(points[i].x, points[i].y);
                    }
                    // 最後のポイントから最初のポイントへ
                    if (points.length > 2) {
                        ctx.lineTo(points[0].x, points[0].y);
                    }
                    ctx.strokeStyle = '#0066cc';
                    ctx.stroke();
                }
                
                // ポイントを描画
                for (let i = 0; i < points.length; i++) {
                    ctx.beginPath();
                    ctx.arc(points[i].x, points[i].y, 5, 0, Math.PI * 2);
                    ctx.fillStyle = i === activePointIndex ? '#ff0000' : '#3399ff';
                    ctx.fill();
                }
            }
            
            // マウスダウンイベント
            canvas.addEventListener('mousedown', function(e) {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // 既存のポイントをクリックしたかチェック
                for (let i = 0; i < points.length; i++) {
                    const dx = points[i].x - x;
                    const dy = points[i].y - y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 10) { // 10ピクセル以内ならポイントをつかむ
                        activePointIndex = i;
                        isDragging = true;
                        return;
                    }
                }
                
                // 最大4点まで
                if (points.length < 4) {
                    points.push({ x, y });
                    drawPoints();
                }
            });
            
            // マウス移動イベント
            canvas.addEventListener('mousemove', function(e) {
                if (isDragging && activePointIndex !== -1) {
                    const rect = canvas.getBoundingClientRect();
                    points[activePointIndex].x = e.clientX - rect.left;
                    points[activePointIndex].y = e.clientY - rect.top;
                    drawPoints();
                }
            });
            
            // マウスアップイベント
            canvas.addEventListener('mouseup', function() {
                isDragging = false;
                activePointIndex = -1;
            });
            
            // クリアボタン
            document.getElementById('clearCanvas').addEventListener('click', clearCanvas);
            
            // JSON生成ボタン
            document.getElementById('generateJSON').addEventListener('click', function() {
                if (points.length !== 4) {
                    alert('エリアには正確に4点が必要です');
                    return;
                }
                
                const jsonPoints = points.map(p => [Math.round(p.x), Math.round(p.y)]);
                const jsonData = JSON.stringify({ points: jsonPoints }, null, 2);
                
                document.getElementById('area_data').value = jsonData;
                document.getElementById('result').textContent = 'JSONデータが生成されました:\n' + jsonData;
            });
            
            // 初期描画
            drawPoints();
        </script>
    </body>
    </html>
    <?php
    exit;
}

// データベースからエリア設定を取得
function get_area_settings($lacis_id) {
    global $db_host, $db_user, $db_password, $db_name;
    
    try {
        // データベース接続
        $mysqli = new mysqli($db_host, $db_user, $db_password, $db_name);
        if ($mysqli->connect_error) {
            throw new Exception("データベース接続エラー: " . $mysqli->connect_error);
        }
        
        // エリア設定を取得
        $query = "SELECT * FROM device_settings WHERE lacis_id = ?";
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("クエリ準備エラー: " . $mysqli->error);
        }
        
        $stmt->bind_param("s", $lacis_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            log_message("エリア設定なし: $lacis_id");
            send_response('error', "LacisID $lacis_id の設定が見つかりません", null);
        }
        
        $data = $result->fetch_assoc();
        $area_settings = isset($data['area_settings']) ? json_decode($data['area_settings'], true) : null;
        
        $stmt->close();
        $mysqli->close();
        
        log_message("エリア設定取得成功: $lacis_id");
        send_response('success', "エリア設定を取得しました", [
            'lacis_id' => $lacis_id,
            'area_settings' => $area_settings,
            'updated_at' => $data['updated_at'] ?? null
        ]);
        
    } catch (Exception $e) {
        log_message("エラー: " . $e->getMessage());
        send_response('error', $e->getMessage(), null);
    }
}

// エリア設定を更新（テスト用）
function update_area_settings($lacis_id, $area_data) {
    global $db_host, $db_user, $db_password, $db_name;
    
    try {
        // データベース接続
        $mysqli = new mysqli($db_host, $db_user, $db_password, $db_name);
        if ($mysqli->connect_error) {
            throw new Exception("データベース接続エラー: " . $mysqli->connect_error);
        }
        
        // JSONデータの検証
        $area_json = $area_data;
        $area_obj = json_decode($area_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("不正なJSON形式: " . json_last_error_msg());
        }
        
        // 既存の設定を確認
        $query = "SELECT id FROM device_settings WHERE lacis_id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("s", $lacis_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // 新規レコードを作成
            $insert_query = "INSERT INTO device_settings (lacis_id, area_settings, updated_at) VALUES (?, ?, NOW())";
            $insert_stmt = $mysqli->prepare($insert_query);
            $insert_stmt->bind_param("ss", $lacis_id, $area_json);
            $insert_stmt->execute();
            
            if ($insert_stmt->affected_rows === 0) {
                throw new Exception("設定の作成に失敗しました: " . $mysqli->error);
            }
            
            log_message("エリア設定新規作成: $lacis_id");
            $message = "新しいエリア設定を作成しました";
            $insert_stmt->close();
        } else {
            // 既存レコードを更新
            $row = $result->fetch_assoc();
            $id = $row['id'];
            
            $update_query = "UPDATE device_settings SET area_settings = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $mysqli->prepare($update_query);
            $update_stmt->bind_param("si", $area_json, $id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows === 0) {
                throw new Exception("設定の更新に失敗しました: " . $mysqli->error);
            }
            
            log_message("エリア設定更新: $lacis_id");
            $message = "エリア設定を更新しました";
            $update_stmt->close();
        }
        
        $stmt->close();
        $mysqli->close();
        
        send_response('success', $message, [
            'lacis_id' => $lacis_id,
            'area_data' => json_decode($area_json)
        ]);
        
    } catch (Exception $e) {
        log_message("エラー: " . $e->getMessage());
        send_response('error', $e->getMessage(), null);
    }
}
?> 