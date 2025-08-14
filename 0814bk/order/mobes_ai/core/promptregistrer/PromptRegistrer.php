<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: adminsetting.json から mobes_ai prompt 設定を読み取るラッパ雛形。
 */

namespace MobesAi\Core\PromptRegistrer;
use MobesAi\Core\AiCore\AiLogger;

class PromptRegistrer
{
    private const ADMIN_REGISTRER = __DIR__ . '/../../../../admin/adminsetting_registrer.php';
    private static array $cache = [];
    /** @var string|null */
    private static ?string $settingsPath = null;

    public function __construct()
    {
        if (!defined('ADMIN_SETTING_INTERNAL_CALL')) {
            define('ADMIN_SETTING_INTERNAL_CALL', true);
        }
        if (!function_exists('loadSettings')) {
            // settingsFilePath の事前注入（bootstrap などから）
            if (self::$settingsPath) {
                $GLOBALS['settingsFilePath'] = self::$settingsPath;
            }
            require_once self::ADMIN_REGISTRER;
        }
        /**
         * Lolipop 環境では realpath() が false を返すケースがあり、
         * adminsetting_registrer.php 内の $logFile が空文字になる。
         * そのまま file_put_contents('', ...) が呼ばれると致命的エラーとなるため、
         * ここで安全なデフォルトパスを注入する。
         */
        if (empty($GLOBALS['logFile'] ?? '')) {
            $GLOBALS['logFile'] = __DIR__ . '/../../../../logs/adminsetting_registrer.log';
            $logDir = dirname($GLOBALS['logFile']);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0775, true);
            }
        }
    }

    private function loadConfig(): array
    {
        $logger = new AiLogger();
        $logger->info('PromptRegistrer loadConfig start');
        if (!empty(self::$cache)) {
            $logger->info('PromptRegistrer loadConfig cache hit', ['keys' => array_keys(self::$cache)]);
            return self::$cache;
        }
        if (function_exists('loadSettings')) {
            $settings = loadSettings();
            if (is_array($settings)) {
                self::$cache = $settings;
            } else {
                // 読み込み失敗時は空配列で初期化
                $logger->warning('PromptRegistrer: settings load returned non-array', ['type' => gettype($settings)]);
                self::$cache = [];
            }
        }
        if (empty(self::$cache)) {
            $logger->warning('PromptRegistrer: settings could not be loaded');
        }
        $logger->info('PromptRegistrer loadConfig end', ['config_keys' => array_keys(self::$cache)]);
        return self::$cache;
    }

    public function getSystemPrompt(string $mode): string
    {
        $logger = new AiLogger();
        $logger->info('PromptRegistrer getSystemPrompt', ['mode' => $mode]);
        $cfg = $this->loadConfig();
        
        // モード別プロンプトがあればそれを優先
        if (isset($cfg['mobes_ai']['mode_prompts'][$mode])) {
            $logger->info('Using mode-specific prompt', ['mode' => $mode]);
            return $cfg['mobes_ai']['mode_prompts'][$mode];
        }
        
        // フォールバック: 基本プロンプト
        return $cfg['mobes_ai']['prompt'] ?? '';
    }

    public function getApiKey(): string
    {
        $logger = new AiLogger();
        $logger->info('PromptRegistrer getApiKey');
        $cfg = $this->loadConfig();
        return $cfg['mobes_ai']['gemini_api_key'] ?? '';
    }

    public function isStockLockEnabled(): bool
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        $enabled = isset($cfg['mobes_ai']['stock lock']) && $cfg['mobes_ai']['stock lock'] === true;
        $logger->info('PromptRegistrer isStockLockEnabled', ['enabled' => $enabled]);
        return $enabled;
    }

    public function getBasicStyle(): string
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        $style = $cfg['mobes_ai']['basicstyle'] ?? '';
        $logger->info('PromptRegistrer getBasicStyle', ['basicstyle' => $style]);
        return $style;
    }

    public function getProhibitions(): string
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        if (isset($cfg['mobes_ai']['prohibitions']['words'])) {
            $words = implode('、', $cfg['mobes_ai']['prohibitions']['words']);
            $logger->info('PromptRegistrer getProhibitions', ['prohibitions' => $words]);
            return $words;
        }
        $logger->info('PromptRegistrer getProhibitions', ['prohibitions' => '']);
        return '';
    }

    public function getChatRule(): string
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        if (isset($cfg['mobes_ai']['chatrule']['rules'])) {
            $rules = implode('、', $cfg['mobes_ai']['chatrule']['rules']);
            $logger->info('PromptRegistrer getChatRule', ['chatrule' => $rules]);
            return $rules;
        }
        $logger->info('PromptRegistrer getChatRule', ['chatrule' => '']);
        return '';
    }

    public function getRateLimitPm(): int
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        $limit = isset($cfg['mobes_ai']['rate_limit_pm']) ? (int)$cfg['mobes_ai']['rate_limit_pm'] : 120;
        $logger->info('PromptRegistrer getRateLimitPm', ['rate_limit_pm' => $limit]);
        return $limit;
    }

    public function getModelId(): string
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        $id = $cfg['mobes_ai']['gemini_model_id'] ?? 'chat-bison-001';
        $logger->info('PromptRegistrer getModelId', ['model_id' => $id]);
        return $id;
    }

    /**
     * 注文履歴の有無に応じたプロンプトを取得
     * @param string $mode モード (sommelier, omakase, suggest)
     * @param bool $hasHistory 注文履歴があるか
     * @return string|null プロンプト文字列、設定がない場合はnull
     */
    public function getHistoryPrompt(string $mode, bool $hasHistory): ?string
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        
        $historyKey = $hasHistory ? 'has_history' : 'no_history';
        
        // ソムリエモードの場合、ワイン履歴用の特別なキーがある
        if ($mode === 'sommelier' && $hasHistory) {
            $historyKey = 'has_wine_history';
        }
        
        $prompt = $cfg['mobes_ai']['history_prompts'][$mode][$historyKey] ?? null;
        $logger->info('PromptRegistrer getHistoryPrompt', [
            'mode' => $mode,
            'has_history' => $hasHistory,
            'prompt' => $prompt
        ]);
        
        return $prompt;
    }

    /**
     * 注文履歴機能が有効かどうかを取得
     * @return bool
     */
    public function isOrderHistoryEnabled(): bool
    {
        $cfg = $this->loadConfig();
        return $cfg['mobes_ai']['use_order_history'] ?? true;
    }

    /**
     * 商品のメタ説明を取得
     * @param string $productName 商品名
     * @return string|null メタ説明、存在しない場合はnull
     */
    public function getProductMetaDescription(string $productName): ?string
    {
        $logger = new AiLogger();
        $cfg = $this->loadConfig();
        
        if (isset($cfg['mobes_ai']['meta_description'][$productName])) {
            $description = $cfg['mobes_ai']['meta_description'][$productName];
            $logger->info('Found meta description for product', [
                'product_name' => $productName,
                'description_length' => mb_strlen($description)
            ]);
            return $description;
        }
        
        $logger->info('No meta description found for product', ['product_name' => $productName]);
        return null;
    }

    /**
     * すべてのメタ説明を取得
     * @return array 商品名をキー、説明を値とする連想配列
     */
    public function getAllProductMetaDescriptions(): array
    {
        $cfg = $this->loadConfig();
        return $cfg['mobes_ai']['meta_description'] ?? [];
    }

    /**
     * モード別のキーワード設定を取得
     */
    public function getModeKeywords(string $mode): array
    {
        $cfg = $this->loadConfig();
        if (!isset($cfg['mobes_ai']['mode_keywords'][$mode])) {
            return [];
        }
        return $cfg['mobes_ai']['mode_keywords'][$mode];
    }

    /** 外部から adminsetting.json の絶対パスを設定 */
    public static function setSettingsPath(string $path): void
    {
        self::$settingsPath = $path;
    }
} 