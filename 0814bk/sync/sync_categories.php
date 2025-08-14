<?php
/**
 * カテゴリ情報をSquare APIから取得してcategory_descripterテーブルに同期するスクリプト
 * 
 * 実行方法:
 * 1. コマンドライン: php sync_categories.php
 * 2. ブラウザから: https://example.com/api/sync/sync_categories.php?token=<SYNC_TOKEN>
 * 
 * 注意: 本番環境で実行する場合は、SYNC_TOKENによる認証を必ず有効にしてください
 */

// 相対パスから必要なファイルをインクルード
$rootPath = realpath(__DIR__ . '/../../');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';
require_once $rootPath . '/api/lib/SquareService.php';

// セキュリティチェック
if (php_sapi_name() !== 'cli') {
    // ブラウザからの実行の場合、トークン認証を行う
    $syncToken = isset($_GET['token']) ? $_GET['token'] : '';
    
    if (!defined('SYNC_TOKEN') || empty(SYNC_TOKEN) || $syncToken !== SYNC_TOKEN) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Invalid or missing sync token']);
        exit;
    }
}

/**
 * カテゴリ同期処理を実行
 * 
 * @return array 処理結果
 */
function syncCategories() {
    $db = Database::getInstance();
    $squareService = new SquareService();
    $stats = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    
    try {
        // ログ記録開始
        Utils::log("カテゴリ同期処理を開始します", 'INFO', 'sync_categories');
        
        // データベース接続テスト
        try {
            $connectionTest = $db->selectOne("SELECT 1 AS connection_test");
            Utils::log("データベース接続テスト成功", 'DEBUG', 'sync_categories');
        } catch (Exception $dbError) {
            Utils::log("データベース接続テストエラー: " . $dbError->getMessage(), 'ERROR', 'sync_categories');
            return [
                'success' => false,
                'message' => 'データベース接続に失敗しました: ' . $dbError->getMessage(),
                'stats' => $stats
            ];
        }
        
        // テーブルが存在するか確認、なければ作成
        Utils::log("category_descripterテーブルの存在確認", 'INFO', 'sync_categories');
        createCategoryDescripterTableIfNotExists($db);
        
        // Square APIからカテゴリ情報を取得
        Utils::log("Square APIからカテゴリ情報を取得中", 'INFO', 'sync_categories');
        $squareCategories = $squareService->getCategories();
        Utils::log("取得したカテゴリ数: " . count($squareCategories), 'INFO', 'sync_categories');
        
        if (empty($squareCategories)) {
            Utils::log("Square APIからカテゴリを取得できませんでした", 'WARNING', 'sync_categories');
            return [
                'success' => false,
                'message' => 'Square APIからカテゴリを取得できませんでした',
                'stats' => $stats
            ];
        }
        
        // トランザクション開始
        $db->beginTransaction();
        Utils::log("トランザクション開始", 'DEBUG', 'sync_categories');
        
        try {
            // 既存カテゴリ情報の取得
            $existingCategories = $db->select("SELECT * FROM category_descripter");
            $existingCategoriesMap = [];
            
            foreach ($existingCategories as $category) {
                $existingCategoriesMap[$category['category_id']] = $category;
            }
            
            // Square APIから取得したカテゴリ情報を処理
            foreach ($squareCategories as $category) {
                $categoryId = $category['id'];
                $categoryName = $category['name'];
                
                if (empty($categoryId) || empty($categoryName)) {
                    Utils::log("無効なカテゴリデータをスキップ: " . json_encode($category), 'WARNING', 'sync_categories');
                    $stats['skipped']++;
                    continue;
                }
                
                // カテゴリが既に存在するか確認
                if (isset($existingCategoriesMap[$categoryId])) {
                    // 既存カテゴリを更新
                    $updateResult = $db->execute(
                        "UPDATE category_descripter 
                         SET category_name = ?, updated_at = NOW() 
                         WHERE category_id = ?",
                        [$categoryName, $categoryId]
                    );
                    
                    if ($updateResult) {
                        $stats['updated']++;
                        Utils::log("カテゴリを更新しました: {$categoryName} (ID: {$categoryId})", 'INFO', 'sync_categories');
                    } else {
                        $stats['errors']++;
                        Utils::log("カテゴリの更新に失敗しました: {$categoryName} (ID: {$categoryId})", 'ERROR', 'sync_categories');
                    }
                } else {
                    // 新規カテゴリを追加
                    // 表示順の初期値は、最大値+10または100
                    $maxOrder = $db->selectOne("SELECT MAX(display_order) as max_order FROM category_descripter");
                    $displayOrder = ($maxOrder && isset($maxOrder['max_order']) && $maxOrder['max_order'] > 0) 
                        ? $maxOrder['max_order'] + 10 
                        : 100;
                    
                    $insertResult = $db->execute(
                        "INSERT INTO category_descripter 
                         (category_id, category_name, display_order, is_active, created_at, updated_at) 
                         VALUES (?, ?, ?, 1, NOW(), NOW())",
                        [$categoryId, $categoryName, $displayOrder]
                    );
                    
                    if ($insertResult) {
                        $stats['created']++;
                        Utils::log("新しいカテゴリを追加しました: {$categoryName} (ID: {$categoryId}) 表示順: {$displayOrder}", 'INFO', 'sync_categories');
                    } else {
                        $stats['errors']++;
                        Utils::log("カテゴリの追加に失敗しました: {$categoryName} (ID: {$categoryId})", 'ERROR', 'sync_categories');
                    }
                }
            }
            
            // 同期ステータスの更新
            updateSyncStatus($db, $stats);
            
            // トランザクションをコミット
            $db->commit();
            Utils::log("トランザクションをコミットしました", 'DEBUG', 'sync_categories');
            
            return [
                'success' => true,
                'message' => 'カテゴリ同期が完了しました',
                'stats' => $stats
            ];
        } catch (Exception $e) {
            // トランザクションをロールバック
            $db->rollback();
            Utils::log("トランザクションをロールバックしました: " . $e->getMessage(), 'ERROR', 'sync_categories');
            
            $stats['errors']++;
            return [
                'success' => false,
                'message' => 'カテゴリ同期中にエラーが発生しました: ' . $e->getMessage(),
                'stats' => $stats
            ];
        }
    } catch (Exception $e) {
        Utils::log("カテゴリ同期処理中に例外が発生しました: " . $e->getMessage(), 'ERROR', 'sync_categories');
        Utils::log("スタックトレース: " . $e->getTraceAsString(), 'ERROR', 'sync_categories');
        
        $stats['errors']++;
        return [
            'success' => false,
            'message' => 'カテゴリ同期処理中に例外が発生しました: ' . $e->getMessage(),
            'stats' => $stats
        ];
    }
}

