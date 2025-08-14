<?php
/**
 * Kitchen Monitor Functions - ロリポップサーバー対応版
 */

// ロリポップ対応：エラー表示設定
if (!defined('LOLIPOP_ERROR_DISPLAY')) {
    define('LOLIPOP_ERROR_DISPLAY', true);
    if (LOLIPOP_ERROR_DISPLAY) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }
}

// ロリポップ対応：絶対パス設定
$currentDir = dirname(__FILE__);
$rootDir = dirname(dirname($currentDir));

// 設定ファイル読み込み（ロリポップ対応）
$mainConfigPath = $rootDir . '/api/config/config.php';
if (!file_exists($mainConfigPath)) {
    die("Main config not found at: $mainConfigPath");
}

// ロリポップ対応：作業ディレクトリ変更
$oldCwd = getcwd();
chdir($rootDir);

try {
    require_once $mainConfigPath;
} catch (Exception $e) {
    chdir($oldCwd);
    die("Config loading failed: " . $e->getMessage());
}

chdir($oldCwd);

// Database クラス読み込み（ロリポップ対応）
$databasePath = $rootDir . '/api/lib/Database.php';
if (!file_exists($databasePath)) {
    die("Database.php not found at: $databasePath");
}

require_once $databasePath;

class KitchenMonitorFunctions {
    private $db;
    private $config;
    private $rootDir;

