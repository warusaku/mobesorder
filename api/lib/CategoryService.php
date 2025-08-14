<?php

/**
 * カテゴリ管理サービスクラス
 */
class CategoryService {
    private $db;
    private $squareService;
    private static $logFile = null;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->initLogFile();
        $this->logMessage('CategoryService::__construct - 初期化開始');
        
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/SquareService.php';
        
        $this->db = Database::getInstance();
        $this->squareService = new SquareService();
        
        $this->logMessage('CategoryService::__construct - 初期化完了');
    }
    
    /**
     * ログファイルの初期化
     * 
     * @return void
     */
    private function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/CategoryService.log';
        
        // ログローテーションのチェック
        $this->checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     * ファイルサイズベースのローテーション
     * 
     * @return void
     */
    private function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
        // ファイルサイズベースのローテーション
        $fileSize = filesize(self::$logFile);
        $maxSize = 300 * 1024; // 300KB
        
        if ($fileSize > $maxSize) {
            // ファイルサイズが上限を超えた場合
            $logContent = file_get_contents(self::$logFile);
            
            // 約20%を保持（最後の部分）
            $keepSize = intval($maxSize * 0.2);
            $newContent = substr($logContent, -$keepSize);
            
            // 新しい内容を書き込み
            file_put_contents(self::$logFile, "--- ログローテーション実行 " . date('Y-m-d H:i:s') . " ---\n" . $newContent);
            
            error_log("CategoryService: ログローテーション実行 - 元サイズ: " . $fileSize . "バイト, 保持サイズ: " . $keepSize . "バイト");
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル（INFO/WARNING/ERROR）
     * @return void
     */
    private function logMessage($message, $level = 'INFO') {
        $this->initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
        $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        
        // ログファイルへの書き込みを試みる
        $result = @file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
        
        // ファイル書き込みに失敗した場合はPHPのエラーログに記録
        if ($result === false && function_exists('error_log')) {
            error_log("CategoryService: " . $logMessage);
            error_log("CategoryService: ログファイルへの書き込みに失敗しました: " . self::$logFile);
        }
    }
    
    /**
     * カテゴリ一覧を取得
     * 
     * @param bool $includeEmpty 商品が存在しないカテゴリも含める場合はtrue
     * @return array カテゴリ情報の配列
     */
    public function getCategories($includeEmpty = false) {
        try {
            $this->logMessage("getCategories - 商品なしカテゴリ含む: " . ($includeEmpty ? "true" : "false"));
            $startTime = microtime(true);
            
            // データベースからのみカテゴリ情報を取得
            $query = "SELECT category_id as id, category_name as name, display_order as sort_order, 
                      is_active, last_order_time, presence 
                      FROM category_descripter 
                      WHERE is_active = 1 AND presence = 1
                      ORDER BY display_order, category_name";
            
            $categories = $this->db->select($query);
            
            // includeEmptyがfalseの場合、商品が存在しないカテゴリを除外
            if (!$includeEmpty && !empty($categories)) {
                $validCategories = [];
                foreach ($categories as $category) {
                    $productCount = $this->db->selectOne(
                        "SELECT COUNT(*) as count FROM products 
                         WHERE category = ? AND presence = 1 AND is_active = 1",
                        [$category['id']]
                    );
                    
                    if ($productCount && $productCount['count'] > 0) {
                        $validCategories[] = $category;
                    } else {
                        $this->logMessage("商品がないためカテゴリを除外: " . $category['name'] . " (ID: " . $category['id'] . ")", 'INFO');
                    }
                }
                $categories = $validCategories;
            }
            
            // カテゴリが見つからない場合はデフォルトカテゴリを返す
            if (empty($categories)) {
                $this->logMessage("カテゴリが見つからなかったためデフォルトカテゴリを使用", 'WARNING');
                return [
                    [
                        'id' => 'default',
                        'name' => 'メニュー',
                        'sort_order' => 1,
                        'is_active' => 1,
                        'presence' => 1
                    ]
                ];
            }
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            $this->logMessage("getCategories完了 - カテゴリ数: " . count($categories) . ", 実行時間: " . $executionTime . "ms");
            
            return $categories;
            
        } catch (Exception $e) {
            $this->logMessage("カテゴリ取得エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            
            // エラー発生時はデフォルトカテゴリを返す
            return [
                [
                    'id' => 'default',
                    'name' => 'メニュー',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'presence' => 1
                ]
            ];
        }
    }
    
    /**
     * カテゴリ同期処理
     * SquareからカテゴリデータをDBに同期する
     * 
     * @return array 同期結果
     */
    public function syncCategories() {
        try {
            $this->logMessage("syncCategories - カテゴリ同期処理開始");
            $startTime = microtime(true);
            
            $stats = [
                'added' => 0,
                'updated' => 0,
                'disabled' => 0,
                'errors' => 0
            ];
            
            // Square APIからカテゴリを取得
            $categories = $this->squareService->getCategories();
            
            if (empty($categories)) {
                $this->logMessage("Squareからカテゴリデータが取得できませんでした", 'WARNING');
                return $stats;
            }
            
            // カテゴリIDのリストを作成（presence確認用）
            $squareCategoryIds = [];
            foreach ($categories as $category) {
                if (isset($category['id'])) {
                    $squareCategoryIds[] = $category['id'];
                }
            }
            
            // トランザクション開始
            $this->db->beginTransaction();
            
            try {
                // category_descripterテーブルが存在しない場合は作成
                $this->createCategoryTableIfNotExists();
                
                // カテゴリデータの更新/追加処理
                foreach ($categories as $category) {
                    try {
                        // 既存のカテゴリをチェック
                        $existingCategory = $this->db->selectOne(
                            "SELECT * FROM category_descripter WHERE category_id = ?",
                            [$category['id']]
                        );
                        
                        if ($existingCategory) {
                            // カテゴリを更新
                            $updateQuery = "UPDATE category_descripter SET 
                                category_name = ?,
                                presence = 1,
                                updated_at = NOW()
                             WHERE category_id = ?";
                             
                            $updateParams = [
                                $category['name'],
                                $category['id']
                            ];
                            
                            $updateResult = $this->db->execute(
                                $updateQuery,
                                $updateParams
                            );
                            
                            if ($updateResult) {
                                $stats['updated']++;
                            } else {
                                $stats['errors']++;
                            }
                        } else {
                            // 新規カテゴリを追加
                            $insertQuery = "INSERT INTO category_descripter (
                                category_id, category_name, display_order, is_active, presence, created_at, updated_at
                            ) VALUES (?, ?, ?, 1, 1, NOW(), NOW())";
                            
                            // 表示順は既存カテゴリの最大値+10か、初期値10
                            $maxOrder = $this->db->selectOne("SELECT MAX(display_order) as max_order FROM category_descripter");
                            $displayOrder = ($maxOrder && isset($maxOrder['max_order'])) ? $maxOrder['max_order'] + 10 : 10;
                            
                            $insertParams = [
                                $category['id'],
                                $category['name'],
                                $displayOrder
                            ];
                            
                            $insertResult = $this->db->execute(
                                $insertQuery,
                                $insertParams
                            );
                            
                            if ($insertResult) {
                                $stats['added']++;
                            } else {
                                $stats['errors']++;
                            }
                        }
                    } catch (Exception $e) {
                        $this->logMessage("カテゴリID " . $category['id'] . " の処理エラー: " . $e->getMessage(), 'ERROR');
                        $stats['errors']++;
                    }
                }
                
                // Squareに存在しなくなったカテゴリのpresenceを0に設定
                if (!empty($squareCategoryIds)) {
                    // プレースホルダを作成
                    $placeholders = implode(',', array_fill(0, count($squareCategoryIds), '?'));
                    
                    // Squareに存在しないカテゴリのpresenceを0に更新
                    $updatePresenceQuery = "UPDATE category_descripter SET 
                        presence = 0,
                        updated_at = NOW()
                        WHERE category_id NOT IN ({$placeholders}) 
                        AND presence = 1";
                    
                    $updatePresenceResult = $this->db->execute($updatePresenceQuery, $squareCategoryIds);
                    
                    // 更新された行数を取得
                    $disabledCount = $updatePresenceResult;
                    $stats['disabled'] = $disabledCount;
                    
                    $this->logMessage("Squareに存在しないカテゴリのpresenceを0に設定: {$disabledCount}件", 'INFO');
                }
                
                // トランザクションコミット
                $this->db->commit();
                
                $endTime = microtime(true);
                $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
                $this->logMessage("syncCategories完了 - 追加: {$stats['added']}, 更新: {$stats['updated']}, 無効化: {$stats['disabled']}, エラー: {$stats['errors']}, 実行時間: {$executionTime}ms", 'INFO');
                
                return $stats;
            } catch (Exception $e) {
                // トランザクションロールバック
                $this->db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            $this->logMessage("カテゴリ同期処理エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            return [
                'added' => 0,
                'updated' => 0,
                'disabled' => 0,
                'errors' => 1
            ];
        }
    }
    
    /**
     * カテゴリテーブルを作成（存在しない場合のみ）
     */
    private function createCategoryTableIfNotExists() {
        try {
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS category_descripter (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category_id VARCHAR(64) NOT NULL,
                    category_name VARCHAR(255) NOT NULL,
                    display_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    presence TINYINT(1) NOT NULL DEFAULT 1,
                    last_order_time DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_category_id (category_id)
                )
            ");
            $this->logMessage("カテゴリテーブルの存在を確認/作成しました", 'INFO');
        } catch (Exception $e) {
            $this->logMessage("カテゴリテーブル作成エラー: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * カテゴリの表示順を更新
     * 
     * @param string $categoryId カテゴリID
     * @param int $displayOrder 新しい表示順
     * @return bool 更新に成功した場合はtrue
     */
    public function updateCategoryOrder($categoryId, $displayOrder) {
        try {
            $result = $this->db->execute(
                "UPDATE category_descripter SET display_order = ? WHERE category_id = ?",
                [(int)$displayOrder, $categoryId]
            );
            
            return $result > 0;
        } catch (Exception $e) {
            $this->logMessage("カテゴリ表示順更新エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * カテゴリの活性状態を更新
     * 
     * @param string $categoryId カテゴリID
     * @param bool $isActive 活性状態
     * @return bool 更新に成功した場合はtrue
     */
    public function updateCategoryStatus($categoryId, $isActive) {
        try {
            $result = $this->db->execute(
                "UPDATE category_descripter SET is_active = ? WHERE category_id = ?",
                [$isActive ? 1 : 0, $categoryId]
            );
            
            return $result > 0;
        } catch (Exception $e) {
            $this->logMessage("カテゴリ状態更新エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * カテゴリの最終注文時間を更新
     * 
     * @param string $categoryId カテゴリID
     * @param string|null $lastOrderTime 最終注文時間（null可）
     * @return bool 更新に成功した場合はtrue
     */
    public function updateLastOrderTime($categoryId, $lastOrderTime) {
        try {
            $sql = "UPDATE category_descripter SET last_order_time = ? WHERE category_id = ?";
            $params = [$lastOrderTime, $categoryId];
            
            // nullの場合はSQL構文を調整
            if ($lastOrderTime === null) {
                $sql = "UPDATE category_descripter SET last_order_time = NULL WHERE category_id = ?";
                $params = [$categoryId];
            }
            
            $result = $this->db->execute($sql, $params);
            
            return $result > 0;
        } catch (Exception $e) {
            $this->logMessage("カテゴリ最終注文時間更新エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    // カテゴリ復元メソッドの追加
    public function restoreCategory($categoryId) {
        try {
            $this->logMessage("カテゴリ復元: " . $categoryId);
            
            $result = $this->db->execute(
                "UPDATE category_descripter SET presence = 1, updated_at = NOW() 
                 WHERE category_id = ?",
                [$categoryId]
            );
            
            return $result > 0;
        } catch (Exception $e) {
            $this->logMessage("カテゴリ復元エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
} 