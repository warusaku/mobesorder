<?php
/**
 * 商品詳細を取得するAPIエンドポイント
 * 
 * 引数:
 * - id: 商品ID
 * - square_item_id: Square商品ID (idが指定されていない場合に使用)
 * 
 * 【重要】商品情報・ラベル情報のハードコードは禁止
 * すべての商品情報とラベル情報はデータベースから動的に取得すること
 * 特定の商品IDに依存した処理も禁止
 * - 商品ID、商品名、ラベル情報などの値をコード内に直接記述してはならない
 * - すべての情報はAPIを通じてDBから取得すること
 * - 特定商品の例外処理・条件分岐も実装してはならない
 */

// エラー出力をバッファに保存するために出力バッファリングを開始
ob_start();

// デバッグモード有効化（問題解決後に削除可能）
// エラー表示を無効化（JSONレスポンスを保護するため）
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// グローバル変数の初期化
$rootDir = dirname(dirname(dirname(__FILE__))); // fgsquareディレクトリへのパス
$logDir = $rootDir . '/logs';
$logFile = $logDir . '/get-product-details.log';
$productId = isset($_GET['id']) ? $_GET['id'] : null;
$squareItemId = isset($_GET['square_item_id']) ? $_GET['square_item_id'] : null;

// ログファイルの準備
if (!file_exists($logDir)) {
    try {
        mkdir($logDir, 0755, true);
    } catch (Exception $e) {
        // エラーは無視して続行
    }
}

