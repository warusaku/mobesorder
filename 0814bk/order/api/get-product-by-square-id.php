<?php
require_once '../../config/database.php';
require_once '../lib/Database.php';
require_once '../lib/Utils.php';
require_once '../lib/SquareService.php';

// CORSヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// エラーレスポンスの共通関数
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

// Square Item IDの取得
$squareItemId = isset($_GET['square_item_id']) ? $_GET['square_item_id'] : '';

if (empty($squareItemId)) {
    sendErrorResponse('Square Item IDが指定されていません', 400);
}

try {
    // データベース接続
    $db = Database::getInstance();
    
    // Square Item IDから商品を検索
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.square_item_id = :square_item_id OR p.square_id = :square_id
              LIMIT 1";
    
    $params = [
        ':square_item_id' => $squareItemId,
        ':square_id' => $squareItemId
    ];
    
    $product = $db->selectOne($query, $params);
    
    if (!$product) {
        // SquareサービスからAPIで取得を試みる
        $squareService = new SquareService();
        $squareItems = $squareService->getItems();
        
        if (is_array($squareItems)) {
            foreach ($squareItems as $item) {
                if ($item['id'] === $squareItemId || (isset($item['square_item_id']) && $item['square_item_id'] === $squareItemId)) {
                    $product = $item;
                    break;
                }
            }
        }
    }
    
    if (!$product) {
        sendErrorResponse('指定されたIDの商品が見つかりません', 404);
    }
    
    // 正常レスポンス
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (Exception $e) {
    Utils::log("商品検索エラー: " . $e->getMessage(), "ERROR", "API");
    sendErrorResponse('商品の検索中にエラーが発生しました: ' . $e->getMessage(), 500);
} 