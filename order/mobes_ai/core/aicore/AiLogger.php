<?php
/**
 * Version: 0.2.0 (2025-05-31)
 * File Description: Mobes AI 専用ログラッパ (詳細化版)。
 *  - JSON Lines, 300 KB rotation, latest 20 % keep
 *  - PSR-3 準拠レベル(debug/info/warn/error/critical)
 *  - リクエスト相関 ID (rid)・経過時間・メモリ使用量・呼び出し元(file/line/func)
 *  - 例外オブジェクトを渡すと message + trace を自動展開
 *  - logs/mobes_ai.log (統合) と logs/<executing-file>.log の二重出力
 */

namespace MobesAi\Core\AiCore;

class AiLogger
{
    private const LOG_DIR = __DIR__ . '/../../../../logs/';
    private const MASTER_FILE = 'mobes_ai.log';
    private const MAX_SIZE = 307200; // 300 KB

    private static string $rid = '';
    private static float $startTs = 0;
    private static array $defaultCtx = [];

    /* ---------- リクエスト単位初期化 ---------- */
    public static function initRequest(array $ctx = []): void
    {
        self::$rid = 'ai' . str_replace('.', '', uniqid('', true));
        self::$startTs = microtime(true);
        self::$defaultCtx = $ctx;
        // フォルダ確保
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0775, true);
        }
        // 最初の INFO を残しておく
        $logger = new self();
        $logger->info('API request start', $ctx);
    }

    /** 追加のデフォルトコンテキストを随時マージ */
    public static function addContext(array $ctx): void
    {
        self::$defaultCtx = array_merge(self::$defaultCtx, $ctx);
    }

    /* ---------- Public Logging APIs ---------- */
    public function debug(string $message, array $context = []): void { $this->write('DEBUG', $message, $context); }
    public function info(string $message, array $context = []): void { $this->write('INFO', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->write('WARN', $message, $context); }
    public function error(string $message, array $context = []): void { $this->write('ERROR', $message, $context); }
    public function critical(string $message, array $context = []): void { $this->write('CRITICAL', $message, $context); }

    /* ---------- Core Write Logic ---------- */
    private function write(string $level, string $message, array $context): void
    {
        // ディレクトリ存在確認（initRequest 未使用パスを考慮）
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0775, true);
        }
        // 呼び出し元特定 (write->level->caller)
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $bt[2] ?? $bt[1] ?? [];
        $file = basename($caller['file'] ?? 'unknown');
        $line = $caller['line'] ?? 0;
        $func = $caller['function'] ?? 'global';

        // Exception 展開
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            /** @var \Throwable $ex */
            $ex = $context['exception'];
            $context['exception'] = [
                'message' => $ex->getMessage(),
                'code'    => $ex->getCode(),
                'trace'   => $level === 'DEBUG' ? $ex->getTraceAsString() : substr($ex->getTraceAsString(), 0, 1000)
            ];
        }

        // メタ情報付与
        $entry = [
            'ts'           => date('Y-m-d H:i:s'),
            'level'        => $level,
            'rid'          => self::$rid,
            'elapsed_ms'   => self::$startTs ? (int) ((microtime(true) - self::$startTs) * 1000) : 0,
            'file'         => $file,
            'line'         => $line,
            'func'         => $func,
            'mem_now_kb'   => (int) (memory_get_usage(true) / 1024),
            'mem_peak_kb'  => (int) (memory_get_peak_usage(true) / 1024),
            'msg'          => $message,
            'ctx'          => array_merge(self::$defaultCtx, $context)
        ];

        $lineJson = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        // 統合ログ
        $masterPath = self::LOG_DIR . self::MASTER_FILE;
        $this->rotateIfNeeded($masterPath);
        file_put_contents($masterPath, $lineJson, FILE_APPEND | LOCK_EX);

        // 呼び出し元別ログ
        $perFilePath = self::LOG_DIR . $file . '.log';
        $this->rotateIfNeeded($perFilePath);
        file_put_contents($perFilePath, $lineJson, FILE_APPEND | LOCK_EX);
    }

    /* ---------- Rotation ---------- */
    private function rotateIfNeeded(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $size = filesize($path);
        if ($size === false || $size <= self::MAX_SIZE) {
            return;
        }

        $keepBytes = max(1024, (int) ($size * 0.2)); // 最新 20 % を保持

        $fp = fopen($path, 'c+');
        if (!$fp) {
            return;
        }

        // 末尾から必要分を読み込む
        fseek($fp, -$keepBytes, SEEK_END);
        $data = fread($fp, $keepBytes);
        if ($data === false) {
            fclose($fp);
            return;
        }

        // truncate & write back
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, $data);
        fclose($fp);
    }
} 