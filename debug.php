<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込み
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/lib/Database.php';
require_once __DIR__ . '/api/lib/Utils.php';
require_once __DIR__ . '/api/lib/ProductService.php';
require_once __DIR__ . '/api/lib/SquareService.php';

// 同期処理を実行
try {
    $productService = new ProductService();
    $result = $productService->syncProductsFromSquare();
    
    echo '<h1>同期結果</h1>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
} catch (Exception $e) {
    echo '<h1>エラーが発生しました</h1>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '<h2>スタックトレース</h2>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}

// データベース接続テスト
try {
    echo '<h1>データベース接続テスト</h1>';
    $db = Database::getInstance();
    echo '<p>データベース接続成功</p>';
    
    // テーブル一覧を取得
    $tables = $db->select("SHOW TABLES");
    echo '<h2>テーブル一覧</h2>';
    echo '<pre>';
    print_r($tables);
    echo '</pre>';
} catch (Exception $e) {
    echo '<h1>データベース接続エラー</h1>';
    echo '<p>' . $e->getMessage() . '</p>';
}

// Square API接続テスト
try {
    echo '<h1>Square API接続テスト</h1>';
    $squareService = new SquareService();
    $items = $squareService->getItems();
    
    echo '<p>Square API接続成功</p>';
    echo '<h2>取得された商品数: ' . count($items) . '</h2>';
} catch (Exception $e) {
    echo '<h1>Square API接続エラー</h1>';
    echo '<p>' . $e->getMessage() . '</p>';
}

// 設定情報（機密情報は隠す）
echo '<h1>設定情報</h1>';
echo '<ul>';
echo '<li>BASE_URL: ' . BASE_URL . '</li>';
echo '<li>DB_HOST: ' . DB_HOST . '</li>';
echo '<li>DB_NAME: ' . DB_NAME . '</li>';
echo '<li>SQUARE_ENVIRONMENT: ' . SQUARE_ENVIRONMENT . '</li>';
echo '</ul>';
?> 