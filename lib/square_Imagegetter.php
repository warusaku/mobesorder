<?php
/**
 * Square APIから画像情報を取得するクラス
 * 
 * ProductServiceから画像取得機能を分離し、独立したクラスとして実装
 */
class SquareImageGetter {
    private $squareService;
    private static $logFile = null;
    
    /**
     * コンストラクタ
     *
     * @param object $squareService SquareServiceのインスタンス
     */
    public function __construct($squareService) {
        self::initLogFile();
        self::logMessage('SquareImageGetter::__construct - 初期化開始');
        
        $this->squareService = $squareService;
        
        self::logMessage('SquareImageGetter::__construct - 初期化完了');
    }
    
    /**
     * ログファイルの初期化
     * 
     * @return void
     */
    private static function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/square_Imagegetter.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     * サイズが上限を超えた場合は古いログを削除し、一部だけ残す
     * 
     * @return void
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
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
            
            error_log("SquareImageGetter: ログローテーション実行 - 元サイズ: " . $fileSize . "バイト, 保持サイズ: " . $keepSize . "バイト");
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル（INFO/WARNING/ERROR）
     * @return void
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        self::checkLogRotation();
        
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
            error_log("SquareImageGetter: " . $logMessage);
            error_log("SquareImageGetter: ログファイルへの書き込みに失敗しました: " . self::$logFile);
            
