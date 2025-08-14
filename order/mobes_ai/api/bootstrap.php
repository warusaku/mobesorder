<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: mobes_ai API 用共通ブートストラップ。
 *  - composer autoload 読込
 *  - AiLogger 初期化 (相関 ID 発行)
 *  - PromptRegistrer に設定ファイルパスを注入
 *  - 共通 HTTP ヘッダを設定
 */

// 1) composer autoload
$autoloadPath = __DIR__ . '/../../../api/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// 2) core クラス直接読み込み (autoload が失敗した場合の保険)
require_once __DIR__ . '/../core/aicore/AiLogger.php';
require_once __DIR__ . '/../core/promptregistrer/PromptRegistrer.php';

use MobesAi\Core\AiCore\AiLogger;
use MobesAi\Core\PromptRegistrer\PromptRegistrer;

// 3) HTTP ヘッダ (JSON 固定)
header('Content-Type: application/json');

// 4) AiLogger 初期化 (リクエスト相関 ID)
AiLogger::initRequest();

// 5) adminsetting.json の絶対パス解決
$defaultSettingsPath = realpath(__DIR__ . '/../../../admin/adminpagesetting/adminsetting.json');
$settingsPath = getenv('AI_SETTING_PATH') ?: $defaultSettingsPath;
if ($settingsPath) {
    PromptRegistrer::setSettingsPath($settingsPath);
} 