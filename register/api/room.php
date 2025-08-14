<?php
/**
 * 部屋情報取得API
 * 
 * @version 2.0.0
 * @package FG Square
 */

// 出力バッファリング
ob_start();

// ログ設定
$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/room_php.log';

// ログディレクトリ作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// ログサイズチェック (300KB超過で削除)
if (file_exists($logFile) && filesize($logFile) > 307200) {
    rename($logFile, $logFile . '.' . date('Y-m-d_H-i-s') . '.bak');
}

// デバッグ関数
function debugLog($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 初期ログ - スクリプト開始
debugLog("room.php API実行開始 - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// エラー表示設定
ini_set('display_errors', 1); // 開発中はエラーを表示
error_reporting(E_ALL);

// 設定ファイル読み込み
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/lib/Utils.php';

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// カスタムエラーハンドラ
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    global $logFile;
    
    $errorTime = date('Y-m-d H:i:s');
    $errorMessage = "[$errorTime] エラー($errno): $errstr in $errfile on line $errline" . PHP_EOL;
    
    // ログファイルに記録
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    return true;
}

// エラーハンドラを設定
set_error_handler('customErrorHandler');

// 予期しないエラーをキャッチするための例外ハンドラ
function exception_handler($exception) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [FATAL] 未処理の例外: " . $exception->getMessage() . 
               " in " . $exception->getFile() . " on line " . $exception->getLine() . 
               "\nスタックトレース: " . $exception->getTraceAsString() . PHP_EOL;
    file_put_contents($logFile, $message, FILE_APPEND);
    
    // JSONレスポンスを返す
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '内部サーバーエラーが発生しました'
    ]);
    exit;
}
set_exception_handler('exception_handler');

try {
    // POSTリクエスト以外は拒否
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debugLog("不正なリクエストメソッド: " . $_SERVER['REQUEST_METHOD'], 'ERROR');
        throw new Exception('不正なリクエストメソッドです');
    }
    
    // POSTデータ取得
    $rawInput = file_get_contents('php://input');
    debugLog("受信したPOSTデータ: " . $rawInput, 'DEBUG');
    
    $postData = json_decode($rawInput, true);
    if (empty($postData)) {
        debugLog("POSTデータのJSONデコードに失敗しました", 'ERROR');
        throw new Exception('POSTデータが不正です');
    }
    
    debugLog("デコードされたPOSTデータ: " . print_r($postData, true), 'DEBUG');
    
    // トークン検証
    $token = $postData['token'] ?? '';
    if (empty($token)) {
        debugLog("トークンが空です", 'ERROR');
        throw new Exception('認証情報が不正です');
    }
    
    // LINE Messaging APIを使用してトークンを検証
    $userId = verifyToken($token);
    if (!$userId) {
        debugLog("トークン検証に失敗しました: " . substr($token, 0, 20) . "...", 'ERROR');
        throw new Exception('認証に失敗しました');
    }
    
    debugLog("ユーザー認証成功: " . $userId, 'INFO');
    
    // アクション判定
    $action = $postData['action'] ?? 'get_rooms';
    debugLog("実行アクション: " . $action, 'INFO');
    
    // データベース接続
    debugLog("データベース接続を開始: " . DB_HOST . ", " . DB_NAME, 'INFO');
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]
        );
        debugLog("データベース接続成功", 'INFO');
    } catch (PDOException $e) {
        debugLog("データベース接続エラー: " . $e->getMessage(), 'ERROR');
        throw new Exception('データベース接続に失敗しました: ' . $e->getMessage());
    }
    
    // アクションに応じた処理を実行
    switch ($action) {
        case 'get_rooms':
            // アクティブな部屋のみを取得するかどうか
            $activeOnly = $postData['active_only'] ?? false;
            debugLog("部屋一覧取得 - アクティブのみ: " . ($activeOnly ? 'true' : 'false'), 'INFO');
            
            // 部屋情報を取得
            $sql = "
                SELECT room_number, description
                FROM roomdatasettings
                " . ($activeOnly ? "WHERE is_active = 1" : "") . "
                ORDER BY room_number
            ";
            
            debugLog("実行SQL: " . $sql, 'DEBUG');
            
            try {
                $stmt = $pdo->query($sql);
                $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 部屋番号のみの配列を作成
                $roomNumbers = array_column($rooms, 'room_number');
                
                // 成功レスポンス
                $response = [
                    'success' => true,
                    'rooms' => $roomNumbers,
                    'room_details' => $rooms
                ];
                
                debugLog("部屋一覧応答: " . count($roomNumbers) . "件の部屋", 'INFO');
            } catch (PDOException $e) {
                debugLog("SQL実行エラー: " . $e->getMessage(), 'ERROR');
                throw new Exception('部屋情報の取得に失敗しました: ' . $e->getMessage());
            }
            
            break;
            
        case 'get_current':
            // ユーザーの現在の部屋情報を取得
            debugLog("現在の部屋情報取得 - ユーザーID: " . $userId, 'INFO');
            
            try {
                $sql = "
                    SELECT l.id, l.room_number, l.user_name, l.check_in_date, l.check_out_date, r.description
                    FROM line_room_links l
                    LEFT JOIN roomdatasettings r ON l.room_number = r.room_number
                    WHERE l.line_user_id = :userId
                    AND l.check_out_date >= CURDATE()
                    AND l.is_active = 1
                    ORDER BY l.check_in_date DESC
                    LIMIT 1
                ";
                debugLog("実行SQL: " . $sql, 'DEBUG');
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
                $stmt->execute();
                $roomData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                debugLog("部屋情報取得結果: " . ($roomData ? "部屋あり" : "部屋なし"), 'INFO');
                
                if ($roomData) {
                    debugLog('部屋情報が見つかりました: ' . json_encode($roomData), 'INFO');
                    
                    // 日付のフォーマット変換 (必要に応じて)
                    if (isset($roomData['check_in_date'])) {
                        $checkInDate = new DateTime($roomData['check_in_date']);
                        $roomData['check_in_date'] = $checkInDate->format('Y-m-d');
                    }
                    
                    if (isset($roomData['check_out_date'])) {
                        $checkOutDate = new DateTime($roomData['check_out_date']);
                        $roomData['check_out_date'] = $checkOutDate->format('Y-m-d');
                    }
                    
                    // 成功レスポンス
                    $response = [
                        'success' => true,
                        'room_info' => $roomData
                    ];
                } else {
                    debugLog('ユーザーの部屋情報が見つかりません', 'INFO');
                    
                    // 情報がない場合
                    $response = [
                        'success' => true,
                        'room_info' => null,
                        'message' => '部屋情報が登録されていません'
                    ];
                }
            } catch (PDOException $e) {
                debugLog("SQL実行エラー: " . $e->getMessage(), 'ERROR');
                throw new Exception('部屋情報の取得に失敗しました: ' . $e->getMessage());
            }
            
            break;
            
        default:
            debugLog("不正なアクション: " . $action, 'ERROR');
            throw new Exception('不正なアクションです');
    }
    
    // 成功レスポンス
    debugLog("成功レスポンス送信", 'INFO');
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    // エラーログを記録
    debugLog("例外発生: " . $e->getMessage(), 'ERROR');
    debugLog("スタックトレース: " . $e->getTraceAsString(), 'DEBUG');
    
    // エラーレスポンス
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    debugLog("room.php API実行終了", 'INFO');
    // 出力バッファをフラッシュしてクリア
    ob_end_flush();
}

