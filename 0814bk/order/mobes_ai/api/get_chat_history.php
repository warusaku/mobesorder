<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: チャット履歴取得APIエンドポイント
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../api/config/config.php';
require_once __DIR__ . '/../../../api/lib/Database.php';
require_once __DIR__ . '/../core/aicore/AiLogger.php';

use MobesAi\Core\AiCore\AiLogger;

$logger = new AiLogger();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['order_session_id'] ?? null;
    $lineUserId = $input['line_user_id'] ?? null;
    $limit = min((int)($input['limit'] ?? 50), 100);
    
    if (!$sessionId && !$lineUserId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'identifier required']);
        exit;
    }
    
    AiLogger::addContext(['order_session_id' => $sessionId]);
    
    $db = \Database::getInstance();
    $params = [];
    $whereClause = '';
    
    if ($sessionId) {
        $whereClause = 'order_session_id = :sid';
        $params[':sid'] = $sessionId;
    } else {
        $whereClause = 'line_user_id = :luid';
        $params[':luid'] = $lineUserId;
    }
    
    $messages = $db->select(
        "SELECT id, role, message, created_at 
         FROM mobes_ai_messages 
         WHERE {$whereClause} 
         ORDER BY created_at ASC 
         LIMIT :limit",
        array_merge($params, [':limit' => $limit])
    );
    
    $logger->info('Chat history retrieved', ['count' => count($messages)]);
    
    echo json_encode([
        'status' => 'ok',
        'messages' => $messages
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    $logger->error('get_chat_history failed', ['exception' => $e]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'internal server error']);
} 