/**
 * category_descripterテーブルが存在しない場合に作成
 * 
 * @param Database $db データベース接続オブジェクト
 * @return bool 成功した場合はtrue
 */
function createCategoryDescripterTableIfNotExists($db) {
    try {
        // テーブルの存在確認
        $tableExists = $db->select("SHOW TABLES LIKE 'category_descripter'");
        
        if (empty($tableExists)) {
            Utils::log("category_descripterテーブルが存在しないため作成します", 'INFO', 'sync_categories');
            
            // テーブル作成
            $result = $db->execute("
                CREATE TABLE category_descripter (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category_id VARCHAR(255) NOT NULL COMMENT 'Square内部カテゴリID',
                    category_name VARCHAR(255) NOT NULL COMMENT '表示用カテゴリ名',
                    display_order INT DEFAULT 100 COMMENT '表示順序（値が小さいほど先頭に表示）',
                    is_active TINYINT(1) DEFAULT 1 COMMENT 'アクティブフラグ（1=表示、0=非表示）',
                    last_order_time TIME DEFAULT NULL COMMENT 'カテゴリ別ラストオーダー時間',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY (category_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='カテゴリ管理テーブル'
            ");
            
            if ($result) {
                Utils::log("category_descripterテーブルを作成しました", 'INFO', 'sync_categories');
                
                // インデックス作成
                $db->execute("CREATE INDEX idx_display_order ON category_descripter (display_order, is_active)");
                Utils::log("category_descripterテーブルにインデックスを作成しました", 'INFO', 'sync_categories');
                
                return true;
            } else {
                Utils::log("category_descripterテーブルの作成に失敗しました", 'ERROR', 'sync_categories');
                return false;
            }
        } else {
            Utils::log("category_descripterテーブルは既に存在しています", 'DEBUG', 'sync_categories');
            return true;
        }
    } catch (Exception $e) {
        Utils::log("テーブル作成中にエラーが発生しました: " . $e->getMessage(), 'ERROR', 'sync_categories');
        return false;
    }
}

/**
 * 同期ステータスを更新
 * 
 * @param Database $db データベース接続オブジェクト
 * @param array $stats 同期結果の統計情報
 * @return bool 成功した場合はtrue
 */
function updateSyncStatus($db, $stats) {
    try {
        // sync_statusテーブルが存在するか確認
        $tableExists = $db->select("SHOW TABLES LIKE 'sync_status'");
        
        if (empty($tableExists)) {
            Utils::log("sync_statusテーブルが存在しないため作成します", 'INFO', 'sync_categories');
            
            $db->execute("
                CREATE TABLE IF NOT EXISTS sync_status (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    provider VARCHAR(50) NOT NULL,
                    table_name VARCHAR(50) NOT NULL,
                    last_sync_time DATETIME NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    details TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY provider_table (provider, table_name)
                )
            ");
        }
        
        // 既存のレコードを確認
        $existingRecord = $db->selectOne(
            "SELECT id FROM sync_status WHERE provider = ? AND table_name = ?",
            ['square', 'category_descripter']
        );
        
        $details = json_encode([
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        if ($existingRecord) {
            // 既存レコードを更新
            $result = $db->execute(
                "UPDATE sync_status SET 
                    last_sync_time = NOW(), 
                    status = ?, 
                    details = ? 
                WHERE provider = ? AND table_name = ?",
                [
                    $stats['errors'] > 0 ? 'warning' : 'success',
                    $details,
                    'square',
                    'category_descripter'
                ]
            );
        } else {
            // 新規レコードを挿入
            $result = $db->execute(
                "INSERT INTO sync_status 
                 (provider, table_name, last_sync_time, status, details)
                 VALUES (?, ?, NOW(), ?, ?)",
                [
                    'square',
                    'category_descripter',
                    $stats['errors'] > 0 ? 'warning' : 'success',
                    $details
                ]
            );
        }
        
        Utils::log("同期ステータスを更新しました", 'INFO', 'sync_categories');
        return true;
    } catch (Exception $e) {
        Utils::log("同期ステータスの更新中にエラーが発生しました: " . $e->getMessage(), 'ERROR', 'sync_categories');
        return false;
    }
}

// メイン処理実行
$result = syncCategories();

// 出力形式を設定（CLIの場合はテキスト、ブラウザの場合はJSON）
if (php_sapi_name() === 'cli') {
    // コマンドライン実行の場合
    echo "カテゴリ同期処理結果: " . ($result['success'] ? "成功" : "失敗") . "\n";
    echo "メッセージ: " . $result['message'] . "\n";
    echo "統計情報:\n";
    echo "  - 作成: " . $result['stats']['created'] . "\n";
    echo "  - 更新: " . $result['stats']['updated'] . "\n";
    echo "  - スキップ: " . $result['stats']['skipped'] . "\n";
    echo "  - エラー: " . $result['stats']['errors'] . "\n";
} else {
    // ブラウザからの実行の場合
    header('Content-Type: application/json');
    echo json_encode($result);
} 