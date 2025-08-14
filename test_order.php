<?php
/**
 * 注文処理テスト用スクリプト
 * 
 * このスクリプトは注文処理フローをテストするためのものです。
 * テスト環境で実行し、本番環境では使用しないでください。
 */

// エラー表示の設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// タイムアウト時間の延長
set_time_limit(300); // 5分に延長

// 必要なファイルの読み込み
require_once __DIR__ . '/api/lib/OrderService.php';
require_once __DIR__ . '/api/lib/ProductService.php';
require_once __DIR__ . '/api/lib/RoomTicketService.php';
require_once __DIR__ . '/api/lib/SquareService.php';
require_once __DIR__ . '/api/lib/Utils.php';
require_once __DIR__ . '/api/config/config.php';

// テスト用の設定
$testRoomNumber = 'test-room-' . date('Ymd-His');
$testGuestName = 'テストユーザー';

// ヘッダー出力
header('Content-Type: text/plain; charset=utf-8');

echo "===== 注文処理フローテスト =====\n";
echo "日時: " . date('Y-m-d H:i:s') . "\n";
echo "環境: " . (SQUARE_ENVIRONMENT === 'production' ? '本番環境' : 'サンドボックス環境') . "\n";
echo "テスト部屋番号: {$testRoomNumber}\n\n";

