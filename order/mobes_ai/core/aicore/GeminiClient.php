<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: Gemini REST API 呼び出しラッパ雛形。
 */

namespace MobesAi\Core\AiCore;

use MobesAi\Core\AiCore\AiLogger;

class GeminiClient
{
    private string $apiKey;
    private string $modelId;
    private AiLogger $logger;

    public function __construct(string $apiKey, string $modelId = 'chat-bison-001')
    {
        $this->apiKey = $apiKey;
        $this->modelId = $modelId;
        $this->logger = new AiLogger();
    }

    /**
     * 送信プロンプト→ Gemini へ POST。
     * @param array $payload
     * @return array|null
     */
    public function send(array $payload): ?array
    {
        // TODO: 実装（cURL POST & エラーハンドリング）
        $this->logger->info('GeminiClient stub called', $payload);
        return null;
    }

    /**
     * @param array $messages array of string segments to send as a single prompt
     * @return string|null raw response text
     */
    public function sendPrompt(array $messages): ?string
    {
        try {
            $payload = implode("\n", $messages);

            // Composer autoload 側で既に読み込まれている想定
            $client = \Gemini::client($this->apiKey);
            $model = $client->generativeModel(model: $this->modelId);

            // 10 秒タイムアウトは guzzle オプションで渡す（Gemini::factory で詳細設定可）
            $response = $model->generateContent($payload);
            $text = $response->text();
            $this->logger->info('Gemini response received');
            return $text;
        } catch (\Throwable $e) {
            $this->logger->error('Gemini request failed', ['exception' => $e]);
            return null;
        }
    }
} 