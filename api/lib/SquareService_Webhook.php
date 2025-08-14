<?php
/**
 * Square API連携サービス Webhook管理クラス
 * Version: 1.0.0
 * Description: Webhookの検証と送信を担当するクラス
 */
require_once __DIR__ . '/SquareService_Base.php';

class SquareService_Webhook extends SquareService_Base {
    
    /**
     * Webhookの署名を検証
     * 
     * @param string $signatureHeader Squareから送信された署名ヘッダー
     * @param string $requestBody リクエストボディ
     * @return bool 署名が有効な場合はtrue
     */
    public function validateWebhookSignature($signatureHeader, $requestBody) {
        // ---- Signature Key を Base64 デコード ----
        if (empty(SQUARE_WEBHOOK_SIGNATURE_KEY)) {
            return false; // 設定なし
        }

        $rawKey      = SQUARE_WEBHOOK_SIGNATURE_KEY;
        $keyStr = SQUARE_WEBHOOK_SIGNATURE_KEY;
        // Base64 文字列のパディングを補正（長さが4の倍数になるよう=追加）
        $padLen = 4 - (strlen($keyStr) % 4);
        if($padLen<4){ $keyStr .= str_repeat('=', $padLen); }

        $decodedKey  = base64_decode($keyStr, true);
        $hasDecoded  = ($decodedKey !== false);
        if(!$hasDecoded){
            $this->logger->logMessage('Signature key base64 decode failed; decoded path will be skipped', 'INFO');
        }

        // === Square Webhook 署名は notification_url + body が対象 ===
        $notificationUrl = 'https://mobes.online/api/webhook/square.php'; // ダッシュボード登録値と完全一致
        $dataForSig      = $notificationUrl . $requestBody;

        // 新方式: X-Square-HmacSHA256-Signature（Base64 HMAC-SHA256）
        if(isset($_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'])){
            $recvSig = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'];
            // calc with raw key
            $calcRaw = base64_encode(hash_hmac('sha256', $dataForSig, $rawKey, true));
            // calc with decoded key if any
            $calcDec = $hasDecoded ? base64_encode(hash_hmac('sha256', $dataForSig, $decodedKey, true)) : '';
            Utils::log("SigCheck SHA256 recv={$recvSig} calcRaw={$calcRaw} calcDec={$calcDec}", 'INFO','SquareWebhook');
            if(hash_equals($calcRaw,$recvSig) || ($hasDecoded && hash_equals($calcDec,$recvSig))){
                return true;
            }
            return false;
        }

        // 旧方式: X-Square-Signature（Base64 HMAC-SHA1）
        if(isset($_SERVER['HTTP_X_SQUARE_SIGNATURE'])){
            $recvSig = $_SERVER['HTTP_X_SQUARE_SIGNATURE'];
            $calcRaw = base64_encode(hash_hmac('sha1', $dataForSig, $rawKey, true));
            $calcDec = $hasDecoded ? base64_encode(hash_hmac('sha1', $dataForSig, $decodedKey, true)) : '';
            Utils::log("SigCheck SHA1 recv={$recvSig} calcRaw={$calcRaw} calcDec={$calcDec}", 'INFO','SquareWebhook');
            if(hash_equals($calcRaw,$recvSig) || ($hasDecoded && hash_equals($calcDec,$recvSig))){
                return true;
            }
            return false;
        }

        // さらに旧い timestamp + v1 形式
        if (empty($signatureHeader) || empty(SQUARE_WEBHOOK_SIGNATURE_KEY)) {
            return false;
        }

        $signature = '';
        $timestamp = '';

        $elements = explode(',', $signatureHeader);
        foreach ($elements as $element) {
            if (strpos($element, 't=') === 0) {
                $timestamp = substr($element, 2);
            } elseif (strpos($element, 'v1=') === 0) {
                $signature = substr($element, 3);
            }
        }

        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        $stringToSign = $timestamp . '.' . $requestBody;
        $hmacRaw = hash_hmac('sha256', $stringToSign, $rawKey, false);
        $hmacDec = $hasDecoded ? hash_hmac('sha256', $stringToSign, $decodedKey, false) : '';
        Utils::log("SigCheck v1 recv={$signature} calcRaw={$hmacRaw} calcDec={$hmacDec}", 'INFO','SquareWebhook');
        if(hash_equals($hmacRaw,$signature) || ($hasDecoded && hash_equals($hmacDec,$signature))){
            return true;
        }
        return false;
    }
    
    /**
     * price mismatch など致命的エラーを通知する Webhook 送信
     *
     * @param string $eventType
     * @param array  $payload
     */
    public function sendWebhookEvent($eventType, $payload){
        $settings = $this->getSquareSettings();
        if(!isset($settings['order_webhooks']) || !is_array($settings['order_webhooks'])){
            $this->logger->logMessage("Webhook URL の設定がありません: event={$eventType}", 'WARNING');
            return;
        }
        
        $body = json_encode(array_merge(['event_type'=>$eventType,'timestamp'=>date('c')],$payload), JSON_UNESCAPED_UNICODE);
        
        foreach($settings['order_webhooks'] as $url){
            if(!filter_var($url, FILTER_VALIDATE_URL)) continue;
            
            // 非同期送信 (1.5s タイムアウト)
            $ch = curl_init($url);
            curl_setopt_array($ch,[
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>$body,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Content-Length: '.strlen($body)],
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_TIMEOUT=>1.5,
                CURLOPT_CONNECTTIMEOUT=>1.0,
            ]);
            
            curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            
            if($err){
                $this->logger->logMessage("Webhook送信エラー({$url}): {$err}", 'WARNING');
            }else{
                $this->logger->logMessage("Webhook送信完了({$url}) event={$eventType}", 'INFO');
            }
        }
    }
} 