<?php
/**
 * WebhookManager クラス
 * 
 * 複数の通知チャネル（Slack、LINE、Discord、一般的なWebhook）を管理し、
 * イベント発生時に設定されたチャネルに通知を送信するクラス。
 */
class WebhookManager {
    // 通知チャネルのハンドラークラス名マッピング
    private static $HANDLER_CLASSES = [
        'slack'   => 'SlackHandler',
        'line'    => 'LineHandler',
        'discord' => 'DiscordHandler',
        'webhook' => 'WebhookHandler'
    ];
    
    private $db;                 // データベース接続
    private $channel_handlers;   // チャネルハンドラーのインスタンス配列
    private $active_channels;    // アクティブなチャネル設定の配列
    private $last_errors;        // 最後のエラーメッセージ配列
    
    /**
     * コンストラクタ
     * 
     * @param PDO $db データベース接続
     */
    public function __construct($db) {
        $this->db = $db;
        $this->channel_handlers = [];
        $this->active_channels = [];
        $this->last_errors = [];
        
        // 必要なハンドラークラスをロード
        $this->loadHandlerClasses();
        
        // アクティブなチャネル設定を読み込む
        $this->loadActiveChannels();
    }
    
    /**
     * ハンドラークラスファイルをロードする
     */
    private function loadHandlerClasses() {
        $handlers_dir = __DIR__ . '/channel_handlers/';
        
        foreach (self::$HANDLER_CLASSES as $type => $class_name) {
            $file_path = $handlers_dir . strtolower($type) . '_handler.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * アクティブな通知チャネル設定をデータベースから読み込む
     */
    private function loadActiveChannels() {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM notification_channels WHERE active = 1"
            );
            $stmt->execute();
            
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($channels as $channel) {
                $channel_id = $channel['channel_id'];
                $this->active_channels[$channel_id] = $channel;
                
                // ハンドラーのインスタンス化
                $this->initializeChannelHandler($channel);
            }
        } catch (PDOException $e) {
            error_log("通知チャネル読み込みエラー: " . $e->getMessage());
        }
    }
    
