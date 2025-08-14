<?php
// File: device_config.php
// Description: デバイス設定（JSON）を表示

// リクエストパラメータ確認
$device = $_GET['device'] ?? '';
if (empty($device)) {
    $error = "デバイスIDが指定されていません。";
}

// 設定ファイルパス
$configsDir = __DIR__ . "/../configs";
$configPath = $configsDir . "/{$device}.json";

// 設定ファイル存在確認
$exists = !empty($device) && file_exists($configPath);

// 設定読み込み
$config = null;
if ($exists) {
    $json = file_get_contents($configPath);
    $config = json_decode($json, true);
    if ($config === null) {
        $error = "JSON形式エラー: " . json_last_error_msg();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTSP_Reader - デバイス設定確認</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .device-header {
            margin: 20px 0;
            padding: 15px;
            background: #f8f8f8;
            border-left: 5px solid #4CAF50;
        }
        .device-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .device-info div {
            padding: 8px 12px;
            background: #eee;
            border-radius: 4px;
        }
        .config-section {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .config-section h3 {
            margin-top: 0;
            color: #333;
        }
        pre {
            white-space: pre-wrap;
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .nav-links {
            margin: 20px 0;
        }
        .nav-links a {
            margin-right: 15px;
            text-decoration: none;
            color: #0066cc;
        }
        .btn {
            display: inline-block;
            padding: 8px 12px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
        .btn-edit {
            background: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>⚙️ デバイス設定確認</h2>
        
        <!-- ナビゲーションリンク -->
        <div class="nav-links">
            <a href="history.php">履歴表示</a>
            <?php if ($exists): ?>
            <a href="device_edit.php?device=<?= htmlspecialchars($device) ?>">設定編集</a>
            <?php endif; ?>
        </div>
        
        <!-- デバイス選択フォーム -->
        <form method="get" action="">
            <label for="device">デバイスID：</label>
            <input type="text" id="device" name="device" value="<?= htmlspecialchars($device) ?>" required>
            <button type="submit">表示</button>
        </form>
        
        <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($exists && $config): ?>
            <!-- デバイス情報ヘッダー -->
            <div class="device-header">
                <h3><?= htmlspecialchars($config['deviceinfo']['devicename'] ?? $device) ?></h3>
                <div class="device-info">
                    <div>LacisID: <?= htmlspecialchars($config['deviceinfo']['LacisID'] ?? '') ?></div>
                    <div>タイプ: <?= htmlspecialchars($config['deviceinfo']['type'] ?? '') ?></div>
                    <div>モデル: <?= htmlspecialchars($config['deviceinfo']['model'] ?? '') ?></div>
                </div>
                
                <div class="buttons">
                    <a href="device_edit.php?device=<?= htmlspecialchars($device) ?>" class="btn btn-edit">編集</a>
                </div>
            </div>
            
            <!-- 各設定セクション -->
            <?php if (isset($config['deviceinfo'])): ?>
            <div class="config-section">
                <h3>デバイス情報</h3>
                <pre><?= htmlspecialchars(json_encode($config['deviceinfo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
            <?php endif; ?>
            
            <?php if (isset($config['settings'])): ?>
            <div class="config-section">
                <h3>設定</h3>
                <?php if (isset($config['settings']['detection'])): ?>
                <h4>検出モード: <?= htmlspecialchars($config['settings']['detection']['Mode'] ?? '') ?></h4>
                <?php endif; ?>
                <pre><?= htmlspecialchars(json_encode($config['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
            <?php endif; ?>
            
            <?php if (isset($config['state'])): ?>
            <div class="config-section">
                <h3>状態</h3>
                <pre><?= htmlspecialchars(json_encode($config['state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
            <?php endif; ?>
            
            <!-- 設定全体表示 -->
            <div class="config-section">
                <h3>設定全体</h3>
                <pre><?= htmlspecialchars(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        <?php elseif (!empty($device)): ?>
            <p>指定されたデバイスの設定ファイルが見つかりません。</p>
            <p>新しく作成するには <a href="device_edit.php?device=<?= htmlspecialchars($device) ?>">設定編集</a> ページを使用してください。</p>
        <?php endif; ?>
    </div>
</body>
</html> 