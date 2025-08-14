<?php
/**
 * Version: 0.1.2 (2025-05-31)
 * File Description: 商品推薦ロジックの雛形クラス。Geminiレスポンスの保存機能と会話履歴対応を追加。
 */

namespace MobesAi\Core\AiCore;
use MobesAi\Core\AiCore\AiLogger;
use MobesAi\Core\AiCore\GeminiClient;

class RecommendationService
{
    public function getRecommendations(string $mode, int $orderSessionId = null, string $lineUserId = null): array
    {
        $logger = new AiLogger();
        $logger->info('RecommendationService getRecommendations start', ['mode' => $mode, 'session' => $orderSessionId]);
        // 商品プール取得
        require_once __DIR__ . '/../../../../api/lib/Database.php';
        $db = \Database::getInstance();
        
        // デバッグ用：シンプルなクエリを優先的に使用するフラグ
        $useSimpleQuery = true; // TODO: 本番環境では false に設定

        try {
            if ($useSimpleQuery) {
                // シンプルなクエリを使用（モバイルオーダーと同じ条件）
                $products = $db->select("
                    SELECT 
                        p.id, 
                        p.name, 
                        p.description, 
                        p.price, 
                        p.category, 
                        p.category_name, 
                        p.image_url,
                        p.item_label1,
                        p.item_label2
                    FROM products p 
                    WHERE p.is_active = 1 
                    AND p.presence = 1 
                    AND p.order_dsp = 1
                    ORDER BY p.id DESC
                    LIMIT 400
                ");
                
                // ラベル情報を別途取得
                foreach($products as &$row){
                    $row['label1'] = null;
                    $row['label2'] = null;
                    
                    if ($row['item_label1']) {
                        try {
                            $label1 = $db->selectOne("SELECT label_text FROM item_label WHERE label_id = ?", [$row['item_label1']]);
                            if ($label1) {
                                $row['label1'] = $label1['label_text'];
                            }
                        } catch (\Throwable $e) {
                            // ラベル取得エラーは無視
                        }
                    }
                    
                    if ($row['item_label2']) {
                        try {
                            $label2 = $db->selectOne("SELECT label_text FROM item_label WHERE label_id = ?", [$row['item_label2']]);
                            if ($label2) {
                                $row['label2'] = $label2['label_text'];
                            }
                        } catch (\Throwable $e) {
                            // ラベル取得エラーは無視
                        }
                    }
                }
                unset($row);
            } else {
                // カテゴリーが有効なものだけを取得するため、category_descripterとJOIN
                // LEFT JOINに変更して、カテゴリーがなくても商品を取得できるようにする
                $products = $db->select("
                    SELECT 
                        p.id, 
                        p.name, 
                        p.description, 
                        p.price, 
                        p.category, 
                        p.category_name, 
                        p.image_url,
                        p.item_label1,
                        p.item_label2,
                        l1.label_text AS label1, 
                        l2.label_text AS label2 
                    FROM products p 
                    LEFT JOIN category_descripter cd ON p.category = cd.category_id
                    LEFT JOIN item_label l1 ON l1.label_id = p.item_label1 
                    LEFT JOIN item_label l2 ON l2.label_id = p.item_label2 
                    WHERE p.is_active = 1 
                    AND p.presence = 1 
                    AND p.order_dsp = 1
                    AND (cd.is_active = 1 OR cd.is_active IS NULL OR p.category IS NULL)
                    ORDER BY p.id DESC
                    LIMIT 400
                ");
            }
        } catch (\Throwable $e) {
            $logger->error('Product query failed', ['exception' => $e->getMessage()]);
            // テーブル or カラム不足の場合は簡易クエリへフォールバック
            try {
                $products = $db->select("
                    SELECT id, name, description, price, category, category_name, image_url 
                    FROM products 
                    WHERE is_active=1 AND presence=1 AND order_dsp=1
                    ORDER BY id DESC
                    LIMIT 400
                ");
                foreach($products as &$row){
                    $row['label1']=null;
                    $row['label2']=null;
                    $row['item_label1']=null;
                    $row['item_label2']=null;
                }
            } catch (\Throwable $e2) {
                $logger->error('Fallback query also failed', ['exception' => $e2->getMessage()]);
                $products = [];
            }
        }

        $logger->info('Products query completed', ['count' => count($products)]);
        
        // 商品データの詳細ログ
        if (count($products) > 0) {
            $logger->info('Sample products', [
                'first_5_products' => array_slice($products, 0, 5),
                'total_count' => count($products)
            ]);
        } else {
            $logger->error('No products found!');
        }

        // "recommend" は旧名称; 内部的には suggest と同一扱い
        if ($mode === 'recommend') {
            $mode = 'suggest';
        }

        // Prompt Registrerを早期にインスタンス化（キーワード取得のため）
        $pr = new \MobesAi\Core\PromptRegistrer\PromptRegistrer();

        // ソムリエモードの場合、酒関連商品のみ抽出
        if ($mode === 'sommelier') {
            // 設定からキーワードを取得
            $modeKeywords = $pr->getModeKeywords('sommelier');
            $categoryKeywords = $modeKeywords['category_keywords'] ?? [];
            $nameKeywords = $modeKeywords['name_keywords'] ?? [];
            
            // フォールバック（設定が空の場合のデフォルト）
            if (empty($categoryKeywords) && empty($nameKeywords)) {
                $logger->warning('No keywords configured for sommelier mode, using defaults');
                $categoryKeywords = ['wine', 'ワイン', 'シャンパン', 'champagne', 'カクテル', 'cocktail', 'アルコール', 'ハイボール'];
                $nameKeywords = ['ワイン', 'シャンパン', 'カクテル', 'ハイボール', 'ビール', '酒'];
            }
            
            $products = array_values(array_filter($products, function ($p) use ($categoryKeywords, $nameKeywords) {
                // カテゴリーチェック
                $categoryStr = mb_strtolower(($p['category'] ?? '') . ' ' . ($p['category_name'] ?? ''));
                foreach ($categoryKeywords as $kw) {
                    if (strpos($categoryStr, mb_strtolower($kw)) !== false) {
                        return true;
                    }
                }
                
                // 商品名チェック
                $nameStr = mb_strtolower($p['name'] ?? '');
                foreach ($nameKeywords as $kw) {
                    if (strpos($nameStr, mb_strtolower($kw)) !== false) {
                        return true;
                    }
                }
                
                return false;
            }));

            // 中価格帯（25〜75パーセンタイル）に絞り込む
            if (count($products) >= 8) { // 十分なデータがある場合のみ実施
                $prices = array_column($products, 'price');
                sort($prices, SORT_NUMERIC);
                $n = count($prices);
                $lowerIdx = (int)floor($n * 0.25);
                $upperIdx = (int)ceil($n * 0.75) - 1;
                $lower = $prices[$lowerIdx];
                $upper = $prices[$upperIdx];

                $midProducts = array_values(array_filter($products, function ($p) use ($lower, $upper) {
                    return $p['price'] >= $lower && $p['price'] <= $upper;
                }));

                if (count($midProducts) >= 3) { // 極端に減る場合はフォールバックしない
                    $products = $midProducts;
                }
            }
        }

        $logger->info('Products fetched', ['count' => count($products)]);

        if (!$products) {
            return [
                'items' => [],
                'reply' => '現在おすすめできる商品がありません。'
            ];
        }

        // 注文履歴（任意）
        $historyStats = [];
        try {
            if ($orderSessionId) {
                $historyStats = $db->select("SELECT od.product_id, SUM(od.qty) AS cnt FROM orders o JOIN order_details od ON od.order_id=o.id WHERE o.order_session_id=:sid AND o.status='COMPLETED' GROUP BY od.product_id", [':sid' => $orderSessionId]);
            } elseif ($lineUserId) {
                $historyStats = $db->select("SELECT od.product_id, SUM(od.qty) AS cnt FROM orders o JOIN order_details od ON od.order_id=o.id WHERE o.line_user_id=:luid AND o.status='COMPLETED' GROUP BY od.product_id", [':luid' => $lineUserId]);
            }
        } catch (\Throwable $e) {
            $logger->warning('history query failed', ['exception'=>$e]);
            $historyStats = [];
        }

        // 会話履歴取得
        $chatHistory = [];
        try {
            if ($orderSessionId || $lineUserId) {
                $params = [];
                $whereClause = '';
                if ($orderSessionId) {
                    $whereClause = 'order_session_id = :sid';
                    $params[':sid'] = $orderSessionId;
                } else {
                    $whereClause = 'line_user_id = :luid';
                    $params[':luid'] = $lineUserId;
                }
                
                $messages = $db->select(
                    "SELECT role, message FROM mobes_ai_messages 
                     WHERE {$whereClause} 
                     ORDER BY created_at DESC 
                     LIMIT 10",
                    $params
                );
                
                // 新しい順なので逆順にする
                $chatHistory = array_reverse($messages);
                $logger->info('Chat history loaded', ['count' => count($chatHistory)]);
            }
        } catch (\Throwable $e) {
            $logger->warning('chat history query failed', ['exception' => $e]);
            $chatHistory = [];
        }

        // Prompt 準備
        $systemPromptBase = $pr->getSystemPrompt($mode);
        $style = $pr->getBasicStyle();
        $prohibitions = $pr->getProhibitions();
        $chatRule = $pr->getChatRule();
        
        // モード別プロンプトはPromptRegistrerから取得されるため、ここでの追加は不要
        $systemPrompt = $systemPromptBase . "\n文体:" . $style . "\n禁止:" . $prohibitions . "\nルール:" . $chatRule;
        
        $logger->info('Prompt assembled', ['system_prompt' => $systemPrompt]);
        $apiKey = $pr->getApiKey();
        $modelId = $pr->getModelId();

        $gemini = new GeminiClient($apiKey, $modelId);

        // build product array with labels
        $productJsonArray = array_map(function($row){
            $labels = array_filter([$row['label1'] ?? null, $row['label2'] ?? null]);
            return [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'price' => (float)$row['price'],
                'category' => $row['category'],
                'category_name' => $row['category_name'] ?? null,
                'labels' => $labels,
                'image_url' => $row['image_url'] ?? null
            ];
        }, $products);

        // メタ説明を取得して商品情報に追加
        $metaDescriptions = $pr->getAllProductMetaDescriptions();
        $logger->info('Meta descriptions loaded', ['count' => count($metaDescriptions)]);
        
        foreach ($productJsonArray as &$product) {
            if (isset($metaDescriptions[$product['name']])) {
                $product['detailed_description'] = $metaDescriptions[$product['name']];
                $logger->info('Added meta description to product', [
                    'product_id' => $product['id'],
                    'product_name' => $product['name']
                ]);
            }
        }
        unset($product); // 参照を解除

        // カテゴリ別に商品を整理（プロンプトの理解を助けるため）
        $productsByCategory = [];
        foreach ($productJsonArray as $product) {
            $categoryName = $product['category_name'] ?? '未分類';
            if (!isset($productsByCategory[$categoryName])) {
                $productsByCategory[$categoryName] = [];
            }
            $productsByCategory[$categoryName][] = $product;
        }
        
        $logger->info('Products organized by category', [
            'categories' => array_keys($productsByCategory),
            'category_count' => count($productsByCategory)
        ]);

        // 注文履歴から詳細情報を取得
        $previousOrderDetails = [];
        if (!empty($historyStats) && $pr->isOrderHistoryEnabled()) {
            try {
                $productIds = array_column($historyStats, 'product_id');
                if (!empty($productIds)) {
                    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
                    $orderProducts = $db->select(
                        "SELECT id, name, category_name FROM products WHERE id IN ($placeholders)",
                        $productIds
                    );
                    foreach ($orderProducts as $op) {
                        $previousOrderDetails[$op['id']] = $op;
                    }
                }
            } catch (\Throwable $e) {
                $logger->warning('Failed to get order product details', ['exception' => $e]);
            }
        }

        // 注文履歴に基づくプロンプトを構築
        $hasRelevantHistory = false;
        $historyPrompt = '';
        
        if ($pr->isOrderHistoryEnabled() && !empty($previousOrderDetails)) {
            if ($mode === 'sommelier') {
                // ソムリエモード: ワイン履歴を確認
                $wineCategories = ['wine', 'ワイン', 'シャンパン', 'champagne'];
                $previousWines = [];
                
                foreach ($previousOrderDetails as $id => $detail) {
                    $categoryLower = mb_strtolower($detail['category_name'] ?? '');
                    foreach ($wineCategories as $wc) {
                        if (strpos($categoryLower, mb_strtolower($wc)) !== false) {
                            $previousWines[] = $detail['name'];
                            break;
                        }
                    }
                }
                
                if (!empty($previousWines)) {
                    $hasRelevantHistory = true;
                    $historyPrompt = $pr->getHistoryPrompt('sommelier', true);
                    if ($historyPrompt) {
                        $historyPrompt = str_replace('{previous_wine}', implode('、', $previousWines), $historyPrompt);
                    }
                }
            } else {
                // その他のモード
                $hasRelevantHistory = true;
                $historyPrompt = $pr->getHistoryPrompt($mode, true);
                if ($historyPrompt && $mode === 'suggest') {
                    $previousItems = array_column($previousOrderDetails, 'name');
                    $historyPrompt = str_replace('{previous_items}', implode('、', array_slice($previousItems, 0, 3)), $historyPrompt);
                }
            }
        }
        
        // 履歴がない場合のプロンプト
        if (!$hasRelevantHistory) {
            $historyPrompt = $pr->getHistoryPrompt($mode, false) ?? '';
        }

        // プロンプトを構造化して構築
        $messages = [];
        
        // 1. システムプロンプト（役割、スタイル、禁止事項、ルール）
        $messages[] = $systemPrompt;
        
        // 2. 商品データベース情報
        $messages[] = "【利用可能な商品データベース】";
        $messages[] = "以下は現在提供可能な商品のカテゴリ別リストです:";
        $messages[] = "※ detailed_descriptionフィールドがある商品は、そちらに詳細な商品知識が記載されています。";
        $messages[] = json_encode($productsByCategory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        // デバッグ: 商品データベースの内容を確認
        $logger->info('Product database being sent to Gemini', [
            'total_products' => count($productJsonArray),
            'categories' => array_keys($productsByCategory),
            'sample_data' => array_slice($productsByCategory, 0, 2) // 最初の2カテゴリのみ
        ]);
        
        // 3. 注文履歴情報（ある場合）
        if (!empty($historyStats)) {
            $enrichedHistory = [];
            foreach ($historyStats as $hs) {
                $item = [
                    'product_id' => $hs['product_id'], 
                    'order_count' => $hs['cnt']
                ];
                if (isset($previousOrderDetails[$hs['product_id']])) {
                    $item['name'] = $previousOrderDetails[$hs['product_id']]['name'];
                    $item['category'] = $previousOrderDetails[$hs['product_id']]['category_name'];
                }
                $enrichedHistory[] = $item;
            }
            $messages[] = "\n【お客様の過去の注文履歴】";
            $messages[] = json_encode($enrichedHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // 4. 会話履歴（ある場合）
        if (!empty($chatHistory)) {
            $messages[] = "\n【これまでの会話】";
            foreach ($chatHistory as $msg) {
                $role = $msg['role'] === 'user' ? 'お客様' : 'アシスタント';
                $messages[] = "{$role}: {$msg['message']}";
            }
        }
        
        // 5. 今回の問いかけ（履歴に基づく）
        if (!empty($historyPrompt)) {
            $messages[] = "\n【今回のお客様への問いかけ】";
            $messages[] = $historyPrompt;
        }
        
        // 6. 出力フォーマットの厳密な指定
        $messages[] = "\n【重要: 出力フォーマット】";
        $messages[] = "必ず以下のフォーマットで出力してください:";
        $messages[] = "1行目: JSON配列のみ。例: [{\"product_id\":123,\"qty\":2},{\"product_id\":456,\"qty\":1}]";
        $messages[] = "2行目: 空行（改行のみ）";
        $messages[] = "3行目以降: 日本語での説明（100〜200文字）";
        $messages[] = "注意: JSON部分にコードブロック記号(```)は付けないこと。";
        
        // 7. モード別の追加指示
        if ($mode === 'sommelier') {
            $messages[] = "\n【ソムリエモード特別指示】";
            $messages[] = "- 中価格帯（全体の25〜75パーセンタイル）の商品を中心に提案";
            $messages[] = "- 必ず3品程度を提案（多すぎず少なすぎず）";
            $messages[] = "- 各商品の特徴と合わせる料理を簡潔に説明";
        } elseif ($mode === 'omakase') {
            $messages[] = "\n【おまかせモード特別指示】";
            $messages[] = "- 必ず10品前後の完全なコーディネートを作成";
            $messages[] = "- カテゴリバランスを考慮（フード3品以上、ドリンク人数分、デザート1品以上）";
            $messages[] = "- 総額が高額になりすぎないよう配慮";
        }

        $raw = $gemini->sendPrompt($messages);
        
        // デバッグ: 送信したプロンプトの長さとサンプル
        $fullPrompt = implode("\n", $messages);
        $logger->info('Prompt sent to Gemini', [
            'total_length' => strlen($fullPrompt),
            'line_count' => count($messages),
            'first_500_chars' => mb_substr($fullPrompt, 0, 500),
            'last_500_chars' => mb_substr($fullPrompt, -500)
        ]);
        
        $logger->info('Received raw response', ['raw' => $raw]);
        if (!$raw) {
            return [
                'items' => [],
                'reply' => 'AI 推薦に失敗しました。時間を置いて再度お試しください。'
            ];
        }

        // JSON 抽出
        $items = [];
        if (preg_match('/\[(.*?)\]/s', $raw, $m)) {
            $jsonPart = '[' . $m[1] . ']';
            $decoded = json_decode($jsonPart, true);
            if (is_array($decoded)) {
                $items = array_filter($decoded, function ($row) {
                    return isset($row['product_id']) && isset($row['qty']) && $row['qty'] >= 1;
                });
            }
        }
        // 返信文 = raw から JSON 部分除外
        $replyText = trim(str_replace($jsonPart ?? '', '', $raw));

        // --- コードフェンス除去 ---
        if (!empty($replyText)) {
            // ```json や ``` を削除
            $replyText = preg_replace('/```(?:json)?\s*/i', '', $replyText);
            $replyText = str_replace('```', '', $replyText);
            $replyText = trim($replyText);
        }

        // フォールバック: items が空で raw の1行目が JSON らしければ採用
        if (empty($items)) {
            $firstLine = strtok($raw, "\n");
            $maybe = json_decode(trim($firstLine), true);
            if (is_array($maybe)) {
                $items = $maybe;
                $replyText = trim(substr($raw, strlen($firstLine)));
                // 再度コードフェンス除去
                $replyText = preg_replace('/```(?:json)?\s*/i', '', $replyText);
                $replyText = str_replace('```', '', $replyText);
                $replyText = trim($replyText);
            }
        }

        // 推薦アイテムに商品詳細を付加
        $productMap = [];
        foreach($products as $prow){
            $productMap[$prow['id']] = $prow;
        }
        foreach($items as &$it){
            $pid = $it['product_id'] ?? null;
            if($pid && isset($productMap[$pid])){
                $row = $productMap[$pid];
                $it['name'] = $row['name'];
                $it['price'] = (float)$row['price'];
                $it['image_url'] = $row['image_url'] ?? null;
            }
        }

        // Geminiからのレスポンスをデータベースに保存
        try {
            if ($orderSessionId || $lineUserId) {
                $db->insert('mobes_ai_messages', [
                    'order_session_id' => $orderSessionId ?? 0,
                    'line_user_id' => $lineUserId,
                    'role' => 'assistant',
                    'message' => $replyText
                ]);
                $logger->info('Saved assistant message to database');
            }
        } catch (\Throwable $e) {
            $logger->warning('Failed to save assistant message', ['exception' => $e]);
        }

        // Determine chat phase for omakase/suggest
        $userMsgs = array_filter($chatHistory, fn($m)=>$m['role']==='user');
        $askPhase = false;
        
        // 初回の問いかけフェーズの判定
        if(in_array($mode, ['omakase', 'suggest'])){
            if(count($userMsgs) === 0){
                $askPhase = true;
            }
        }

        // askPhase handling - 履歴プロンプトを活用
        if($askPhase){
            // 履歴がない場合の初回プロンプトを使用
            $initialPrompt = $pr->getHistoryPrompt($mode, false);
            if($initialPrompt){
                return ['items' => [], 'reply' => $initialPrompt];
            }
            
            // フォールバック（プロンプトが設定されていない場合）
            $question = ($mode === 'omakase') ?
                '初めてのご利用ありがとうございます。人数とお好みを教えていただければ、最適なコーディネートをご提案いたします。' :
                '本日はどのようなものをお探しでしょうか？お気軽にお申し付けください。';
            return ['items' => [], 'reply' => $question];
        }

        return [
            'items' => $items,
            'reply' => $replyText
        ];
    }
} 