<?php
// エラー設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ヘッダー設定
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>カテゴリAPI テスト</h1>";

try {
    // 設定ファイルを読み込み
    require_once __DIR__ . '/api/config/config.php';
    
    echo "<p>設定ファイル読み込み完了</p>";
    
    // 必要なライブラリを読み込み
    require_once __DIR__ . '/api/lib/Database.php';
    require_once __DIR__ . '/api/lib/Utils.php';
    require_once __DIR__ . '/api/lib/ProductService.php';
    
    echo "<p>ライブラリ読み込み完了</p>";
    
    // データベース接続テスト
    try {
        $db = Database::getInstance();
        echo "<p style='color:green'>データベース接続成功</p>";
        
        // データベース情報を表示
        $tables = $db->select("SHOW TABLES");
        echo "<p>テーブル一覧:</p>";
        echo "<pre>";
        print_r($tables);
        echo "</pre>";
        
        // products テーブルの構造を表示
        $columns = $db->select("DESCRIBE products");
        echo "<p>products テーブル構造:</p>";
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        
        // サンプルデータを表示
        $products = $db->select("SELECT * FROM products LIMIT 5");
        echo "<p>products サンプルデータ:</p>";
        echo "<pre>";
        print_r($products);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<p style='color:red'>データベース接続エラー: " . $e->getMessage() . "</p>";
    }
    
    // ProductServiceからのカテゴリ取得を試みる
    try {
        echo "<p>ProductService->getCategories() 実行中...</p>";
        $productService = new ProductService();
        $categories = $productService->getCategories();
        
        echo "<p style='color:green'>カテゴリ取得成功</p>";
        echo "<pre>";
        print_r($categories);
        echo "</pre>";
    } catch (Exception $e) {
        echo "<p style='color:red'>カテゴリ取得エラー: " . $e->getMessage() . "</p>";
        echo "<p>エラー詳細:</p>";
        echo "<pre>";
        print_r($e);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>致命的なエラー: " . $e->getMessage() . "</p>";
} 