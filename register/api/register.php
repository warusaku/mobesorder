<?php
/**
 * 部屋番号登録API
 * 
 * @version 3.0.0
 * @package FG Square
 * 
 * このファイルは登録処理のハンドラーとして機能し、
 * 実際の処理は各専門クラスに委譲します。
 */

// 出力バッファリング
ob_start();

// エラー表示設定
ini_set('display_errors', 0);
error_reporting(E_ALL);

// クラスファイルの読み込み
require_once 'register_Logger.php';
require_once 'register_TokenValidator.php';
require_once 'register_DatabaseHandler.php';
require_once 'register_RoomManager.php';

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ログインスタンスの作成
$logger = new RegisterLogger('register.log');

try {
    // API処理開始をログ
    $logger->logApiStart('部屋番号登録API');
    
    // POSTリクエストの確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }

    // POSTデータの取得
    $postData = file_get_contents('php://input');
    $logger->debug('受信POSTデータ', ['size' => strlen($postData)]);
    
    $data = json_decode($postData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // 必須パラメータの取得
    $token = $data['token'] ?? null;
    $roomNumber = $data['room_number'] ?? null;
    $userName = $data['user_name'] ?? null;
    $checkIn = $data['check_in'] ?? null;
    $checkOut = $data['check_out'] ?? null;
    $force = isset($data['force']) && $data['force'] === true;
    
    // パラメータ検証
    if (!$token) throw new Exception('token parameter is required');
    if (!$roomNumber) throw new Exception('room_number parameter is required');
    if (!$userName) throw new Exception('user_name parameter is required');
    if (!$checkIn) throw new Exception('check_in parameter is required');
    if (!$checkOut) throw new Exception('check_out parameter is required');
    
    $logger->info('処理パラメータ', [
        'room' => $roomNumber,
        'user' => $userName,
        'checkIn' => $checkIn,
        'checkOut' => $checkOut,
        'force' => $force
    ]);
    
    // トークン検証
    $tokenValidator = new RegisterTokenValidator($logger);
    $userId = $tokenValidator->validateAndGetUserId($token);
    
    // データベースハンドラの作成
    $db = new RegisterDatabaseHandler($logger);
    
    // 部屋管理クラスの作成
    $roomManager = new RegisterRoomManager($logger, $db);
    
    // トランザクション開始
    $db->beginTransaction();
    
    try {
        // 部屋の検証
        $roomManager->validateRoom($roomNumber);
        
        // 既存の登録を取得
        $activeRegistration = $roomManager->getActiveRegistration($userId);
        
        // 部屋変更の場合の処理
        if ($activeRegistration && $activeRegistration['room_number'] !== $roomNumber) {
            $logger->info('部屋変更要求を検出', [
                'old' => $activeRegistration['room_number'],
                'new' => $roomNumber
            ]);
            
            // 未払いチェック
            $unpaidCount = $roomManager->checkUnpaidSessions($activeRegistration['room_number']);
            
            if ($unpaidCount > 0 && !$force) {
                throw new Exception('未払いの注文が残っているため部屋変更はできません');
            }

                // 旧セッションをクローズ
            $roomManager->closeOrderSessions($activeRegistration['room_number']);
            
            // 既存登録を非アクティブ化
            $roomManager->deactivateUserRegistrations($userId);
            
            // 新規登録として処理
            $activeRegistration = null;
        }
        
        // 登録データの準備
        $registrationData = [
            'user_id' => $userId,
            'room_number' => $roomNumber,
            'user_name' => $userName,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut
        ];
        
        // 登録処理
        if ($activeRegistration) {
            // 既存登録を更新
            $roomManager->updateRegistration($activeRegistration['id'], $registrationData);
                $registrationId = $activeRegistration['id'];
            $registrationType = 'update';
            $message = '部屋番号情報が更新されました';
        } else {
            // 既存の登録を非アクティブ化
            $roomManager->deactivateUserRegistrations($userId);
            
            // 新規登録
            $registrationId = $roomManager->createRegistration($registrationData);
            $registrationType = 'new';
            $message = '部屋番号が正常に登録されました';
        }
        
        // コミット
        $db->commit();
        
        // 成功レスポンス
        $response = [
            'success' => true,
            'message' => $message,
            'registration_id' => $registrationId,
            'registration_type' => $registrationType
        ];
        
        $logger->logApiEnd('部屋番号登録API', true, $response);
        
        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        // ロールバック
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    $logger->logException($e, '部屋番号登録API処理中');
    
            $errorResponse = ['error' => $e->getMessage()];
    
        if (!headers_sent()) {
            http_response_code(500);
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    $logger->logApiEnd('部屋番号登録API', false, $errorResponse);
    
} finally {
    // 出力バッファをフラッシュ
    ob_end_flush();
}
?> 