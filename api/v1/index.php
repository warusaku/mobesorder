<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/AuthService.php';
require_once __DIR__ . '/../lib/SquareService.php';
require_once __DIR__ . '/../lib/LineService.php';
require_once __DIR__ . '/../lib/ProductService.php';
require_once __DIR__ . '/../lib/OrderService.php';

// CORSヘッダーを設定
Utils::setCorsHeaders();

// リクエストメソッドとパスを取得
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/v1';

// ベースパスを削除してエンドポイントパスを取得
$endpoint = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));

// エンドポイントに基づいてルーティング
switch ($endpoint) {
    // 認証関連
    case '/auth/token':
        handleTokenRequest($requestMethod);
        break;
        
    // LINE認証のエンドポイント追加    
    case '/auth':
        handleLineAuthRequest($requestMethod);
        break;
        
    // 商品関連
    case '/products':
        handleProductsRequest($requestMethod);
        break;
        
    case '/products/categories':
        handleProductCategoriesRequest($requestMethod);
        break;
        
    case '/products/sync':
        handleProductSyncRequest($requestMethod);
        break;
        
    // 注文関連
    case '/orders':
        handleOrdersRequest($requestMethod);
        break;
        
    case '/orders/history':
        handleOrderHistoryRequest($requestMethod);
        break;
        
    // その他
    default:
        Utils::sendErrorResponse('Endpoint not found', 404);
        break;
}

/**
 * トークン認証リクエストを処理
 */
function handleTokenRequest($method) {
    if ($method !== 'POST') {
        Utils::sendErrorResponse('Method not allowed', 405);
        return;
    }
    
    $data = Utils::getJsonInput();
    
    if (empty($data['room_number'])) {
        Utils::sendErrorResponse('Room number is required', 400);
        return;
    }
    
    $auth = new Auth();
    $roomNumber = $data['room_number'];
    $guestName = $data['guest_name'] ?? '';
    $checkInDate = $data['check_in_date'] ?? null;
    $checkOutDate = $data['check_out_date'] ?? null;
    
    $token = $auth->generateRoomToken($roomNumber, $guestName, $checkInDate, $checkOutDate);
    
    Utils::sendJsonResponse([
        'success' => true,
        'token' => $token,
        'room_number' => $roomNumber,
        'guest_name' => $guestName
    ]);
}

/**
 * 商品リクエストを処理
 */
function handleProductsRequest($method) {
    if ($method !== 'GET') {
        Utils::sendErrorResponse('Method not allowed', 405);
        return;
    }
    
    // トークン認証（オプショナル）- トークンがなくても続行可能
    $auth = new Auth();
    $roomInfo = $auth->authenticateRequest();
    
    // 認証エラーでもブロックしない
    /* 以下のコードをコメントアウト
    if (!$roomInfo) {
        Utils::sendErrorResponse('Unauthorized', 401);
        return;
    }
    */
    
    $productService = new ProductService();
    
    // カテゴリIDパラメータがある場合はカテゴリ別商品を取得
    if (isset($_GET['category_id'])) {
        $categoryId = $_GET['category_id'];
        $products = $productService->getProductsByCategoryId($categoryId);
        
        Utils::sendJsonResponse([
            'success' => true,
            'products' => $products,
            'category_id' => $categoryId
        ]);
    } else {
        // カテゴリIDがない場合は全商品を取得
    $products = $productService->getProducts();
    
    Utils::sendJsonResponse([
        'success' => true,
        'products' => $products
    ]);
    }
}

/**
 * 商品カテゴリリクエストを処理
 */
function handleProductCategoriesRequest($method) {
    switch ($method) {
        case 'GET':
            // ProductServiceを使用してカテゴリー一覧を直接取得
    $productService = new ProductService();
            $categories = $productService->getCategories();
    
    Utils::sendJsonResponse([
        'success' => true,
        'categories' => $categories
    ]);
            break;
        
        default:
            Utils::sendErrorResponse('Method not allowed', 405);
            break;
    }
}

/**
 * 商品同期リクエストを処理
 */
function handleProductSyncRequest($method) {
    if ($method !== 'POST') {
        Utils::sendErrorResponse('Method not allowed', 405);
        return;
    }
    
    // 管理者認証（簡易版）
    $data = Utils::getJsonInput();
    $adminKey = $data['admin_key'] ?? '';
    
    if ($adminKey !== ADMIN_KEY) {
        Utils::sendErrorResponse('Unauthorized', 401);
        return;
    }
    
    $productService = new ProductService();
    $result = $productService->syncProductsFromSquare();
    
    Utils::sendJsonResponse([
        'success' => true,
        'result' => $result
    ]);
}

/**
 * 注文リクエストを処理
 */
