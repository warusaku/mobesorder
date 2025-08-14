<?php
/**
 * Square API 修正版オーダー作成テスト - 最小限バージョン
 */

// エラー表示の設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 文字列出力関数（デバッグ用）
function echo_log($message) {
    echo $message . "<br>\n";
    flush();
}

echo "<html><head><title>最小限テスト</title></head><body>";
echo "<h1>最小限テスト</h1>";

try {
    echo_log("ステップ1: 基本設定の確認");
    
    // PHPバージョン確認
    echo_log("PHP バージョン: " . phpversion());
    
    // メモリ制限
    echo_log("メモリ制限: " . ini_get('memory_limit'));
    
    // 実行時間制限
    echo_log("実行時間制限: " . ini_get('max_execution_time') . "秒");
    
    // ステップ1.5: 設定ファイルの読み込み（重要: Database.phpより先に読み込む）
    echo_log("設定ファイルの読み込み中...");
    require_once __DIR__ . '/config/config.php';
    echo_log("設定ファイルの読み込み完了");
    
    // 設定値の存在確認（DBホスト）
    echo_log("DB接続設定確認: " . (defined('DB_HOST') ? "DB_HOST定義あり" : "DB_HOST定義なし"));
    
    // ステップ2: データベース接続のみ
    echo_log("ステップ2: データベース接続テスト");
    require_once __DIR__ . '/lib/Database.php';
    
    echo_log("Database.phpの読み込み完了");
    $db = Database::getInstance();
    echo_log("データベース接続成功");
    
    // ステップ3: 商品情報取得
    echo_log("ステップ3: 商品情報取得");
    $products = $db->select(
        "SELECT id, square_item_id, name, price FROM products 
         WHERE price > 0 AND is_active = 1 
         ORDER BY id ASC LIMIT 3"
    );
    
    if (empty($products)) {
        echo_log("商品情報が取得できませんでした");
    } else {
        echo_log("商品情報取得成功: " . count($products) . "件");
        echo "<pre>";
        print_r($products);
        echo "</pre>";
    }
    
    // ステップ4: Square APIテスト（独立した最小限のコード）
    echo_log("ステップ4: Square API接続テスト");
    
    // APIキーと環境情報を表示（アクセストークンは一部だけ表示）
    $token = defined('SQUARE_ACCESS_TOKEN') ? substr(SQUARE_ACCESS_TOKEN, 0, 4) . '...' : '未設定';
    $env = defined('SQUARE_ENVIRONMENT') ? SQUARE_ENVIRONMENT : '未設定';
    $loc = defined('SQUARE_LOCATION_ID') ? SQUARE_LOCATION_ID : '未設定';
    
    echo_log("Square設定: 環境=" . $env . ", ロケーションID=" . $loc . ", トークン=" . $token);
    
    // SquareクライアントSDKが利用可能かチェック
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo_log("Vendorディレクトリが見つかりません。Composerでの依存関係インストールが必要です。");
    } else {
        echo_log("vendor/autoload.php が存在します");
        require_once __DIR__ . '/vendor/autoload.php';
        echo_log("autoload.php 読み込み完了");
        
        if (!class_exists('Square\SquareClient')) {
            echo_log("Square SDKクラスが見つかりません。Square SDKがインストールされていない可能性があります。");
        } else {
            echo_log("Square SDKクラスが見つかりました");
            
            // Square SDKの基本的な使用テスト
            echo_log("Square APIクライアント初期化テスト");
            try {
                $env = SQUARE_ENVIRONMENT === 'production' 
                    ? \Square\Environment::PRODUCTION 
                    : \Square\Environment::SANDBOX;
                    
                $client = new \Square\SquareClient([
                    'accessToken' => SQUARE_ACCESS_TOKEN,
                    'environment' => $env
                ]);
                
                echo_log("Square APIクライアント初期化成功");
                
                // ロケーション情報取得（最も基本的なAPI呼び出し）
                echo_log("ロケーション情報取得テスト実行中...");
                $locationsApi = $client->getLocationsApi();
                $response = $locationsApi->listLocations();
                
                if ($response->isSuccess()) {
                    $locations = $response->getResult()->getLocations();
                    echo_log("ロケーション情報取得成功: " . count($locations) . "件");
                    
                    foreach ($locations as $index => $location) {
                        echo_log("ロケーション" . ($index + 1) . ": " . $location->getName() . " (ID: " . $location->getId() . ")");
                    }
                } else {
                    $errors = $response->getErrors();
                    echo_log("ロケーション情報取得エラー: " . json_encode($errors));
                }
            } catch (Exception $e) {
                echo_log("Square API例外: " . $e->getMessage());
            }
        }
    }
    
    // ステップ5: 簡易的な注文作成テスト（RoomTicketServiceなしで直接）
    if (isset($client) && isset($products) && !empty($products)) {
        echo_log("ステップ5: 簡易的な注文作成テスト");
        
        $roomNumber = 'TEST105';
        $firstProduct = $products[0];
        
        echo_log("テスト用商品: ID=" . $firstProduct['id'] . ", 名前=" . $firstProduct['name']);
        
        // 注文作成APIを直接使用
        try {
            $orderApi = $client->getOrdersApi();
            
            // 名前と価格を使った注文を作成
            echo_log("名前と価格のみを使用した注文作成");
            
            // 注文商品を作成
            $lineItem = new \Square\Models\OrderLineItem(1);
            $lineItem->setName($firstProduct['name']);
            
            // 価格設定
            $money = new \Square\Models\Money();
            $money->setAmount((int)($firstProduct['price'] * 100)); // セント単位に変換
            $money->setCurrency('JPY');
            $lineItem->setBasePriceMoney($money);
            
            echo_log("商品設定: 名前=" . $firstProduct['name'] . ", 価格=" . $firstProduct['price'] . "円");
            
            // 注文オブジェクト作成
            $order = new \Square\Models\Order(SQUARE_LOCATION_ID);
            $order->setLineItems([$lineItem]);
            $order->setReferenceId($roomNumber);
            
            // メタデータ設定
            $order->setMetadata([
                'room_number' => $roomNumber,
                'test_mode' => 'true'
            ]);
            
            // 注文作成リクエスト
            $request = new \Square\Models\CreateOrderRequest();
            $request->setOrder($order);
            $request->setIdempotencyKey('test_name_price_' . uniqid());
            
            echo_log("注文リクエスト送信中...");
            
            // APIリクエスト送信
            $response = $orderApi->createOrder($request);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $orderData = $result->getOrder();
                
                echo_log("注文作成成功！ID: " . $orderData->getId());
                echo "<div style='color:green; font-weight:bold;'>テスト成功: 名前と価格で注文が作成されました</div>";
            } else {
                $errors = $response->getErrors();
                echo_log("注文作成エラー: " . json_encode($errors));
                echo "<div style='color:red; font-weight:bold;'>テスト失敗: APIエラーが発生しました</div>";
            }
        } catch (Exception $e) {
            echo_log("注文作成例外: " . $e->getMessage());
            echo "<div style='color:red; font-weight:bold;'>テスト失敗: 例外が発生しました</div>";
        }
    }
    
} catch (Exception $e) {
    echo_log("エラー発生: " . $e->getMessage());
    echo_log("スタックトレース: " . $e->getTraceAsString());
}

echo "</body></html>";
?> 