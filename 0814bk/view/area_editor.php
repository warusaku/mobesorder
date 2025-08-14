<?php
/**
 * File: area_editor.php
 * Description: RTSPカメラの台形補正エリア指定用WebUI
 */

// ログファイル設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " [AREA_EDITOR] " . $msg . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// セキュリティコード設定
$securecode = "rtsp_test";

// リクエストパラメータ
$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
$lacis_id = filter_input(INPUT_GET, 'lacis_id', FILTER_SANITIZE_STRING);
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);

// セキュリティチェック
if ($key !== $securecode) {
    http_response_code(403);
    echo "セキュリティコードが無効です。正しいキーを指定してください。";
    exit;
}

// データベース接続
require_once '../dbconfig.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    log_message("データベース接続エラー: " . $mysqli->connect_error);
    die("データベース接続に失敗しました");
}

// LacisID一覧を取得（ドロップダウンリスト用）
$devices = [];
$query = "SELECT lacis_id, has_image, image_updated_at FROM sync_status WHERE online_status = 1 ORDER BY lacis_id";
$result = $mysqli->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
}

// 最新画像の確認
$image_url = null;
$image_updated = null;
$has_image = false;
$refresh_requested = false;

if (!empty($lacis_id)) {
    $image_path = __DIR__ . '/../latestimages/' . $lacis_id . '.jpg';
    $timestamp_file = __DIR__ . '/../latestimages/' . $lacis_id . '.timestamp';
    
    $has_image = file_exists($image_path);
    $image_updated = file_exists($timestamp_file) ? date('Y-m-d H:i:s', file_get_contents($timestamp_file)) : null;
    
    if ($has_image) {
        $cache_buster = file_exists($timestamp_file) ? file_get_contents($timestamp_file) : time();
        $image_url = '../latestimages/' . $lacis_id . '.jpg?t=' . $cache_buster;
    }
    
    // 画像更新リクエスト
    if ($action === 'refresh' && !empty($lacis_id)) {
        $refresh_url = '../api/update_image.php?lacis_id=' . urlencode($lacis_id) . '&key=' . urlencode($securecode);
        $response = file_get_contents($refresh_url);
        $result = json_decode($response, true);
        
        if (isset($result['status']) && $result['status'] === 'success') {
            $refresh_requested = true;
            log_message("画像更新リクエスト送信: $lacis_id");
        }
    }
    
    // 設定の保存処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_area'])) {
        $area_data = [
            'x' => (int)$_POST['area_x'],
            'y' => (int)$_POST['area_y'],
            'w' => (int)$_POST['area_width'],
            'h' => (int)$_POST['area_height']
        ];
        
        // 既存の設定を取得
        $config_sql = "SELECT config_json FROM device_configs WHERE lacis_id = ? ORDER BY updated_at DESC LIMIT 1";
        $config_stmt = $mysqli->prepare($config_sql);
        $config_stmt->bind_param("s", $lacis_id);
        $config_stmt->execute();
        $config_result = $config_stmt->get_result();
        
        if ($config_result->num_rows > 0) {
            $config_row = $config_result->fetch_assoc();
            $config_data = json_decode($config_row['config_json'], true);
            
            // 設定が存在する場合は更新
            if (!isset($config_data['settings']['detection'])) {
                $config_data['settings']['detection'] = [];
            }
            
            // モードがareaでない場合は変更
            $config_data['settings']['detection']['Mode'] = 'area';
            
            if (!isset($config_data['settings']['detection']['areaSetting'])) {
                $config_data['settings']['detection']['areaSetting'] = [];
            }
            
            // 領域設定を更新
            $area_id = isset($_POST['area_id']) ? $_POST['area_id'] : 'primary';
            $config_data['settings']['detection']['areaSetting'][$area_id] = [
                'id' => $area_id,
                'x' => $area_data['x'],
                'y' => $area_data['y'],
                'w' => $area_data['w'],
                'h' => $area_data['h']
            ];
            
            // 設定を保存
            $new_config_json = json_encode($config_data);
            
            // 設定更新APIを呼び出し
            $post_data = [
                'lacis_id' => $lacis_id,
                'key' => $securecode,
                'config' => $new_config_json,
                'change_type' => 'area_setting'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, '../api/update_config.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            $save_success = isset($result['status']) && $result['status'] === 'success';
            
            log_message("エリア設定保存 " . ($save_success ? "成功" : "失敗") . ": $lacis_id, Area: $area_id");
        }
    }
    
    // 現在の設定を取得
    $area_settings = [];
    $current_mode = 'unknown';
    
    $config_sql = "SELECT config_json FROM device_configs WHERE lacis_id = ? ORDER BY updated_at DESC LIMIT 1";
    $config_stmt = $mysqli->prepare($config_sql);
    $config_stmt->bind_param("s", $lacis_id);
    $config_stmt->execute();
    $config_result = $config_stmt->get_result();
    
    if ($config_result->num_rows > 0) {
        $config_row = $config_result->fetch_assoc();
        $config_data = json_decode($config_row['config_json'], true);
        
        if (isset($config_data['settings']['detection']['Mode'])) {
            $current_mode = $config_data['settings']['detection']['Mode'];
        }
        
        if (isset($config_data['settings']['detection']['areaSetting'])) {
            $area_settings = $config_data['settings']['detection']['areaSetting'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTSPカメラ エリア設定</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        #image-container {
            position: relative;
            max-width: 100%;
            margin: 20px 0;
            overflow: auto;
        }
        #camera-image {
            max-width: 100%;
            border: 1px solid #ccc;
        }
        #area-selector {
            position: absolute;
            border: 2px dashed red;
            background-color: rgba(255, 0, 0, 0.2);
            pointer-events: none;
        }
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">RTSPカメラ エリア設定</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>デバイス選択</h5>
            </div>
            <div class="card-body">
                <form method="get" class="mb-3">
                    <input type="hidden" name="key" value="<?php echo htmlspecialchars($securecode); ?>">
                    
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label for="lacis_id" class="col-form-label">LacisID:</label>
                        </div>
                        <div class="col-md-4">
                            <select name="lacis_id" id="lacis_id" class="form-select" required>
                                <option value="">選択してください</option>
                                <?php foreach ($devices as $device): ?>
                                    <option value="<?php echo htmlspecialchars($device['lacis_id']); ?>" 
                                            <?php echo ($device['lacis_id'] === $lacis_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($device['lacis_id']); ?>
                                        <?php if ($device['has_image']): ?>
                                            - 画像あり (<?php echo date('m/d H:i', strtotime($device['image_updated_at'])); ?>)
                                        <?php else: ?>
                                            - 画像なし
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">選択</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($lacis_id)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>カメラ画像: <?php echo htmlspecialchars($lacis_id); ?></h5>
                    <div>
                        <form method="get" class="d-inline">
                            <input type="hidden" name="key" value="<?php echo htmlspecialchars($securecode); ?>">
                            <input type="hidden" name="lacis_id" value="<?php echo htmlspecialchars($lacis_id); ?>">
                            <input type="hidden" name="action" value="refresh">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-clockwise"></i> 画像を更新
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($refresh_requested): ?>
                        <div class="alert alert-info">
                            画像更新リクエストを送信しました。数秒後に更新ボタンを押して確認してください。
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($has_image): ?>
                        <p class="mb-2">最終更新: <?php echo $image_updated ? htmlspecialchars($image_updated) : '不明'; ?></p>
                        <div id="image-container">
                            <img id="camera-image" src="<?php echo htmlspecialchars($image_url); ?>" alt="カメラ画像">
                            <div id="area-selector"></div>
                        </div>
                        
                        <form method="post" id="area-form" class="mt-4">
                            <h5>エリア設定</h5>
                            
                            <?php if (!empty($area_settings)): ?>
                                <div class="mb-3">
                                    <label for="area_id" class="form-label">エリアID:</label>
                                    <select id="area_id" name="area_id" class="form-select">
                                        <?php foreach ($area_settings as $id => $area): ?>
                                            <option value="<?php echo htmlspecialchars($id); ?>">
                                                <?php echo htmlspecialchars($id); ?>
                                                (<?php echo $area['x']; ?>, <?php echo $area['y']; ?>, 
                                                <?php echo $area['w']; ?>x<?php echo $area['h']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="new_area">新規エリア</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="area_id" value="primary">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="area_x" class="form-label">X位置:</label>
                                    <input type="number" id="area_x" name="area_x" class="form-control" value="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="area_y" class="form-label">Y位置:</label>
                                    <input type="number" id="area_y" name="area_y" class="form-control" value="0" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="area_width" class="form-label">幅:</label>
                                    <input type="number" id="area_width" name="area_width" class="form-control" value="100" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="area_height" class="form-label">高さ:</label>
                                    <input type="number" id="area_height" name="area_height" class="form-control" value="100" required>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" id="clear-btn" class="btn btn-secondary">クリア</button>
                                <button type="submit" name="save_area" class="btn btn-success">保存</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            画像がありません。「画像を更新」ボタンをクリックして画像を取得してください。
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>現在の設定</h5>
                </div>
                <div class="card-body">
                    <p><strong>検出モード: </strong> <?php echo htmlspecialchars($current_mode); ?></p>
                    
                    <?php if (!empty($area_settings)): ?>
                        <h6>エリア設定:</h6>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>X</th>
                                    <th>Y</th>
                                    <th>幅</th>
                                    <th>高さ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($area_settings as $id => $area): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($id); ?></td>
                                        <td><?php echo $area['x']; ?></td>
                                        <td><?php echo $area['y']; ?></td>
                                        <td><?php echo $area['w']; ?></td>
                                        <td><?php echo $area['h']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>エリア設定はありません。</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                LacisIDを選択してください。
            </div>
        <?php endif; ?>
        
        <div id="loading-overlay" style="display: none;">
            <div class="loading-content">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">処理中です...</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageContainer = document.getElementById('image-container');
            const cameraImage = document.getElementById('camera-image');
            const areaSelector = document.getElementById('area-selector');
            const areaForm = document.getElementById('area-form');
            const areaX = document.getElementById('area_x');
            const areaY = document.getElementById('area_y');
            const areaWidth = document.getElementById('area_width');
            const areaHeight = document.getElementById('area_height');
            const clearBtn = document.getElementById('clear-btn');
            const areaIdSelect = document.getElementById('area_id');
            const loadingOverlay = document.getElementById('loading-overlay');
            
            let isDrawing = false;
            let startX, startY;
            
            // 既存の設定で初期値を設定
            <?php if (!empty($area_settings) && !empty($lacis_id)): ?>
                // 最初のエリアを選択
                function loadAreaData() {
                    if (!areaIdSelect) return;
                    
                    const selectedAreaId = areaIdSelect.value;
                    
                    if (selectedAreaId === 'new_area') {
                        // 新規エリアの場合はデフォルト値
                        areaX.value = 0;
                        areaY.value = 0;
                        areaWidth.value = 100;
                        areaHeight.value = 100;
                        updateAreaSelector();
                        return;
                    }
                    
                    // 既存のエリア設定
                    const areaSettings = <?php echo json_encode($area_settings); ?>;
                    const selectedArea = areaSettings[selectedAreaId];
                    
                    if (selectedArea) {
                        areaX.value = selectedArea.x;
                        areaY.value = selectedArea.y;
                        areaWidth.value = selectedArea.w;
                        areaHeight.value = selectedArea.h;
                        updateAreaSelector();
                    }
                }
                
                // エリア選択が変更されたら設定を読み込む
                if (areaIdSelect) {
                    areaIdSelect.addEventListener('change', loadAreaData);
                    loadAreaData(); // 初期値を設定
                }
            <?php endif; ?>
            
            // マウスイベント処理
            if (imageContainer && cameraImage) {
                // マウスダウンで描画開始
                imageContainer.addEventListener('mousedown', function(e) {
                    const rect = cameraImage.getBoundingClientRect();
                    startX = e.clientX - rect.left;
                    startY = e.clientY - rect.top;
                    
                    // 範囲外クリック防止
                    if (startX < 0 || startX > cameraImage.width || 
                        startY < 0 || startY > cameraImage.height) {
                        return;
                    }
                    
                    isDrawing = true;
                    
                    // 初期位置を設定
                    areaX.value = Math.round(startX);
                    areaY.value = Math.round(startY);
                    areaWidth.value = 0;
                    areaHeight.value = 0;
                    
                    // 新規エリア選択
                    if (areaIdSelect && areaIdSelect.querySelector('option[value="new_area"]')) {
                        areaIdSelect.value = 'new_area';
                    }
                    
                    updateAreaSelector();
                });
                
                // マウス移動でサイズ変更
                imageContainer.addEventListener('mousemove', function(e) {
                    if (!isDrawing) return;
                    
                    const rect = cameraImage.getBoundingClientRect();
                    const currentX = e.clientX - rect.left;
                    const currentY = e.clientY - rect.top;
                    
                    // 幅と高さを計算
                    const width = currentX - startX;
                    const height = currentY - startY;
                    
                    // 負の値の場合は位置を調整
                    if (width < 0) {
                        areaX.value = Math.round(currentX);
                        areaWidth.value = Math.round(Math.abs(width));
                    } else {
                        areaX.value = Math.round(startX);
                        areaWidth.value = Math.round(width);
                    }
                    
                    if (height < 0) {
                        areaY.value = Math.round(currentY);
                        areaHeight.value = Math.round(Math.abs(height));
                    } else {
                        areaY.value = Math.round(startY);
                        areaHeight.value = Math.round(height);
                    }
                    
                    updateAreaSelector();
                });
                
                // マウスアップで描画終了
                imageContainer.addEventListener('mouseup', function() {
                    isDrawing = false;
                });
                
                // キャンバス外でもマウスアップを検知
                document.addEventListener('mouseup', function() {
                    isDrawing = false;
                });
            }
            
            // 数値入力でも表示を更新
            areaX.addEventListener('input', updateAreaSelector);
            areaY.addEventListener('input', updateAreaSelector);
            areaWidth.addEventListener('input', updateAreaSelector);
            areaHeight.addEventListener('input', updateAreaSelector);
            
            // クリアボタン
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    areaX.value = 0;
                    areaY.value = 0;
                    areaWidth.value = 0;
                    areaHeight.value = 0;
                    updateAreaSelector();
                });
            }
            
            // エリアセレクタ更新
            function updateAreaSelector() {
                if (!areaSelector) return;
                
                const x = parseInt(areaX.value) || 0;
                const y = parseInt(areaY.value) || 0;
                const width = parseInt(areaWidth.value) || 0;
                const height = parseInt(areaHeight.value) || 0;
                
                areaSelector.style.left = x + 'px';
                areaSelector.style.top = y + 'px';
                areaSelector.style.width = width + 'px';
                areaSelector.style.height = height + 'px';
            }
            
            // フォーム送信時の処理
            if (areaForm) {
                areaForm.addEventListener('submit', function() {
                    loadingOverlay.style.display = 'flex';
                });
            }
            
            // 初期表示時に更新
            updateAreaSelector();
        });
    </script>
</body>
</html>