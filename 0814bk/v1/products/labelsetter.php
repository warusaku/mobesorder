<?php
/**
 * 商品データにラベル情報を付加するスクリプト
 * item_label1とitem_label2のIDからラベル情報（テキストと色）を取得し、商品データに含める
 * 
 * このファイルはProductServiceから呼び出される想定です
 */

// ログファイル設定
define('LOG_FILE', dirname(__FILE__) . '/../../../../logs/labelsetter.log');
define('LOG_MAX_SIZE', 300 * 1024); // 300KB
define('LOG_KEEP_PERCENT', 20); // ローテーション時に20%を保持

/**
 * ログファイルのローテーションをチェック
 * サイズが上限を超えた場合は古いログを削除し、一部だけ残す
 */
function checkLogRotation() {
    if (!file_exists(LOG_FILE)) {
        return;
    }
    
    $fileSize = filesize(LOG_FILE);
    if ($fileSize > LOG_MAX_SIZE) {
        // ファイルサイズが上限を超えた場合
        $logContent = file_get_contents(LOG_FILE);
        
        // 指定した割合だけを保持（最後の部分）
        $keepSize = intval(LOG_MAX_SIZE * (LOG_KEEP_PERCENT / 100));
        $newContent = substr($logContent, -$keepSize);
        
        // 新しい内容を書き込み
        file_put_contents(LOG_FILE, "--- ログローテーション実行 " . date('Y-m-d H:i:s') . " ---\n" . $newContent);
        
        logMessage("ログローテーション実行 - 元サイズ: " . $fileSize . "バイト, 保持サイズ: " . $keepSize . "バイト");
    }
}

/**
 * ログメッセージを出力
 * 
 * @param string $message ログメッセージ
 * @param string $level ログレベル (INFO, WARNING, ERROR)
 */
function logMessage($message, $level = 'INFO') {
    checkLogRotation();
    
    $timestamp = date('Y-m-d H:i:s');
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
    $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
    $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
    
    $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
    
    // ログファイルへの書き込みを試みる
    $result = @file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    
    // ファイル書き込みに失敗した場合はPHPのエラーログに記録
    if ($result === false && function_exists('error_log')) {
        error_log("LabelSetter: " . $logMessage);
        error_log("LabelSetter: ログファイルへの書き込みに失敗しました: " . LOG_FILE);
    }
}

/**
 * ラベルIDからラベル情報を取得
 * 
 * @param PDO $db データベース接続オブジェクト
 * @param int $labelId ラベルID
 * @return array|null ラベル情報の連想配列、または取得失敗時はnull
 */
function getLabelById($db, $labelId) {
    if (empty($labelId) || $labelId == 0) {
        return null;
    }
    
    try {
        $query = "SELECT label_id, label_text, label_color FROM item_label WHERE label_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$labelId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return [
                'id' => $result['label_id'],
                'text' => $result['label_text'],
                'color' => $result['label_color']
            ];
        }
        
        return null;
    } catch (Exception $e) {
        logMessage("ラベル情報取得エラー: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 商品データにラベル情報を追加
 * 
 * @param array $products 商品データの配列
 * @param PDO $db データベース接続オブジェクト
 * @return array ラベル情報を追加した商品データの配列
 */
function addLabelsToProducts($products, $db) {
    logMessage("商品データにラベル情報付加処理開始 - 商品数: " . count($products));
    $startTime = microtime(true);
    
    // ラベルID取得とキャッシュを準備
    $labelCache = [];
    
    // 商品データを処理
    foreach ($products as &$product) {
        $item_label1 = isset($product['item_label1']) ? (int)$product['item_label1'] : 0;
        $item_label2 = isset($product['item_label2']) ? (int)$product['item_label2'] : 0;
        
        // 商品に 'labels' 配列を用意
        $product['labels'] = [];
        
        // ラベル1を処理
        if ($item_label1 > 0) {
            // キャッシュにあるか確認
            if (!isset($labelCache[$item_label1])) {
                $labelCache[$item_label1] = getLabelById($db, $item_label1);
            }
            
            if ($labelCache[$item_label1]) {
                $product['labels'][] = $labelCache[$item_label1];
            }
        }
        
        // ラベル2を処理
        if ($item_label2 > 0) {
            // キャッシュにあるか確認
            if (!isset($labelCache[$item_label2])) {
                $labelCache[$item_label2] = getLabelById($db, $item_label2);
            }
            
            if ($labelCache[$item_label2]) {
                $product['labels'][] = $labelCache[$item_label2];
            }
        }
        
        // 下位互換性のために個別のラベルオブジェクトも提供
        if (!empty($product['labels'])) {
            if (isset($product['labels'][0])) {
                $product['label1'] = $product['labels'][0];
            }
            if (isset($product['labels'][1])) {
                $product['label2'] = $product['labels'][1];
            }
        }
    }
    
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
    logMessage("ラベル情報付加処理完了 - 実行時間: " . $executionTime . "ms, 処理ラベル数: " . count($labelCache));
    
    return $products;
}

// このファイルは直接実行されず、他のファイルからインクルードされて利用されます
// 直接実行された場合のテスト用コード
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    logMessage("このファイルは直接実行せず、他のファイルからインクルードして使用してください", 'WARNING');
    echo "このファイルは直接実行せず、他のファイルからインクルードして使用してください。";
} 