            // ディレクトリのパーミッションをチェック
            $logDir = dirname(self::$logFile);
            if (!is_writable($logDir)) {
                error_log("SquareImageGetter: ログディレクトリに書き込み権限がありません: " . $logDir);
            }
        }
    }
    
    /**
     * Square画像IDから実際の画像URLを取得するバッチ処理
     * 複数の画像IDを一度に処理して効率化
     * 
     * @param array $imageIds 画像IDの配列
     * @return array 画像ID => 画像URLのマッピング配列
     */
    public function batchFetchImageUrls($imageIds) {
        // 重複排除と空値除去
        $imageIds = array_filter(array_unique($imageIds));
        if (empty($imageIds)) {
            return [];
        }
        
        $imageUrlMap = [];
        $errorCount = 0;
        $successCount = 0;
        
        // タイムアウト設定
        ini_set('default_socket_timeout', 5);
        
        self::logMessage("一括画像URL取得を開始: " . count($imageIds) . "件", 'INFO');
        
        try {
            $catalogApi = $this->squareService->getSquareClient()->getCatalogApi();
            
            // 一度に処理する数を制限（10件ずつなど）
            $batchSize = 10;
            $batches = array_chunk($imageIds, $batchSize);
            
            foreach ($batches as $batchIndex => $batch) {
                self::logMessage("画像バッチ#" . ($batchIndex + 1) . " 処理開始: " . count($batch) . "件", 'DEBUG');
                
                // 各画像IDを処理
                foreach ($batch as $imageId) {
                    try {
                        $response = $catalogApi->retrieveCatalogObject($imageId);
                        
                        if ($response->isSuccess()) {
                            $result = $response->getResult();
                            $catalogObject = $result->getObject();
                            
                            if ($catalogObject && $catalogObject->getType() === 'IMAGE') {
                                $imageData = $catalogObject->getImageData();
                                
                                if ($imageData && $imageData->getUrl()) {
                                    $imageUrlMap[$imageId] = $imageData->getUrl();
                                    $successCount++;
                                } else {
                                    // 画像URLが取得できない場合は空文字をセット
                                    $imageUrlMap[$imageId] = '';
                                    $errorCount++;
                                }
                            } else {
                                $imageUrlMap[$imageId] = '';
                                $errorCount++;
                            }
                        } else {
                            $imageUrlMap[$imageId] = '';
                            $errorCount++;
                        }
                    } catch (Exception $e) {
                        // エラーがあっても処理を続行
                        self::logMessage("画像ID:{$imageId} 取得エラー: " . $e->getMessage(), 'ERROR');
                        $imageUrlMap[$imageId] = '';
                        $errorCount++;
                    }
                }
                
                // バッチ間で少し待機（API制限対策）
                if (count($batches) > 1 && $batchIndex < count($batches) - 1) {
                    self::logMessage("API制限対策: 短時間待機", 'DEBUG');
                    usleep(500000); // 0.5秒待機
                }
            }
            
            self::logMessage("一括画像URL取得完了 - 成功:{$successCount}件, 失敗:{$errorCount}件", 'INFO');
            return $imageUrlMap;
        } catch (Exception $e) {
            self::logMessage("一括画像URL取得で例外発生: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * 画像URL取得用の特化型一括処理
     * 更新処理専用に最適化
     * 
     * @param array $imageIds 画像IDのマップ（product_id => image_id）
     * @return array 画像ID => 画像URLのマッピング配列
     */
    public function batchFetchImageUrlsForUpdate($imageIds) {
        // 値のみを抽出し、重複排除と空値除去
        $uniqueImageIds = array_filter(array_unique(array_values($imageIds)));
        if (empty($uniqueImageIds)) {
            return [];
        }
        
        $imageUrlMap = [];
        $errorCount = 0;
        $successCount = 0;
        
        // タイムアウト設定を増やす
        ini_set('default_socket_timeout', 30); // 30秒に延長
        
        self::logMessage("画像URL一括取得を開始: " . count($uniqueImageIds) . "件の画像ID", 'INFO');
        self::logMessage("画像ID一覧: " . implode(', ', array_slice($uniqueImageIds, 0, 5)) . (count($uniqueImageIds) > 5 ? "... (他 " . (count($uniqueImageIds) - 5) . "件)" : ""), 'DEBUG');
        
        try {
            // Square APIクライアントの取得試行
            try {
                $catalogApi = $this->squareService->getSquareClient()->getCatalogApi();
                self::logMessage("Square CatalogAPIクライアント取得成功", 'INFO');
            } catch (Exception $e) {
                self::logMessage("Square CatalogAPIクライアント取得失敗: " . $e->getMessage(), 'ERROR');
                // クライアント取得失敗時は空のマップを返す
                return [];
            }
            
            // 一度に処理する数を大幅に制限（2件ずつ）
            $smallBatchSize = 2; // 10から2に減らす
            $smallBatches = array_chunk($uniqueImageIds, $smallBatchSize);
            
            foreach ($smallBatches as $smallBatchIndex => $smallBatch) {
                self::logMessage("小バッチ#" . ($smallBatchIndex + 1) . " 処理開始: " . count($smallBatch) . "件 [" . implode(', ', $smallBatch) . "]", 'INFO');
                
                // 各画像IDを処理
                foreach ($smallBatch as $imageId) {
                    try {
                        self::logMessage("画像ID: {$imageId} の取得を開始", 'DEBUG');
                        
                        // APIリクエスト実行前のログ
                        self::logMessage("Square API呼び出し前: retrieveCatalogObject({$imageId})", 'DEBUG');
                        
                        // API呼び出しを試行
                        $response = null;
                        try {
                            $response = $catalogApi->retrieveCatalogObject($imageId);
                            self::logMessage("Square API呼び出し成功: retrieveCatalogObject({$imageId})", 'DEBUG');
                        } catch (Exception $apiError) {
                            self::logMessage("Square API呼び出しエラー: " . $apiError->getMessage(), 'ERROR');
                            $errorCount++;
                            continue; // 次の画像IDに進む
                        }
                        
                        // レスポンスのチェック
                        if ($response && $response->isSuccess()) {
                            $result = $response->getResult();
                            $catalogObject = $result->getObject();
                            
                            if ($catalogObject && $catalogObject->getType() === 'IMAGE') {
                                $imageData = $catalogObject->getImageData();
                                
                                if ($imageData && $imageData->getUrl()) {
                                    $imageUrl = $imageData->getUrl();
                                    $imageUrlMap[$imageId] = $imageUrl;
                                    $successCount++;
                                    self::logMessage("画像URL取得成功: {$imageId} -> {$imageUrl}", 'INFO');
                                } else {
                                    self::logMessage("画像URLなし: {$imageId} (ImageDataまたはURLが空)", 'WARNING');
                                    $imageUrlMap[$imageId] = '';
                                    $errorCount++;
                                }
                            } else {
                                self::logMessage("画像オブジェクトではない: {$imageId} (Type: " . ($catalogObject ? $catalogObject->getType() : 'null') . ")", 'WARNING');
                                $imageUrlMap[$imageId] = '';
                                $errorCount++;
                            }
                        } else {
                            $errorMessage = $response ? "HTTP Status: " . ($response->getStatusCode() ?? 'unknown') : "レスポンスがnull";
                            self::logMessage("API応答エラー: {$imageId} - {$errorMessage}", 'WARNING');
                            $imageUrlMap[$imageId] = '';
                            $errorCount++;
                        }
                    } catch (Exception $e) {
                        // エラーがあっても処理を続行
                        self::logMessage("画像ID取得例外: {$imageId} - " . $e->getMessage(), 'ERROR');
                        self::logMessage("例外スタックトレース: " . $e->getTraceAsString(), 'ERROR');
                        $imageUrlMap[$imageId] = '';
                        $errorCount++;
                    }
                    
                    // 連続呼び出しによるAPI制限回避のための待機時間を増やす
                    self::logMessage("API制限対策: 1秒間待機", 'DEBUG');
                    sleep(1); // 0.2秒から1秒に変更
                }
                
                // 小バッチ間で待機時間を延長
                if (count($smallBatches) > 1 && $smallBatchIndex < count($smallBatches) - 1) {
                    self::logMessage("バッチ間待機: 3秒", 'INFO');
                    sleep(3); // 1秒から3秒に延長
                }
            }
            
            self::logMessage("一括画像URL取得完了 - 成功:{$successCount}件, 失敗:{$errorCount}件", 'INFO');
            return $imageUrlMap;
        } catch (Exception $e) {
            self::logMessage("一括画像URL取得で例外発生: " . $e->getMessage(), 'ERROR');
            self::logMessage("例外スタックトレース: " . $e->getTraceAsString(), 'ERROR');
            return $imageUrlMap; // 部分的に取得できた結果を返す
        }
    }
    
    /**
     * フロントエンドから呼び出される画像URL取得処理
     * 表示時に必要な画像のURLのみを取得することで負荷を最小化
     * 
     * @param string $imageId 画像ID
     * @param PDO $db データベース接続オブジェクト（オプション）
     * @return string 画像URL
     */
    public function getImageUrlById($imageId, $db = null) {
        // 既にURLの場合はそのまま返す
        if (empty($imageId) || strpos($imageId, 'http') === 0) {
            return $imageId;
        }
        
        // タイムアウト設定を短く
        ini_set('default_socket_timeout', 5);
        
        try {
            $catalogApi = $this->squareService->getSquareClient()->getCatalogApi();
            $response = $catalogApi->retrieveCatalogObject($imageId);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $catalogObject = $result->getObject();
                
                if ($catalogObject && $catalogObject->getType() === 'IMAGE') {
                    $imageData = $catalogObject->getImageData();
                    
                    if ($imageData && $imageData->getUrl()) {
                        $imageUrl = $imageData->getUrl();
                        
                        // DBも更新しておく（DBが指定されている場合）
                        if ($db !== null) {
                            try {
                                $stmt = $db->prepare("UPDATE products SET image_url = ? WHERE image_url = ?");
                                $stmt->execute([$imageUrl, $imageId]);
                            } catch (Exception $dbError) {
                                self::logMessage("DB更新エラー: " . $dbError->getMessage(), 'ERROR');
                            }
                        }
                        
                        return $imageUrl;
                    }
                }
            }
        } catch (Exception $e) {
            self::logMessage("フロントエンド用画像URL取得エラー: " . $e->getMessage(), 'ERROR');
        }
        
        return '';
    }
    
    /**
     * Square商品IDから画像URLを取得する
     * 商品IDを使って画像情報を取得し、実際の画像URLを返す
     * 最適化された実装で高速に画像URLを取得する
     * 
     * @param string $squareItemId Square商品ID
     * @return string 画像URL (取得できない場合は空文字)
     */
    public function processImageUrl($squareItemId) {
        // パラメータチェック
        if (empty($squareItemId)) {
            self::logMessage("画像URL取得: 商品IDが空です", 'WARNING');
            return '';
        }
        
        self::logMessage("商品ID {$squareItemId} の画像URL取得を開始", 'DEBUG');
        
        try {
            // タイムアウト設定を最適化
            ini_set('default_socket_timeout', 10);
            
            // Square APIクライアント取得を明示的に例外処理
            try {
                $catalogApi = $this->squareService->getSquareClient()->getCatalogApi();
                self::logMessage("CatalogAPI取得成功", 'DEBUG');
            } catch (\Throwable $e) {
                self::logMessage("Square APIクライアント取得エラー: " . $e->getMessage(), 'ERROR');
                return '';
            }
            
            // 方法1: 関連オブジェクト含めて1回のリクエストで取得（最も効率的）
            try {
                self::logMessage("Square API呼び出し開始: retrieveCatalogObject({$squareItemId})", 'DEBUG');
                $itemResponse = $catalogApi->retrieveCatalogObject($squareItemId, true); // include_related_objects=true
                self::logMessage("Square API呼び出し成功", 'DEBUG');
            } catch (\Throwable $e) {
                self::logMessage("Square API呼び出しエラー: " . $e->getMessage(), 'ERROR');
                return '';
            }
            
            if (!$itemResponse->isSuccess()) {
                self::logMessage("商品取得失敗: {$squareItemId} - HTTP Status: " . $itemResponse->getStatusCode(), 'WARNING');
                return '';
            }
            
            // オブジェクト取得とNULLチェック（安全なアクセス）
            $object = $itemResponse->getResult()->getObject();
            if (!$object) {
                self::logMessage("商品オブジェクトがnull: {$squareItemId}", 'WARNING');
                return '';
            }
            
            $itemData = $object->getItemData();
            if (!$itemData) {
                self::logMessage("商品データがnull: {$squareItemId}", 'WARNING');
                return '';
            }
            
            // Square APIの仕様変更に対応するため、複数の方法で画像IDを取得
            $imageIds = [];
            
            // 方法1: getImageIds()メソッドを試す
            try {
                $ids = $itemData->getImageIds();
                if ($ids && is_array($ids) && count($ids) > 0) {
                    $imageIds = $ids;
                    self::logMessage("getImageIds()メソッドから画像ID取得: " . implode(', ', $imageIds), 'DEBUG');
                }
            } catch (\Throwable $e) {
                self::logMessage("getImageIds()メソッドからの取得失敗: " . $e->getMessage(), 'DEBUG');
            }
            
            // 方法2: ecomImageIds属性を試す（新しいAPIバージョンの場合）
            if (empty($imageIds) && method_exists($itemData, 'getEcomImageIds')) {
                try {
                    $ids = $itemData->getEcomImageIds();
                    if ($ids && is_array($ids) && count($ids) > 0) {
                        $imageIds = $ids;
                        self::logMessage("getEcomImageIds()メソッドから画像ID取得: " . implode(', ', $imageIds), 'DEBUG');
                    }
                } catch (\Throwable $e) {
                    self::logMessage("getEcomImageIds()メソッドからの取得失敗: " . $e->getMessage(), 'DEBUG');
                }
            }
            
            // 方法3: カスタム属性から取得（旧バージョンの場合）
            if (empty($imageIds) && method_exists($itemData, 'getCustomAttributeValues')) {
                try {
                    $customAttributes = $itemData->getCustomAttributeValues();
                    if ($customAttributes) {
                        foreach (['image_id', 'main_image_id', 'imageId'] as $possibleKey) {
                            if (isset($customAttributes[$possibleKey]) && 
                                method_exists($customAttributes[$possibleKey], 'getStringValue')) {
                                $imageId = $customAttributes[$possibleKey]->getStringValue();
                                if (!empty($imageId)) {
                                    $imageIds[] = $imageId;
                                    self::logMessage("カスタム属性 '{$possibleKey}' から画像ID取得: {$imageId}", 'DEBUG');
                                    break;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    self::logMessage("カスタム属性からの取得失敗: " . $e->getMessage(), 'DEBUG');
                }
            }
            
            // 方法4: 直接商品オブジェクトからImageData取得を試行
            if (empty($imageIds) && method_exists($object, 'getImageData')) {
                try {
                    $imageData = $object->getImageData();
                    if ($imageData && method_exists($imageData, 'getId')) {
                        $imageId = $imageData->getId();
                        if (!empty($imageId)) {
                            $imageIds[] = $imageId;
                            self::logMessage("オブジェクトのImageDataから画像ID取得: {$imageId}", 'DEBUG');
                        }
                    }
                } catch (\Throwable $e) {
                    self::logMessage("ImageDataからの取得失敗: " . $e->getMessage(), 'DEBUG');
                }
            }
            
            // 画像IDが取得できなかった場合
            if (empty($imageIds)) {
                self::logMessage("商品に画像IDがありません: {$squareItemId}", 'WARNING');
                
                // Square APIで利用可能なすべての画像を取得し、名前が一致するものを探す試み
                try {
                    // Square\Models クラスをインポート
                    $searchRequest = new \Square\Models\SearchCatalogObjectsRequest();
                    
                    // クエリオブジェクトの構築
                    $queryObj = new \Square\Models\CatalogQuery();
                    $objectQuery = new \Square\Models\CatalogQueryText();
                    $objectQuery->setKeywords([$itemData->getName()]);  // 商品名をキーワードとして使用
                    
                    // クエリの設定
                    $queryObj->setTextQuery($objectQuery);
                    $searchRequest->setQuery($queryObj);
                    $searchRequest->setLimit(10);
                    $searchRequest->setObjectTypes(['IMAGE']);
                    
                    // 検索を実行
                    $allImagesResponse = $catalogApi->searchCatalogObjects($searchRequest);
                    
                    if ($allImagesResponse->isSuccess()) {
                        $allImages = $allImagesResponse->getResult()->getObjects();
                        $itemName = $itemData->getName();
                        $itemNameLower = strtolower($itemName);
                        
                        foreach ($allImages as $image) {
                            if ($image->getType() === 'IMAGE') {
                                $imageData = $image->getImageData();
                                $imageName = $imageData->getName() ?? '';
                                $imageNameLower = strtolower($imageName);
                                
                                // 画像名と商品名が一致する場合
                                if (strpos($imageNameLower, $itemNameLower) !== false ||
                                    strpos($itemNameLower, $imageNameLower) !== false) {
                                    $imageIds[] = $image->getId();
                                    self::logMessage("名前一致による画像ID取得: {$image->getId()} (商品名:{$itemName}, 画像名:{$imageName})", 'DEBUG');
                                    break;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    self::logMessage("すべての画像検索中にエラー: " . $e->getMessage(), 'DEBUG');
                }
                
                // それでも見つからない場合
                if (empty($imageIds)) {
                    return '';
                }
            }
            
            $firstImageId = $imageIds[0];
            if (empty($firstImageId)) {
                self::logMessage("最初の画像IDが無効です: {$squareItemId}", 'WARNING');
                return '';
            }
            
            self::logMessage("商品ID {$squareItemId} の画像ID: {$firstImageId}", 'DEBUG');
            
            // 関連オブジェクト取得
            $relatedObjects = null;
            try {
                $relatedObjects = $itemResponse->getResult()->getRelatedObjects();
            } catch (\Throwable $e) {
                self::logMessage("関連オブジェクト取得エラー: " . $e->getMessage(), 'WARNING');
                // 関連オブジェクト取得失敗は致命的ではないので続行
            }
            
            // 関連オブジェクトから画像URLを検索
            if ($relatedObjects) {
                try {
                    foreach ($relatedObjects as $relObj) {
                        if ($relObj->getType() === 'IMAGE' && $relObj->getId() === $firstImageId) {
                            $imageData = $relObj->getImageData();
                            if ($imageData && method_exists($imageData, 'getUrl') && $imageData->getUrl()) {
                                $imageUrl = $imageData->getUrl();
                                self::logMessage("関連オブジェクトから画像URL取得成功: {$imageUrl}", 'DEBUG');
                                return $imageUrl;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    self::logMessage("関連オブジェクトからの画像URL抽出エラー: " . $e->getMessage(), 'WARNING');
                    // エラーでも次の方法で再試行
                }
            }
            
            // 方法2: 関連オブジェクトで見つからない場合は直接取得を試行
            self::logMessage("関連オブジェクトに画像がないため、直接取得を試行: {$firstImageId}", 'DEBUG');
            
            try {
                $imageObject = $this->squareService->getImageById($firstImageId);
                if (!$imageObject) {
                    self::logMessage("画像オブジェクト取得失敗: {$firstImageId}", 'WARNING');
                    return '';
                }
                
                if ($imageObject->getType() !== 'IMAGE') {
                    self::logMessage("取得したオブジェクトが画像ではありません: " . $imageObject->getType(), 'WARNING');
                    return '';
                }
                
                $imageData = $imageObject->getImageData();
                if (!$imageData) {
                    self::logMessage("画像データがnull: {$firstImageId}", 'WARNING');
                    return '';
                }
                
                if (!method_exists($imageData, 'getUrl')) {
                    self::logMessage("getUrlメソッドが存在しません", 'WARNING');
                    return '';
                }
                
                $imageUrl = $imageData->getUrl();
                if (empty($imageUrl)) {
                    self::logMessage("画像URLが空: {$firstImageId}", 'WARNING');
                    return '';
                }
                
                self::logMessage("直接取得で画像URL取得成功: {$imageUrl}", 'DEBUG');
                return $imageUrl;
            } catch (\Throwable $e) {
                self::logMessage("直接画像取得中にエラー: " . $e->getMessage(), 'ERROR');
                return '';
            }
            
        } catch (\Throwable $e) {
            // すべての例外を捕捉
            self::logMessage("画像URL取得で予期せぬエラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            return '';
        }
        
        // 念のためここにも到達できるようにしておく
        self::logMessage("画像URL取得失敗: 商品ID {$squareItemId} - 原因不明", 'WARNING');
        return '';
    }
    
    /**
     * 複数商品の画像URLを一括取得
     * Square APIの呼び出し回数を最小限に抑えるために一括で処理
     *
     * @param array $squareItemIds Square商品IDの配列
     * @return array 商品ID => 画像URLのマッピング配列
     */
    public function batchProcessImageUrls($squareItemIds) {
        // 重複排除と空値除去
        $squareItemIds = array_filter(array_unique($squareItemIds));
        if (empty($squareItemIds)) {
            return [];
        }
        
        $results = [];
        $batchSize = 5; // 一度に処理する商品数
        $batches = array_chunk($squareItemIds, $batchSize);
        
        self::logMessage("一括画像URL取得開始: " . count($squareItemIds) . "商品", 'INFO');
        
        foreach ($batches as $index => $batch) {
            self::logMessage("バッチ #" . ($index + 1) . " 処理開始", 'DEBUG');
            
            foreach ($batch as $squareItemId) {
                $results[$squareItemId] = $this->processImageUrl($squareItemId);
            }
            
            // バッチ間の短い待機（APIレート制限対策）
            if (count($batches) > 1 && $index < count($batches) - 1) {
                usleep(200000); // 0.2秒待機
            }
        }
        
        $successCount = count(array_filter($results));
        self::logMessage("一括画像URL取得完了: 成功 " . $successCount . "/" . count($squareItemIds) . "件", 'INFO');
        
        return $results;
    }
} 