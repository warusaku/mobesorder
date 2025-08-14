<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: get_recommendations API エンドポイント雛形。
 */

require_once __DIR__ . '/bootstrap.php';

// DB 設定読み込み
require_once __DIR__ . '/../../../api/config/config.php';
require_once __DIR__ . '/../core/aicore/RecommendationService.php';
require_once __DIR__ . '/../core/aicore/AiLogger.php';
require_once __DIR__ . '/middleware/RateLimiter.php';
require_once __DIR__ . '/../core/promptregistrer/PromptRegistrer.php';
require_once __DIR__ . '/../core/aicore/GeminiClient.php';

use MobesAi\Core\AiCore\RecommendationService;
use MobesAi\Core\AiCore\AiLogger;
use MobesAi\Api\Middleware\RateLimiter;
use MobesAi\Core\PromptRegistrer\PromptRegistrer;

// $logger は bootstrap でクラスロード済み
$logger = new MobesAi\Core\AiCore\AiLogger();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['mode'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'mode is required']);
        exit;
    }
    $mode = $input['mode'];
    $sessionId = $input['order_session_id'] ?? null;
    $lineUserId = $input['line_user_id'] ?? null;

    // コンテキスト補足
    AiLogger::addContext(['order_session_id' => $sessionId]);

    // rate limit
    $prLimiter = new PromptRegistrer();
    $limit = $prLimiter->getRateLimitPm();
    $rl = new RateLimiter(session_id(), $limit);
    if (!$rl->check()) {
        http_response_code(429);
        echo json_encode(['status'=>'error','message'=>'rate limit']);
        exit;
    }

    $svc = new RecommendationService();
    $result = $svc->getRecommendations($mode, $sessionId, $lineUserId);

    echo json_encode(array_merge(['status' => 'ok'], $result), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logger->error('get_recommendations failed', ['exception' => $e]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'internal server error']);
} 