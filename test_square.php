<?php
/**
 * Square APIテスト用スクリプト
 * 
 * このスクリプトはSquare APIへの各種操作をテストするためのものです。
 * テスト環境で実行し、本番環境では使用しないでください。
 */

// エラー表示の設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// タイムアウト時間の延長
set_time_limit(300); // 5分に延長

// 必要なファイルの読み込み
require_once __DIR__ . '/api/lib/SquareService.php';
require_once __DIR__ . '/api/lib/Utils.php';
require_once __DIR__ . '/api/config/config.php';

// テスト用の設定
$testRoomNumber = 'test-room-' . date('Ymd-His');
$testGuestName = 'テストユーザー';

// ヘッダー出力
header('Content-Type: text/plain; charset=utf-8');

echo "===== Square API テスト実行 =====\n";
echo "日時: " . date('Y-m-d H:i:s') . "\n";
echo "環境: " . (SQUARE_ENVIRONMENT === 'production' ? '本番環境' : 'サンドボックス環境') . "\n";
echo "テスト部屋番号: {$testRoomNumber}\n\n";

try {
    // SquareServiceのインスタンス化
    echo "1. Square APIへの接続テスト...\n";
    $squareService = new SquareService();
    
    // 接続テスト
    $connectionResult = $squareService->testConnection();
    if ($connectionResult['success']) {
        echo "✓ Square APIへの接続に成功しました\n";
        echo "  ロケーション名: {$connectionResult['location_name']}\n";
        echo "  ロケーションID: {$connectionResult['location_id']}\n\n";
    } else {
        echo "✕ Square APIへの接続に失敗しました: {$connectionResult['error']}\n\n";
        exit(1);
    }
    
    // 商品カタログの取得
    echo "2. 商品カタログの取得...\n";
    try {
        $catalogItems = $squareService->getItems(false, 10); // 最大10件のみ取得
        
        if (count($catalogItems) > 0) {
            echo "✓ " . count($catalogItems) . "件の商品を取得しました\n";
            
            // 最初の数件を表示
            $displayCount = min(3, count($catalogItems));
            echo "  最初の{$displayCount}件:\n";
            
            for ($i = 0; $i < $displayCount; $i++) {
                $item = $catalogItems[$i];
                echo "  - ID: {$item['id']}, 名前: {$item['name']}, 価格: {$item['price']}円\n";
            }
            echo "\n";
            
            // テスト用に最初の商品を保存
            $testItemId = $catalogItems[0]['id'];
            $testItemName = $catalogItems[0]['name'];
            $testItemPrice = $catalogItems[0]['price'];
        } else {
            echo "✕ 商品が取得できませんでした\n\n";
        }
    } catch (Exception $catalogEx) {
        echo "✕ 商品カタログの取得中にエラーが発生しました: " . $catalogEx->getMessage() . "\n\n";
    }
    
    // ルームチケットの作成
    echo "3. ルームチケットの作成テスト...\n";
    try {
        $ticketResult = $squareService->createRoomTicket($testRoomNumber, $testGuestName);
        
        if ($ticketResult) {
            echo "✓ ルームチケットの作成に成功しました\n";
            echo "  チケットID: {$ticketResult['square_order_id']}\n";
            echo "  部屋番号: {$ticketResult['room_number']}\n";
            echo "  ステータス: {$ticketResult['status']}\n\n";
            
            $testTicketId = $ticketResult['square_order_id'];
            
            // ルームチケットへの商品追加テスト
            if (isset($testItemId)) {
                echo "4. ルームチケットへの商品追加テスト...\n";
                $itemsToAdd = [
                    [
                        'square_item_id' => $testItemId,
                        'quantity' => 1,
                        'note' => 'テスト注文アイテム'
                    ]
                ];
                
                echo "  追加するアイテム情報: " . json_encode($itemsToAdd, JSON_UNESCAPED_UNICODE) . "\n";
                
                try {
                    echo "  Square APIへの商品追加リクエストを送信します...\n";
                    
                    // デバッグ用に引数情報を詳細に表示
                    echo "  - 部屋番号: {$testRoomNumber}\n";
                    echo "  - 商品ID: {$testItemId}\n";
                    
                    $addResult = $squareService->addItemToRoomTicket($testRoomNumber, $itemsToAdd);
                    
                    echo "  Square APIへの商品追加リクエスト完了\n";
                    
                    if ($addResult) {
                        echo "✓ ルームチケットへの商品追加に成功しました\n";
                        echo "  更新されたチケットID: {$addResult['square_order_id']}\n";
                        
                        // 商品リストを表示
                        if (isset($addResult['line_items']) && count($addResult['line_items']) > 0) {
                            echo "  追加された商品:\n";
                            foreach ($addResult['line_items'] as $item) {
                                echo "  - {$item['name']} x {$item['quantity']}個 ({$item['price']}円)\n";
                            }
                        }
                        echo "\n";
                    } else {
                        echo "✕ ルームチケットへの商品追加に失敗しました\n";
                        echo "  ログファイルを確認してください: logs/SquareService.log\n\n";
                    }
                } catch (Exception $addEx) {
                    echo "✕ ルームチケット商品追加時に例外が発生しました: " . $addEx->getMessage() . "\n";
                    echo "  例外の詳細: " . get_class($addEx) . "\n";
                    echo "  スタックトレース: \n" . $addEx->getTraceAsString() . "\n\n";
                }
            }
            
            // ルームチケット情報の取得テスト
            echo "5. ルームチケット情報の取得テスト...\n";
            try {
                $ticketInfo = $squareService->getRoomTicket($testRoomNumber);
                
                if ($ticketInfo) {
                    echo "✓ ルームチケット情報の取得に成功しました\n";
                    echo "  チケットID: {$ticketInfo['square_order_id']}\n";
                    echo "  部屋番号: {$ticketInfo['room_number']}\n";
                    echo "  ステータス: {$ticketInfo['status']}\n";
                    echo "  合計金額: {$ticketInfo['total_amount']}円\n";
                    
                    // 商品リストを表示
                    if (isset($ticketInfo['line_items']) && count($ticketInfo['line_items']) > 0) {
                        echo "  商品リスト:\n";
                        foreach ($ticketInfo['line_items'] as $item) {
                            echo "  - {$item['name']} x {$item['quantity']}個 ({$item['price']}円)\n";
                        }
                    }
                    echo "\n";
                } else {
                    echo "✕ ルームチケット情報の取得に失敗しました\n\n";
                }
            } catch (Exception $getTicketEx) {
                echo "✕ ルームチケット情報の取得中に例外が発生しました: " . $getTicketEx->getMessage() . "\n\n";
            }
        } else {
            echo "✕ ルームチケットの作成に失敗しました\n\n";
        }
    } catch (Exception $ticketEx) {
        echo "✕ ルームチケットの作成中に例外が発生しました: " . $ticketEx->getMessage() . "\n\n";
    }
    
    // 注文の取得テスト
    if (isset($testTicketId)) {
        echo "6. 注文情報の取得テスト...\n";
        try {
            $orderInfo = $squareService->getOrder($testTicketId);
            
            if ($orderInfo) {
                echo "✓ 注文情報の取得に成功しました\n";
                echo "  注文ID: {$orderInfo['id']}\n";
                echo "  参照ID: {$orderInfo['reference_id']}\n";
                echo "  ステータス: {$orderInfo['state']}\n";
                echo "  合計金額: {$orderInfo['total_money']}円\n\n";
            } else {
                echo "✕ 注文情報の取得に失敗しました\n\n";
            }
        } catch (Exception $orderEx) {
            echo "✕ 注文情報の取得中に例外が発生しました: " . $orderEx->getMessage() . "\n\n";
        }
    }
    
    echo "===== テスト完了 =====\n";
    echo "すべてのテストが完了しました。\n";
    
} catch (Exception $e) {
    echo "エラー発生: " . $e->getMessage() . "\n";
    echo "例外の種類: " . get_class($e) . "\n";
    echo "スタックトレース: \n" . $e->getTraceAsString() . "\n";
} 