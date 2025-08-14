<?php
// File: device_edit.php
// Description: デバイス設定JSONの簡易編集UI

// 保存処理と完了メッセージ初期化
$saveMessage = '';
$saveError = '';

// デバイスID取得
$device = $_GET['device'] ?? '';
if (empty($device)) {
    $saveError = "デバイスIDが指定されていません。";
}

// 設定ファイルパス
$configsDir = __DIR__ . "/../configs";
$configPath = $configsDir . "/{$device}.json";

// ディレクトリが存在しない場合は作成
if (!is_dir($configsDir)) {
    if (!mkdir($configsDir, 0755, true)) {
        $saveError = "設定ディレクトリを作成できませんでした。";
    }
}

// POST処理（設定保存）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($device)) {
    $postedJson = $_POST['config'] ?? '';
    
    if (empty($postedJson)) {
        $saveError = "設定データが空です。";
    } else {
        // JSONデータとして有効か検証
        $json = json_decode($postedJson);
        if ($json === null) {
            $saveError = "JSON形式エラー: " . json_last_error_msg();
        } else {
            // 必須項目チェック
            if (!isset($json->deviceinfo) || !isset($json->deviceinfo->LacisID)) {
                $saveError = "必須項目が不足しています: deviceinfo.LacisID";
            } else {
                // JSONを整形して保存
                $formattedJson = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if (file_put_contents($configPath, $formattedJson)) {
                    $saveMessage = "設定を保存しました。";
                } else {
                    $saveError = "ファイルの書き込みに失敗しました。";
                }
            }
        }
    }
}

// 既存設定読み込み
$jsonContent = '';
$isNewDevice = true;

if (file_exists($configPath)) {
    $jsonContent = file_get_contents($configPath);
    $isNewDevice = false;
} else if (empty($saveError)) {
    // 新規デバイス用のテンプレート
    $template = [
        "deviceinfo" => [
            "LacisID" => $device,
            "devicename" => "新規デバイス",
            "description" => "説明を入力してください",
            "aid" => "",
            "fid" => "",
            "rid" => "",
            "did" => "",
            "type" => "RTSP_ReadCAM",
            "model" => ""
        ],
        "settings" => [
            "detection" => [
                "Mode" => "aruco",
                "arucoSetting" => [
                    "aruco_ids" => [0, 1, 2, 3],
                    "output_size" => [300, 100]
                ],
                "areaSetting" => []
            ],
            "access" => [
                "userID" => "admin",
                "pass" => "password",
                "port" => "554",
                "camID" => "",
                "Interval" => "10000",
                "endpoint" => "https://example.com/endpoint.php",
                "param" => "token=xxx"
            ]
        ],
        "state" => [
            "online" => false
        ]
    ];
    
    $jsonContent = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTSP_Reader - デバイス設定編集</title>
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
        textarea {
            width: 100%;
            font-family: monospace;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 500px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .buttons {
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn-cancel {
            background: #f44336;
        }
        .nav-links {
            margin: 20px 0;
        }
        .nav-links a {
            margin-right: 15px;
            text-decoration: none;
            color: #0066cc;
        }
        .help-box {
            background: #e6f7ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1890ff;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🛠️ デバイス設定編集</h2>
        
        <!-- ナビゲーションリンク -->
        <div class="nav-links">
            <a href="history.php">履歴表示</a>
            <?php if (!$isNewDevice): ?>
            <a href="device_config.php?device=<?= htmlspecialchars($device) ?>">設定表示</a>
            <?php endif; ?>
        </div>
        
        <!-- エラー/成功メッセージ表示 -->
        <?php if (!empty($saveMessage)): ?>
        <div class="message success"><?= htmlspecialchars($saveMessage) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($saveError)): ?>
        <div class="message error"><?= htmlspecialchars($saveError) ?></div>
        <?php endif; ?>
        
        <!-- ヘルプ情報 -->
        <div class="help-box">
            <h3>JSON設定の編集について</h3>
            <p>以下の点に注意して編集してください：</p>
            <ul>
                <li>JSONの構造と形式を維持してください（カンマ、引用符などの構文に注意）</li>
                <li>LacisIDはシステム内で一意である必要があります</li>
                <li>検出モードは "aruco" または "area" のいずれかを指定してください</li>
                <li>arucoSettingでは正確に4つのIDを指定する必要があります</li>
            </ul>
        </div>
        
        <!-- 編集フォーム -->
        <form method="post" action="device_edit.php?device=<?= htmlspecialchars($device) ?>" onsubmit="return validateJson()">
            <textarea name="config" id="config"><?= htmlspecialchars($jsonContent) ?></textarea>
            
            <div class="buttons">
                <button type="submit" class="btn">保存</button>
                <a href="device_config.php?device=<?= htmlspecialchars($device) ?>" class="btn btn-cancel">キャンセル</a>
            </div>
        </form>
    </div>
    
    <script>
        function validateJson() {
            const jsonText = document.getElementById('config').value;
            try {
                const json = JSON.parse(jsonText);
                
                // 簡易バリデーション
                if (!json.deviceinfo || !json.deviceinfo.LacisID) {
                    alert('エラー: deviceinfo.LacisID が必要です');
                    return false;
                }
                
                if (!json.settings || !json.settings.detection || !json.settings.detection.Mode) {
                    alert('エラー: settings.detection.Mode が必要です');
                    return false;
                }
                
                const mode = json.settings.detection.Mode;
                if (mode !== 'aruco' && mode !== 'area') {
                    alert('エラー: Mode は "aruco" または "area" のいずれかである必要があります');
                    return false;
                }
                
                return true;
            } catch (e) {
                alert('JSON構文エラー: ' + e.message);
                return false;
            }
        }
    </script>
</body>
</html> 