    /**
     * 単一のチャネルハンドラーを初期化する
     * 
     * @param array $channel チャネル設定
     * @return bool 初期化成功フラグ
     */
    private function initializeChannelHandler($channel) {
        $channel_id = $channel['channel_id'];
        $channel_type = $channel['channel_type'];
        $channel_config = json_decode($channel['config'], true);
        
        // チャネルタイプに対応するハンドラークラスが存在するか確認
        if (!isset(self::$HANDLER_CLASSES[$channel_type])) {
            $this->last_errors[$channel_id] = "不明なチャネルタイプ: $channel_type";
            return false;
        }
        
        $handler_class = self::$HANDLER_CLASSES[$channel_type];
        
        // ハンドラークラスが利用可能か確認
        if (!class_exists($handler_class)) {
            $this->last_errors[$channel_id] = "ハンドラークラスが見つかりません: $handler_class";
            return false;
        }
        
        try {
            // ハンドラーのインスタンス化
            $this->channel_handlers[$channel_id] = new $handler_class($channel_config);
            return true;
        } catch (Exception $e) {
            $this->last_errors[$channel_id] = "ハンドラー初期化エラー: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * 判定結果通知を送信する
     * 
     * @param string $message メッセージ本文
     * @param array $determination_result 判定結果データ (省略可)
     * @param array $options 追加オプション (省略可)
     * @return array 各チャネルの送信結果
     */
    public function sendDeterminationNotification($message, $determination_result = null, $options = []) {
        $results = [];
        
        // オプションの初期化（デフォルト値設定）
        $options = array_merge([
            'include_details' => true,  // 詳細情報を含める
            'include_image' => true,    // 画像情報を含める
            'notification_level' => 'ALL', // 通知レベル（ALL, ALERTS_ONLY, CRITICAL_ONLY）
            'target_channels' => []      // 特定のチャネルのみに送信する場合
        ], $options);
        
        // 判定結果が指定されている場合、オプションに追加
        if ($determination_result !== null) {
            $options['determination_result'] = $determination_result;
            
            // 通知レベルに基づくフィルタリング
            $display_level = isset($determination_result['display_level']) 
                ? $determination_result['display_level'] 
                : 'INFO';
            
            if ($options['notification_level'] === 'ALERTS_ONLY' && 
                !in_array($display_level, ['HIGH', 'CRITICAL'])) {
                return ['status' => 'skipped', 'reason' => 'notification_level_filter'];
            } else if ($options['notification_level'] === 'CRITICAL_ONLY' && 
                       $display_level !== 'CRITICAL') {
                return ['status' => 'skipped', 'reason' => 'notification_level_filter'];
            }
        }
        
        // 各アクティブチャネルに送信
        foreach ($this->active_channels as $channel_id => $channel) {
            // 特定チャネルのみに送信する場合のフィルタリング
            if (!empty($options['target_channels']) && 
                !in_array($channel_id, $options['target_channels'])) {
                continue;
            }
            
            // 対象トリガータイプのフィルタリング
            if (isset($determination_result['display_type']) && 
                !empty($channel['trigger_types'])) {
                
                $trigger_types = json_decode($channel['trigger_types'], true);
                
                if (!in_array($determination_result['display_type'], $trigger_types) && 
                    !in_array('ALL', $trigger_types)) {
                    continue;
                }
            }
            
            // チャネル固有の通知レベルフィルタリング
            if (isset($determination_result['display_level']) && 
                !empty($channel['notification_level'])) {
                
                $display_level = $determination_result['display_level'];
                $channel_level = $channel['notification_level'];
                
                if ($channel_level === 'ALERTS_ONLY' && 
                    !in_array($display_level, ['HIGH', 'CRITICAL'])) {
                    continue;
                } else if ($channel_level === 'CRITICAL_ONLY' && 
                           $display_level !== 'CRITICAL') {
                    continue;
                }
            }
            
            $handler = $this->getChannelHandler($channel_id);
            
            if ($handler) {
                // ハンドラーを使用して通知を送信
                $success = $handler->send($message, $options);
                
                $results[$channel_id] = [
                    'success' => $success,
                    'error' => $success ? null : $handler->getLastError(),
                    'response' => $handler->getResponseData()
                ];
                
                // エラーがあれば記録
                if (!$success) {
                    $this->last_errors[$channel_id] = $handler->getLastError();
                }
            } else {
                $results[$channel_id] = [
                    'success' => false,
                    'error' => "ハンドラーが初期化されていません: " . 
                              (isset($this->last_errors[$channel_id]) ? $this->last_errors[$channel_id] : '不明なエラー')
                ];
            }
        }
        
        // 送信結果を追加
        $this->logNotificationResult($message, $determination_result, $options, $results);
        
        return [
            'status' => 'completed',
            'results' => $results
        ];
    }
    
    /**
     * チャネルハンドラーのインスタンスを取得する
     * 
     * @param string $channel_id チャネルID
     * @return object|null ハンドラーインスタンス
     */
    public function getChannelHandler($channel_id) {
        if (isset($this->channel_handlers[$channel_id])) {
            return $this->channel_handlers[$channel_id];
        }
        
        // ハンドラーが初期化されていない場合、初期化を試みる
        if (isset($this->active_channels[$channel_id])) {
            if ($this->initializeChannelHandler($this->active_channels[$channel_id])) {
                return $this->channel_handlers[$channel_id];
            }
        }
        
        return null;
    }
    
    /**
     * 通知結果をデータベースに記録する
     * 
     * @param string $message メッセージ
     * @param array $determination_result 判定結果
     * @param array $options オプション
     * @param array $results 送信結果
     */
    private function logNotificationResult($message, $determination_result, $options, $results) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notification_logs 
                 (message, determination_result_id, options, results, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            
            $result_id = isset($determination_result['result_id']) ? $determination_result['result_id'] : null;
            $options_json = json_encode($options);
            $results_json = json_encode($results);
            
            $stmt->execute([$message, $result_id, $options_json, $results_json]);
        } catch (PDOException $e) {
            error_log("通知ログ記録エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 最後のエラーメッセージを取得する
     * 
     * @param string $channel_id チャネルID（省略時は全チャネルのエラー）
     * @return string|array エラーメッセージ
     */
    public function getLastError($channel_id = null) {
        if ($channel_id !== null) {
            return isset($this->last_errors[$channel_id]) ? $this->last_errors[$channel_id] : null;
        }
        
        return $this->last_errors;
    }
    
    /**
     * アクティブなチャネル一覧を取得する
     * 
     * @return array チャネル設定の配列
     */
    public function getActiveChannels() {
        return $this->active_channels;
    }
    
    /**
     * 通知ログを取得する
     * 
     * @param int $limit 取得する最大数
     * @param int $offset オフセット
     * @return array 通知ログの配列
     */
    public function getNotificationLogs($limit = 50, $offset = 0) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM notification_logs 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset"
            );
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("通知ログ取得エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 新しい通知チャネルを作成する
     * 
     * @param array $channel_data チャネルデータ
     * @return int|bool 作成されたチャネルIDまたはfalse
     */
    public function createChannel($channel_data) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notification_channels 
                 (channel_name, channel_type, config, trigger_types, notification_level, active, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            
            $channel_name = $channel_data['channel_name'];
            $channel_type = $channel_data['channel_type'];
            $config = is_array($channel_data['config']) ? json_encode($channel_data['config']) : $channel_data['config'];
            $trigger_types = is_array($channel_data['trigger_types']) ? json_encode($channel_data['trigger_types']) : $channel_data['trigger_types'];
            $notification_level = $channel_data['notification_level'] ?? 'ALL';
            $active = isset($channel_data['active']) ? (int)$channel_data['active'] : 1;
            
            $stmt->execute([$channel_name, $channel_type, $config, $trigger_types, $notification_level, $active]);
            
            $channel_id = $this->db->lastInsertId();
            
            // 新しいチャネルをアクティブリストに追加
            if ($active) {
                $this->loadActiveChannels();
            }
            
            return $channel_id;
        } catch (PDOException $e) {
            error_log("通知チャネル作成エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 既存の通知チャネルを更新する
     * 
     * @param int $channel_id チャネルID
     * @param array $channel_data 更新データ
     * @return bool 更新成功フラグ
     */
    public function updateChannel($channel_id, $channel_data) {
        try {
            $updates = [];
            $params = [];
            
            // 更新フィールドの作成
            if (isset($channel_data['channel_name'])) {
                $updates[] = "channel_name = ?";
                $params[] = $channel_data['channel_name'];
            }
            
            if (isset($channel_data['channel_type'])) {
                $updates[] = "channel_type = ?";
                $params[] = $channel_data['channel_type'];
            }
            
            if (isset($channel_data['config'])) {
                $updates[] = "config = ?";
                $config = is_array($channel_data['config']) ? json_encode($channel_data['config']) : $channel_data['config'];
                $params[] = $config;
            }
            
            if (isset($channel_data['trigger_types'])) {
                $updates[] = "trigger_types = ?";
                $trigger_types = is_array($channel_data['trigger_types']) ? json_encode($channel_data['trigger_types']) : $channel_data['trigger_types'];
                $params[] = $trigger_types;
            }
            
            if (isset($channel_data['notification_level'])) {
                $updates[] = "notification_level = ?";
                $params[] = $channel_data['notification_level'];
            }
            
            if (isset($channel_data['active'])) {
                $updates[] = "active = ?";
                $params[] = (int)$channel_data['active'];
            }
            
            $updates[] = "updated_at = NOW()";
            
            // チャネルIDを追加
            $params[] = $channel_id;
            
            // SQL実行
            $sql = "UPDATE notification_channels SET " . implode(", ", $updates) . " WHERE channel_id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            // アクティブチャネルリストの再読み込み
            $this->loadActiveChannels();
            
            // ハンドラーの更新
            if (isset($this->channel_handlers[$channel_id])) {
                unset($this->channel_handlers[$channel_id]);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("通知チャネル更新エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 通知チャネルを削除する
     * 
     * @param int $channel_id チャネルID
     * @return bool 削除成功フラグ
     */
    public function deleteChannel($channel_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM notification_channels WHERE channel_id = ?");
            $result = $stmt->execute([$channel_id]);
            
            // アクティブチャネルとハンドラーから削除
            if (isset($this->active_channels[$channel_id])) {
                unset($this->active_channels[$channel_id]);
            }
            
            if (isset($this->channel_handlers[$channel_id])) {
                unset($this->channel_handlers[$channel_id]);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("通知チャネル削除エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * テスト通知を送信する
     * 
     * @param int $channel_id チャネルID
     * @param string $message テストメッセージ
     * @return array テスト結果
     */
    public function sendTestNotification($channel_id, $message = "これはテスト通知です") {
        // テスト用のサンプル判定結果データ
        $sample_result = [
            'result_id' => 'TEST_' . time(),
            'camera_id' => 'TEST_CAM',
            'area_id' => 'TEST_AREA',
            'ocr_text' => 'テスト OCR 123',
            'numerical_value' => 123,
            'display_type' => 'TEST',
            'display_level' => 'INFO',
            'determination_type' => 'TEST',
            'is_threshold_alert' => false,
            'capture_time' => date('Y-m-d H:i:s')
        ];
        
        $options = [
            'include_details' => true,
            'include_image' => false,
            'target_channels' => [$channel_id],
            'is_test' => true
        ];
        
        return $this->sendDeterminationNotification($message, $sample_result, $options);
    }
}

// Loggerクラスが未定義の場合は定義
if (!class_exists('Logger')) {
    /**
     * シンプルなロガークラス (本来はちゃんとしたロギングライブラリを使用する)
     */
    class Logger {
        private $name;
        
        public function __construct($name) {
            $this->name = $name;
        }
        
        public function info($message) {
            $this->log('INFO', $message);
        }
        
        public function warning($message) {
            $this->log('WARNING', $message);
        }
        
        public function error($message) {
            $this->log('ERROR', $message);
        }
        
        private function log($level, $message) {
            $date = date('Y-m-d H:i:s');
            $log_message = "[$date] [$level] [{$this->name}] $message" . PHP_EOL;
            error_log($log_message, 3, __DIR__ . '/../logs/webhook_manager.log');
        }
    }
} 
 
 
 
 