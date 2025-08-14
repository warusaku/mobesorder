<?php
/**
 * 決済処理API
 * 
 * Square Web Payment SDKから送信されたカードnonceを使用して決済を処理します。
 */
require_once 'config/config.php';
require_once 'lib/Utils.php';
require_once 'lib/SquareService.php';

// CORSヘッダーを設定
Utils::setCorsHeaders();

// POSTメソッド以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::sendErrorResponse('Method Not Allowed', 405);
}

try {
    // リクエストボディからJSONデータを取得
    $data = Utils::getJsonInput();
    
    // 必要なパラメータが存在するかチェック
    if (empty($data['sourceId']) || empty($data['orderId']) || !isset($data['amount'])) {
        Utils::sendErrorResponse('必須パラメータが不足しています（sourceId, orderId, amount）', 400);
    }
    
    // 金額が有効かチェック
    if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
        Utils::sendErrorResponse('無効な金額です', 400);
    }
    
    // Square決済サービスのインスタンスを作成
    $squareService = new SquareService();
    
    // 決済処理実行
    $paymentResult = $squareService->processPayment(
        $data['orderId'], 
        $data['amount'],
        $data['sourceId']
    );
    
    // 成功レスポンス
    Utils::sendJsonResponse([
        'success' => true,
        'payment_id' => $paymentResult['payment_id'],
        'status' => $paymentResult['status'],
        'amount' => $paymentResult['amount'],
        'receipt_url' => $paymentResult['receipt_url']
    ]);
    
} catch (Exception $e) {
    // エラーログ記録
    Utils::log("Payment API Error: " . $e->getMessage(), 'ERROR', 'payment_api');
    
    // エラーレスポンス
    Utils::sendErrorResponse($e->getMessage(), 400);
} 