// デバッグログ関数
function debug_log($message) {
    global $logFile, $productId, $squareItemId;
    
    if (!$logFile) {
        error_log("Product Details API: [$productId, $squareItemId] $message");
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $requestInfo = "ID={$productId}, SquareID={$squareItemId}";
    
    @file_put_contents($logFile, "[$timestamp] [$requestInfo] $message\n", FILE_APPEND);
}

// エラーレスポンス関数
function sendErrorResponse($message, $code = 400) {
    global $logFile, $productId;
    
    // ログに記録
    debug_log("エラー: $message (コード: $code)");
    
    // 出力バッファをクリア
    ob_end_clean();
    
    // HTTPステータスコード
    http_response_code($code);
    
    // JSONレスポンス
    echo json_encode([
        'success' => false, 
        'error' => $message
    ]);
    exit;
}

// メイン処理
try {
    // レスポンスヘッダー設定
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    
    debug_log("=== 商品詳細API リクエスト開始 ===");
    
    // パラメータチェック
    if (!$productId && !$squareItemId) {
        sendErrorResponse('Product ID or Square Item ID is required');
    }
    
    // 重要な設定ファイルとライブラリの読み込み
    $configPath = $rootDir . '/api/config/config.php';
    $dbLibPath = $rootDir . '/api/lib/Database.php';
    
    if (!file_exists($configPath)) {
        debug_log("エラー: 設定ファイルが見つかりません: $configPath");
        sendErrorResponse("設定ファイルが見つかりません", 500);
    }
    
    if (!file_exists($dbLibPath)) {
        debug_log("エラー: データベースライブラリが見つかりません: $dbLibPath");
        sendErrorResponse("データベースライブラリが見つかりません", 500);
    }
    
    debug_log("設定ファイルパス: $configPath");
    debug_log("DBライブラリパス: $dbLibPath");
    
    require_once $configPath;
    require_once $dbLibPath;
    
    // データベース接続
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", 
            DB_USER, 
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        debug_log("データベース接続成功");
    } catch (PDOException $e) {
        debug_log("DB接続エラー: " . $e->getMessage());
        sendErrorResponse("データベース接続エラー: " . $e->getMessage(), 500);
    }
    
    // 商品クエリ構築
    $query = "
        SELECT p.id, p.square_item_id, p.name, p.description, p.price, 
               p.image_url, p.stock_quantity, p.local_stock_quantity, 
               p.category, p.category_name, p.is_active, 
               p.item_pickup, p.item_label1, p.item_label2
        FROM products p
        WHERE ";
    
    if ($productId) {
        $query .= "p.id = :id";
        $param = $productId;
        $paramName = ':id';
    } else {
        $query .= "p.square_item_id = :square_item_id";
        $param = $squareItemId;
        $paramName = ':square_item_id';
    }
    
    debug_log("実行クエリ: $query ($paramName=$param)");
    
    // 商品データ取得
    $stmt = $conn->prepare($query);
    $stmt->bindParam($paramName, $param);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendErrorResponse('Product not found', 404);
    }
    
    debug_log("商品データ取得成功: ID=" . $product['id'] . ", 商品名=" . $product['name']);
    debug_log("商品ラベル情報: item_label1=" . $product['item_label1'] . ", item_label2=" . $product['item_label2']);
    
    // ラベル情報を取得
    $labelQuery = "SELECT label_id, label_text, label_color FROM item_label";
    $stmtLabel = $conn->prepare($labelQuery);
    $stmtLabel->execute();
    $labels = $stmtLabel->fetchAll(PDO::FETCH_ASSOC);
    
    debug_log("ラベル取得件数: " . count($labels) . "件");
    
    // 使用可能なラベル情報を詳細にログ記録
    debug_log("=== 使用可能なラベル情報 ===");
    foreach ($labels as $label) {
        debug_log("ラベルID: {$label['label_id']}, テキスト: {$label['label_text']}, 色: {$label['label_color']}");
    }
    
    // ラベル情報をマッピング（文字列型のキーに統一）
    $labelMap = [];
    foreach ($labels as $label) {
        // ラベルIDを必ず文字列として扱う
        $labelId = (string)$label['label_id'];
        $labelMap[$labelId] = [
            'text' => $label['label_text'],
            'color' => $label['label_color']
        ];
        debug_log("ラベルマップ登録: キー=\"$labelId\", 値={\"text\":\"{$label['label_text']}\", \"color\":\"{$label['label_color']}\"}");
    }
    
    // 使用可能なすべてのキーを記録
    debug_log("ラベルマップのキー: " . implode(", ", array_keys($labelMap)));
    
    // 商品にラベル情報を追加
    $product['label1_info'] = null;
    $product['label2_info'] = null;
    
    // item_label1の処理（nullでない場合のみ処理）
    if (!empty($product['item_label1'])) {
        // 元の値と型を記録
        $rawLabel1 = $product['item_label1'];
        debug_log("商品ID={$product['id']} item_label1の元の値: $rawLabel1, 型: " . gettype($rawLabel1));
        
        // ラベルIDを文字列として扱う
        $itemLabel1 = (string)$product['item_label1'];
        debug_log("変換後のitem_label1: \"$itemLabel1\", 型: " . gettype($itemLabel1));
        
        // ラベルマップからラベル情報を取得
        if (isset($labelMap[$itemLabel1])) {
            $product['label1_info'] = $labelMap[$itemLabel1];
            debug_log("ラベル1情報設定: " . json_encode($product['label1_info']));
        } else {
            debug_log("ラベル1情報取得失敗: item_label1=\"$itemLabel1\", マップに存在=" . (isset($labelMap[$itemLabel1]) ? 'はい' : 'いいえ'));
        }
    } else {
        debug_log("item_label1が設定されていないか空のため、ラベル情報をスキップ");
    }
    
    // item_label2の処理（nullでない場合のみ処理）
    if (!empty($product['item_label2'])) {
        // 元の値と型を記録
        $rawLabel2 = $product['item_label2'];
        debug_log("商品ID={$product['id']} item_label2の元の値: $rawLabel2, 型: " . gettype($rawLabel2));
        
        // ラベルIDを文字列として扱う
        $itemLabel2 = (string)$product['item_label2'];
        debug_log("変換後のitem_label2: \"$itemLabel2\", 型: " . gettype($itemLabel2));
        
        // ラベルマップからラベル情報を取得
        if (isset($labelMap[$itemLabel2])) {
            $product['label2_info'] = $labelMap[$itemLabel2];
            debug_log("ラベル2情報設定: " . json_encode($product['label2_info']));
        } else {
            debug_log("ラベル2情報取得失敗: item_label2=\"$itemLabel2\", マップに存在=" . (isset($labelMap[$itemLabel2]) ? 'はい' : 'いいえ'));
        }
    } else {
        debug_log("item_label2が設定されていないか空のため、ラベル情報をスキップ");
    }
    
    // 説明文がない場合のデフォルト値
    if (empty($product['description'])) {
        $product['description'] = '商品説明はありません。';
    }
    
    // レスポンス作成
    $response = [
        'success' => true,
        'data' => $product
    ];
    
    debug_log("レスポンス生成完了");
    debug_log("=== 商品詳細API リクエスト終了 ===");
    
    // 出力バッファをクリア（エラー出力など意図しない出力があれば削除）
    ob_end_clean();
    
    // レスポンス送信
    echo json_encode($response);
    
} catch (Exception $e) {
    // 出力バッファをクリア
    ob_end_clean();
    
    // エラーログ
    error_log("商品詳細API致命的エラー: " . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine());
    debug_log("致命的エラー: " . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine());
    
    // HTTPステータスコード
    http_response_code(500);
    
    // JSONエラーレスポンス
    echo json_encode([
        'success' => false,
        'error' => 'システムエラーが発生しました',
        'debug' => $e->getMessage()
    ]);
} 