<?php
/**
 * register_Logger.php
 * Version: 1.0.0
 * 
 * 部屋番号登録API用のログ処理クラス
 * ログファイルのローテーション（300KB制限、20%保持）を実装
 */

class RegisterLogger {
    private $logFile;
    private $maxFileSize = 307200; // 300KB
    private $retainPercent = 0.2; // 20%保持
    
    /**
     * コンストラクタ
     * 
     * @param string $logFileName ログファイル名（省略時はデフォルト）
     */
    public function __construct($logFileName = 'register_api.log') {
        $logDir = dirname(__DIR__, 2) . '/logs';
        
        // ログディレクトリの確認と作成
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logFile = $logDir . '/' . $logFileName;
        
        // ログファイルサイズチェックとローテーション
        $this->rotateLogIfNeeded();
    }
    
    /**
     * ログファイルのローテーション
     */
    private function rotateLogIfNeeded() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $fileSize = filesize($this->logFile);
        if ($fileSize > $this->maxFileSize) {
            // ファイル内容を読み込む
            $content = file_get_contents($this->logFile);
            if ($content === false) {
                return;
            }
            
            // 行単位で分割
            $lines = explode("\n", $content);
            $totalLines = count($lines);
            
            // 保持する行数を計算（最新の20%）
            $linesToKeep = (int)($totalLines * $this->retainPercent);
            $startIndex = $totalLines - $linesToKeep;
            
            // 最新の20%を取得
            $newContent = implode("\n", array_slice($lines, $startIndex));
            
            // ファイルを書き換え
            file_put_contents($this->logFile, $newContent);
            
            // ローテーション実行をログに記録
            $this->writeLog("ログファイルをローテーションしました。元サイズ: " . number_format($fileSize) . " bytes", 'INFO');
        }
    }
    
    /**
     * ログメッセージを記録
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル（INFO, DEBUG, WARNING, ERROR, FATAL）
     * @param mixed $context 追加のコンテキスト情報
     */
    public function log($message, $level = 'INFO', $context = null) {
        $this->writeLog($message, $level, $context);
    }
    
    /**
     * INFOレベルのログを記録
     */
    public function info($message, $context = null) {
        $this->writeLog($message, 'INFO', $context);
    }
    
    /**
     * DEBUGレベルのログを記録
     */
    public function debug($message, $context = null) {
        $this->writeLog($message, 'DEBUG', $context);
    }
    
    /**
     * WARNINGレベルのログを記録
     */
    public function warning($message, $context = null) {
        $this->writeLog($message, 'WARNING', $context);
    }
    
    /**
     * ERRORレベルのログを記録
     */
    public function error($message, $context = null) {
        $this->writeLog($message, 'ERROR', $context);
    }
    
    /**
     * FATALレベルのログを記録
     */
    public function fatal($message, $context = null) {
        $this->writeLog($message, 'FATAL', $context);
    }
    
    /**
     * ログをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル
     * @param mixed $context 追加のコンテキスト情報
     */
    private function writeLog($message, $level, $context = null) {
        // タイムスタンプ（JST）
        $timestamp = date('Y-m-d H:i:s');
        
        // 基本的なログメッセージ
        $logMessage = "[$timestamp] [$level] $message";
        
        // コンテキスト情報がある場合は追加
        if ($context !== null) {
            if (is_array($context) || is_object($context)) {
                $logMessage .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $logMessage .= " | Context: " . $context;
            }
        }
        
        $logMessage .= PHP_EOL;
        
        // ファイルに追記
        error_log($logMessage, 3, $this->logFile);
    }
    
    /**
     * API処理の開始をログに記録
     * 
     * @param string $apiName API名
     * @param array $params リクエストパラメータ
     */
    public function logApiStart($apiName, $params = []) {
        $this->info("--- $apiName 処理開始 ---");
        if (!empty($params)) {
            $this->debug("リクエストパラメータ", $params);
        }
        $this->info("リクエスト元IPアドレス: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
    }
    
    /**
     * API処理の完了をログに記録
     * 
     * @param string $apiName API名
     * @param bool $success 成功/失敗
     * @param mixed $result 処理結果
     */
    public function logApiEnd($apiName, $success = true, $result = null) {
        if ($success) {
            $this->info("$apiName 処理完了", $result);
        } else {
            $this->error("$apiName 処理失敗", $result);
        }
        $this->info("--- $apiName 処理終了 ---");
    }
    
    /**
     * 例外をログに記録
     * 
     * @param Exception $e 例外オブジェクト
     * @param string $context 例外が発生したコンテキスト
     */
    public function logException(Exception $e, $context = '') {
        $message = $context ? "$context: " : '';
        $message .= $e->getMessage();
        
        $this->error($message, [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} 