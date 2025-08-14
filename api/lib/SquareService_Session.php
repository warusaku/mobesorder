<?php
/**
 * Square API連携サービス セッション商品管理クラス
 * Version: 1.0.0
 * Description: セッション用の一時的な商品の作成と管理を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';
require_once __DIR__ . '/SquareService_Webhook.php';

use Square\Exceptions\ApiException;
use Square\Models\CreateOrderRequest;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\Money;

class SquareService_Session extends SquareService_Base {
    
    private $webhookService;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct();
        $this->webhookService = new SquareService_Webhook();
    }
    
    /**
     * セッション用の合計金額商品を作成 / 更新
     * @param string $sessionId  order_session_id (21桁)
     * @param string $roomNumber 部屋番号
     * @param float  $totalAmount 税込合計金額
     * @param string|null $existingItemId 既に作成済みの場合はそのCatalogObjectId (ITEM_VARIATION ID)
     * @return string|null Square CatalogObjectId (item variation ID)
     */
    public function createOrUpdateSessionProduct($sessionId, $roomNumber, $totalAmount, $existingItemId = null) {
        $this->logger->logMessage("createOrUpdateSessionProduct: session={$sessionId}, total={$totalAmount}, existing=" . ($existingItemId ?? 'null'), 'INFO');
        
        try {
            // --- DB インスタンス（order_sessions 更新用） ---
            require_once __DIR__ . '/Database.php';
            $dbi = Database::getInstance();

            // 商品名を組み立て
            $itemName = sprintf('%s-%s', $roomNumber, $sessionId);
            $catalogApi = $this->client->getCatalogApi();

            // Moneyオブジェクト作成
            $money = new Money();
            $currency = 'JPY';
            $money->setCurrency($currency);
            if (in_array($currency, ['JPY', 'HUF'])) {
                $money->setAmount(intval(round($totalAmount)));
            } else {
                $money->setAmount(intval(round($totalAmount * 100)));
            }

            // 変数を初期化
            $parentItemIdOut = null;
            $variationIdOut = null;

            // ===============================
            // 1) 既存バリエーションの価格更新フェーズ
            // ===============================
            if (!empty($existingItemId)) {
                // retrieve with related objects to get parent ITEM
                $retrieveResp = $catalogApi->retrieveCatalogObject($existingItemId, true);
                if ($retrieveResp->isSuccess()) {
                    $obj = $retrieveResp->getResult()->getObject();
                    if ($obj && $obj->getType() === 'ITEM_VARIATION' && $obj->getItemVariationData()) {
                        // バリエーションの価格を更新
                        $obj->getItemVariationData()->setPriceMoney($money);
                        $obj->setUpdatedAt(date('c'));
                        $upsertReq = new \Square\Models\UpsertCatalogObjectRequest(uniqid(), $obj);
                        $upsertResp = $catalogApi->upsertCatalogObject($upsertReq);
                        if ($upsertResp->isSuccess()) {
                            // 価格検証のため再度 retrieve
                            $verifyResp = $catalogApi->retrieveCatalogObject($existingItemId, true);
                            if ($verifyResp->isSuccess()) {
                                $vObj = $verifyResp->getResult()->getObject();
                                $curMoney = $vObj && $vObj->getItemVariationData() && $vObj->getItemVariationData()->getPriceMoney()
                                    ? $vObj->getItemVariationData()->getPriceMoney()->getAmount() : null;
                                if ($curMoney === intval(round($totalAmount))) {
                                    // 親ITEM IDを relatedObjects から取得
                                    $parentItemId = null;
                                    $related = $verifyResp->getResult()->getRelatedObjects() ?? [];
                                    foreach ($related as $rel) {
                                        if ($rel->getType() === 'ITEM') {
                                            $parentItemId = $rel->getId();
                                            break;
                                        }
                                    }
                                    if (empty($parentItemId) && $vObj->getItemVariationData()) {
                                        $parentItemId = $vObj->getItemVariationData()->getItemId();
                                    }
                                    // ✅ ログ出力: 既存更新
                                    $this->logger->logMessage("✅ 既存更新: variationId={$existingItemId}, parentItemId={$parentItemId}", 'INFO');
                                    // DB更新 (二重チェック WHERE 条件は sessionId のみ)
                                    $this->logger->logMessage("▶▶▶ 項目更新 (既存): variationId={$existingItemId}, parentItemId={$parentItemId}, sessionId={$sessionId}", 'DEBUG');
                                    try {
                                        $dbi->execute(
                                            "UPDATE order_sessions
                                                SET square_variation_id = ?, square_item_id = ?
                                              WHERE id = ?",
                                            [$existingItemId, $parentItemId ?? '', $sessionId]
                                        );
                                    } catch (\Throwable $dbEx) {
                                        $this->logger->logMessage("order_sessions 更新失敗: " . $dbEx->getMessage(), 'WARNING');
                                    }
                                    return $existingItemId;
                                }
                                // 価格不整合
                                $this->logger->logMessage("価格更新検証NG (期待:" . intval(round($totalAmount)) . " 実際:" . $curMoney . ")", 'ERROR');
                                $this->webhookService->sendWebhookEvent('price_mismatch_error', [
                                    'session_id' => $sessionId,
                                    'room_number' => $roomNumber,
                                    'expected_total' => $totalAmount,
                                    'actual_total' => $curMoney,
                                    'square_item_id' => $existingItemId
                                ]);
                                // DBには既存のIDを残すため、nullを返さず既存IDを返す
                                return $existingItemId;
                            }
                        }
                    }
                }
                // retrieve 失敗や更新失敗した場合も、新規作成フェーズにフォールバックする
            }

            // ===============================
            // 2) 新規作成フェーズ
            // ===============================
            // 2-1. 任意の一意ID を生成 (先頭に '#' を付与する)
            $itemVariationId = '#' . uniqid();
            $itemId = '#' . uniqid();

            // 2-2. Variation データ作成
            $variationData = new \Square\Models\CatalogItemVariation();
            if (method_exists($variationData, 'setPricingType')) {
                $variationData->setPricingType('FIXED_PRICING');
            }
            $variationData->setPriceMoney($money);
            $variationData->setName('Regular');

            // 2-3. Variation を CatalogObject でラップ
            $variationObject = new \Square\Models\CatalogObject('ITEM_VARIATION', $itemVariationId);
            if (method_exists($variationObject, 'setItemVariationData')) {
                $variationObject->setItemVariationData($variationData);
            }
            if (method_exists($variationData, 'setItemId')) {
                $variationData->setItemId($itemId);
            }

            // 2-4. カテゴリ設定の取得と作成
            $settings = self::getSquareSettings();
            $categoryName = isset($settings['mobile_order_category']) ? trim($settings['mobile_order_category']) : '';
            $categoryIdForUse = null;
            $categoryObject = null;
            if ($categoryName !== '') {
                try {
                    $catResp = $catalogApi->listCatalog(null, 'CATEGORY');
                    if ($catResp->isSuccess()) {
                        foreach ($catResp->getResult()->getObjects() as $catObj) {
                            if ($catObj->getCategoryData()
                                && strtolower($catObj->getCategoryData()->getName()) === strtolower($categoryName)
                            ) {
                                $categoryIdForUse = $catObj->getId();
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logger->logMessage('listCatalog CATEGORY error: ' . $e->getMessage(), 'WARNING');
                }
                if (empty($categoryIdForUse)) {
                    $categoryIdForUse = '#cat_' . preg_replace('/[^A-Za-z0-9]/', '', strtolower($categoryName)) . uniqid();
                    $catData = new \Square\Models\CatalogCategory();
                    $catData->setName($categoryName);
                    $categoryObject = new \Square\Models\CatalogObject('CATEGORY', $categoryIdForUse);
                    if (method_exists($categoryObject, 'setCategoryData')) {
                        $categoryObject->setCategoryData($catData);
                    }
                    $catBody = new \Square\Models\UpsertCatalogObjectRequest(uniqid(), $categoryObject);
                    try {
                        $catUp = $catalogApi->upsertCatalogObject($catBody);
                        if ($catUp->isSuccess()) {
                            $categoryIdForUse = $catUp->getResult()->getCatalogObject()->getId();
                            $this->logger->logMessage('CATEGORY created on Square: ' . $categoryName . ' id=' . $categoryIdForUse, 'INFO');
                        }
                    } catch (\Throwable $catEx) {
                        $this->logger->logMessage('CATEGORY upsert exception: ' . $catEx->getMessage(), 'WARNING');
                    }
                }
            }

            // 2-5. Item データ作成（メタデータに order_session_id を設定）
            $itemData = new \Square\Models\CatalogItem();
            $itemData->setName($itemName);
            if (method_exists($itemData, 'setMetadata')) {
                $itemData->setMetadata(['order_session_id' => (string)$sessionId]);
            } else {
                $this->logger->logMessage("CatalogItem に metadata セッターが存在しません (SDK ver?). order_session_id は未設定", 'WARNING');
            }
            $itemData->setVariations([$variationObject]);
            if ($categoryIdForUse && method_exists($itemData, 'setCategoryId')) {
                $itemData->setCategoryId($categoryIdForUse);
            }

            // 2-6. CatalogObject('ITEM') にラップ
            $catalogObject = new \Square\Models\CatalogObject('ITEM', $itemId);
            $catalogObject->setItemData($itemData);

            // 2-7. relatedObjects を準備
            $bodyRelatedObjects = [$variationObject];
            if (!empty($categoryObject)) {
                $bodyRelatedObjects[] = $categoryObject;
            }

            // 2-8. UpsertCatalogObjectRequest を生成して送信
            $upsertBody = new \Square\Models\UpsertCatalogObjectRequest(uniqid(), $catalogObject);
            if (method_exists($upsertBody, 'setRelatedObjects')) {
                $upsertBody->setRelatedObjects($bodyRelatedObjects);
            }
            $this->logger->logMessage("UpsertCatalogObjectRequest prepared: item={$itemId}, variation={$itemVariationId}", 'INFO');
            $resp = $catalogApi->upsertCatalogObject($upsertBody);

            if ($resp->isSuccess()) {
                $createdItemObj = $resp->getResult()->getCatalogObject(); // TYPE='ITEM'
                $parentItemIdOut   = $createdItemObj->getId();
                $variations = $createdItemObj->getItemData()->getVariations() ?? [];
                if (count($variations) === 0) {
                    $this->logger->logMessage("新規作成後、バリエーションが見つかりません: itemId={$parentItemIdOut}", 'ERROR');
                    return null;
                }
                $variationIdOut = $variations[0]->getId();
                // ✅ ログ出力: 新規作成
                $this->logger->logMessage("✅ 新規作成: parentItemIdOut={$parentItemIdOut}, variationIdOut={$variationIdOut}", 'INFO');
                // 価格検証 (variationIdOut を使って retrieve)
                try {
                    $chkResp = $catalogApi->retrieveCatalogObject($variationIdOut, true);
                    if ($chkResp->isSuccess()) {
                        $chkObj = $chkResp->getResult()->getObject();
                        $chkMoney = $chkObj && $chkObj->getItemVariationData() && $chkObj->getItemVariationData()->getPriceMoney()
                            ? $chkObj->getItemVariationData()->getPriceMoney()->getAmount() : null;
                        if ($chkMoney === intval(round($totalAmount))) {
                            // DB 更新 (variation & parent)
                            $this->logger->logMessage("▶▶▶ SQLパラメータ (新規): variationIdOut={$variationIdOut}, parentItemIdOut={$parentItemIdOut}, sessionId={$sessionId}", 'DEBUG');
                            try {
                                $dbi->execute(
                                    "UPDATE order_sessions
                                        SET square_variation_id = ?, square_item_id = ?
                                      WHERE id = ?",
                                    [$variationIdOut, $parentItemIdOut, $sessionId]
                                );
                            } catch (\Throwable $dbEx) {
                                $this->logger->logMessage("order_sessions 更新失敗: " . $dbEx->getMessage(), 'WARNING');
                            }
                            return $variationIdOut;
                        }
                        // 価格検証NG
                        $this->logger->logMessage("新規商品作成後の価格照合NG (期待:" . intval(round($totalAmount)) . " 実際:" . $chkMoney . ")", 'ERROR');
                        $this->webhookService->sendWebhookEvent('price_mismatch_error', [
                            'session_id' => $sessionId,
                            'room_number' => $roomNumber,
                            'expected_total' => $totalAmount,
                            'actual_total' => $chkMoney,
                            'square_item_id' => $variationIdOut
                        ]);
                        // それでも variationIdOut を返す (DBには一応書かせておく)
                        $this->logger->logMessage("▶▶▶ SQLパラメータ (新規): variationIdOut={$variationIdOut}, parentItemIdOut={$parentItemIdOut}, sessionId={$sessionId}", 'DEBUG');
                        try {
                            $dbi->execute(
                                "UPDATE order_sessions
                                    SET square_variation_id = ?, square_item_id = ?
                                  WHERE id = ?",
                                [$variationIdOut, $parentItemIdOut, $sessionId]
                            );
                        } catch (\Throwable $dbEx) {
                            // 無視
                        }
                        return $variationIdOut;
                    }
                } catch (\Throwable $cEx) {
                    $this->logger->logMessage('新規商品照合で例外: ' . $cEx->getMessage(), 'ERROR');
                    $this->webhookService->sendWebhookEvent('price_mismatch_exception', [
                        'session_id' => $sessionId,
                        'room_number' => $roomNumber,
                        'expected_total' => $totalAmount,
                        'square_item_id' => $variationIdOut,
                        'error' => $cEx->getMessage()
                    ]);
                    // それでも variationIdOut を返す
                    try {
                        $dbi->execute(
                            "UPDATE order_sessions
                                SET square_variation_id = ?, square_item_id = ?
                              WHERE id = ?",
                            [$variationIdOut, $parentItemIdOut, $sessionId]
                        );
                    } catch (\Throwable $dbEx) {
                        // 無視
                    }
                    return $variationIdOut;
                }
            } else {
                $errors = $resp->getErrors();
                $this->logger->logMessage('Catalog upsert error: ' . json_encode($errors), 'ERROR');
                $this->webhookService->sendWebhookEvent('catalog_update_failed', [
                    'session_id' => $sessionId,
                    'room_number' => $roomNumber,
                    'expected_total' => $totalAmount,
                    'square_item_id' => $existingItemId
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->logMessage('createOrUpdateSessionProduct exception: ' . $e->getMessage(), 'ERROR');
            $this->webhookService->sendWebhookEvent('create_failed', [
                'session_id' => $sessionId,
                'room_number' => $roomNumber,
                'expected_total' => $totalAmount,
                'square_item_id' => $existingItemId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * ダミー商品を無効化 (非公開) にする
     * @param string $squareItemId CatalogObjectId (ITEM_VARIATION) または ITEM の ID
     * @return bool 成功時 true
     */
    public function disableSessionProduct($squareItemId){
        $this->logger->logMessage("disableSessionProduct: id={$squareItemId}", 'INFO');
        
        try{
            $catalogApi = $this->client->getCatalogApi();
            $resp = $catalogApi->retrieveCatalogObject($squareItemId, true);
            if(!$resp->isSuccess()){
                $this->logger->logMessage('retrieveCatalogObject failed: '.json_encode($resp->getErrors()),'WARNING');
                return false;
            }
            $obj = $resp->getResult()->getObject();
            if(!$obj){
                $this->logger->logMessage('catalog object not found','WARNING');
                return false;
            }
            // ITEM_VARIATION の場合は親 ITEM も取得
            if($obj->getType()==='ITEM_VARIATION' && $obj->getItemVariationData()){
                $parentId = $obj->getItemVariationData()->getItemId();
                if($parentId){
                    $parentResp = $catalogApi->retrieveCatalogObject($parentId);
                    if($parentResp->isSuccess()){
                        $obj = $parentResp->getResult()->getObject();
                    }
                }
            }
            if($obj->getType()!=='ITEM'){
                $this->logger->logMessage('unsupported object type '.$obj->getType(),'WARNING');
                return false;
            }
            // available_for_sale を false にする (Square 2023 API 以降)
            if(method_exists($obj->getItemData(),'setAvailableForSale')){
                $obj->getItemData()->setAvailableForSale(false);
            }
            // present_at_all_locations を false にすると販売できなくなる場合もある
            if(method_exists($obj,'setPresentAtAllLocations')){
                $obj->setPresentAtAllLocations(false);
            }
            // Upsert
            $updateBody = new \Square\Models\UpsertCatalogObjectRequest(uniqid(), $obj);
            $upResp = $catalogApi->upsertCatalogObject($updateBody);
            if($upResp->isSuccess()){
                $this->logger->logMessage('disableSessionProduct success','INFO');
                return true;
            }else{
                $this->logger->logMessage('disableSessionProduct upsert error: '.json_encode($upResp->getErrors()),'ERROR');
                return false;
            }
        }catch(\Throwable $e){
            $this->logger->logMessage('disableSessionProduct exception: '.$e->getMessage(),'ERROR');
            return false;
        }
    }

    /**
     * セッション用ダミー商品を 1 行だけ含む Square 注文を作成（Sandbox 専用）
     * Webhook テスト目的で使用し、支払いは行わない。
     *
     * @param string $squareItemId カタログアイテム（バリエーション）ID
     * @param int    $quantity     購入数量 (通常 1)
     * @param string $sessionId    order_sessions.id（メタデータに入れる）
     * @return array|false 成功時 [order_id=>...,\n   ]、失敗時 false
     */
    public function createSessionOrder($squareItemId,$quantity=1,$sessionId=''){
        $this->logger->logMessage("createSessionOrder 開始: item={$squareItemId} qty={$quantity} session={$sessionId}",'INFO');
        
        try{
            $orderApi=$this->client->getOrdersApi();
            $lineItem=new OrderLineItem((string)$quantity);
            $lineItem->setCatalogObjectId($squareItemId);
            $order=new Order($this->locationId);
            $order->setLineItems([$lineItem]);
            if($sessionId){
                $order->setReferenceId($sessionId);
                $order->setMetadata(['order_session_id'=>$sessionId]);
            }
            $order->setState('OPEN');
            $body=new CreateOrderRequest();
            $body->setOrder($order);
            $body->setIdempotencyKey(uniqid('sessorder_',true));
            $resp=$orderApi->createOrder($body);
            if($resp->isSuccess()){
                $oid=$resp->getResult()->getOrder()->getId();
                $this->logger->logMessage("createSessionOrder success: order_id={$oid}",'INFO');
                return ['order_id'=>$oid];
            }else{
                $this->logger->logMessage('createSessionOrder error: '.json_encode($resp->getErrors()),'ERROR');
                return false;
            }
        }catch(ApiException $e){
            $this->logger->logMessage('createSessionOrder ApiException: '.$e->getMessage(),'ERROR');
            return false;
        }catch(\Throwable $ex){
            $this->logger->logMessage('createSessionOrder exception: '.$ex->getMessage(),'ERROR');
            return false;
        }
    }
}