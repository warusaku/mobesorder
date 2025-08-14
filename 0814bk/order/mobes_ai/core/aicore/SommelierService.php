<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: 酒類専用推薦ロジックの雛形クラス。RecommendationService を拡張。
 */

namespace MobesAi\Core\AiCore;

class SommelierService extends RecommendationService
{
    public function getRecommendations(string $mode, int $orderSessionId = null): array
    {
        // 酒類カテゴリフィルタ取得
        require_once __DIR__ . '/../../../../api/lib/Database.php';
        $db = \Database::getInstance();
        $products = $db->select("SELECT p.id, p.name, p.price, p.category, l1.label_name AS label1, l2.label_name AS label2 FROM products p LEFT JOIN item_label l1 ON l1.id=p.item_label1 LEFT JOIN item_label l2 ON l2.id=p.item_label2 WHERE p.is_active=1 AND p.category IN ('ワイン','カクテル','アルコール','酒') LIMIT 50");
        if (!$products) {
            return [ 'items'=>[], 'reply'=>'現在おすすめできるお酒がありません。'];
        }

        // 親処理呼び出し; mode を sommelier 指定
        return parent::getRecommendations('sommelier', $orderSessionId);
    }
} 