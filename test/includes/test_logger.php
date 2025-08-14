<?php
/**
 * RTSP_Reader Test Framework - TestLogger
 * 
 * テストモジュール用のロギングクラス
 */

class TestLogger {
    // ログレベル定数
    const DEBUG = 1;
    const INFO = 2;
    const WARNING = 3;
    const ERROR = 4;
    const CRITICAL = 5;
    
    // レベル名マッピング
    private static $levelNames = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::WARNING => 'WARNING',
        self::ERROR => 'ERROR',
        self::CRITICAL => 'CRITICAL'
    ];
    
    // レベル色マッピング（コンソール/HTMLカラー）
    private static $levelColors = [
        self::DEBUG => ['color' => "\033[0;36m", 'html' => '#6c757d'], // シアン/グレー
        self::INFO => ['color' => "\033[0;32m", 'html' => '#28a745'],  // 緑
        self::WARNING => ['color' => "\033[1;33m", 'html' => '#ffc107'], // 黄色
        self::ERROR => ['color' => "\033[0;31m", 'html' => '#dc3545'],  // 赤
        self::CRITICAL => ['color' => "\033[1;31m", 'html' => '#721c24'] // 明るい赤
    ];
    
    private $logFile;
    private $minLevel;
    private $messages = [];
    private $maxMessages = 1000; // 保持する最大メッセージ数
    
    /**
     * TestLoggerコンストラクタ
     *
     * @param string $logFile ログファイルパス（nullの場合はファイルに書き込まない）
     * @param int $minLevel 記録する最小ログレベル
     */
    public function __construct($logFile = null, $minLevel = self::DEBUG) {
        $this->logFile = $logFile;
        $this->minLevel = $minLevel;
        
        // ログファイルが指定されている場合、ディレクトリが存在することを確認
        if ($this->logFile) {
            $dir = dirname($this->logFile);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * メッセージをログに記録
     *
     * @param int $level ログレベル
     * @param string $message ログメッセージ
     * @param array $context 追加コンテキスト情報
     * @return bool 成功したかどうか
     */
    public function log($level, $message, array $context = []) {
        // ログレベルがしきい値未満なら記録しない
        if ($level < $this->minLevel) {
            return false;
        }
        
        // タイムスタンプと整形されたメッセージを生成
        $timestamp = date('Y-m-d H:i:s');
        $levelName = isset(self::$levelNames[$level]) ? self::$levelNames[$level] : 'UNKNOWN';
        
        // コンテキスト変数を置換
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $message = str_replace('{' . $key . '}', $value, $message);
            }
        }
        
        // フォーマット済みログエントリの生成
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'level_name' => $levelName,
            'message' => $message,
            'context' => $context
        ];
        
        // メモリ内にメッセージを保持（最大数を超えた場合は古いものを削除）
        $this->messages[] = $logEntry;
        if (count($this->messages) > $this->maxMessages) {
            array_shift($this->messages);
        }
        
        // ログファイルが指定されている場合はファイルに書き込み
        if ($this->logFile) {
            $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
            $logLine = sprintf("[%s] [%s] %s%s\n", $timestamp, $levelName, $message, $contextStr);
            
            return file_put_contents($this->logFile, $logLine, FILE_APPEND) !== false;
        }
        
        return true;
    }
    
    /**
     * デバッグメッセージを記録
     *
     * @param string $message ログメッセージ
     * @param array $context 追加コンテキスト情報
     */
    public function debug($message, array $context = []) {
        return $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * 情報メッセージを記録
     *
     * @param string $message ログメッセージ
     * @param array $context 追加コンテキスト情報
     */
    public function info($message, array $context = []) {
        return $this->log(self::INFO, $message, $context);
    }
    
    /**
     * 警告メッセージを記録
     *
     * @param string $message ログメッセージ
     * @param array $context 追加コンテキスト情報
     */
    public function warning($message, array $context = []) {
        return $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * エラーメッセージを記録
     *
     * @param string $message ログメッセージ
     * @param array $context 追加コンテキスト情報
     */
    public function error($message, array $context = []) {
        return $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * クリティカルエラーメッセージを記録
     *
     * @param string $message ログメッセージ
     * @param array $context 追加コンテキスト情報
     */
    public function critical($message, array $context = []) {
        return $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * ログメッセージを取得
     *
     * @param int $limit 取得するメッセージの最大数（0=すべて）
     * @param int $level 取得する最小ログレベル
     * @return array ログメッセージの配列
     */
    public function getMessages($limit = 0, $level = null) {
        $filtered = [];
        
        // レベルフィルタリング
        if ($level !== null) {
            foreach ($this->messages as $message) {
                if ($message['level'] >= $level) {
                    $filtered[] = $message;
                }
            }
        } else {
            $filtered = $this->messages;
        }
        
        // 結果を新しい順にソート
        usort($filtered, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // 制限がある場合は適用
        if ($limit > 0) {
            return array_slice($filtered, 0, $limit);
        }
        
        return $filtered;
    }
    
    /**
     * HTML形式のログ出力を取得
     *
     * @param int $limit 取得するメッセージの最大数（0=すべて）
     * @param int $level 取得する最小ログレベル
     * @return string HTML形式のログ
     */
    public function getHtmlLog($limit = 0, $level = null) {
        $messages = $this->getMessages($limit, $level);
        $html = '<div class="test-log">';
        
        foreach ($messages as $message) {
            $levelName = $message['level_name'];
            $levelColor = isset(self::$levelColors[$message['level']]) ? 
                          self::$levelColors[$message['level']]['html'] : '#000000';
            
            $html .= sprintf(
                '<div class="log-entry" style="border-left: 4px solid %s; margin-bottom: 5px; padding: 5px; background-color: %s20;">' .
                '<span class="log-timestamp">%s</span> ' .
                '<span class="log-level" style="color: %s; font-weight: bold;">[%s]</span> ' .
                '<span class="log-message">%s</span>',
                $levelColor,
                $levelColor,
                htmlspecialchars($message['timestamp']),
                $levelColor,
                htmlspecialchars($levelName),
                htmlspecialchars($message['message'])
            );
            
            // コンテキストが存在する場合は表示
            if (!empty($message['context'])) {
                $html .= '<div class="log-context" style="margin-top: 5px; font-size: 0.9em; color: #666;">';
                $html .= '<pre style="margin: 0; white-space: pre-wrap;">' . htmlspecialchars(json_encode($message['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        if (empty($messages)) {
            $html .= '<div class="log-empty">ログメッセージはありません</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * ログをクリア
     */
    public function clear() {
        $this->messages = [];
    }
} 