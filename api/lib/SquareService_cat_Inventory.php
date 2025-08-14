<?php
/**
 * Square API連携サービス 在庫管理クラス
 * Version: 1.0.0
 * Description: 商品の在庫情報取得と管理を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';

use Square\Exceptions\ApiException;

class SquareService_cat_Inventory extends SquareService_Base {
    
    /**
     * 商品の在庫数を取得
     * 
     * @param array $catalogItemIds 商品IDの配列
     * @return array 在庫情報の配列
     */
    public function getInventoryCounts($catalogItemIds) {
        $this->logger->logMessage("getInventoryCounts 開始: " . (is_array($catalogItemIds) ? count($catalogItemIds) : 0) . "件の商品ID", 'INFO');
        
        if (empty($catalogItemIds)) {
            $this->logger->logMessage("No catalog item IDs provided for inventory count", 'WARNING');
            Utils::log("No catalog item IDs provided for inventory count", 'WARNING', 'SquareService');
            return [];
        }
        
        try {
            $this->logger->logMessage("在庫情報を取得中: " . count($catalogItemIds) . "件の商品", 'INFO');
            Utils::log("在庫情報を取得中: " . count($catalogItemIds) . "件の商品", 'DEBUG', 'SquareService');
            
            $inventoryApi = $this->client->getInventoryApi();
            
            // Square SDKの新しいバージョンに対応
            $request = new \Square\Models\BatchRetrieveInventoryCountsRequest();
            $request->setCatalogObjectIds($catalogItemIds);
            
            // APIリクエスト実行
            $response = $inventoryApi->batchRetrieveInventoryCounts($request);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $counts = $result->getCounts() ?? [];
                
                $this->logger->logMessage("在庫情報を取得完了: " . count($counts) . "件", 'INFO');
                Utils::log("在庫情報を取得完了: " . count($counts) . "件", 'DEBUG', 'SquareService');
                
                return $counts;
            } else {
                $errors = $response->getErrors();
                $errorDetail = json_encode($errors);
                $this->logger->logMessage("Square API Error when retrieving inventory: " . $errorDetail, 'ERROR');
                Utils::log("Square API Error when retrieving inventory: " . $errorDetail, 'ERROR', 'SquareService');
                
                // エラーコードに応じた処理
                foreach ($errors as $error) {
                    // カタログにアクセスできないエラー
                    if ($error->getCategory() === 'AUTHENTICATION_ERROR' || 
                        $error->getCategory() === 'AUTHORIZATION_ERROR') {
                        $this->logger->logMessage("Square API認証エラー: " . $error->getDetail(), 'ERROR');
                        Utils::log("Square API authentication error: " . $error->getDetail(), 'ERROR', 'SquareService');
                    }
                }
                return [];
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception in getInventoryCounts: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception in getInventoryCounts: " . $e->getMessage(), 'ERROR', 'SquareService');
            return [];
        }
    }
    
    /**
     * 商品の在庫情報を取得（互換性のために維持）
     * 
     * @param array $catalogItemIds 商品IDの配列
     * @return array 在庫情報の配列
     */
    public function getInventory($catalogItemIds) {
        $this->logger->logMessage("getInventory 開始: " . (is_array($catalogItemIds) ? count($catalogItemIds) : 0) . "件の商品ID", 'INFO');
        
        // 在庫数を取得
        $counts = $this->getInventoryCounts($catalogItemIds);
        
        // 整形された在庫情報
        $inventory = [];
        
        // 在庫情報を整形
        foreach ($counts as $count) {
            $catalogObjectId = $count->getCatalogObjectId();
            
            // このアイテムを保存
            $inventory[$catalogObjectId] = [
                'catalog_object_id' => $catalogObjectId,
                'quantity' => $count->getQuantity(),
                'state' => $count->getState(),
                'updated_at' => $count->getCalculatedAt(),
                'location_id' => $count->getLocationId()
            ];
        }
        
        $this->logger->logMessage("在庫情報整形完了: " . count($inventory) . "件", 'INFO');
        return $inventory;
    }
} 