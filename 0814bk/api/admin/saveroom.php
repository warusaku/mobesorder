<?php
/**
 * 部屋情報保存API
 * 部屋情報の追加、更新、削除を行うAPIエンドポイント
 */

// 出力バッファリングを開始
ob_start();

// エラー表示設定
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ログファイルの設定
$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/saveroom.log';

// ログディレクトリの存在確認と作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// ログ関数
function writeLog($message, $level = 'INFO') {
    global $logFile;
    
    // ファイルサイズをチェック
    if (file_exists($logFile) && filesize($logFile) > 204800) { // 200KB
        // ファイルを削除して新規作成
        unlink($logFile);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// エラーハンドラー
function handleError($errno, $errstr, $errfile, $errline) {
    writeLog("Error: [$errno] $errstr in $errfile on line $errline", 'ERROR');
    return true;
}
set_error_handler('handleError');

try {
    // 設定ファイルを読み込み
    $rootPath = realpath(__DIR__ . '/../..');
    require_once $rootPath . '/api/config/config.php';
    require_once $rootPath . '/api/lib/Utils.php';
    
    // CORSヘッダー設定
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // OPTIONSリクエストの場合は終了
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        ob_end_clean();
        exit(0);
    }
    
    // POSTリクエストのみ許可
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('不正なリクエストメソッド');
    }
    
    // 管理者認証
    if (!isset($_POST['token']) || empty($_POST['token'])) {
        writeLog("認証エラー: トークンが指定されていません", 'WARNING');
        throw new Exception('認証エラー');
    }
    
    // セッションからの認証トークンを使用
    session_start();
    if (!isset($_SESSION['auth_token']) || $_POST['token'] !== $_SESSION['auth_token']) {
        writeLog("認証エラー: トークンが一致しません " . substr($_POST['token'], 0, 3) . "... vs " . (isset($_SESSION['auth_token']) ? substr($_SESSION['auth_token'], 0, 3) . "..." : "未設定"), 'WARNING');
        throw new Exception('認証エラー');
    }
    
    writeLog("部屋保存API呼び出し: アクション = " . ($_POST['action'] ?? 'なし'));
    
    // データベース接続
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 操作タイプにより処理分岐
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // 新規部屋追加
            $roomNumber = trim($_POST['room_number']);
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
            
            // バリデーション
            if (empty($roomNumber)) {
                throw new Exception('部屋番号は必須です');
            }
            
            if (strlen($roomNumber) > 5) {
                throw new Exception('部屋番号は5文字以内で入力してください');
            }
            
            // 重複チェック
            $stmt = $db->prepare("SELECT COUNT(*) FROM roomdatasettings WHERE room_number = ?");
            $stmt->execute([$roomNumber]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('この部屋番号は既に存在します');
            }
            
            // 登録
            $stmt = $db->prepare("INSERT INTO roomdatasettings (room_number, description, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$roomNumber, $description, $isActive]);
            
            writeLog("部屋を追加しました: $roomNumber");
            
            // バッファをクリアしてレスポンスを返す
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => '部屋を追加しました'
            ]);
            break;
            
        case 'update':
            // 部屋データ更新
            $roomData = json_decode($_POST['room_data'] ?? '[]', true);
            
            if (!$roomData || !is_array($roomData)) {
                throw new Exception('不正なデータ形式です');
            }
            
            writeLog("部屋データ更新: " . count($roomData) . "件");
            
            $db->beginTransaction();
            
            foreach ($roomData as $room) {
                if (!isset($room['id'])) {
                    continue;
                }
                
                $id = (int)$room['id'];
                $description = $room['description'] ?? null;
                $isActive = isset($room['is_active']) ? (int)$room['is_active'] : 1;
                $roomNumber = $room['room_number'] ?? null; // 部屋番号の変更に対応
                
                $updateFields = [];
                $params = [];
                
                if ($roomNumber !== null) {
                    // 部屋番号の重複チェック
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM roomdatasettings WHERE room_number = ? AND id != ?");
                    $checkStmt->execute([$roomNumber, $id]);
                    if ($checkStmt->fetchColumn() > 0) {
                        throw new Exception("部屋番号 '{$roomNumber}' は既に使用されています");
                    }
                    
                    $updateFields[] = "room_number = ?";
                    $params[] = $roomNumber;
                }
                
                if ($description !== null) {
                    $updateFields[] = "description = ?";
                    $params[] = $description;
                }
                
                $updateFields[] = "is_active = ?";
                $params[] = $isActive;
                
                if (empty($updateFields)) {
                    continue;
                }
                
                $params[] = $id; // WHERE条件のパラメータ
                
                $sql = "UPDATE roomdatasettings SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                writeLog("部屋ID: $id を更新しました");
            }
            
            $db->commit();
            
            // バッファをクリアしてレスポンスを返す
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => '部屋設定を更新しました'
            ]);
            break;
            
        case 'delete':
            // 部屋削除
            $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
            
            if ($roomId <= 0) {
                throw new Exception('不正な部屋IDです');
            }
            
            // 使用中チェック
            $stmt = $db->prepare("
                SELECT r.room_number, COUNT(l.id) as in_use 
                FROM roomdatasettings r
                LEFT JOIN line_room_links l ON r.room_number = l.room_number AND l.is_active = 1
                WHERE r.id = ?
                GROUP BY r.room_number
            ");
            $stmt->execute([$roomId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['in_use'] > 0) {
                throw new Exception('この部屋は現在使用中のため削除できません');
            }
            
            // 部屋情報を取得（ログ用）
            $stmt = $db->prepare("SELECT room_number FROM roomdatasettings WHERE id = ?");
            $stmt->execute([$roomId]);
            $roomInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 部屋を削除
            $stmt = $db->prepare("DELETE FROM roomdatasettings WHERE id = ?");
            $stmt->execute([$roomId]);
            
            if ($roomInfo) {
                writeLog("部屋を削除しました: ID=$roomId, 部屋番号=" . ($roomInfo['room_number'] ?? '不明'));
            } else {
                writeLog("部屋を削除しました: ID=$roomId (存在しない部屋)");
            }
            
            // バッファをクリアしてレスポンスを返す
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => '部屋を削除しました'
            ]);
            break;
            
        default:
            throw new Exception('不明な操作です');
    }
    
} catch (Exception $e) {
    writeLog("エラー発生: " . $e->getMessage(), 'ERROR');
    
    // トランザクションのロールバック
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // バッファをクリアしてエラーレスポンスを返す
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 