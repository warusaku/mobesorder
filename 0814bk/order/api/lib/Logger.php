<?php
/**
 * ログユーティリティークラス
 */
class Logger {
    // デフォルトのログファイル名
    private static $defaultLogFile = 'Order_system.log';
    
    // デバッグログファイル名
    private static $debugLogFile = 'Logger_debug.log';
    
    // ログローテーションサイズ (300KB)
    private static $maxLogSize = 307200;
    
    // デバッグモード
    private static $debugMode = true;
    
    // ログディレクトリ（デフォルトは相対パス）
    private static $logDirectory = '../../logs';
    
    /**
     * ログディレクトリを設定
     * @param string $dir ログディレクトリのパス
     */
    public static function setLogDirectory($dir) {
        self::$logDirectory = $dir;
        self::debugLog("ログディレクトリを設定: $dir");
    }
    
    /**
     * 内部デバッグログ
     * @param string $message デバッグメッセージ
     */
    private static function debugLog($message) {
        if (!self::$debugMode) {
            return;
        }
        
        try {
            // デバッグログファイルパス
            $logDir = self::$logDirectory;
            $logPath = $logDir . '/' . self::$debugLogFile;
            
            // ディレクトリがなければ作成（エラー抑制）
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            // タイムスタンプ
            $timestamp = date('Y-m-d H:i:s');
            
            // ログメッセージ作成
            $logEntry = "[$timestamp] [DEBUG] $message\n";
            
            // エラー抑制してファイル書き込み
            @file_put_contents($logPath, $logEntry, FILE_APPEND);
        } catch (Exception $e) {
            // デバッグログエラー時は何もしない（無限ループ防止）
        }
    }
    
    /**
     * ログを記録
     * @param string $message ログメッセージ
     * @param string $level ログレベル (INFO, ERROR, WARNING, DEBUG)
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function log($message, $level = 'INFO', $file = null) {
        try {
            // ログファイル設定
            $logFile = $file ? $file : self::$defaultLogFile;
            self::debugLog("ログ出力開始: ファイル=$logFile, レベル=$level");
            
            // ログディレクトリのパスを使用
            $logDir = self::$logDirectory;
            $logPath = $logDir . '/' . $logFile;
            self::debugLog("ログパス: $logPath");
            
            // ログディレクトリがなければ作成
            if (!is_dir($logDir)) {
                self::debugLog("ディレクトリが存在しません: $logDir - 作成を試みます");
                $mkdirResult = @mkdir($logDir, 0755, true);
                self::debugLog("ディレクトリ作成結果: " . ($mkdirResult ? '成功' : '失敗'));
            } else {
                self::debugLog("ディレクトリ存在確認: $logDir - 存在します");
                self::debugLog("ディレクトリ書き込み権限: " . (is_writable($logDir) ? 'あり' : 'なし'));
            }
            
            // ログローテーション処理
            self::rotateLog($logPath);
            
            // タイムスタンプ
            $timestamp = date('Y-m-d H:i:s');
            
            // ログメッセージ作成
            $logEntry = "[$timestamp] [$level] $message\n";
            
            // ログ書き込み
            $writeResult = @file_put_contents($logPath, $logEntry, FILE_APPEND);
            
            if ($writeResult === false) {
                self::debugLog("ログ書き込み失敗: $logPath");
                $errorInfo = error_get_last();
                if ($errorInfo) {
                    self::debugLog("最終エラー: " . $errorInfo['message']);
                }
                return false;
            } else {
                self::debugLog("ログ書き込み成功: $writeResult バイト書き込み");
                return true;
            }
        } catch (Exception $e) {
            self::debugLog("ログ処理中に例外発生: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ログローテーション処理
     * @param string $logFile ログファイルのパス
     */
    public static function rotateLog($logFile) {
        try {
            if (file_exists($logFile)) {
                $fileSize = filesize($logFile);
                self::debugLog("ログローテーション確認: $logFile, サイズ=$fileSize バイト, 上限=" . self::$maxLogSize . " バイト");
                
                if ($fileSize > self::$maxLogSize) {
                    self::debugLog("ログローテーション実行: ファイルを削除します");
                    $unlinkResult = @unlink($logFile);
                    self::debugLog("ファイル削除結果: " . ($unlinkResult ? '成功' : '失敗'));
                }
            } else {
                self::debugLog("ログファイル存在確認: $logFile - 存在しません");
            }
        } catch (Exception $e) {
            self::debugLog("ローテーション処理中に例外発生: " . $e->getMessage());
        }
    }
    
    /**
     * エラーログを記録
     * @param string $message エラーメッセージ
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function error($message, $file = null) {
        return self::log($message, 'ERROR', $file);
    }
    
    /**
     * 警告ログを記録
     * @param string $message 警告メッセージ
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function warning($message, $file = null) {
        return self::log($message, 'WARNING', $file);
    }
    
    /**
     * 情報ログを記録
     * @param string $message 情報メッセージ
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function info($message, $file = null) {
        return self::log($message, 'INFO', $file);
    }
    
    /**
     * デバッグログを記録
     * @param string $message デバッグメッセージ
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function debug($message, $file = null) {
        return self::log($message, 'DEBUG', $file);
    }
    
    /**
     * リクエスト情報をログに記録
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function logRequest($file = null) {
        self::debugLog("リクエストログ出力開始");
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        
        $params = [];
        if ($method === 'GET') {
            $params = $_GET;
        } elseif ($method === 'POST') {
            $params = $_POST;
        }
        
        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
        
        $message = "リクエスト: $method $uri | IP: $ip | UA: $userAgent | パラメータ: $paramsJson";
        
        return self::log($message, 'INFO', $file);
    }
    
    /**
     * レスポンス情報をログに記録
     * @param mixed $response レスポンスデータ
     * @param string $file ログファイル名（拡張子を含む）
     * @return bool 成功したかどうか
     */
    public static function logResponse($response, $file = null) {
        self::debugLog("レスポンスログ出力開始");
        
        $responseJson = is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE);
        
        // 長すぎるレスポンスは省略
        if (strlen($responseJson) > 1000) {
            $responseJson = substr($responseJson, 0, 997) . '...';
        }
        
        $message = "レスポンス: $responseJson";
        
        return self::log($message, 'INFO', $file);
    }
} 