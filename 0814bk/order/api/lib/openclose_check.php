<?php
/**
 * 営業時間チェックAPI
 * 
 * カテゴリの営業状態を確認するためのAPIエンドポイント
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 必要なファイルを読み込み
$rootPath = realpath(__DIR__ . '/../../..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';
require_once __DIR__ . '/openclose_manager.php';

// レスポンスをJSON形式で返す
header('Content-Type: application/json; charset=UTF-8');

// マネージャインスタンスを作成
$openCloseManager = new OpenCloseManager();
$db = Database::getInstance();

// リクエストの処理
$action = $_GET['action'] ?? 'check';

switch ($action) {
    case 'check':
        // 特定のカテゴリIDの営業状態を確認
        $categoryId = $_GET['category_id'] ?? null;
        
        if ($categoryId) {
            $isOpen = $openCloseManager->isCategoryOpen($categoryId, $db);
            echo json_encode([
                'success' => true,
                'is_open' => $isOpen,
                'category_id' => $categoryId
            ]);
        } else {
            // すべてのカテゴリの営業状態を確認
            $categoriesStatus = $openCloseManager->getAllCategoriesOpenStatus($db);
            echo json_encode([
                'success' => true,
                'categories_status' => $categoriesStatus
            ]);
        }
        break;
        
    case 'next_open':
        // 次の営業開始時間を取得
        $nextOpeningTime = $openCloseManager->getNextOpeningTime();
        echo json_encode([
            'success' => true,
            'next_opening_time' => $nextOpeningTime
        ]);
        break;
        
    case 'settings':
        // 営業時間設定を取得
        $settings = $openCloseManager->getDefaultOpeningHours();
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action'
        ]);
        break;
} 