    public function __construct() {
        $this->rootDir = dirname(dirname(dirname(__FILE__)));
        
        try {
            // ロリポップ対応：Database インスタンス作成時のエラーハンドリング
            $this->db = Database::getInstance();
            
            // 設定ファイル読み込み
            $configPath = dirname(__FILE__) . '/config.php';
            if (file_exists($configPath)) {
                $this->config = include $configPath;
            } else {
                // ロリポップ用デフォルト設定
                $this->config = [
                    'allowed_ips' => [
                        '127.0.0.1', 
                        '::1',
                        '153.127.0.0/16',  // ロリポップサーバーIP範囲
                        '157.7.0.0/16'     // ロリポップサーバーIP範囲
                    ],
                    'kitchen_auth' => ['require_login' => false],
                    'lumos_integration' => false,
                    'auto_refresh_interval' => 30000,
                    'audio_enabled' => true
                ];
            }
        } catch (Exception $e) {
            $this->logError("KitchenMonitorFunctions initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ロリポップ対応：エラーログ機能
     */
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] Kitchen Monitor Error: $message\n";
        error_log($logMessage);
        
        // ロリポップ対応：ファイルログも試す
        $logFile = dirname(__FILE__) . '/../logs/kitchen_error.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * ロリポップ対応：情報ログ機能
     */
    private function logInfo($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] Kitchen Monitor: $message\n";
        error_log($logMessage);
    }

    /**
     * Get active orders for kitchen display（ロリポップ対応版）
     */
    public function getActiveOrders($showCompleted = false) {
        try {
            // ロリポップ対応：メモリ使用量チェック
            if (memory_get_usage(true) > (50 * 1024 * 1024)) { // 50MB
                $this->logError("Memory usage high: " . memory_get_usage(true));
            }

            // ステータスカラム存在チェック（ロリポップMySQL対応）
            $checkSql = "SHOW COLUMNS FROM order_details WHERE Field = 'status'";
            $statusColumnExists = $this->db->select($checkSql);
            
            if (empty($statusColumnExists)) {
                // フォールバック：ステータスカラムなし
                $sql = "SELECT 
                            od.id as order_detail_id,
                            od.order_id,
                            od.order_session_id,
                            od.product_name,
                            od.quantity,
                            'ordered' as status,
                            od.created_at as order_datetime,
                            NULL as status_updated_at,
                            o.room_number,
                            COALESCE(o.memo, '') as memo,
                            TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) as minutes_elapsed,
                            CASE 
                                WHEN TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) >= 30 THEN 'urgent'
                                WHEN TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) >= 20 THEN 'warning'
                                ELSE 'normal'
                            END as priority_level
                        FROM order_details od
                        JOIN orders o ON od.order_id = o.id
                        WHERE o.order_status = 'OPEN'
                        ORDER BY od.created_at ASC, od.id ASC
                        LIMIT 100"; // ロリポップ対応：結果数制限
            } else {
                // 通常のクエリ（ロリポップMySQL対応）
                $statusFilter = $showCompleted 
                    ? "COALESCE(od.status, 'ordered') IN ('ordered', 'ready', 'delivered', 'cancelled')"
                    : "COALESCE(od.status, 'ordered') IN ('ordered', 'ready')";
                
                $sql = "SELECT 
                            od.id as order_detail_id,
                            od.order_id,
                            od.order_session_id,
                            od.product_name,
                            od.quantity,
                            COALESCE(od.status, 'ordered') as status,
                            od.created_at as order_datetime,
                            od.status_updated_at,
                            o.room_number,
                            COALESCE(o.memo, '') as memo,
                            TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) as minutes_elapsed,
                            CASE 
                                WHEN TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) >= 30 THEN 'urgent'
                                WHEN TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) >= 20 THEN 'warning'
                                ELSE 'normal'
                            END as priority_level
                        FROM order_details od
                        JOIN orders o ON od.order_id = o.id
                        WHERE $statusFilter
                            AND o.order_status = 'OPEN'
                        ORDER BY 
                            FIELD(COALESCE(od.status, 'ordered'), 'ordered', 'ready'),
                            od.created_at ASC,
                            od.id ASC
                        LIMIT 100"; // ロリポップ対応：結果数制限
            }

            $result = $this->db->select($sql);
            
            // 時間フォーマット追加
            if (is_array($result)) {
                foreach ($result as &$order) {
                    $order['time_ago'] = $this->formatTimeAgo($order['minutes_elapsed']);
                    $order['formatted_time'] = date('H:i', strtotime($order['order_datetime']));
                }
            }

            return $result ?: [];
            
        } catch (Exception $e) {
            $this->logError("Failed to get active orders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update order status（ロリポップ対応版）
     */
    public function updateOrderStatus($orderDetailId, $newStatus, $updatedBy = 'kitchen_monitor', $note = '') {
        try {
            // 入力値検証（ロリポップ対応）
            $orderDetailId = intval($orderDetailId);
            if ($orderDetailId <= 0) {
                throw new Exception("Invalid order detail ID");
            }

            $allowedStatuses = ['ordered', 'ready', 'delivered', 'cancelled'];
            if (!in_array($newStatus, $allowedStatuses)) {
                throw new Exception("Invalid status: $newStatus");
            }

            // ステータスカラム存在チェック
            $checkSql = "SHOW COLUMNS FROM order_details WHERE Field = 'status'";
            $statusColumnExists = $this->db->select($checkSql);
            
            if (empty($statusColumnExists)) {
                throw new Exception("Status column does not exist. Please run database migration first.");
            }

            // 現在のステータス取得
            $currentOrder = $this->db->selectOne(
                "SELECT COALESCE(status, 'ordered') as status FROM order_details WHERE id = ? LIMIT 1",
                [$orderDetailId]
            );

            if (!$currentOrder) {
                throw new Exception("Order detail not found: $orderDetailId");
            }

            // ステータス遷移検証
            if (!$this->isValidStatusTransition($currentOrder['status'], $newStatus)) {
                throw new Exception("Invalid status transition from {$currentOrder['status']} to {$newStatus}");
            }

            // 更新データ準備
            $updateData = [
                'status' => $newStatus,
                'status_updated_by' => substr($updatedBy, 0, 100) // ロリポップ対応：長さ制限
            ];

            // status_updated_at カラム存在チェック
            $checkUpdatedAtSql = "SHOW COLUMNS FROM order_details WHERE Field = 'status_updated_at'";
            $updatedAtColumnExists = $this->db->select($checkUpdatedAtSql);
            
            if (!empty($updatedAtColumnExists)) {
                $updateData['status_updated_at'] = date('Y-m-d H:i:s');
            }

            // データベース更新
            $setClause = [];
            $params = [];
            foreach ($updateData as $column => $value) {
                $setClause[] = "$column = ?";
                $params[] = $value;
            }
            $params[] = $orderDetailId;
            
            $sql = "UPDATE order_details SET " . implode(', ', $setClause) . " WHERE id = ?";
            $updateResult = $this->db->update($sql, $params);

            if ($updateResult) {
                $this->logInfo("Order status updated: ID=$orderDetailId, {$currentOrder['status']} -> $newStatus");

                return [
                    'success' => true,
                    'message' => 'Status updated successfully',
                    'data' => [
                        'order_detail_id' => $orderDetailId,
                        'previous_status' => $currentOrder['status'],
                        'new_status' => $newStatus,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                throw new Exception("Database update failed");
            }

        } catch (Exception $e) {
            $this->logError("Failed to update order status: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get kitchen statistics（ロリポップ対応版）
     */
    public function getKitchenStats() {
        try {
            // ステータスカラム存在チェック
            $checkSql = "SHOW COLUMNS FROM order_details WHERE Field = 'status'";
            $statusColumnExists = $this->db->select($checkSql);
            
            if (empty($statusColumnExists)) {
                // フォールバック統計
                $sql = "SELECT 
                            COUNT(*) as pending_orders,
                            0 as ready_orders,
                            0 as delivered_today,
                            0 as cancelled_today,
                            0 as avg_completion_time,
                            MAX(created_at) as last_order_time
                        FROM order_details od
                        JOIN orders o ON od.order_id = o.id
                        WHERE DATE(od.created_at) = CURDATE()
                            AND o.order_status = 'OPEN'";
            } else {
                $sql = "SELECT 
                            COUNT(CASE WHEN COALESCE(status, 'ordered') = 'ordered' THEN 1 END) as pending_orders,
                            COUNT(CASE WHEN COALESCE(status, 'ordered') = 'ready' THEN 1 END) as ready_orders,
                            COUNT(CASE WHEN COALESCE(status, 'ordered') = 'delivered' AND DATE(COALESCE(status_updated_at, created_at)) = CURDATE() THEN 1 END) as delivered_today,
                            COUNT(CASE WHEN COALESCE(status, 'ordered') = 'cancelled' AND DATE(COALESCE(status_updated_at, created_at)) = CURDATE() THEN 1 END) as cancelled_today,
                            AVG(CASE 
                                WHEN COALESCE(status, 'ordered') = 'delivered' AND status_updated_at IS NOT NULL 
                                THEN TIMESTAMPDIFF(MINUTE, created_at, status_updated_at) 
                                END) as avg_completion_time,
                            MAX(created_at) as last_order_time
                        FROM order_details od
                        JOIN orders o ON od.order_id = o.id
                        WHERE DATE(od.created_at) = CURDATE()
                            AND o.order_status = 'OPEN'";
            }

            $stats = $this->db->selectOne($sql);

            // 最も忙しい部屋を取得
            $busiestRoomSql = "SELECT o.room_number, COUNT(*) as order_count
                               FROM order_details od
                               JOIN orders o ON od.order_id = o.id
                               WHERE DATE(od.created_at) = CURDATE()
                                   AND o.order_status = 'OPEN'
                               GROUP BY o.room_number
                               ORDER BY order_count DESC
                               LIMIT 1";
            
            $busiestRoom = $this->db->selectOne($busiestRoomSql);

            // 結果整形
            $stats['busiest_room'] = $busiestRoom ? $busiestRoom['room_number'] : null;
            $stats['avg_completion_time'] = $stats['avg_completion_time'] ? round($stats['avg_completion_time'], 1) : 0;

            return $stats;
            
        } catch (Exception $e) {
            $this->logError("Failed to get kitchen stats: " . $e->getMessage());
            return [
                'pending_orders' => 0,
                'ready_orders' => 0,
                'delivered_today' => 0,
                'cancelled_today' => 0,
                'avg_completion_time' => 0,
                'busiest_room' => null,
                'last_order_time' => null
            ];
        }
    }

    /**
     * Check for new orders since last update
     */
    public function getNewOrders($lastUpdateTime) {
        try {
            $sql = "SELECT COUNT(*) as new_count 
                    FROM order_details od
                    JOIN orders o ON od.order_id = o.id
                    WHERE od.created_at > ? 
                        AND o.order_status = 'OPEN'
                    LIMIT 1";
            
            $result = $this->db->selectOne($sql, [$lastUpdateTime]);
            return intval($result['new_count'] ?? 0);
        } catch (Exception $e) {
            $this->logError("Failed to get new orders count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Validate status transition
     */
    private function isValidStatusTransition($currentStatus, $newStatus) {
        $validTransitions = [
            'ordered' => ['ready', 'cancelled'],
            'ready' => ['delivered', 'cancelled', 'ordered'], 
            'delivered' => ['ordered'], 
            'cancelled' => ['ordered'] 
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Format elapsed time for display
     */
    private function formatTimeAgo($minutes) {
        $minutes = intval($minutes);
        if ($minutes < 60) {
            return "{$minutes}分前";
        } elseif ($minutes < 1440) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return "{$hours}時間{$remainingMinutes}分前";
        } else {
            $days = floor($minutes / 1440);
            return "{$days}日前";
        }
    }

    /**
     * Check IP access permission（ロリポップ対応）
     */
    public function isIpAllowed($ip) {
        // ロリポップ対応：より柔軟なIP制限
        $allowedIps = $this->config['allowed_ips'];
        
        // 直接一致チェック
        if (in_array($ip, $allowedIps)) {
            return true;
        }

        // CIDR範囲チェック
        foreach ($allowedIps as $allowedIp) {
            if (strpos($allowedIp, '/') !== false) {
                if ($this->ipInRange($ip, $allowedIp)) {
                    return true;
                }
            }
        }

        // ロリポップ開発環境では制限を緩和
        if (in_array($ip, ['127.0.0.1', '::1']) || 
            strpos($ip, '192.168.') === 0 || 
            strpos($ip, '10.') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange($ip, $range) {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }

    /**
     * Authenticate kitchen access（ロリポップ対応）
     */
    public function authenticateKitchenAccess() {
        // ロリポップ対応：セッション処理
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // IP制限チェック
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 
                   $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                   $_SERVER['HTTP_X_REAL_IP'] ?? 
                   '127.0.0.1';

        // ロリポップでは複数のIPが返される場合がある
        if (strpos($clientIP, ',') !== false) {
            $clientIP = trim(explode(',', $clientIP)[0]);
        }

        // IP制限を無効化（テスト用）
        // if (!$this->isIpAllowed($clientIP)) {
        //     $this->logError("Access denied for IP: $clientIP");
        //     http_response_code(403);
        //     die(json_encode(['error' => 'Access denied from this IP']));
        // }

        // 認証が必要な場合のチェック
        if ($this->config['kitchen_auth']['require_login']) {
            if (!isset($_SESSION['kitchen_authenticated'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Send Discord webhook notification
     */
    public function sendDiscordWebhook($orderDetailId, $previousStatus, $newStatus, $productName, $roomNumber) {
        try {
            // Check if webhook is enabled
            if (!$this->config['discord']['webhook_enabled'] || 
                empty($this->config['discord']['webhook_url']) ||
                $this->config['discord']['webhook_url'] === 'YOUR_DISCORD_WEBHOOK_URL_HERE') {
                return true; // Skip if not configured
            }

            // Check if this status change should trigger webhook
            if (!in_array($newStatus, $this->config['discord']['send_on_status'])) {
                return true; // Skip if status not in trigger list
            }

            $webhookUrl = $this->config['discord']['webhook_url'];
            $timeout = $this->config['discord']['timeout'] ?? 5;

            // Create Discord message
            $message = $this->createDiscordMessage($orderDetailId, $previousStatus, $newStatus, $productName, $roomNumber);

            // Send webhook
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhookUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($message),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Kitchen-Monitor/1.0'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->logError("Discord webhook curl error: $error");
                return false;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logError("Discord webhook HTTP error: $httpCode - $response");
                return false;
            }

            $this->logInfo("Discord webhook sent successfully for order $orderDetailId");
            return true;

        } catch (Exception $e) {
            $this->logError("Discord webhook exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create Discord message payload
     */
    private function createDiscordMessage($orderDetailId, $previousStatus, $newStatus, $productName, $roomNumber) {
        // Status translations
        $statusText = [
            'ordered' => '注文済み',
            'ready' => '調理完了',
            'delivered' => '配達完了',
            'cancelled' => 'キャンセル'
        ];

        // Status colors for Discord embeds
        $statusColors = [
            'ready' => 0xf39c12,     // Orange
            'delivered' => 0x27ae60, // Green
            'cancelled' => 0xe74c3c  // Red
        ];

        // Status emojis
        $statusEmojis = [
            'ready' => '👨‍🍳',
            'delivered' => '🚚',
            'cancelled' => '❌'
        ];

        $prevText = $statusText[$previousStatus] ?? $previousStatus;
        $newText = $statusText[$newStatus] ?? $newStatus;
        $emoji = $statusEmojis[$newStatus] ?? '📋';
        $color = $statusColors[$newStatus] ?? 0x3498db;

        $timestamp = date('c'); // ISO 8601 format

        return [
            'embeds' => [
                [
                    'title' => "{$emoji} 注文ステータス更新",
                    'description' => "**{$productName}** のステータスが更新されました",
                    'color' => $color,
                    'fields' => [
                        [
                            'name' => '🏠 部屋番号',
                            'value' => $roomNumber,
                            'inline' => true
                        ],
                        [
                            'name' => '📝 注文ID',
                            'value' => "#$orderDetailId",
                            'inline' => true
                        ],
                        [
                            'name' => '🔄 ステータス変更',
                            'value' => "$prevText → **$newText**",
                            'inline' => false
                        ]
                    ],
                    'footer' => [
                        'text' => 'Kitchen Monitor System'
                    ],
                    'timestamp' => $timestamp
                ]
            ],
            'allowed_mentions' => [
                'parse' => []  // 通知を無効化
            ]
        ];
    }
}