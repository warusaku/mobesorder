<?php
/**
 * Square API連携サービス 決済管理クラス
 * Version: 1.0.0
 * Description: 決済処理を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';
require_once __DIR__ . '/SquareService_Utility.php';

use Square\Exceptions\ApiException;

class SquareService_Payment extends SquareService_Base {
    
    private $utilityService;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct();
        $this->utilityService = new SquareService_Utility();
    }
    
    /**
     * 決済処理を実行
     * 
     * @param string $orderId 注文ID
     * @param float $amount 金額
     * @param string $sourceId カード支払いソースID（Square Web Payment SDKから取得したnonce）
     * @return array 決済結果情報
     * @throws Exception 決済処理に失敗した場合
     */
    public function processPayment($orderId, $amount, $sourceId) {
        if (empty($sourceId)) {
            throw new Exception("支払いソースIDが必要です。カード情報から有効なnonceを取得してください。");
        }
        
        try {
            // 支払いAPI
            $paymentsApi = $this->client->getPaymentsApi();
            
            // 金額オブジェクトの作成（日本円の場合は整数）
            $amountMoney = new \Square\Models\Money();
            $amountMoney->setAmount((int)$amount);
            $amountMoney->setCurrency('JPY');
            
            // 決済リクエストの作成
            $createPaymentRequest = new \Square\Models\CreatePaymentRequest(
                $sourceId,
                uniqid('payment_', true),
                $amountMoney
            );
            
            // 関連注文IDの設定
            $createPaymentRequest->setOrderId($orderId);
            
            // 受領書の設定
            $createPaymentRequest->setReceiptUrl(BASE_URL . '/receipts/' . $orderId);
            
            // メモの設定
            $createPaymentRequest->setNote('LacisMobileOrder - Room Order');
            
            // 決済実行
            $response = $paymentsApi->createPayment($createPaymentRequest);
            
            if ($response->isSuccess()) {
                $result = $response->getResult();
                $payment = $result->getPayment();
                
                $this->logger->logMessage("Payment Created: " . $payment->getId(), 'INFO');
                
                // 支払い情報を返す
                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'order_id' => $payment->getOrderId(),
                    'amount' => $this->utilityService->formatMoney($payment->getAmountMoney()),
                    'status' => $payment->getStatus(),
                    'created_at' => $payment->getCreatedAt(),
                    'card_details' => $payment->getCardDetails() ? [
                        'card_brand' => $payment->getCardDetails()->getCard()->getCardBrand(),
                        'last_4' => $payment->getCardDetails()->getCard()->getLast4(),
                        'exp_month' => $payment->getCardDetails()->getCard()->getExpMonth(),
                        'exp_year' => $payment->getCardDetails()->getCard()->getExpYear()
                    ] : null,
                    'receipt_url' => $payment->getReceiptUrl()
                ];
            } else {
                $errors = $response->getErrors();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getDetail();
                }
                throw new Exception(implode(", ", $errorMessages));
            }
        } catch (ApiException $e) {
            $this->logger->logMessage("Payment Processing Failed: " . $e->getMessage(), 'ERROR');
            throw new Exception("決済処理に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * Session 注文 (order_id) を現金支払いとして即時決済する (Sandbox / 本番共用)
     * Webhook 発火目的で使用。
     *
     * @param string $orderId Square Order ID (createSessionOrder で取得)
     * @return array|false 成功時 payment 情報, 失敗時 false
     */
    public function createSessionCashPayment($orderId) {
        $this->logger->logMessage("createSessionCashPayment 開始: order={$orderId}", 'INFO');
        
        try {
            // まず注文金額を取得
            $orderApi = $this->client->getOrdersApi();
            $oResp = $orderApi->retrieveOrder($orderId);
            
            if (!$oResp->isSuccess()) {
                $this->logger->logMessage('retrieveOrder error: ' . json_encode($oResp->getErrors()), 'ERROR');
                return false;
            }
            
            $order = $oResp->getResult()->getOrder();
            
            // OPEN 注文は total_money が null のため自前計算
            $subtotal = 0;
            $lineItems = $order->getLineItems() ?? [];
            
            foreach ($lineItems as $li) {
                $qty = (int)$li->getQuantity();
                $priceMoney = $li->getBasePriceMoney();
                if ($priceMoney) {
                    $unit = $priceMoney->getAmount(); // セント単位
                    $subtotal += $unit * $qty;
                }
            }
            
            if ($subtotal <= 0) {
                $this->logger->logMessage('計算された注文金額が 0 以下です', 'ERROR');
                return false;
            }
            
            $amountMoney = new \Square\Models\Money();
            $amountMoney->setAmount($subtotal);
            $amountMoney->setCurrency('JPY');

            $paymentsApi = $this->client->getPaymentsApi();
            
            // CASH payment: amount_money は必須 (Square SDK v2023 以降は setter で指定)
            $idempotencyKey = uniqid('cashpay_', true);
            $req = new \Square\Models\CreatePaymentRequest('CASH', $idempotencyKey);
            $req->setAmountMoney($amountMoney);          // ← 新 API 仕様
            $req->setOrderId($orderId);

            // CashPaymentDetails を設定して現金決済として明示
            $cashDetails = new \Square\Models\CashPaymentDetails($amountMoney);
            $req->setCashDetails($cashDetails);

            $pResp = $paymentsApi->createPayment($req);
            
            if ($pResp->isSuccess()) {
                $payId = $pResp->getResult()->getPayment()->getId();
                $this->logger->logMessage("Cash payment success pay_id={$payId}", 'INFO');
                return ['payment_id' => $payId];
            } else {
                $this->logger->logMessage('createPayment error: ' . json_encode($pResp->getErrors()), 'ERROR');
                return false;
            }
        } catch (ApiException $e) {
            $this->logger->logMessage('createSessionCashPayment ApiException: ' . $e->getMessage(), 'ERROR');
            return false;
        } catch (\Throwable $ex) {
            $this->logger->logMessage('createSessionCashPayment exception: ' . $ex->getMessage(), 'ERROR');
            return false;
        }
    }
} 