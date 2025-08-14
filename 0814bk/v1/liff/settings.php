<?php
/**
 * LIFF設定情報を提供するAPIエンドポイント
 */

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 絶対パスを構築
$rootPath = realpath(__DIR__ . '/../../../');
$configPath = $rootPath . '/config/LIFF_config.php';

// ファイルの存在確認
$fileExists = file_exists($configPath);

// 設定ファイルの読み込み
if ($fileExists) {
    require_once $configPath;
    $loadPath = $configPath;
} else {
    // 設定ファイルが見つからない場合のエラーレスポンス
    $response = [
        'success' => false,
        'error' => 'LIFF設定ファイルが見つかりません',
        'debug' => [
            'config_path' => $configPath,
            'file_exists' => $fileExists,
            'current_dir' => __DIR__,
            'root_path' => $rootPath
        ]
    ];
    echo json_encode($response);
    exit;
}

// レスポンスデータ
$response = [
    'success' => true,
    'liffId' => LIFF_ID,
    'environment' => getLiffEnvironment(),
    'debug' => [
        'loaded_from' => $loadPath
    ]
];

// JSONとして出力
echo json_encode($response); 