function handleOrdersRequest($method) {
    // トークン認証
    $auth = new Auth();
    $roomInfo = $auth->authenticateRequest();
    
    if (!$roomInfo) {
        Utils::sendErrorResponse('Unauthorized', 401);
        return;
    }
    
    switch ($method) {
        case 'GET':
            // 注文情報を取得
            $orderId = $_GET['id'] ?? null;
            
            if ($orderId) {
                $orderService = new OrderService();
                $order = $orderService->getOrder($orderId);
                
                if (!$order || $order['room_number'] !== $roomInfo['room_number']) {
                    Utils::sendErrorResponse('Order not found', 404);
                    return;
                }
                
                Utils::sendJsonResponse([
                    'success' => true,
                    'order' => $order
                ]);
            } else {
                Utils::sendErrorResponse('Order ID is required', 400);
            }
            break;
            
        case 'POST':
            // 新規注文を作成
            try {
                $data = Utils::getJsonInput();
                
                if (empty($data)) {
                    Utils::log("注文リクエストのJSONデータが空です", 'ERROR', 'API');
                    Utils::sendErrorResponse('Invalid request data', 400);
                    return;
                }
                
                // リクエストデータをより詳細にログに記録
                Utils::log("注文リクエスト生データ: " . file_get_contents('php://input'), 'DEBUG', 'API');
                Utils::log("注文リクエストデータ(パース後): " . json_encode($data), 'INFO', 'API');
                
                // LINE IDの存在をチェックし、ログに記録
                $lineUserId = isset($data['line_user_id']) ? $data['line_user_id'] : 'なし';
                Utils::log("リクエストのLINE ID: {$lineUserId}", 'INFO', 'API');
                
                // 明示的に部屋番号をログに記録
                $requestedRoom = isset($data['roomNumber']) ? $data['roomNumber'] : 'なし';
                $authRoom = $roomInfo['room_number'];
                Utils::log("部屋番号比較 - リクエスト: {$requestedRoom}, 認証: {$authRoom}", 'INFO', 'API');
                
                // 認証情報の詳細をログに記録
                Utils::log("認証情報詳細: " . json_encode($roomInfo), 'INFO', 'API');
                Utils::log("認証方法: " . ($roomInfo['auth_method'] ?? 'token'), 'INFO', 'API');
                
                // 常に認証から取得した部屋番号を使用する
                $roomToUse = $authRoom;
                Utils::log("使用する部屋番号: {$roomToUse}", 'INFO', 'API');
                
                if (empty($data['items'])) {
                    Utils::log("注文アイテムが指定されていません", 'ERROR', 'API');
                    Utils::sendErrorResponse('Items are required', 400);
                    return;
                }
                
                $items = $data['items'];
                
                // 文字列として渡された場合はJSONデコード
                if (is_string($items)) {
                    try {
                        $decodedItems = json_decode($items, true);
                        if (is_array($decodedItems)) {
                            $items = $decodedItems;
                            Utils::log("文字列形式のitemsをデコードしました", 'INFO', 'API');
                        }
                    } catch (Exception $e) {
                        Utils::log("itemsのJSONデコードエラー: " . $e->getMessage(), 'ERROR', 'API');
                    }
                }
                
                // 最終チェック
                if (!is_array($items)) {
                    Utils::log("itemsが配列ではありません: " . gettype($items), 'ERROR', 'API');
                    Utils::sendErrorResponse('Items must be an array', 400);
                    return;
                }
            
            $orderService = new OrderService();
            Utils::log("OrderService::createOrder呼び出し直前 - 部屋番号: {$roomToUse}", 'INFO', 'API');
            $result = $orderService->createOrder(
                $roomToUse,
                $items,
                $roomInfo['guest_name'],
                $data['note'] ?? '',
                $data['line_user_id'] ?? ''
            );
            
            if (!$result) {
                    Utils::log("注文作成に失敗しました: " . $roomInfo['room_number'], 'ERROR', 'API');
                    
                    // SquareServiceとOrderServiceのログを確認して詳細なエラーを取得
                    $errorDetail = "";
                    if (file_exists(__DIR__ . '/../../logs/OrderService.log')) {
                        $lastLogLines = shell_exec('tail -20 ' . __DIR__ . '/../../logs/OrderService.log');
                        $errorDetail .= "OrderService: " . $lastLogLines;
                    }
                    
                    if (file_exists(__DIR__ . '/../../logs/SquareService.log')) {
                        $lastLogLines = shell_exec('tail -20 ' . __DIR__ . '/../../logs/SquareService.log');
                        $errorDetail .= "\nSquareService: " . $lastLogLines;
                    }
                    
                    Utils::log("詳細なエラーログ: " . $errorDetail, 'ERROR', 'API');
                    Utils::sendErrorResponse('Failed to create order. Please check server logs for details.', 500);
                return;
            }
            
                Utils::log("注文作成成功: " . json_encode($result), 'INFO', 'API');
            Utils::sendJsonResponse([
                'success' => true,
                'order' => $result
            ]);
            } catch (Exception $e) {
                Utils::log("注文処理中の例外: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR', 'API');
                Utils::sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
            }
            break;
            
        default:
            Utils::sendErrorResponse('Method not allowed', 405);
            break;
    }
}

/**
 * 注文履歴リクエストを処理
 */
function handleOrderHistoryRequest($method) {
    if ($method !== 'GET') {
        Utils::sendErrorResponse('Method not allowed', 405);
        return;
    }
    
    // トークン認証
    $auth = new Auth();
    $roomInfo = $auth->authenticateRequest();
    
    if (!$roomInfo) {
        Utils::sendErrorResponse('Unauthorized', 401);
        return;
    }
    
    $orderService = new OrderService();
    $orders = $orderService->getOrdersByRoom($roomInfo['room_number']);
    
    Utils::sendJsonResponse([
        'success' => true,
        'orders' => $orders
    ]);
}

/**
 * LINE認証リクエストを処理
 */
function handleLineAuthRequest($method) {
    // HTTPメソッドに応じて処理を分岐
    switch ($method) {
        case 'GET':
            // LINE User IDから部屋情報を取得
            include_once __DIR__ . '/auth/index.php';
            handleGetRequest(AuthService::getInstance());
            break;
            
        case 'POST':
            // LINEユーザーと部屋番号の紐づけを行う
            include_once __DIR__ . '/auth/index.php';
            handlePostRequest(AuthService::getInstance());
            break;
            
        default:
            // サポートされていないメソッド
            Utils::sendErrorResponse('Method not allowed', 405);
            break;
    }
} 