try {
    // ProductServiceのインスタンス化
    echo "1. 商品情報の取得...\n";
    try {
        $productService = new ProductService();
        $products = $productService->getProducts();
        
        if (count($products) > 0) {
            echo "✓ " . count($products) . "件の商品を取得しました\n";
            
            // 最初の数件を表示
            $displayCount = min(3, count($products));
            echo "  最初の{$displayCount}件:\n";
            
            for ($i = 0; $i < $displayCount; $i++) {
                $product = $products[$i];
                echo "  - ID: {$product['square_item_id']}, 名前: {$product['name']}, 価格: {$product['price']}円\n";
            }
            echo "\n";
            
            // テスト用にいくつかの商品を選択
            $orderItems = [];
            $totalItems = min(2, count($products));
            
            for ($i = 0; $i < $totalItems; $i++) {
                $orderItems[] = [
                    'square_item_id' => $products[$i]['square_item_id'],
                    'quantity' => 1,
                    'note' => 'テスト注文アイテム'
                ];
            }
            
            echo "  テスト用に{$totalItems}件の商品を選択しました\n";
            echo "  選択した商品情報: " . json_encode($orderItems, JSON_UNESCAPED_UNICODE) . "\n\n";
        } else {
            echo "✕ 商品が取得できませんでした\n\n";
            exit(1);
        }
    } catch (Exception $productEx) {
        echo "✕ 商品情報の取得中に例外が発生しました: " . $productEx->getMessage() . "\n\n";
        exit(1);
    }
    
    // OrderServiceのインスタンス化と注文作成テスト
    echo "2. 注文作成テスト...\n";
    try {
        $orderService = new OrderService();
        
        // 注文リクエストのデータを表示
        echo "  注文リクエストデータ:\n";
        echo "  - 部屋番号: {$testRoomNumber}\n";
        echo "  - ゲスト名: {$testGuestName}\n";
        echo "  - 注文アイテム数: " . count($orderItems) . "\n";
        foreach ($orderItems as $index => $item) {
            echo "    アイテム" . ($index + 1) . ": ID={$item['square_item_id']}, 数量={$item['quantity']}\n";
        }
        echo "\n";
        
        // 注文作成実行
        echo "  注文作成を実行します...\n";
        echo "  開始時刻: " . date('H:i:s') . "\n";
        
        $orderResult = $orderService->createOrder(
            $testRoomNumber,
            $orderItems,
            $testGuestName,
            'テスト用注文データ [テストモード]'
        );
        
        echo "  完了時刻: " . date('H:i:s') . "\n";
        
        if ($orderResult) {
            echo "✓ 注文作成に成功しました\n";
            echo "  注文ID: {$orderResult['id']}\n";
            echo "  Square注文ID: {$orderResult['square_order_id']}\n";
            echo "  部屋番号: {$orderResult['room_number']}\n";
            echo "  合計金額: {$orderResult['total_amount']}円\n";
            echo "  ステータス: {$orderResult['status']}\n\n";
            
            $testOrderId = $orderResult['id'];
        } else {
            echo "✕ 注文作成に失敗しました\n";
            echo "  ログファイルを確認してください: logs/OrderService.log\n\n";
        }
    } catch (Exception $orderEx) {
        echo "✕ 注文作成中に例外が発生しました: " . $orderEx->getMessage() . "\n";
        echo "  例外の詳細: " . get_class($orderEx) . "\n";
        echo "  スタックトレース: \n" . $orderEx->getTraceAsString() . "\n\n";
    }
    
    // 作成した注文の取得テスト
    if (isset($testOrderId)) {
        echo "3. 作成した注文情報の取得テスト...\n";
        try {
            $orderInfo = $orderService->getOrder($testOrderId);
            
            if ($orderInfo) {
                echo "✓ 注文情報の取得に成功しました\n";
                echo "  注文ID: {$orderInfo['id']}\n";
                echo "  Square注文ID: {$orderInfo['square_order_id']}\n";
                echo "  部屋番号: {$orderInfo['room_number']}\n";
                echo "  ゲスト名: {$orderInfo['guest_name']}\n";
                echo "  合計金額: {$orderInfo['total_amount']}円\n";
                echo "  注文ステータス: {$orderInfo['order_status']}\n";
                echo "  注文日時: {$orderInfo['order_datetime']}\n";
                
                // 注文アイテムの表示
                if (isset($orderInfo['items']) && count($orderInfo['items']) > 0) {
                    echo "  注文アイテム:\n";
                    foreach ($orderInfo['items'] as $item) {
                        echo "  - {$item['product_name']} x {$item['quantity']}個 ({$item['unit_price']}円/個 = {$item['subtotal']}円)\n";
                    }
                }
                echo "\n";
            } else {
                echo "✕ 注文情報の取得に失敗しました\n\n";
            }
        } catch (Exception $getOrderEx) {
            echo "✕ 注文情報の取得中に例外が発生しました: " . $getOrderEx->getMessage() . "\n\n";
        }
    }
    
    // 部屋の注文履歴取得テスト
    echo "4. 部屋の注文履歴取得テスト...\n";
    try {
        $orderHistory = $orderService->getOrdersByRoom($testRoomNumber);
        
        if (is_array($orderHistory) && count($orderHistory) > 0) {
            echo "✓ 部屋の注文履歴取得に成功しました\n";
            echo "  注文履歴件数: " . count($orderHistory) . "\n";
            
            foreach ($orderHistory as $index => $order) {
                echo "  履歴" . ($index + 1) . ": ID={$order['id']}, 日時={$order['order_datetime']}, 金額={$order['total_amount']}円\n";
            }
            echo "\n";
        } else {
            echo "✕ 部屋の注文履歴取得に失敗しました\n\n";
        }
    } catch (Exception $historyEx) {
        echo "✕ 注文履歴の取得中に例外が発生しました: " . $historyEx->getMessage() . "\n\n";
    }
    
    // RoomTicketServiceのテスト
    echo "5. RoomTicketServiceテスト...\n";
    try {
        $roomTicketService = new RoomTicketService();
        
        // 部屋のチケット取得
        echo "  部屋のチケット情報を取得します...\n";
        $roomTicket = $roomTicketService->getRoomTicket($testRoomNumber);
        
        if ($roomTicket) {
            echo "✓ 部屋のチケット取得に成功しました\n";
            echo "  チケットID: {$roomTicket['id']}\n";
            echo "  Square注文ID: {$roomTicket['square_order_id']}\n";
            echo "  部屋番号: {$roomTicket['room_number']}\n";
            echo "  ステータス: {$roomTicket['status']}\n\n";
        } else {
            echo "  部屋のチケットが見つかりませんでした。新しく作成します...\n";
            
            // 新しいチケットを作成
            echo "  新しいチケットを作成中...\n";
            $newTicket = $roomTicketService->createRoomTicket($testRoomNumber, $testGuestName);
            
            if ($newTicket) {
                echo "✓ 新しいチケット作成に成功しました\n";
                echo "  チケットID: {$newTicket['id']}\n";
                echo "  Square注文ID: {$newTicket['square_order_id']}\n";
                echo "  部屋番号: {$newTicket['room_number']}\n";
                echo "  ステータス: {$newTicket['status']}\n\n";
            } else {
                echo "✕ 新しいチケット作成に失敗しました\n\n";
            }
        }
    } catch (Exception $ticketEx) {
        echo "✕ チケット処理中に例外が発生しました: " . $ticketEx->getMessage() . "\n";
        echo "  例外の詳細: " . get_class($ticketEx) . "\n";
        echo "  スタックトレース: \n" . $ticketEx->getTraceAsString() . "\n\n";
    }
    
    echo "===== テスト完了 =====\n";
    echo "すべてのテストが完了しました。\n";
    
} catch (Exception $e) {
    echo "エラー発生: " . $e->getMessage() . "\n";
    echo "例外の種類: " . get_class($e) . "\n";
    echo "スタックトレース: \n" . $e->getTraceAsString() . "\n";
} 