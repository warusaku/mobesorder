<?php
/**
 * Square API連携サービス ログ管理クラス
 * Version: 1.0.0
 * Description: ログ出力とログローテーションを管理する専用クラス
 */
class SquareService_Logger {
    private static $logFile = null;
    private static $maxLogSize = 300 * 1024; // 300KB（ルールに基づく）
    private static $instance = null;
    private $logFileName;
    
    /**
     * コンストラクタ
     * 
     * @param string $logFileName ログファイル名（デフォルト: SquareService.log）
     */
    public function __construct($logFileName = 'SquareService.log') {
        $this->logFileName = $logFileName;
        $this->initLogFile();
    }
    
    /**
     * シングルトンインスタンスを取得
     * 
     * @param string $logFileName ログファイル名
     * @return SquareService_Logger
     */
    public static function getInstance($logFileName = 'SquareService.log') {
        if (self::$instance === null) {
            self::$instance = new self($logFileName);
        }
        return self::$instance;
    }
    
    /**
     * ログファイルの初期化
     */
    private function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/' . $this->logFileName;
        
        // ログローテーションのチェック
        $this->checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     * ルールに基づき300KBを超えたら最新20%を残して切り捨て
     */
    private function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            // ログファイルが存在しない場合は作成する
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログファイル作成\n";
            file_put_contents(self::$logFile, $message);
            return;
        }
        
        // ファイルサイズを確認
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            // ログの内容を読み込む
            $content = file_get_contents(self::$logFile);
            $lines = explode("\n", $content);
            
            // 最新20%のラインを保持（ルールに基づく）
            $keepPercentage = 0.2;
            $totalLines = count($lines);
            $keepLines = (int)($totalLines * $keepPercentage);
            
            // 最新の行を保持
            $newContent = array_slice($lines, -$keepLines);
            
            // ローテーション実行のログを追加
            $timestamp = date('Y-m-d H:i:s');
            array_unshift($newContent, "[$timestamp] [INFO] ログローテーション実行 - 元のサイズ: $fileSize bytes, 保持ライン数: $keepLines/$totalLines");
            
            // 新しい内容でファイルを書き換え
            file_put_contents(self::$logFile, implode("\n", $newContent));
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル (INFO, WARNING, ERROR)
     */
    public function logMessage($message, $level = 'INFO') {
        $this->initLogFile();
        
        // JST形式のタイムスタンプ（ルールに基づく）
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        
        // 呼び出し元の情報を取得（このクラスのメソッドをスキップ）
        $caller = 'unknown';
        $file = 'unknown';
        $line = 0;
        
        // バックトレースから適切な呼び出し元を探す
        foreach ($backtrace as $index => $trace) {
            if (isset($trace['class']) && $trace['class'] === __CLASS__) {
                continue;
            }
            if (isset($trace['function'])) {
                $caller = $trace['function'];
                if (isset($trace['class'])) {
                    $caller = $trace['class'] . '::' . $caller;
                }
            }
            if (isset($backtrace[$index - 1])) {
                $file = isset($backtrace[$index - 1]['file']) ? basename($backtrace[$index - 1]['file']) : 'unknown';
                $line = isset($backtrace[$index - 1]['line']) ? $backtrace[$index - 1]['line'] : 0;
            }
            break;
        }
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        
        // ログファイルへの書き込み
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // ログローテーションチェック（書き込み後）
        $this->checkLogRotation();
    }
    
    /**
     * 引数の内容を文字列化する
     * 
     * @param mixed $args 引数
     * @return string 文字列化された引数
     */
    public function formatArgs($args) {
        if (is_array($args)) {
            // 配列の場合は再帰的に処理
            $result = [];
            foreach ($args as $key => $value) {
                if (is_array($value)) {
                    // 配列が大きすぎる場合は要約
                    if (count($value) > 5) {
                        $result[$key] = '[配列: ' . count($value) . '件]';
                    } else {
                        $result[$key] = $this->formatArgs($value);
                    }
                } elseif (is_object($value)) {
                    $result[$key] = '[オブジェクト: ' . get_class($value) . ']';
                } else {
                    $result[$key] = $value;
                }
            }
            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } elseif (is_object($args)) {
            return '[オブジェクト: ' . get_class($args) . ']';
        } else {
            return json_encode($args, JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * 最後のエラーメッセージを取得
     * 
     * @return string エラーメッセージ
     */
    public function getLastErrorMessage() {
        if (file_exists(self::$logFile)) {
            $lastLines = shell_exec('tail -10 ' . self::$logFile);
            return $lastLines ?? 'エラーログが取得できません';
        }
        return 'エラーログファイルが存在しません';
    }
} 