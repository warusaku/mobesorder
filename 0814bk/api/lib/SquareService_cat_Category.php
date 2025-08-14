<?php
/**
 * Square API連携サービス カテゴリ管理クラス
 * Version: 1.0.0
 * Description: 商品カテゴリの取得と管理を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';

use Square\Exceptions\ApiException;

class SquareService_cat_Category extends SquareService_Base {
    
    /**
     * カテゴリ一覧を取得
     * 
     * @return array カテゴリ情報の配列 [['id' => 'xxx', 'name' => 'カテゴリ名'], ...]
     */
    public function getCategories() {
        try {
            $this->logger->logMessage("Square APIからカテゴリ取得開始", 'INFO');
            $catalogApi = $this->client->getCatalogApi();
            $response = $catalogApi->listCatalog(null, "CATEGORY");
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $objects = $result->getObjects() ?? [];
                $categories = [];
                
                foreach ($objects as $object) {
                    if ($object->getType() !== 'CATEGORY') {
                        continue;
                    }
                    
                    $categoryData = $object->getCategoryData();
                    if (!$categoryData) {
                        continue;
                    }
                    
                    // カテゴリIDと名前を取得（重要）
                    $categories[] = [
                        'id' => $object->getId(),
                        'name' => $categoryData->getName() ?? 'カテゴリ' . count($categories)
                    ];
                }
                
                $this->logger->logMessage("Square APIからカテゴリ取得成功: " . count($categories) . "件", 'INFO');
                return $categories;
            } else {
                $errors = $response->getErrors();
                $this->logger->logMessage("Square API Error: " . json_encode($errors), 'ERROR');
                Utils::log("Square API Error: " . json_encode($errors), 'ERROR', 'SquareService');
                return [];
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Square API Exception: " . $e->getMessage(), 'ERROR');
            Utils::log("Square API Exception: " . $e->getMessage(), 'ERROR', 'SquareService');
            return [];
        }
    }
} 