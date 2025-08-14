<?php
/**
 * ログヘルパークラス
 * order.php内でのログ出力を支援するためのクラス
 */
class LogHelper {
    /**
     * ログを出力する
     * 
     * @param string $message ログメッセージ
     * @param string $type ログタイプ (INFO|WARN|ERROR|DEBUG)
     * @param string $fileName 出力先ファイル名（拡張子込み）
     * @return bool 成功したかどうか
     */
    public static function log($message, $type = 'INFO', $fileName = 'order.log') {
        // ベースディレクトリからlogsへのパス
        $logDir = dirname(dirname(__DIR__)) . '/logs';
        
        // ディレクトリチェック
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log('ログディレクトリの作成に失敗しました: ' . $logDir);
                return false;
            }
        }
        
        // ファイルパス
        $filePath = $logDir . '/' . $fileName;
        
        // タイムスタンプ
        $timestamp = date('Y-m-d H:i:s');
        
        // ログ行を作成
        $logLine = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        
        // ファイルサイズチェックとローテーション
        $maxSize = 300 * 1024; // 300KB
        $reservePercent = 20;   // 20%を残す
        
        if (file_exists($filePath) && filesize($filePath) > $maxSize) {
            self::rotateLogFile($filePath, $maxSize, $reservePercent);
        }
        
        // ログ書き込み
        return file_put_contents($filePath, $logLine, FILE_APPEND) !== false;
    }
    
    /**
     * ファイル名をPHPファイル名から生成
     * 
     * @param string $phpFile PHPファイル名
     * @return string 対応するログファイル名
     */
    public static function getLogFileNameFromPhp($phpFile) {
        // ファイル名のみを取得
        $fileName = basename($phpFile);
        
        // 拡張子を変更
        return str_replace('.php', '.log', $fileName);
    }
    
    /**
     * ログローテーション
     * 
     * @param string $filePath ログファイルのパス
     * @param int $maxSize 最大サイズ (バイト)
     * @param int $reservePercent 残す割合 (%)
     * @return bool 成功したかどうか
     */
    private static function rotateLogFile($filePath, $maxSize, $reservePercent) {
        // ファイルが存在しない場合は何もしない
        if (!file_exists($filePath)) {
            return false;
        }
        
        // ファイルサイズを取得
        $fileSize = filesize($filePath);
        
        // 最大サイズを超えていない場合は何もしない
        if ($fileSize <= $maxSize) {
            return true;
        }
        
        // ファイル内容を読み込む
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return false;
        }
        
        // 行に分割
        $lines = explode(PHP_EOL, $contents);
        
        // 残す行数を計算（全体の20%）
        $linesToKeep = max(1, intval(count($lines) * ($reservePercent / 100)));
        
        // 残す行を抽出（最新のものから）
        $newLines = array_slice($lines, -$linesToKeep);
        
        // ファイルに書き戻す
        $rotationMsg = "[" . date('Y-m-d H:i:s') . "] [SYSTEM] ログローテーション実行: " . 
                       count($lines) . "行 → " . count($newLines) . "行" . PHP_EOL;
        
        $newContent = implode(PHP_EOL, $newLines) . PHP_EOL . $rotationMsg;
        return file_put_contents($filePath, $newContent) !== false;
    }
    
    /**
     * INFOレベルログ
     * 
     * @param string $message メッセージ
     * @param string $fileName ファイル名
     * @return bool 成功したかどうか
     */
    public static function info($message, $fileName = 'order.log') {
        return self::log($message, 'INFO', $fileName);
    }
    
    /**
     * WARNレベルログ
     * 
     * @param string $message メッセージ
     * @param string $fileName ファイル名
     * @return bool 成功したかどうか
     */
    public static function warn($message, $fileName = 'order.log') {
        return self::log($message, 'WARN', $fileName);
    }
    
    /**
     * ERRORレベルログ
     * 
     * @param string $message メッセージ
     * @param string $fileName ファイル名
     * @return bool 成功したかどうか
     */
    public static function error($message, $fileName = 'order.log') {
        return self::log($message, 'ERROR', $fileName);
    }
    
    /**
     * DEBUGレベルログ
     * 
     * @param string $message メッセージ
     * @param string $fileName ファイル名
     * @return bool 成功したかどうか
     */
    public static function debug($message, $fileName = 'order.log') {
        return self::log($message, 'DEBUG', $fileName);
    }
} 