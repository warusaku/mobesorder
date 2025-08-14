<?php
/**
 * 商品表示順管理API
 * 
 * このAPIは商品の表示順と表示/非表示設定を管理するためのエンドポイントを提供します。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// セッション開始
session_start();

// ログイン状態をチェック
if (!isset($_SESSION['auth_user'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => '認証が必要です'
    ]);
    exit;
}

// ログ関数
function logMessage($message, $level = 'INFO') {
    Utils::log($message, $level, 'ProductDisplayAPI');
}

// データベース接続
$db = Database::getInstance();

// レスポンス変数の初期化
$response = [
    'success' => false,
    'message' => 'リクエストが無効です'
];

// アクションパラメータを取得
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // カテゴリ一覧の取得
        case 'get_categories':
            $categories = $db->select(
                "SELECT id, category_id, category_name, display_order, is_active 
                FROM category_descripter 
                ORDER BY display_order ASC, category_name ASC"
            );
            
            $response = [
                'success' => true,
                'categories' => $categories
            ];
            break;
            
        // カテゴリ内の商品一覧を取得
        case 'get_products':
            $categoryId = $_GET['category'] ?? '';
            
            if (empty($categoryId)) {
                throw new Exception('カテゴリIDが指定されていません');
            }
            
            $products = $db->select(
                "SELECT id, square_item_id, name, price, is_active, updated_at, sort_order, order_dsp,
                        item_pickup, item_label1, item_label2
                FROM products 
                WHERE category = ? AND presence = 1
                ORDER BY COALESCE(sort_order, 999999), id ASC",
                [$categoryId]
            );
            
            // ラベル情報も取得
            $labels = $db->select("SELECT * FROM item_label ORDER BY label_id");
            $labelMap = [];
            if ($labels) {
                foreach ($labels as $label) {
                    $labelMap[$label['label_id']] = [
                        'text' => $label['label_text'],
                        'color' => $label['label_color']
                    ];
                }
            }
            
            // 商品データにラベル情報を追加
            if ($products) {
                foreach ($products as &$product) {
                    // ラベル情報の追加
                    $product['label1_info'] = null;
                    $product['label2_info'] = null;
                    
                    if (!empty($product['item_label1']) && isset($labelMap[$product['item_label1']])) {
                        $product['label1_info'] = $labelMap[$product['item_label1']];
                    }
                    
                    if (!empty($product['item_label2']) && isset($labelMap[$product['item_label2']])) {
                        $product['label2_info'] = $labelMap[$product['item_label2']];
                    }
                }
            }
            
            $response = [
                'success' => true,
                'products' => $products,
                'category_id' => $categoryId,
                'labels' => $labels ?: []
            ];
            break;
            
        // 商品の表示設定を更新
        case 'update_settings':
            // POSTリクエストであることを確認
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POSTメソッドが必要です');
            }
            
            // JSONデータを取得
            $jsonData = file_get_contents('php://input');
            $data = json_decode($jsonData, true);
            
            if (!$data || !isset($data['products']) || !is_array($data['products'])) {
                throw new Exception('無効なデータ形式です');
            }
            
            // トランザクション開始
            $db->beginTransaction();
            
            $updatedCount = 0;
            
            foreach ($data['products'] as $product) {
                if (!isset($product['id'])) {
                    continue;
                }
                
                $id = (int)$product['id'];
                $sortOrder = isset($product['sort_order']) ? (int)$product['sort_order'] : null;
                $orderDsp = isset($product['order_dsp']) ? ($product['order_dsp'] ? 1 : 0) : 1;
                
                // 新しいフィールド
                $itemPickup = isset($product['item_pickup']) ? ($product['item_pickup'] ? 1 : 0) : 0;
                $itemLabel1 = isset($product['item_label1']) ? $product['item_label1'] : null;
                $itemLabel2 = isset($product['item_label2']) ? $product['item_label2'] : null;
                
                // SQL文とパラメータを構築
                $sql = "UPDATE products SET sort_order = ?, order_dsp = ?, item_pickup = ?, ";
                $params = [$sortOrder, $orderDsp, $itemPickup];
                
                // ラベルが設定されている場合のみパラメータに追加
                if ($itemLabel1 !== null) {
                    $sql .= "item_label1 = ?, ";
                    $params[] = $itemLabel1 ?: null;
                } else {
                    $sql .= "item_label1 = item_label1, ";
                }
                
                if ($itemLabel2 !== null) {
                    $sql .= "item_label2 = ?, ";
                    $params[] = $itemLabel2 ?: null;
                } else {
                    $sql .= "item_label2 = item_label2, ";
                }
                
                $sql .= "updated_at = NOW() WHERE id = ?";
                $params[] = $id;
                
                $result = $db->execute($sql, $params);
                
                if ($result) {
                    $updatedCount++;
                }
            }
            
            // トランザクションをコミット
            $db->commit();
            
            $response = [
                'success' => true,
                'message' => '商品表示設定を更新しました',
                'updated_count' => $updatedCount
            ];
            
            // ログを記録
            logMessage("商品表示設定が更新されました: {$updatedCount}件");
            break;
            
        default:
            throw new Exception('無効なアクションです');
    }
} catch (Exception $e) {
    // エラーが発生した場合はロールバック
    if ($action === 'update_settings' && isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    // エラーレスポンスを設定
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    // ログを記録
    logMessage("エラー: " . $e->getMessage(), 'ERROR');
}

// JSONレスポンスを返す
echo json_encode($response, JSON_UNESCAPED_UNICODE); 