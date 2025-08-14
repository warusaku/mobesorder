<?php
/**
 * LINE UserIDから部屋情報を取得する認証APIエンドポイント
 */

// 事前に全てのエラー出力を無効化
error_reporting(0);
ini_set('display_errors', 0);

// 以前の可能性のある出力をすべて破棄
if (ob_get_level()) {
    ob_end_clean();
}

// 新しいバッファを開始
ob_start();

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// ルートパスの取得
$rootPath = realpath(__DIR__ . '/../../../');

// 設定ファイルとライブラリの読み込み
$liffConfigPath = $rootPath . '/config/LIFF_config.php';
$configPath = $rootPath . '/api/config/config.php';
$dbPath = $rootPath . '/api/lib/Database.php';
$authServicePath = $rootPath . '/api/lib/AuthService.php';
$utilsPath = $rootPath . '/api/lib/Utils.php';

// パスの存在確認とデバッグ情報（error_logに記録、表示はしない）
error_log("LIFF設定パス: " . $liffConfigPath . " 存在: " . (file_exists($liffConfigPath) ? "はい" : "いいえ"));
error_log("API設定パス: " . $configPath . " 存在: " . (file_exists($configPath) ? "はい" : "いいえ"));
error_log("DBパス: " . $dbPath . " 存在: " . (file_exists($dbPath) ? "はい" : "いいえ"));
error_log("AuthServiceパス: " . $authServicePath . " 存在: " . (file_exists($authServicePath) ? "はい" : "いいえ"));
error_log("Utilsパス: " . $utilsPath . " 存在: " . (file_exists($utilsPath) ? "はい" : "いいえ"));

// ファイルが存在する場合のみ読み込む
if (file_exists($liffConfigPath)) {
    @require_once $liffConfigPath;
} else {
    // LIFF設定がなくても最低限のものを定義
    if (!defined('LIFF_ID')) define('LIFF_ID', '2007360690-Da3WzGrJ');
}

// 必須ファイルの読み込み
@require_once $configPath;  // データベース設定を含む設定ファイル
@require_once $dbPath;
@require_once $authServicePath;
@require_once $utilsPath;

// バッファをクリアして、ここまでの出力を捨てる
ob_clean();

// 認証サービスのインスタンスを取得
$authService = AuthService::getInstance();

// HTTPメソッドに応じて処理を分岐
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // LINE UserIDから部屋情報を取得
        handleGetRequest($authService);
        break;
        
    case 'POST':
        // LINEユーザーと部屋番号の紐づけを行う
        handlePostRequest($authService);
        break;
        
    default:
        // サポートされていないメソッド
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        ob_end_flush();
        exit();
}

// 出力が既に送信されている場合は追加のflushを行わない
if (!headers_sent()) {
    ob_end_flush();
}

/**
 * GET リクエストを処理
 * LINE UserIDから部屋情報を取得
 */
function handleGetRequest($authService) {
    // LINE UserIDの取得
    $lineUserId = isset($_GET['line_user_id']) ? $_GET['line_user_id'] : null;
    
    if (!$lineUserId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'LINE User ID is required'
        ]);
        ob_end_flush();
        exit();
    }
    
    try {
        // LINE User IDに紐づく部屋情報を取得
        $roomLink = $authService->getUserByLineId($lineUserId);
        
        if (!$roomLink) {
            // 部屋との紐づけがない場合
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'room_not_linked',
                'message' => 'LINEアカウントが部屋と紐づけられていません'
            ]);
            ob_end_flush();
            exit();
        }
        
        // 部屋情報を取得
        $roomNumber = $roomLink['room_number'];
        
        // 部屋の保留伝票情報を取得
        $roomTicket = $authService->getRoomOrder($roomNumber);
        
        // ユーザー認証トークン - データベースから取得できない場合は空文字列を使用
        $token = '';
        if (isset($roomLink['token']) && !empty($roomLink['token'])) {
            $token = $roomLink['token'];
        } else if (isset($roomLink['access_token']) && !empty($roomLink['access_token'])) {
            // 古いDBスキーマをサポート
            $token = $roomLink['access_token'];
        }
        
        // 応答データの作成
        $response = [
            'success' => true,
            'room_info' => [
                'room_number' => $roomNumber,
                'token' => $token,
                'square_order_id' => ($roomTicket && isset($roomTicket['square_order_id'])) ? $roomTicket['square_order_id'] : null
            ]
        ];
        
        // JSON_UNESCAPED_UNICODEを使用して、JSON形式で出力
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ob_end_flush();
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'データベースエラー: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit();
    }
}

/**
 * POST リクエストを処理
 * LINEユーザーと部屋番号の紐づけを行う
 */
function handlePostRequest($authService) {
    // リクエストボディの取得
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    // 必要なパラメータのチェック
    if (!isset($data['line_user_id']) || !isset($data['room_number']) || !isset($data['token'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '必要なパラメータが不足しています'
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit();
    }
    
    $lineUserId = $data['line_user_id'];
    $roomNumber = $data['room_number'];
    $token = $data['token'];
    
    try {
        // ユーザーと部屋を紐づける
        $result = $authService->linkUserToRoom($lineUserId, $roomNumber, $token);
        
        if (!$result) {
            // 紐づけ失敗
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'この部屋番号は既に別のLINEアカウントと紐づけられています'
            ], JSON_UNESCAPED_UNICODE);
            ob_end_flush();
            exit();
        }
        
        // 紐づけ成功
        echo json_encode([
            'success' => true,
            'message' => 'LINEアカウントと部屋番号の紐づけが完了しました'
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'データベースエラー: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit();
    }
} 