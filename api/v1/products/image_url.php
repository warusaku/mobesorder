<?php
/**
 * 画像ID→URL変換API
 * 画像IDをSquare APIを使って実際のURLに変換する
 */

require_once '../../lib/init.php';

// CORSヘッダー設定
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// デフォルトのレスポンス
$response = [
    'success' => false,
    'message' => '',
    'url' => ''
];

// リクエストパラメータ取得
$imageId = $_GET['id'] ?? '';

// 画像IDチェック
if (empty($imageId)) {
    $response['message'] = '画像IDが指定されていません';
    echo json_encode($response);
    exit;
}

// 既にURLの場合はそのまま返す
if (strpos($imageId, 'http') === 0) {
    $response['success'] = true;
    $response['url'] = $imageId;
    echo json_encode($response);
    exit;
}

try {
    $productService = new ProductService();
    
    // 画像URLを取得
    $imageUrl = $productService->getImageUrlById($imageId);
    
    if (!empty($imageUrl)) {
        $response['success'] = true;
        $response['url'] = $imageUrl;
    } else {
        $response['message'] = '画像URLを取得できませんでした';
    }
} catch (Exception $e) {
    Utils::log("画像URL取得APIエラー: " . $e->getMessage(), 'ERROR', 'ImageUrlApi');
    $response['message'] = 'エラーが発生しました: ' . $e->getMessage();
}

echo json_encode($response); 