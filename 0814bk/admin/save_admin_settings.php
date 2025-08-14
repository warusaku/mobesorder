<?php
// JSONファイルに管理者設定を保存する

// POSTからデータを取得
$settings = isset($_POST['settings']) ? $_POST['settings'] : '';

if (empty($settings)) {
    echo json_encode(['success' => false, 'message' => '設定データがありません']);
    exit;
}

// デコードして妥当性チェック
$decodedSettings = json_decode($settings, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => '無効なJSON形式です']);
    exit;
}

// ディレクトリ確認（存在しなければ作成）
$dir = __DIR__ . '/adminpagesetting';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'ディレクトリの作成に失敗しました']);
        exit;
    }
}

// ファイルに保存
$filePath = $dir . '/adminsetting.json';
$result = file_put_contents($filePath, json_encode($decodedSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'ファイルの保存に失敗しました']);
} else {
    echo json_encode(['success' => true, 'message' => '設定を保存しました']);
} 