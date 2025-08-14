<?php
/**
 * LINEログインと部屋連携設定を管理するクラス
 * index.phpからincludeされ、LINEログインと部屋連携の必要性を制御します
 */
class LOGIN_CONTROL {
    // シングルトンインスタンス
    private static $instance = null;
    
    // ログファイル
    private $logFile = "../logs/login_control.log";
    
    // 設定値（デフォルト）
    private $settings = [
        'line_login_required' => true,  // LINEログインが必要かどうか
        'room_link_required' => true,   // 部屋連携が必要かどうか
    ];

    /**
     * コンストラクタ
     * 設定ファイルから値を読み込み
     */
    private function __construct() {
        // 設定ファイルからの読み込み処理
        $this->loadSettings();
        $this->logMessage("LOGIN_CONTROLクラスが初期化されました", "INFO");
    }

    /**
     * シングルトンインスタンスの取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 設定ファイルからの読み込み
     */
    private function loadSettings() {
        // adminsetting.json から設定を読み込む
        $rootPath = realpath(__DIR__ . '/../../..'); // fgsquare ルート
        $settingsFile = $rootPath . '/admin/adminpagesetting/adminsetting.json';

        if (!file_exists($settingsFile)) {
            $this->logMessage("設定ファイルが見つかりません: " . $settingsFile, "WARNING");
            return; // デフォルト設定のまま
        }

        $json = file_get_contents($settingsFile);
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logMessage("設定ファイルのJSON解析に失敗しました: " . json_last_error_msg(), "ERROR");
            return;
        }

        // login_settings セクション またはトップレベルキーに対応
        if (isset($data['login_settings'])) {
            $sect = $data['login_settings'];
        } else {
            $sect = $data; // フラット構造想定
        }

        if (isset($sect['line_login_required'])) {
            $this->settings['line_login_required'] = (bool)$sect['line_login_required'];
        }
        if (isset($sect['room_link_required'])) {
            $this->settings['room_link_required'] = (bool)$sect['room_link_required'];
        }

        $this->logMessage("設定ファイル読み込み完了: line_login_required=" . ($this->settings['line_login_required']? 'true':'false') . ", room_link_required=" . ($this->settings['room_link_required']? 'true':'false'), "INFO");
    }

    /**
     * LINEログインが必要かどうかを取得
     * @return bool LINEログインが必要な場合はtrue
     */
    public function isLineLoginRequired() {
        return $this->settings['line_login_required'];
    }

    /**
     * 部屋連携が必要かどうかを取得
     * @return bool 部屋連携が必要な場合はtrue
     */
    public function isRoomLinkRequired() {
        return $this->settings['room_link_required'];
    }

    /**
     * ログメッセージの出力
     * @param string $message メッセージ
     * @param string $level ログレベル（INFO/WARN/ERROR）
     */
    private function logMessage($message, $level = "INFO") {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp][$level] $message" . PHP_EOL;
        
        // ログファイルのパスを確認
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // ログファイルにメッセージを追記
        error_log($logMessage, 3, $this->logFile);
        
        // ローテーション処理
        $this->rotateLogFile();
    }
    
    /**
     * ログファイルのローテーション処理
     * ファイルサイズが300KBを超えたら古いログを削除
     */
    private function rotateLogFile() {
        if (file_exists($this->logFile) && filesize($this->logFile) > 300 * 1024) {
            // ファイルの内容を取得
            $content = file_get_contents($this->logFile);
            
            // 最新の80%程度を残す（約20%を削除）
            $newContent = substr($content, (int)(strlen($content) * 0.2));
            
            // ファイルに書き戻す
            file_put_contents($this->logFile, $newContent);
        }
    }
} 