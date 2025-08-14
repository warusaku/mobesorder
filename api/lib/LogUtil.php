<?php
/**
 * Version: 0.1.0 (2025-05-31)
 * File Description: 共通ログユーティリティ。ログファイルを <実行ファイル名>.log とし、300KB を超えた場合に末尾約20%を残してローテートする。
 */

class LogUtil
{
    // 最大ファイルサイズ: 300 KB
    private const MAX_SIZE = 307200; // 300 * 1024
    // 残すサイズ: 約 20% (60 KB)
    private const KEEP_SIZE = 61440; // 60 * 1024

    /**
     * ログ書き込み（必要に応じてローテーション）
     * @param string $executingFile 実行中ファイル (__FILE__ 等)
     * @param string $message       書き込むメッセージ
     * @param string $level         ログレベル（INFO, ERROR 等）
     */
    public static function rotateAndWrite(string $executingFile, string $message, string $level = 'INFO'): void
    {
        $logDir = dirname(__DIR__, 2) . '/logs'; // /api/lib から 2 つ上 = プロジェクトルート直下
        if (!is_dir($logDir)) {
            // ディレクトリが無ければ作成
            mkdir($logDir, 0755, true);
        }

        $baseName = basename($executingFile) . '.log';
        $logFile = $logDir . '/' . $baseName;

        // ローテーションチェック
        if (file_exists($logFile) && filesize($logFile) > self::MAX_SIZE) {
            self::rotate($logFile);
        }

        $timestamp = date('Y-m-d H:i:s'); // JST assumed (php.ini で date.timezone=Asia/Tokyo が前提)
        $requestId = substr(md5(uniqid('', true)), 0, 8);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $formattedMsg = "[$timestamp] [$level] [REQ:$requestId] [IP:$clientIp] $message\n";

        // 書き込み
        $fp = fopen($logFile, 'ab');
        if ($fp !== false) {
            flock($fp, LOCK_EX);
            fwrite($fp, $formattedMsg);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * サイズ超過時に末尾 KEEP_SIZE を残す形でトランケート
     * @param string $logFile
     */
    private static function rotate(string $logFile): void
    {
        // 読み込み
        $fp = fopen($logFile, 'rb');
        if ($fp === false) {
            return; // 読めなければスキップ
        }

        $fileSize = filesize($logFile);
        $startPos = max(0, $fileSize - self::KEEP_SIZE);
        fseek($fp, $startPos);
        $data = stream_get_contents($fp);
        fclose($fp);

        // 上書き
        $fp = fopen($logFile, 'wb');
        if ($fp === false) {
            return;
        }
        fwrite($fp, $data);
        fclose($fp);
    }
} 