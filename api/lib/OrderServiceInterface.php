<?php

/**
 * 注文サービスインターフェース
 * 
 * カタログ商品モードとOpenTicketモードの共通インターフェースを定義
 */
interface OrderServiceInterface {
    /**
     * 注文を作成
     * 
     * @param string $roomNumber 部屋番号
     * @param array|string $items 注文商品の配列
     * @param string $guestName ゲスト名
     * @param string $note 注文全体の備考
     * @param string $lineUserId LINE User ID
     * @return array|false 成功時は注文情報、失敗時はfalse
     */
    public function createOrder($roomNumber, $items, $guestName = '', $note = '', $lineUserId = '');
    
    /**
     * モード名を取得
     * 
     * @return string モード名
     */
    public function getModeName();
} 