<?php
/**
 * Square API連携サービス ユーティリティクラス
 * Version: 1.0.0
 * Description: 共通のユーティリティ機能を提供するクラス
 */
require_once __DIR__ . '/SquareService_Logger.php';

use Square\Models\Money;

class SquareService_Utility {
    
    private $logger;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->logger = SquareService_Logger::getInstance();
    }
    
    /**
     * Money型をフォーマットして返す
     * 
     * @param Money|null $money Square MoneyまたはMoney互換のオブジェクト
     * @return float|int|null フォーマット済みの金額（円）
     */
    public function formatMoney($money) {
        // null または undefined の場合は0を返す
        if ($money === null) {
            return 0;
        }

        try {
            // Money型から値を取得
            $amount = $money->getAmount();
            
            // 単位がセント（=1/100円）で保存されているので、円表示に変換
            if (is_int($amount)) {
                return $amount / 100;
            }
            
            return $amount;
        } catch (Exception $e) {
            // エラー時は0を返す
            $this->logger->logMessage("金額の変換中にエラーが発生: " . $e->getMessage(), 'WARNING');
            return 0;
        }
    }
    
    /**
     * 注文の商品リストをフォーマット
     * 
     * @param array $lineItems 注文商品リスト
     * @return array フォーマット済み商品リスト
     */
    public function formatLineItems($lineItems) {
        if (!$lineItems) {
            return [];
        }
        
        $formattedItems = [];
        try {
            foreach ($lineItems as $item) {
                // 安全に値を取得
                $formattedItem = [
                    'uid' => $item->getUid() ?? '',
                    'name' => $item->getName() ?? 'Unknown Item',
                    'quantity' => $item->getQuantity() ?? 0,
                    'base_price_money' => $this->formatMoney($item->getBasePriceMoney()),
                    'variation_name' => $item->getVariationName() ?? '',
                    'note' => $item->getNote() ?? ''
                ];
                
                $formattedItems[] = $formattedItem;
            }
        } catch (\Throwable $e) {
            $this->logger->logMessage("LineItems処理中にエラー: " . $e->getMessage(), 'ERROR');
            // エラー発生時でも空の配列は返す
        }
        
        return $formattedItems;
    }
    
    /**
     * LINE User IDからguest_name情報を設定するメソッド
     * @param array &$metadata メタデータ配列（参照渡し）
     * @param string $lineUserId LINE User ID
     * @param string $roomNumber 部屋番号
     * @return bool 設定成功したかどうか
     */
    public function setupGuestNameFromLineUserId(&$metadata, $lineUserId, $roomNumber) {
        // 既にguest_nameが設定されている場合は何もしない
        if (!empty($metadata['guest_name'])) {
            return true;
        }

        try {
            // LINE User IDからuser_nameを取得
            if (!empty($lineUserId)) {
                $this->logger->logMessage("LINE User ID ({$lineUserId}) からユーザー情報取得を試行", 'INFO');
                
                // データベース接続を取得
                require_once __DIR__ . '/Database.php';
                $db = Database::getInstance()->getConnection();
                
                $stmt = $db->prepare("SELECT user_name FROM line_room_links WHERE line_user_id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$lineUserId]);
                $userData = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($userData && !empty($userData['user_name'])) {
                    $metadata['guest_name'] = $userData['user_name'];
                    $this->logger->logMessage("LINE情報からguest_nameを設定: {$userData['user_name']}", 'INFO');
                    return true;
                }
            }
            
            // LINE User IDで取得できなかった場合は部屋番号から取得
            if (!empty($roomNumber)) {
                $this->logger->logMessage("部屋番号 ({$roomNumber}) からユーザー情報取得を試行", 'INFO');
                
                if (!isset($db)) {
                    require_once __DIR__ . '/Database.php';
                    $db = Database::getInstance()->getConnection();
                }
                
                $stmt = $db->prepare("SELECT user_name FROM line_room_links WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
                $stmt->execute([$roomNumber]);
                $roomData = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($roomData && !empty($roomData['user_name'])) {
                    $metadata['guest_name'] = $roomData['user_name'];
                    $this->logger->logMessage("部屋番号からguest_nameを設定: {$roomData['user_name']}", 'INFO');
                    return true;
                }
            }
            
            // どちらの方法でも取得できなかった場合
            $metadata['guest_name'] = "Guest_" . substr(md5($roomNumber . time()), 0, 8);
            $this->logger->logMessage("フォールバック: 自動生成したguest_nameを設定: {$metadata['guest_name']}", 'INFO');
            return true;
        } catch (Exception $e) {
            $this->logger->logMessage("guest_name設定中にエラー発生: " . $e->getMessage(), 'ERROR');
            // エラー時でも処理を継続するため、最低限の値を設定
            $metadata['guest_name'] = "Room" . $roomNumber;
            return false;
        }
    }
} 