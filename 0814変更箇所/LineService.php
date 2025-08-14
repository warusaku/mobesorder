<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;

/**
 * LINE Messaging API連携サービスクラス
 */
class LineService {
    private $bot;
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $httpClient = new CurlHTTPClient(LINE_CHANNEL_ACCESS_TOKEN);
        $this->bot = new LINEBot($httpClient, ['channelSecret' => LINE_CHANNEL_SECRET]);
        $this->db = Database::getInstance();
    }
    
    /**
     * テキストメッセージを送信
     * 
     * @param string $userId LINE ユーザーID
     * @param string $message 送信するメッセージ
     * @return bool 成功した場合はtrue
     */
    public function sendTextMessage($userId, $message) {
        try {
            $textMessageBuilder = new TextMessageBuilder($message);
            $response = $this->bot->pushMessage($userId, $textMessageBuilder);
            
            if ($response->isSucceeded()) {
                Utils::log("LINE message sent to $userId", 'INFO', 'LineService');
                return true;
            } else {
                Utils::log("LINE API Error: " . $response->getHTTPStatus() . ' ' . $response->getRawBody(), 'ERROR', 'LineService');
                return false;
            }
        } catch (Exception $e) {
            Utils::log("LINE Exception: " . $e->getMessage(), 'ERROR', 'LineService');
            return false;
        }
    }
    
    /**
     * 注文完了通知を送信
     * 
     * @param string $userId LINE ユーザーID
     * @param array $orderData 注文データ
     * @return bool 成功した場合はtrue
     */
    public function sendOrderCompletionNotice($userId, $orderData) {
        $roomNumber = $orderData['room_number'] ?? '';
        $orderId = $orderData['id'] ?? '';
        $totalAmount = $orderData['total_amount'] ?? 0;
        
        $message = "ご注文ありがとうございます。\n"
                 . "注文番号: {$orderId}\n"
                 . "合計金額: ¥{$totalAmount}\n"
                 . "お部屋({$roomNumber})までお届けします。";
                 
        return $this->sendTextMessage($userId, $message);
    }
    
    /**
     * モバイルオーダーへのリンクを送信
     * 
     * @param string $userId LINE ユーザーID
     * @param string $token アクセストークン
     * @return bool 成功した場合はtrue
     */
    public function sendOrderLink($userId, $token) {
        try {
            $appUrl = BASE_URL . "/app?token={$token}";
            
            $buttonTemplate = new ButtonTemplateBuilder(
                'モバイルオーダー',
                'ルームサービスをご注文いただけます。下のボタンをタップしてください。',
                null,
                [new UriTemplateActionBuilder('注文する', $appUrl)]
            );
            
            $templateMessage = new TemplateMessageBuilder('モバイルオーダーのご案内', $buttonTemplate);
            $response = $this->bot->pushMessage($userId, $templateMessage);
            
            if ($response->isSucceeded()) {
                Utils::log("LINE order link sent to $userId", 'INFO', 'LineService');
                return true;
            } else {
                Utils::log("LINE API Error: " . $response->getHTTPStatus() . ' ' . $response->getRawBody(), 'ERROR', 'LineService');
                return false;
            }
        } catch (Exception $e) {
            Utils::log("LINE Exception: " . $e->getMessage(), 'ERROR', 'LineService');
            return false;
        }
    }
    
    /**
     * Webhookの署名を検証
     * 
     * @param string $signature リクエストヘッダーの署名
     * @param string $body リクエストボディ
     * @return bool 署名が有効な場合はtrue
     */
    public function validateSignature($signature, $body) {
        return $this->bot->validateSignature($body, LINE_CHANNEL_SECRET, $signature);
    }
    
    /**
     * LINE ユーザーIDと部屋を紐付け
     * 
     * @param string $userId LINE ユーザーID
     * @param string $roomNumber 部屋番号
     * @param string $token アクセストークン
     * @return bool 成功した場合はtrue
     */
    public function linkUserToRoom($userId, $roomNumber, $token) {
        try {
            // 既存の紐付けを無効化
            $this->db->execute(
                "UPDATE line_room_links SET is_active = 0 WHERE line_user_id = ?",
                [$userId]
            );
            
            // 新しい紐付けを作成
            $result = $this->db->execute(
                "INSERT INTO line_room_links (line_user_id, room_number, access_token, is_active) 
                 VALUES (?, ?, ?, 1)",
                [$userId, $roomNumber, $token]
            );
            
            if ($result) {
                Utils::log("LINE user $userId linked to room $roomNumber", 'INFO', 'LineService');
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            Utils::log("LINE link error: " . $e->getMessage(), 'ERROR', 'LineService');
            return false;
        }
    }
    
    /**
     * LINE ユーザーIDから部屋情報を取得
     * 
     * @param string $userId LINE ユーザーID
     * @return array|null 部屋情報、または紐付けがない場合はnull
     */
    public function getRoomByUserId($userId) {
        return $this->db->selectOne(
            "SELECT l.room_number, l.access_token, r.guest_name, r.check_in_date, r.check_out_date
             FROM line_room_links l
             JOIN room_tokens r ON l.room_number = r.room_number AND r.is_active = 1
             WHERE l.line_user_id = ? AND l.is_active = 1",
            [$userId]
        );
    }
} 