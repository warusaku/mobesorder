<?php
/**
 * Square APIから画像URLを取得するテストスクリプト
 * 
 * 実行方法: php test_square_image.php
 * 結果: 同じディレクトリにsquare_image_test.logファイルが生成されます
 */

// 必要なファイルを読み込む
require_once __DIR__ . '/../api/lib/init.php';

// ログファイルのパス
$logFile = __DIR__ . '/square_image_test.log';

// ログ関数
function log_test($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // 画面出力
    echo $logMessage;
    
    // ファイルに書き込み
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 最初の行を書き込む
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Square API 画像URL取得テスト開始\n");

try {
    log_test("========== テスト開始 ==========");
    
    // SquareServiceインスタンスの作成（既存のアクセストークンを使用）
    log_test("Square クライアントの初期化...");
    $squareService = new SquareService();
    $client = $squareService->getSquareClient();
    $catalogApi = $client->getCatalogApi();
    
    log_test("Square クライアント初期化成功");
    
    // テスト1: 基本的なAPIアクセス確認
    log_test("\n【テスト1】基本的なAPIアクセス確認 - catalogInfo()");
    try {
        $infoResponse = $catalogApi->catalogInfo();
        
        if ($infoResponse->isSuccess()) {
            log_test("✅ 成功: 基本API接続OK");
            $result = $infoResponse->getResult();
            $limits = $result->getLimits();
            if ($limits) {
                log_test("API制限情報: " . json_encode($limits));
            }
        } else {
            log_test("❌ 失敗: APIアクセスエラー: " . json_encode($infoResponse->getErrors()));
        }
    } catch (Exception $e) {
        log_test("❌ 例外: " . $e->getMessage());
    }
    
    // テスト2: 商品一覧から最初の10件を取得
    log_test("\n【テスト2】商品一覧の取得 (最大10件)");
    try {
        $listRequest = new \Square\Models\ListCatalogRequest();
        $listRequest->setTypes(['ITEM']);
        $listRequest->setLimit(10);
        
        $listResponse = $catalogApi->listCatalog($listRequest);
        
        if ($listResponse->isSuccess()) {
            $objects = $listResponse->getResult()->getObjects();
            log_test("✅ 成功: " . count($objects) . "件の商品を取得");
            
            if (count($objects) > 0) {
                // テスト用に最初の商品を選択
                $testItem = $objects[0];
                $testItemId = $testItem->getId();
                $itemName = $testItem->getItemData() ? $testItem->getItemData()->getName() : 'Unknown';
                
                log_test("テスト用商品: ID=$testItemId, 名前=$itemName");
                
                // 画像ID情報を表示
                $imageIds = $testItem->getItemData() ? $testItem->getItemData()->getImageIds() : null;
                if ($imageIds && count($imageIds) > 0) {
                    log_test("商品の画像ID: " . json_encode($imageIds));
                } else {
                    log_test("❓ この商品には画像IDがありません");
                    
                    // 画像のある商品を探す
                    log_test("画像のある商品を探します...");
                    $foundItemWithImage = false;
                    
                    foreach ($objects as $item) {
                        $itemImageIds = $item->getItemData() ? $item->getItemData()->getImageIds() : null;
                        if ($itemImageIds && count($itemImageIds) > 0) {
                            $testItem = $item;
                            $testItemId = $item->getId();
                            $itemName = $item->getItemData() ? $item->getItemData()->getName() : 'Unknown';
                            
                            log_test("画像付き商品を発見: ID=$testItemId, 名前=$itemName");
                            log_test("商品の画像ID: " . json_encode($itemImageIds));
                            $foundItemWithImage = true;
                            break;
                        }
                    }
                    
                    if (!$foundItemWithImage) {
                        log_test("❗ 画像のある商品が見つかりませんでした。画像APIテストができません。");
                        throw new Exception("テスト中止: 画像のある商品が見つかりません");
                    }
                }
                
                // テスト3: include_related_objects=true で商品情報と関連画像を一度に取得
                log_test("\n【テスト3】方法1: 関連オブジェクト付き単一リクエスト (include_related_objects=true)");
                try {
                    $response = $catalogApi->retrieveCatalogObject($testItemId, true);
                    
                    if ($response->isSuccess()) {
                        log_test("✅ 成功: 商品データ取得");
                        
                        $object = $response->getResult()->getObject();
                        $relatedObjects = $response->getResult()->getRelatedObjects();
                        
                        log_test("関連オブジェクト数: " . ($relatedObjects ? count($relatedObjects) : 0));
                        
                        $imageIds = $object->getItemData() ? $object->getItemData()->getImageIds() : null;
                        
                        if ($imageIds && count($imageIds) > 0) {
                            log_test("商品の画像ID: " . json_encode($imageIds));
                            $foundImageUrl = false;
                            
                            // 関連オブジェクトから画像を検索
                            if ($relatedObjects) {
                                foreach ($relatedObjects as $relObj) {
                                    if ($relObj->getType() === 'IMAGE' && in_array($relObj->getId(), $imageIds)) {
                                        $imageData = $relObj->getImageData();
                                        if ($imageData && $imageData->getUrl()) {
                                            log_test("✅ 成功: 画像URL = " . $imageData->getUrl());
                                            $foundImageUrl = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            if (!$foundImageUrl) {
                                log_test("❌ 関連オブジェクトに画像URLが見つかりませんでした");
                            }
                        } else {
                            log_test("❓ 商品に画像IDがありません");
                        }
                    } else {
                        log_test("❌ 失敗: 商品取得エラー: " . json_encode($response->getErrors()));
                    }
                } catch (Exception $e) {
                    log_test("❌ 例外: " . $e->getMessage());
                }
                
                // テスト4: 画像IDから直接取得
                $imageIds = $testItem->getItemData() ? $testItem->getItemData()->getImageIds() : null;
                if ($imageIds && count($imageIds) > 0) {
                    $testImageId = $imageIds[0];
                    
                    log_test("\n【テスト4】方法2: 画像IDから直接取得");
                    log_test("テスト用画像ID: $testImageId");
                    
                    try {
                        $imageResponse = $catalogApi->retrieveCatalogObject($testImageId);
                        
                        if ($imageResponse->isSuccess()) {
                            $imageObj = $imageResponse->getResult()->getObject();
                            
                            if ($imageObj->getType() === 'IMAGE' && $imageObj->getImageData()) {
                                log_test("✅ 成功: 画像URL = " . $imageObj->getImageData()->getUrl());
                            } else {
                                log_test("❌ 失敗: 取得したオブジェクトは画像ではありません (Type: " . $imageObj->getType() . ")");
                            }
                        } else {
                            log_test("❌ 失敗: 画像取得エラー: " . json_encode($imageResponse->getErrors()));
                        }
                    } catch (Exception $e) {
                        log_test("❌ 例外: " . $e->getMessage());
                    }
                }
                
                // テスト5: バッチリクエストで商品と画像を一度に取得
                log_test("\n【テスト5】方法3: バッチリクエストで取得");
                try {
                    $body = new \Square\Models\BatchRetrieveCatalogObjectsRequest(
                        [$testItemId],
                        true // include_related_objects
                    );
                    
                    $batchResponse = $catalogApi->batchRetrieveCatalogObjects($body);
                    
                    if ($batchResponse->isSuccess()) {
                        log_test("✅ 成功: バッチ取得");
                        
                        $objects = $batchResponse->getResult()->getObjects();
                        $relatedObjects = $batchResponse->getResult()->getRelatedObjects();
                        
                        log_test("取得したオブジェクト数: " . count($objects));
                        log_test("関連オブジェクト数: " . ($relatedObjects ? count($relatedObjects) : 0));
                        
                        if ($objects && count($objects) > 0) {
                            $firstObj = $objects[0];
                            $batchImageIds = $firstObj->getItemData() ? $firstObj->getItemData()->getImageIds() : null;
                            
                            if ($batchImageIds && count($batchImageIds) > 0) {
                                log_test("商品の画像ID: " . json_encode($batchImageIds));
                                $foundBatchImageUrl = false;
                                
                                // 関連オブジェクトから画像を検索
                                if ($relatedObjects) {
                                    foreach ($relatedObjects as $relObj) {
                                        if ($relObj->getType() === 'IMAGE' && in_array($relObj->getId(), $batchImageIds)) {
                                            $imageData = $relObj->getImageData();
                                            if ($imageData && $imageData->getUrl()) {
                                                log_test("✅ 成功: 画像URL = " . $imageData->getUrl());
                                                $foundBatchImageUrl = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                                
                                if (!$foundBatchImageUrl) {
                                    log_test("❌ バッチ関連オブジェクトに画像URLが見つかりませんでした");
                                }
                            } else {
                                log_test("❓ バッチ取得した商品に画像IDがありません");
                            }
                        }
                    } else {
                        log_test("❌ 失敗: バッチ取得エラー: " . json_encode($batchResponse->getErrors()));
                    }
                } catch (Exception $e) {
                    log_test("❌ 例外: " . $e->getMessage());
                }
            } else {
                log_test("❗ 商品が見つかりませんでした");
            }
        } else {
            log_test("❌ 失敗: 商品一覧取得エラー: " . json_encode($listResponse->getErrors()));
        }
    } catch (Exception $e) {
        log_test("❌ 例外: " . $e->getMessage());
    }
    
    // テスト6: 画像一覧を直接取得
    log_test("\n【テスト6】方法4: 画像一覧の直接取得");
    try {
        $imageListRequest = new \Square\Models\ListCatalogRequest();
        $imageListRequest->setTypes(['IMAGE']);
        $imageListRequest->setLimit(5);
        
        $imageListResponse = $catalogApi->listCatalog($imageListRequest);
        
        if ($imageListResponse->isSuccess()) {
            $images = $imageListResponse->getResult()->getObjects();
            log_test("✅ 成功: " . count($images) . "件の画像を取得");
            
            if (count($images) > 0) {
                for ($i = 0; $i < min(count($images), 3); $i++) {
                    $image = $images[$i];
                    log_test("画像#" . ($i + 1) . " ID: " . $image->getId());
                    
                    if ($image->getImageData() && $image->getImageData()->getUrl()) {
                        log_test("画像#" . ($i + 1) . " URL: " . $image->getImageData()->getUrl());
                    } else {
                        log_test("画像#" . ($i + 1) . " にURLがありません");
                    }
                }
                
                if (count($images) > 3) {
                    log_test("... 他 " . (count($images) - 3) . " 件の画像があります");
                }
            } else {
                log_test("❓ 画像が見つかりませんでした");
            }
        } else {
            log_test("❌ 失敗: 画像一覧取得エラー: " . json_encode($imageListResponse->getErrors()));
        }
    } catch (Exception $e) {
        log_test("❌ 例外: " . $e->getMessage());
    }
    
    // テスト7: リスト内の商品から画像URLを取得する関数をテスト
    log_test("\n【テスト7】画像URL取得関数のテスト");
    
    // 画像URL取得のサンプル実装
    function getImageUrlForItem($itemId, $catalogApi) {
        try {
            // 関連オブジェクトを含めて単一リクエストで取得
            $response = $catalogApi->retrieveCatalogObject($itemId, true);
            
            if ($response->isSuccess()) {
                $object = $response->getResult()->getObject();
                $relatedObjects = $response->getResult()->getRelatedObjects();
                
                if (!$object || !$object->getItemData() || !$object->getItemData()->getImageIds() || 
                    count($object->getItemData()->getImageIds()) === 0) {
                    return [false, "商品に画像IDがありません"];
                }
                
                $imageIds = $object->getItemData()->getImageIds();
                $firstImageId = $imageIds[0];
                
                // 関連オブジェクトから画像を検索
                if ($relatedObjects) {
                    foreach ($relatedObjects as $relObj) {
                        if ($relObj->getType() === 'IMAGE' && $relObj->getId() === $firstImageId) {
                            $imageData = $relObj->getImageData();
                            if ($imageData && $imageData->getUrl()) {
                                return [true, $imageData->getUrl()];
                            }
                        }
                    }
                }
                
                // 関連オブジェクトに見つからない場合は直接取得を試行
                $imageResponse = $catalogApi->retrieveCatalogObject($firstImageId);
                
                if ($imageResponse->isSuccess()) {
                    $imageObj = $imageResponse->getResult()->getObject();
                    
                    if ($imageObj->getType() === 'IMAGE' && $imageObj->getImageData() && $imageObj->getImageData()->getUrl()) {
                        return [true, $imageObj->getImageData()->getUrl()];
                    }
                }
                
                return [false, "画像URLが見つかりませんでした"];
            } else {
                return [false, "商品取得エラー: " . json_encode($response->getErrors())];
            }
        } catch (Exception $e) {
            return [false, "例外: " . $e->getMessage()];
        }
    }
    
    // この関数をいくつかの商品でテスト
    try {
        $testListRequest = new \Square\Models\ListCatalogRequest();
        $testListRequest->setTypes(['ITEM']);
        $testListRequest->setLimit(5);
        
        $testListResponse = $catalogApi->listCatalog($testListRequest);
        
        if ($testListResponse->isSuccess()) {
            $testItems = $testListResponse->getResult()->getObjects();
            log_test(count($testItems) . "件の商品でテスト");
            
            foreach ($testItems as $index => $item) {
                $itemId = $item->getId();
                $itemName = $item->getItemData() ? $item->getItemData()->getName() : 'Unknown';
                
                log_test("\n商品#" . ($index + 1) . ": ID=$itemId, 名前=$itemName");
                
                list($success, $result) = getImageUrlForItem($itemId, $catalogApi);
                
                if ($success) {
                    log_test("✅ 成功: 画像URL = $result");
                } else {
                    log_test("❌ 失敗: $result");
                }
            }
        } else {
            log_test("❌ 失敗: 商品一覧取得エラー: " . json_encode($testListResponse->getErrors()));
        }
    } catch (Exception $e) {
        log_test("❌ 例外: " . $e->getMessage());
    }
    
    log_test("\n========== テスト完了 ==========");
    log_test("テスト結果は $logFile に保存されました");
    
} catch (Exception $e) {
    log_test("❌ テスト全体の実行中に例外が発生しました: " . $e->getMessage());
    log_test("スタックトレース: " . $e->getTraceAsString());
} 