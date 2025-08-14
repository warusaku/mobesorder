<?php
/**
 * ラベル管理API
 * 
 * 商品ラベルのCRUD操作を提供するAPI
 */

// 設定ファイルとライブラリを読み込み
$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// データベース接続
$db = Database::getInstance();
$pdo = $db->getConnection();

// リクエストメソッドにより処理を分岐
$method = $_SERVER['REQUEST_METHOD'];

// レスポンスはJSON形式
header('Content-Type: application/json');

try {
    switch ($method) {
        case 'GET':
            // ラベル一覧を取得
            getLabels();
            break;
            
        case 'POST':
            // 新しいラベルを追加
            $action = isset($_GET['action']) ? $_GET['action'] : '';
            if ($action === 'add_label') {
                addLabel();
            } else {
                sendError('無効なアクション');
            }
            break;
            
        case 'PUT':
            // ラベルを更新
            updateLabel();
            break;
            
        case 'DELETE':
            // ラベルを削除
            deleteLabel();
            break;
            
        default:
            sendError('無効なリクエストメソッド');
    }
} catch (Exception $e) {
    sendError('エラーが発生しました: ' . $e->getMessage());
}

/**
 * ラベル一覧を取得
 */
function getLabels() {
    global $pdo;
    
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'item_label'");
        if ($stmt->rowCount() === 0) {
            // テーブルが存在しない場合は作成
            $createTableSql = "
                CREATE TABLE item_label (
                    label_id INT AUTO_INCREMENT PRIMARY KEY,
                    label_text VARCHAR(8) NOT NULL,
                    label_color CHAR(6) NOT NULL,
                    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            $pdo->exec($createTableSql);
        }
        
        $stmt = $pdo->query("SELECT * FROM item_label ORDER BY label_id ASC");
        $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(['success' => true, 'labels' => $labels]);
    } catch (PDOException $e) {
        sendError('ラベル一覧の取得に失敗しました: ' . $e->getMessage(), 500);
    }
}

/**
 * 新しいラベルを追加
 */
function addLabel() {
    global $pdo;
    
    // JSONリクエストボディを取得
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['label_text']) || !isset($data['label_color'])) {
        sendError('不正なデータ形式');
        return;
    }
    
    $labelText = $data['label_text'];
    $labelColor = $data['label_color'];
    
    // データの検証
    if (empty($labelText) || mb_strlen($labelText, 'UTF-8') > 8) {
        sendError('ラベル名は1〜8文字で入力してください');
        return;
    }
    
    if (empty($labelColor) || !preg_match('/^[0-9a-fA-F]{6}$/', $labelColor)) {
        sendError('カラーコードは16進数6桁で入力してください');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO item_label (label_text, label_color) VALUES (?, ?)");
        $stmt->execute([$labelText, $labelColor]);
        
        $labelId = $pdo->lastInsertId();
        
        sendResponse([
            'success' => true, 
            'message' => 'ラベルを追加しました',
            'label_id' => $labelId
        ]);
    } catch (PDOException $e) {
        sendError('ラベルの追加に失敗しました: ' . $e->getMessage(), 500);
    }
}

/**
 * ラベルを更新
 */
function updateLabel() {
    global $pdo;
    
    // JSONリクエストボディを取得
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['label_id']) || !isset($data['label_text']) || !isset($data['label_color'])) {
        sendError('不正なデータ形式');
        return;
    }
    
    $labelId = $data['label_id'];
    $labelText = $data['label_text'];
    $labelColor = $data['label_color'];
    
    // データの検証
    if (empty($labelId) || !is_numeric($labelId)) {
        sendError('無効なラベルIDです');
        return;
    }
    
    if (empty($labelText) || mb_strlen($labelText, 'UTF-8') > 8) {
        sendError('ラベル名は1〜8文字で入力してください');
        return;
    }
    
    if (empty($labelColor) || !preg_match('/^[0-9a-fA-F]{6}$/', $labelColor)) {
        sendError('カラーコードは16進数6桁で入力してください');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE item_label SET label_text = ?, label_color = ? WHERE label_id = ?");
        $stmt->execute([$labelText, $labelColor, $labelId]);
        
        if ($stmt->rowCount() === 0) {
            sendError('ラベルが見つかりません');
            return;
        }
        
        sendResponse([
            'success' => true, 
            'message' => 'ラベルを更新しました'
        ]);
    } catch (PDOException $e) {
        sendError('ラベルの更新に失敗しました: ' . $e->getMessage(), 500);
    }
}

/**
 * ラベルを削除
 */
function deleteLabel() {
    global $pdo;
    
    $labelId = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (empty($labelId) || !is_numeric($labelId)) {
        sendError('無効なラベルIDです');
        return;
    }
    
    try {
        // トランザクション開始
        $pdo->beginTransaction();
        
        // 商品からラベル参照を削除
        $stmt = $pdo->prepare("UPDATE products SET item_label1 = NULL WHERE item_label1 = ?");
        $stmt->execute([$labelId]);
        
        $stmt = $pdo->prepare("UPDATE products SET item_label2 = NULL WHERE item_label2 = ?");
        $stmt->execute([$labelId]);
        
        // ラベル自体を削除
        $stmt = $pdo->prepare("DELETE FROM item_label WHERE label_id = ?");
        $stmt->execute([$labelId]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            sendError('ラベルが見つかりません');
            return;
        }
        
        $pdo->commit();
        
        sendResponse([
            'success' => true, 
            'message' => 'ラベルを削除しました'
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        sendError('ラベルの削除に失敗しました: ' . $e->getMessage(), 500);
    }
}

/**
 * 成功レスポンスを送信
 */
function sendResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンスを送信
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
} 