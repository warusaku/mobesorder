<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: save_message API エンドポイント雛形。
 */

require_once __DIR__ . '/bootstrap.php';
// DB 設定読み込み
require_once __DIR__ . '/../../../api/config/config.php';
require_once __DIR__ . '/../../../api/lib/Database.php';
require_once __DIR__ . '/middleware/RateLimiter.php';

use MobesAi\Core\AiCore\AiLogger;
use MobesAi\Api\Middleware\RateLimiter;

$logger = new MobesAi\Core\AiCore\AiLogger();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $role = $input['role'] ?? null;
    $message = $input['message'] ?? null;
    $sessionId = $input['order_session_id'] ?? null;
    $lineUserId = $input['line_user_id'] ?? null;
    if (!$role || !$message) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'role and message required']);
        exit;
    }
    if (!$sessionId && !$lineUserId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'identifier required']);
        exit;
    }

    AiLogger::addContext(['order_session_id' => $sessionId]);

    $rlKey = $sessionId ?? $lineUserId ?? 'guest';
    $rl=new RateLimiter($rlKey,100);
    if(!$rl->check()){http_response_code(429);echo json_encode(['status'=>'error','message'=>'rate limit']);exit;}

    if($sessionId || $lineUserId){
        $db = \Database::getInstance();
        $db->insert('mobes_ai_messages', [
            'order_session_id' => $sessionId ?? 0,
            'line_user_id' => $lineUserId,
            'role' => $role,
            'message' => $message
        ]);
    }

    echo json_encode(['status' => 'saved','sessionless'=> $sessionId?false:true]);
} catch (Throwable $e) {
    $logger->error('save_message failed', ['exception' => $e]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'internal server error']);
} 