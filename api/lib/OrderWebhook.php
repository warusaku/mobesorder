<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';

/**
 * 注文Webhook管理クラス
 * @version 1.0.0
 */
class OrderWebhook {
    private $db;
    private static $logFile = null;
    private static $maxLogSize = 300 * 1024; // 300KB 規約

    /**
     * コンストラクタ
     */
    public function __construct() {
        self::initLogFile();
        self::logMessage("OrderWebhook::__construct - Webhookサービス初期化開始", 'INFO');
        $this->db = Database::getInstance();
        self::logMessage("OrderWebhook::__construct - Webhookサービス初期化完了", 'INFO');
    }

    /**
     * ログファイルの初期化
     */
    private static function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/OrderWebhook.log';
        self::checkLogRotation();
    }

    /**
     * ログローテーションのチェックと実行
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログファイル作成\n";
            file_put_contents(self::$logFile, $message);
            return;
        }
        
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            $keep = intval(self::$maxLogSize * 0.2);
            $content = file_get_contents(self::$logFile);
            $content = substr($content, -$keep);
            $rotateMsg = "[".date('Y-m-d H:i:s')."] [INFO] log rotated\n";
            file_put_contents(self::$logFile, $rotateMsg . $content);
        }
    }

    /**
     * ログメッセージをファイルに書き込む
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
        $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Webhook URLの解決
     */
    private function resolveWebhookUrls() {
        $urls = [];

        // 1) square_settings.order_webhooks
        $settings = $this->getSquareSettings();
        if(isset($settings['order_webhooks'])) {
            $ow = $settings['order_webhooks'];
            if(is_object($ow)) $ow = get_object_vars($ow);
            if(is_array($ow)) {
                foreach($ow as $v) {
                    if(is_string($v) && filter_var($v, FILTER_VALIDATE_URL)) {
                        $urls[] = $v;
                    }
                }
            }
        }

        // 2) ルート階層 order_webhooks
        if(function_exists('loadSettings')) {
            $root = loadSettings();
            if($root && isset($root['order_webhooks'])) {
                $row = $root['order_webhooks'];
                if(is_object($row)) $row = get_object_vars($row);
                if(is_array($row)) {
                    foreach($row as $v) {
                        if(is_string($v) && filter_var($v, FILTER_VALIDATE_URL)) {
                            $urls[] = $v;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Square設定の取得
     */
    private function getSquareSettings() {
        static $settings = null;
        if($settings !== null) return $settings;

        $regPath = realpath(__DIR__ . '/../../admin/adminsetting_registrer.php');
        self::logMessage("adminsetting_registrer.php を読み込み: $regPath", 'INFO');

        if(!$regPath || !file_exists($regPath)) {
            self::logMessage('adminsetting_registrer.php が見つかりません', 'ERROR');
            return $settings = [];
        }

        if(!isset($GLOBALS['settingsFilePath']) || empty($GLOBALS['settingsFilePath'])) {
            $GLOBALS['settingsFilePath'] = dirname($regPath).'/adminpagesetting/adminsetting.json';
        }
        if(!isset($GLOBALS['logFile']) || empty($GLOBALS['logFile'])) {
            $rootPath = realpath(dirname($regPath).'/..');
            $logPath  = ($rootPath ?: __DIR__.'/../../') . '/logs/adminsetting_registrer.log';
            $GLOBALS['logFile'] = $logPath;
        }

        if(!defined('ADMIN_SETTING_INTERNAL_CALL')) {
            define('ADMIN_SETTING_INTERNAL_CALL', true);
        }

        include_once $regPath;

        if(function_exists('loadSettings')) {
            $all = loadSettings();
            if(is_array($all) && isset($all['square_settings'])) {
                return $settings = $all['square_settings'];
            }
            self::logMessage('square_settings セクションが見つかりません', 'WARNING');
        } else {
            self::logMessage('loadSettings 関数が定義されていません', 'ERROR');
        }
        return $settings = [];
    }

    /**
     * 注文作成時のWebhook通知
     */
    public function queueOrderWebhook($orderResult, $orderItems) {
        $urls = $this->resolveWebhookUrls();
        if(empty($urls)) {
            self::logMessage('order_webhooks が未設定のため通知はスキップします', 'WARNING');
            return;
        }

        self::logMessage('Webhook 送信先 URL 一覧: '.implode(', ', $urls), 'INFO');

        // メッセージ構築
        $sessionId = $orderResult['order_session_id'];
        $lines = [];
        $lines[] = "## ///新しい注文が追加されました///";
        $lines[] = "## room_NO. " . $orderResult['room_number'];
        $lines[] = "```";
        $lines[] = "session_ID:" . $sessionId;
        $lines[] = "square_ID:" . ($orderResult['square_item_id'] ?? 'N/A');
        $lines[] = "```";
        $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
        $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
        $lines[] = "## ///order_detail///";
        $lines[] = "";

        // スタッフメモ
        if (!empty($orderResult['note'])) {
            $lines[] = "**▼スタッフへの連絡**";
            $lines[] = $orderResult['note'];
            $lines[] = "";
        }

        // 最新注文詳細
        $lines[] = sprintf("注文#%s %s", $orderResult['id'], $orderResult['created_at']);
        foreach($orderItems as $it) {
            $lines[] = "---";
            $lines[] = $it['name'];
            $lines[] = "**x" . $it['quantity'] . "**";
            $lines[] = "¥" . number_format($it['price']);
        }
        $lines[] = "---";
        $lines[] = "** 合計: ¥" . number_format($orderResult['total_amount']) . " **";
        $lines[] = "";
        $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
        $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
        $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
        $lines[] = "";
        $lines[] = "↓↓ここまでのオーダー(確認用)↓↓";
        $lines[] = "--------------------";

        // 同セッションの過去注文を取得
        $subtotalSum = $orderResult['total_amount'];
        try {
            $dbConn = $this->db->getConnection();
            $stmt = $dbConn->prepare("SELECT id, order_datetime, total_amount FROM orders WHERE order_session_id = ? AND id < ? ORDER BY id DESC");
            $stmt->execute([$sessionId, $orderResult['id']]);
            $histOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($histOrders) {
                foreach($histOrders as $ho) {
                    $lines[] = "```";
                    $lines[] = sprintf("注文#%s", $ho['id']);
                    $lines[] = $ho['order_datetime'];

                    $stmt2 = $dbConn->prepare("SELECT product_name, quantity, unit_price FROM order_details WHERE order_id = ?");
                    $stmt2->execute([$ho['id']]);
                    $det = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    foreach($det as $d) {
                        $lines[] = $d['product_name'];
                        $lines[] = "x" . $d['quantity'];
                        $lines[] = "¥" . number_format($d['unit_price']);
                    }
                    $lines[] = "合計: ¥" . number_format($ho['total_amount']);
                    $lines[] = "```";
                    $lines[] = "--------------------";
                    $subtotalSum += $ho['total_amount'];
                }
            }
        } catch(Exception $hx) {
            self::logMessage('履歴取得エラー: '.$hx->getMessage(), 'WARNING');
        }

        // 累計
        $tax = round($subtotalSum * 0.1);
        $lines[] = "";
        $lines[] = "ご利用合計(税抜き): ¥" . number_format($subtotalSum);
        $lines[] = "消費税: ¥" . number_format($tax);
        $lines[] = "これまでのご利用合計: ¥" . number_format($subtotalSum + $tax);
        $lines[] = "";
        $lines[] = "--------------------";
        $lines[] = "issued by mobes system";

        $textPayload = implode("\n", $lines);

        $payload = [
            'event_type' => 'order_created',
            'order' => $orderResult,
            'items' => $orderItems,
            'text' => $textPayload
        ];

        $eventId = uniqid('evt_');
        foreach($urls as $url) {
            self::logMessage("Webhook 送信開始: $url", 'INFO');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

            if(strpos($url, 'discord.com/api/webhooks') !== false) {
                $bodyData = ['content' => $textPayload];
            } else {
                $bodyData = $payload;
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bodyData));
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $responseBody = curl_exec($ch);
            $curlErrNo = curl_errno($ch);
            $curlErrMsg = $curlErrNo ? curl_error($ch) : '';
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if(defined('CURLINFO_TOTAL_TIME_MS')) {
                $totalTimeMs = curl_getinfo($ch, CURLINFO_TOTAL_TIME_MS);
            } else {
                $totalTimeMs = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
            }
            curl_close($ch);

            if($curlErrNo) {
                self::logMessage("Webhook 送信失敗: $url errno=$curlErrNo message=$curlErrMsg", 'ERROR');
            } else {
                $respSnippet = substr(trim($responseBody), 0, 200);
                $level = ($httpCode >= 200 && $httpCode < 300) ? 'INFO' : 'WARNING';
                self::logMessage("Webhook 送信完了: $url http_code=$httpCode time={$totalTimeMs}ms resp_len=".strlen((string)$responseBody)." body='".$respSnippet."'", $level);
            }
        }

        // DB記録
        try {
            $this->db->insert('webhook_events', [
                'event_id' => $eventId,
                'order_session_id' => $orderResult['order_session_id'] ?? null,
                'event_type' => 'order_created',
                'processed_at' => '1970-01-01 00:00:00'
            ]);
        } catch(Exception $dbex) {
            self::logMessage('webhook_events 挿入エラー: '.$dbex->getMessage(), 'ERROR');
        }
    }

    /**
     * セッション終了時のWebhook通知
     */
    public function sendSessionCloseWebhook($sessionId, $closeType = 'Completed') {
        self::logMessage("sendSessionCloseWebhook start: session={$sessionId} type={$closeType}", 'INFO');
        try {
            // セッション情報取得
            $orders = $this->db->select("SELECT id,total_amount,order_datetime FROM orders WHERE order_session_id = ? ORDER BY id ASC", [$sessionId]);
            if(!$orders) {
                self::logMessage('no orders for session', 'WARNING');
                return;
            }

            $first = $orders[0];
            $roomNumberRow = $this->db->selectOne("SELECT room_number FROM order_sessions WHERE id = ?", [$sessionId]);
            $roomNumber = $roomNumberRow['room_number'] ?? 'unknown';

            // 合計
            $subtotal = array_sum(array_column($orders, 'total_amount'));
            $tax = round($subtotal * 0.1);
            $grand = $subtotal + $tax;

            // メッセージ構築
            $lines = [];
            
            // closeType に応じたヘッダーメッセージ
            if ($closeType === 'Pending_payment') {
                $lines[] = "** +++++管理者によるクローズ処理+++++ **";
                $lines[] = "- 管理者コンソールよりクローズ処理が行われました。";
                $lines[] = "- 注文は未会計処理です、支払い処理を確認してください。";
                $lines[] = "";
            } elseif ($closeType === 'Force_closed') {
                $lines[] = "** ##### 強制クローズしました #### **";
                $lines[] = "- 管理者コンソールよりこのオーダーは強制終了されました";
                $lines[] = "- この注文はSquareに記録されません";
                $lines[] = "";
            }
            
            $lines[] = "# !!!!Order close!!!!";
            $lines[] = "* ★★" . ($closeType === 'Force_closed' ? '注文を強制終了しました' : ($closeType === 'Pending_payment' ? '注文を未会計クローズしました' : '注文が決済されました')) . "★★ *";
            $lines[] = "# room_NO. " . $roomNumber;
            $lines[] = "`session_ID:" . $sessionId . "`";
            $lines[] = "";
            
            foreach($orders as $od) {
                $lines[] = "**";
                $lines[] = sprintf('注文#%s', $od['id']);
                $lines[] = $od['order_datetime'];
                $dets = $this->db->select("SELECT product_name,quantity,unit_price FROM order_details WHERE order_id = ?", [$od['id']]);
                foreach($dets as $d) {
                    $lines[] = $d['product_name'];
                    $lines[] = "x" . $d['quantity'];
                    $lines[] = "¥" . number_format($d['unit_price']);
                }
                $lines[] = "_ 合計: ¥" . number_format($od['total_amount']) . " _";
                $lines[] = "**";
                $lines[] = "--------------------";
            }
            
            $lines[] = "";
            $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
            $lines[] = "";
            $lines[] = "** ご利用合計(税抜き): ¥" . number_format($subtotal);
            $lines[] = "消費税: ¥" . number_format($tax);
            $lines[] = "_ ご利用合計: ¥" . number_format($grand) . " _";
            $lines[] = "**";
            $lines[] = "▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️";
            $lines[] = "issued by mobes system";
            
            $textPayload = implode("\n", $lines);

            $urls = $this->resolveWebhookUrls();
            if(empty($urls)) {
                self::logMessage('order_webhooks が未設定のため通知はスキップします', 'WARNING');
                return;
            }

            $eventId = uniqid('evt_');
            $payload = [
                'event_type' => 'session_closed',
                'session_id' => $sessionId,
                'room_number' => $roomNumber,
                'close_type' => $closeType,
                'text' => $textPayload
            ];

            // webhook_events にキューを記録
            try {
                $this->db->insert('webhook_events', [
                    'event_id' => $eventId,
                    'order_session_id' => $sessionId,
                    'event_type' => 'session_closed',
                    'processed_at' => '1970-01-01 00:00:00'
                ]);
            } catch(Exception $ix) {
                self::logMessage('webhook_events pre-insert error: '.$ix->getMessage(), 'WARNING');
            }

            foreach($urls as $url) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => (strpos($url, 'discord.com/api/webhooks') !== false
                        ? json_encode(['content' => $textPayload], JSON_UNESCAPED_UNICODE)
                        : json_encode($payload, JSON_UNESCAPED_UNICODE)),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT_MS => 1500
                ]);
                curl_exec($ch);
                curl_close($ch);
            }

            // 送信完了後、processed_at を更新
            try {
                $this->db->execute("UPDATE webhook_events SET processed_at = NOW() WHERE event_id = ?", [$eventId]);
            } catch(Exception $ux) {
                self::logMessage('webhook_events update error: '.$ux->getMessage(), 'WARNING');
            }
        } catch(Exception $e) {
            self::logMessage('sendSessionCloseWebhook error: '.$e->getMessage(), 'ERROR');
        }
    }
} 