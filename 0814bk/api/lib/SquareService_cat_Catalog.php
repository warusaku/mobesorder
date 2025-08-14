<?php
/**
 * Square API連携サービス カタログ管理クラス
 * Version: 1.0.0
 * Description: 商品カタログの取得と管理を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';

use Square\Exceptions\ApiException;

class SquareService_cat_Catalog extends SquareService_Base {
    
    /**
     * 商品カタログを取得
     * 
     * @param bool $returnRawObjects trueの場合、Square APIから返されたオブジェクトをそのまま返す
     * @param int $maxResults 取得する最大商品数（最大200）
     * @return array 商品情報の配列
     */
    public function getItems($returnRawObjects = false, $maxResults = 200) {
        $this->logger->logMessage("getItems 開始: returnRawObjects=" . ($returnRawObjects ? "true" : "false") . ", maxResults={$maxResults}", 'INFO');
        
        try {
            $catalogApi = $this->client->getCatalogApi();
            $allObjects = [];
            $cursor = null;
            $pageCount = 0;
            $maxPages = ceil(min(200, $maxResults) / 100); // 最大2ページ（200件）まで取得

            do {
                $this->logger->logMessage("Square API 商品取得: ページ " . ($pageCount + 1) . " カーソル: " . ($cursor ?: "初回"), 'INFO');
                Utils::log("Square API 商品取得: ページ " . ($pageCount + 1) . " カーソル: " . ($cursor ?: "初回"), 'DEBUG', 'SquareService');
                $response = $catalogApi->listCatalog($cursor, "ITEM");
            
                if ($response->isSuccess()) {
                    $result = $response->getResult();
                    $objects = $result->getObjects() ?? [];
                    $allObjects = array_merge($allObjects, $objects);
                    
                    // 次のページを取得するためのカーソルを設定
                    $cursor = $result->getCursor();
                    $pageCount++;
                    
                    $this->logger->logMessage("Square API 商品取得: ページ " . $pageCount . " で " . count($objects) . "件取得", 'INFO');
                    Utils::log("Square API 商品取得: ページ " . $pageCount . " で " . count($objects) . "件取得", 'DEBUG', 'SquareService');
                    
                    // 十分な数のアイテムを取得したか、または次のページがない場合は終了
                    if ($pageCount >= $maxPages || empty($cursor) || count($allObjects) >= $maxResults) {
                        break;
                    }
                } else {
                    $errors = $response->getErrors();
                    $this->logger->logMessage("Square API Error: " . json_encode($errors), 'ERROR');
                    Utils::log("Square API Error: " . json_encode($errors), 'ERROR', 'SquareService');
                    return [];
                }
            } while (true);
            
            // 最大件数に制限
            if (count($allObjects) > $maxResults) {
                $allObjects = array_slice($allObjects, 0, $maxResults);
            }
            
            // オブジェクトをそのまま返す場合
            if ($returnRawObjects) {
                $this->logger->logMessage("Square APIから生のオブジェクト形式で商品を取得: " . count($allObjects) . "件", 'INFO');
                Utils::log("Square APIから生のオブジェクト形式で商品を取得: " . count($allObjects) . "件", 'DEBUG', 'SquareService');
                return $allObjects;
            }
            
            // 連想配列に変換して返す場合
            $items = $this->convertObjectsToArray($allObjects);
            
            $this->logger->logMessage("Square APIから配列形式で商品を取得: " . count($items) . "件", 'INFO');
            Utils::log("Square APIから配列形式で商品を取得: " . count($items) . "件", 'DEBUG', 'SquareService');
            return $items;
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return [];
        }
    }
    
    /**
     * Squareオブジェクトを配列に変換
     * 
     * @param array $objects Squareカタログオブジェクトの配列
     * @return array 変換された商品情報の配列
     */
    private function convertObjectsToArray($objects) {
        $items = [];
        
        foreach ($objects as $object) {
            if ($object->getType() !== 'ITEM') {
                continue;
            }
            
            $itemData = $object->getItemData();
            if (!$itemData) {
                continue;
            }
            
            // 価格情報を取得（最初のバリエーションを使用）
            $price = 0;
            $variations = $itemData->getVariations();
            if ($variations && count($variations) > 0) {
                $variation = $variations[0];
                $variationData = $variation->getItemVariationData();
                if ($variationData && $variationData->getPriceMoney()) {
                    // 日本円はそのままの金額を使用
                    $price = $variationData->getPriceMoney()->getAmount();
                }
            }
            
            // 画像ID取得
            $imageIds = $this->extractImageIds($object, $itemData);
            
            // 連想配列に変換
            $items[] = [
                'id' => $object->getId(),
                'name' => $itemData->getName(),
                'description' => $itemData->getDescription() ?? '',
                'price' => $price,
                'variations' => count($variations ?? []),
                'category_id' => $itemData->getCategoryId() ?? '',
                'image_ids' => $imageIds
            ];
        }
        
        return $items;
    }
    
    /**
     * 画像IDを抽出
     * 
     * @param object $object カタログオブジェクト
     * @param object $itemData アイテムデータ
     * @return array 画像IDの配列
     */
    private function extractImageIds($object, $itemData) {
        $imageIds = [];
        
        // 新しい方法1: imageDataプロパティからの取得を試みる
        if (method_exists($object, 'getImageData') && $object->getImageData()) {
            $imageId = $object->getImageData()->getId();
            if ($imageId) {
                $imageIds[] = $imageId;
            }
        }
        // 新しい方法2: itemDataからの取得を試みる
        else if ($itemData && method_exists($itemData, 'getImageIds')) {
            $imageIds = $itemData->getImageIds() ?? [];
        }
        // 新しい方法3: カスタム属性から取得を試みる
        else if ($itemData && method_exists($itemData, 'getCustomAttributeValues')) {
            $customAttributes = $itemData->getCustomAttributeValues();
            if ($customAttributes && isset($customAttributes['image_id'])) {
                $imageIds[] = $customAttributes['image_id']->getStringValue();
            }
        }
        
        // デバッグ用に記録
        if (empty($imageIds)) {
            $this->logger->logMessage("No image IDs found for item: " . $itemData->getName(), 'INFO');
            Utils::log("No image IDs found for item: " . $itemData->getName(), 'DEBUG', 'SquareService');
        }
        
        return $imageIds;
    }
    
    /**
     * 画像IDから画像オブジェクトを取得
     * 
     * @param string $imageId 画像ID
     * @return object|null 画像オブジェクト、または取得失敗時はnull
     */
    public function getImageById($imageId) {
        try {
            $catalogApi = $this->client->getCatalogApi();
            $response = $catalogApi->retrieveCatalogObject($imageId);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                return $result->getObject();
            } else {
                $errors = $response->getErrors();
                $this->logger->logMessage("Square API Error: " . json_encode($errors), 'ERROR');
                Utils::log("Square API Error: " . json_encode($errors), 'ERROR', 'SquareService');
                return null;
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return null;
        }
    }
} 