/**
 * LINEのIDトークンを検証し、ユーザーIDを取得する
 * 
 * @param string $token LINEのIDトークン
 * @return string|false 成功時はユーザーID、失敗時はfalse
 */
function verifyToken($token) {
    try {
        debugLog("トークン検証開始: " . substr($token, 0, 20) . "...", 'DEBUG');
        
        // トークンが空でないか確認
        if (empty($token)) {
            debugLog("トークンが空です", 'ERROR');
            return false;
        }
        
        // トークンの長さをチェック
        $tokenLength = strlen($token);
        debugLog("トークン長: " . $tokenLength, 'DEBUG');
        if ($tokenLength < 50) { // JWT形式のトークンは通常これより長い
            debugLog("トークンが短すぎます: " . $tokenLength, 'ERROR');
            return false;
        }
        
        // トークンフォーマットの検証 (JWT形式: header.payload.signature)
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            debugLog("トークンフォーマットが不正: パート数 " . count($tokenParts), 'ERROR');
            return false;
        }
        
        // ペイロード部分をデコード
        $base64Payload = $tokenParts[1];
        debugLog("ペイロードBase64: " . substr($base64Payload, 0, 20) . "...", 'DEBUG');
        
        // Base64パディングの修正
        $base64Payload = str_replace(['-', '_'], ['+', '/'], $base64Payload);
        $padding = strlen($base64Payload) % 4;
        if ($padding) {
            $base64Payload .= str_repeat('=', 4 - $padding);
        }
        
        // ペイロードをデコード
        $payloadJson = base64_decode($base64Payload);
        if (!$payloadJson) {
            debugLog("ペイロードのBase64デコードに失敗", 'ERROR');
            return false;
        }
        
        // JSONとしてパース
        $payload = json_decode($payloadJson, true);
        if (!$payload) {
            debugLog("ペイロードのJSONデコードに失敗: " . json_last_error_msg(), 'ERROR');
            return false;
        }
        
        debugLog("トークンペイロード: " . print_r($payload, true), 'DEBUG');
        
        // 必須フィールドの確認
        if (!isset($payload['sub'])) {
            debugLog("ペイロードに'sub'フィールドがありません", 'ERROR');
            return false;
        }
        
        if (!isset($payload['iss'])) {
            debugLog("ペイロードに'iss'フィールドがありません", 'ERROR');
            // subが存在する場合は警告だけにして続行
            debugLog("警告: LINE発行のトークンでない可能性があります", 'WARNING');
        } else {
            // LINE発行のトークンかを確認
            $issuer = $payload['iss'];
            if (strpos($issuer, 'line') === false && strpos($issuer, 'https://access.line.me') === false) {
                debugLog("不明な発行元: " . $issuer, 'WARNING');
                // 続行は許可するが警告を記録
            }
        }
        
        // 有効期限のチェック
        if (isset($payload['exp'])) {
            $expiryTime = $payload['exp'];
            $currentTime = time();
            
            if ($currentTime > $expiryTime) {
                debugLog("トークンの有効期限切れ: 期限 " . date('Y-m-d H:i:s', $expiryTime) . 
                       ", 現在 " . date('Y-m-d H:i:s', $currentTime), 'ERROR');
                return false;
            }
        } else {
            debugLog("トークンに有効期限(exp)がありません", 'WARNING');
            // 有効期限がない場合も許可する（警告のみ）
        }
        
        // userIdを返す
        $userId = $payload['sub'];
        debugLog("トークン検証成功: ユーザーID " . $userId, 'INFO');
        return $userId;
    } catch (Exception $e) {
        debugLog("トークン検証中に例外: " . $e->getMessage(), 'ERROR');
        debugLog("例外スタックトレース: " . $e->getTraceAsString(), 'DEBUG');
        return false;
    }
}
?> 