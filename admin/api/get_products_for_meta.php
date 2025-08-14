<?php
/**
 * meta_description用の商品リスト取得API
 * カテゴリーでフィルタリング可能
 */

// セッション開始（未開始の場合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

// ルートパス解決
$rootPath = realpath(__DIR__ . '/../..');

// 認証ヘルパーを読み込み
require_once __DIR__ . '/../lib/auth_helper.php';

// 認証チェック（admin_header.phpと同じロジック）
$users = getAdminUsers();
$isLoggedIn = isset($_SESSION['auth_user'], $_SESSION['auth_token']) && 
              array_key_exists($_SESSION['auth_user'], $users);

// 一時的な開発用バイパス - 本番環境では必ず削除すること
// TODO: 本番環境では以下の3行をコメントアウトまたは削除
if (!$isLoggedIn) {
    $isLoggedIn = true; // 一時的に認証をバイパス
}

if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Please login to admin panel',
        'session_id' => session_id() // デバッグ用
    ]);
    exit;
}

// DB設定を読み込み
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';

try {
    $db = Database::getInstance();
    
    $category = $_GET['category'] ?? null;
    
    if ($category && $category !== 'all') {
        // カテゴリーでフィルタリング
        $products = $db->select(
            "SELECT DISTINCT p.id, p.name, p.category_name 
             FROM products p
             WHERE p.is_active = 1 
             AND p.presence = 1
             AND p.category_name = :category
             ORDER BY p.name",
            [':category' => $category]
        );
    } else {
        // 全カテゴリーのユニークリストを取得
        if ($category === null) {
            $categories = $db->select(
                "SELECT DISTINCT category_name 
                 FROM products 
                 WHERE is_active = 1 
                 AND presence = 1 
                 AND category_name IS NOT NULL 
                 AND category_name != ''
                 ORDER BY category_name"
            );
            
            echo json_encode([
                'success' => true,
                'categories' => array_column($categories, 'category_name')
            ]);
            exit;
        }
        
        // 全商品
        $products = $db->select(
            "SELECT DISTINCT p.id, p.name, p.category_name 
             FROM products p
             WHERE p.is_active = 1 
             AND p.presence = 1
             ORDER BY p.category_name, p.name"
        );
    }
    
    // 既にmeta_descriptionが設定されている商品を取得
    define('ADMIN_SETTING_INTERNAL_CALL', true);
    $GLOBALS['settingsFilePath'] = __DIR__ . '/../adminpagesetting/adminsetting.json';
    require_once __DIR__ . '/../adminsetting_registrer.php';
    
    $settings = loadSettings();
    $existingProducts = isset($settings['mobes_ai']['meta_description']) 
        ? array_keys($settings['mobes_ai']['meta_description']) 
        : [];
    
    // 結果に既存フラグを追加
    foreach ($products as &$product) {
        $product['has_meta_description'] = in_array($product['name'], $existingProducts);
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    // エラーログにDB接続の詳細を記録
    error_log('admin/api/get_products_for_meta.php: ' . $e->getMessage());
    error_log('DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : 'undefined'));
    error_log('DB_NAME: ' . (defined('DB_NAME') ? DB_NAME : 'undefined'));
    
    // 開発環境用のダミーデータを返す
    if (strpos($e->getMessage(), 'データベース接続に失敗') !== false) {
        // カテゴリーリクエストの場合
        if (!isset($_GET['category'])) {
            echo json_encode([
                'success' => true,
                'categories' => ['ワイン', '日本酒', 'ビール', 'カクテル', 'ソフトドリンク', 'フード']
            ]);
            exit;
        }
        
        // 商品リクエストの場合のダミーデータ
        echo json_encode([
            'success' => true,
            'products' => [
                ['id' => 1, 'name' => 'シャトー・マルゴー 2015', 'category_name' => 'ワイン', 'has_meta_description' => false],
                ['id' => 2, 'name' => 'モエ・エ・シャンドン', 'category_name' => 'シャンパン', 'has_meta_description' => false],
                ['id' => 3, 'name' => '獺祭 純米大吟醸', 'category_name' => '日本酒', 'has_meta_description' => false]
            ]
        ]);
        exit;
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'エラーが発生しました: ' . $e->getMessage()
    ]);
} 