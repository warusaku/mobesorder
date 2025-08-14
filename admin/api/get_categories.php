<?php
/**
 * カテゴリ一覧取得API
 * バージョン: 1.0.1
 * ファイル説明: 商品カテゴリの一覧を取得するエンドポイント
 * 返却: JSON { success, categories: [{id, name}] }
 * 更新履歴: 2025-01-31 エラーハンドリング改善
 */

// エラー出力を一時的に有効化（デバッグ用）
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // 設定ファイルとデータベース接続
    $configPath = __DIR__ . '/../../api/config/config.php';
    $dbPath = __DIR__ . '/../../api/lib/Database.php';
    $utilsPath = __DIR__ . '/../../api/lib/Utils.php';
    
    // ファイルの存在確認
    if (!file_exists($configPath)) {
        throw new Exception('Config file not found: ' . $configPath);
    }
    if (!file_exists($dbPath)) {
        throw new Exception('Database file not found: ' . $dbPath);
    }
    if (!file_exists($utilsPath)) {
        throw new Exception('Utils file not found: ' . $utilsPath);
    }
    
    require_once $configPath;
    require_once $utilsPath;  // Utilsを先に読み込む
    require_once $dbPath;
    
    $db = Database::getInstance();
    
    // category_descripterテーブルから有効なカテゴリを取得
    $sql = "SELECT id, category_name as name, display_order 
            FROM category_descripter 
            WHERE is_active = 1 
            ORDER BY display_order ASC, category_name ASC";
    
    $categories = $db->select($sql);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    
} catch (Exception $e) {
    error_log('get_categories.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 