<?php
/**
 * Webhook機能
 * 
 * このスクリプトはwebhookの読み込み、保存、送信機能を提供します
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/lib/Utils.php';

/**
 * Webhookマネージャークラス
 */
class WebhookManager {
    private $settingsFile;
    private $settings;
    private $maxWebhooks = 5;
    // adminsetting_registrer.php へのエンドポイント
    private $adminSettingEndpoint;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // 旧設定ファイル(テンプレート保持用) ※フォールバック
        $this->settingsFile = __DIR__ . '/adminpagesetting/webhooksetting.json';

        // adminsetting_registrer.php のURLを生成
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // 例: /fgsquare/admin
        $this->adminSettingEndpoint = $protocol . $host . $basePath . '/adminsetting_registrer.php';

        // 設定読み込み
        $this->loadSettings();
    }
    
    /**
     * 設定を読み込む
     * @return array 設定データ
     */
    public function loadSettings() {
        /*
         * まず adminsetting_registrer.php から order_webhooks セクションを取得
         * 取得できない場合のみ従来のwebhooksetting.jsonにフォールバックする
         */
        $orderWebhooks = $this->fetchAdminSettingSection('order_webhooks');

        if (is_array($orderWebhooks)) {
            // デフォルトテンプレートを基に設定を構築
            $this->settings = $this->getDefaultSettings();

            $webhooks = [];
            foreach ($orderWebhooks as $key => $url) {
                if (!empty($url)) {
                    $webhooks[] = [
                        'url'        => $url,
                        'name'       => 'Webhook ' . strtoupper($key),
                        'enabled'    => true,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            $this->settings['webhooks'] = $webhooks;
            return $this->settings;
        }

        // ===== ここからフォールバック処理 =====
        if (file_exists($this->settingsFile)) {
            $content = file_get_contents($this->settingsFile);
            $this->settings = json_decode($content, true);
            if (!$this->settings) {
                // JSONデコードに失敗した場合はデフォルト設定
                $this->settings = $this->getDefaultSettings();
                $this->saveSettings();
            }
        } else {
            // ファイルが存在しない場合はデフォルト設定
            $this->settings = $this->getDefaultSettings();
            $this->saveSettings();
        }
        
        return $this->settings;
    }
    
    /**
     * デフォルト設定を取得
     * @return array デフォルト設定
     */
    private function getDefaultSettings() {
        return [
            'webhooks' => [],
            'templates' => [
                'order_added' => [
                    'title' => '新規注文通知',
                    'description' => '新しい注文が追加されました',
                    'color' => '5763719', // Discord用カラーコード（ブルー）
                    'fields' => [
                        [
                            'name' => '注文ID',
                            'value' => '{{order_id}}',
                            'inline' => true
                        ],
                        [
                            'name' => '部屋番号',
                            'value' => '{{room_number}}',
                            'inline' => true
                        ],
                        [
                            'name' => '金額',
                            'value' => '{{amount}}円',
                            'inline' => true
                        ],
                        [
                            'name' => 'ステータス',
                            'value' => '{{status}}',
                            'inline' => true
                        ],
                        [
                            'name' => '商品',
                            'value' => '{{products}}',
                            'inline' => false
                        ]
                    ],
                    'footer' => [
                        'text' => 'FG Square 販売情報モニター'
                    ]
                ]
            ]
        ];
    }
    
    /**
     * 設定を保存
     * @return bool 保存結果
     */
    public function saveSettings() {
        // adminsetting.json へ保存（優先）
        if ($this->saveToAdminSettings()) {
            return true;
        }
        // 失敗した場合は旧ファイルに保存
        $json = json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->settingsFile, $json) !== false;
    }
    
    /**
     * 登録済みのwebhookURLを取得
     * @return array webhookのURL一覧
     */
    public function getWebhooks() {
        return isset($this->settings['webhooks']) ? $this->settings['webhooks'] : [];
    }
    
    /**
     * WebhookのURLを追加
     * @param string $url webhook URL
     * @param string $name webhook名（オプション）
     * @return bool 追加結果
     */
    public function addWebhook($url, $name = '') {
        // URL検証
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 重複チェック
        $webhooks = $this->getWebhooks();
        foreach ($webhooks as $webhook) {
            if ($webhook['url'] === $url) {
                return false; // 既に存在する
            }
        }
        
        // 上限チェック
        if (count($webhooks) >= $this->maxWebhooks) {
            return false; // 上限到達
        }
        
        // 追加
        $webhooks[] = [
            'url' => $url,
            'name' => $name ?: '未設定',
            'enabled' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->settings['webhooks'] = $webhooks;
        return $this->saveSettings();
    }
    
    /**
     * WebhookのURLを削除
     * @param string $url webhook URL
     * @return bool 削除結果
     */
    public function removeWebhook($url) {
        $webhooks = $this->getWebhooks();
        $found = false;
        
        foreach ($webhooks as $key => $webhook) {
            if ($webhook['url'] === $url) {
                unset($webhooks[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $this->settings['webhooks'] = array_values($webhooks); // インデックスを振り直し
            return $this->saveSettings();
        }
        
        return false;
    }
    
    /**
     * Webhook状態を切り替え
     * @param string $url webhook URL
     * @param bool $enabled 有効/無効
     * @return bool 結果
     */
    public function toggleWebhook($url, $enabled) {
        $webhooks = $this->getWebhooks();
        $found = false;
        
        foreach ($webhooks as $key => $webhook) {
            if ($webhook['url'] === $url) {
                $webhooks[$key]['enabled'] = (bool)$enabled;
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $this->settings['webhooks'] = $webhooks;
            return $this->saveSettings();
        }
        
        return false;
    }
    
    /**
     * テンプレート変数を置換
     * @param array $template テンプレート
     * @param array $data 置換データ
     * @return array 置換後のテンプレート
     */
    private function processTemplate($template, $data) {
        $result = $template;
        
        // JSON文字列に変換して一括置換
        $json = json_encode($result);
        
        foreach ($data as $key => $value) {
            $json = str_replace('"{{' . $key . '}}"', json_encode($value), $json);
            $json = str_replace('{{' . $key . '}}', $value, $json);
        }
        
        return json_decode($json, true);
    }
    
    /**
     * Discord用にwebhookデータを整形
     * @param array $data webhook送信データ
     * @return array Discord形式データ
     */
    private function formatDiscordWebhook($data) {
        $embed = [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'color' => intval($data['color'] ?? 0),
            'fields' => $data['fields'] ?? [],
            'footer' => $data['footer'] ?? null,
            'timestamp' => date('c')
        ];
        
        return [
            'content' => $data['content'] ?? null,
            'username' => $data['username'] ?? 'FG Square Bot',
            'embeds' => [$embed]
        ];
    }
    
    /**
     * 注文情報をwebhookで送信
     * @param array $orderData 注文データ
     * @return array 送信結果
     */
    public function sendOrderNotification($orderData) {
        $webhooks = $this->getWebhooks();
        $template = $this->settings['templates']['order_added'] ?? null;
        $results = [];
        
        if (empty($webhooks) || !$template) {
            return ['success' => false, 'message' => 'Webhook設定が見つかりません'];
        }
        
        // 商品一覧をテキスト形式に変換
        $productsText = "";
        if (isset($orderData['products']) && is_array($orderData['products'])) {
            foreach ($orderData['products'] as $product) {
                $name = $product['name'] ?? '不明な商品';
                $quantity = $product['quantity'] ?? 1;
                $price = $product['price'] ?? 0;
                
                $productsText .= "- {$name} x {$quantity} ({$price}円)\n";
            }
        }
        
        if (empty($productsText)) {
            $productsText = "商品情報なし";
        }
        
        // データを準備
        $data = [
            'order_id' => $orderData['id'] ?? '不明',
            'room_number' => $orderData['room_number'] ?? '不明',
            'amount' => number_format($orderData['total_amount'] ?? 0),
            'status' => $orderData['order_status'] ?? '不明',
            'products' => $productsText,
            'timestamp' => date('c')
        ];
        
        // テンプレート処理
        $processedTemplate = $this->processTemplate($template, $data);
        
        // Discord形式に変換
        $discordPayload = $this->formatDiscordWebhook($processedTemplate);
        
        // 各webhookに送信
        foreach ($webhooks as $webhook) {
            if (!isset($webhook['enabled']) || $webhook['enabled'] !== true) {
                continue; // 無効なwebhookはスキップ
            }
            
            $url = $webhook['url'];
            $result = $this->sendWebhook($url, $discordPayload);
            
            $results[] = [
                'url' => $url,
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
        
        return [
            'success' => count($results) > 0,
            'sent' => $results
        ];
    }
    
    /**
     * Webhookを送信
     * @param string $url webhook URL
     * @param array $data 送信データ
     * @return array 送信結果
     */
    private function sendWebhook($url, $data) {
        try {
            $ch = curl_init($url);
            
            $payload = json_encode($data);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                return ['success' => false, 'message' => "cURLエラー: $error"];
            }
            
            curl_close($ch);
            
            // HTTPステータスコードをチェック
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'message' => "送信成功 (HTTP $httpCode)"];
            } else {
                return ['success' => false, 'message' => "HTTPエラー: $httpCode"];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => "例外エラー: " . $e->getMessage()];
        }
    }
    
    /**
     * テスト通知を送信
     * @param string $url webhook URL
     * @return array 送信結果
     */
    public function sendTestNotification($url) {
        $testData = [
            'title' => 'テスト通知',
            'description' => 'これはwebhookのテスト通知です',
            'color' => '5763719',
            'fields' => [
                [
                    'name' => 'サーバー時間',
                    'value' => date('Y-m-d H:i:s'),
                    'inline' => true
                ],
                [
                    'name' => '送信元',
                    'value' => 'FG Square 販売情報モニター',
                    'inline' => true
                ]
            ],
            'footer' => [
                'text' => 'Webhook テスト'
            ]
        ];
        
        $discordPayload = $this->formatDiscordWebhook($testData);
        return $this->sendWebhook($url, $discordPayload);
    }

    /**
     * adminsetting_registrer.php から指定セクションを取得
     * @param string $section
     * @return array|null
     */
    private function fetchAdminSettingSection($section) {
        if (empty($this->adminSettingEndpoint)) {
            return null;
        }

        $url = $this->adminSettingEndpoint . '?section=' . urlencode($section);
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['success']) || $data['success'] !== true) {
            return null;
        }

        return $data['settings'];
    }

    /**
     * adminsetting.json の order_webhooks セクションへ書き込み
     * @return bool 成功可否
     */
    private function saveToAdminSettings() {
        if (empty($this->adminSettingEndpoint)) {
            return false;
        }

        // order_webhooks 用の連想配列を作成（最大3件まで）
        $orderWebhooks = [];
        $index = 1;
        foreach ($this->settings['webhooks'] as $webhook) {
            $orderWebhooks['webhook_url' . $index] = $webhook['url'];
            $index++;
            if ($index > 3) break; // 上限3件
        }
        // 未使用分を空文字で埋める
        for (; $index <= 3; $index++) {
            $orderWebhooks['webhook_url' . $index] = '';
        }

        $ch = curl_init($this->adminSettingEndpoint . '?section=order_webhooks');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderWebhooks));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return false;
        }

        $data = json_decode($response, true);
        curl_close($ch);

        return isset($data['success']) && $data['success'] === true;
    }
}

// 直接アクセス時の処理（APIエンドポイントとして動作）
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    // 出力はJSONで返す
    header('Content-Type: application/json');
    
    // CSRF対策
    $isValidRequest = false;
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    
    if (strpos($referer, $host) !== false) {
        $isValidRequest = true;
    }
    
    if (!$isValidRequest) {
        echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
        exit;
    }
    
    $manager = new WebhookManager();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'get_webhooks':
            echo json_encode([
                'success' => true, 
                'webhooks' => $manager->getWebhooks()
            ]);
            break;
            
        case 'add_webhook':
            $url = isset($_POST['url']) ? $_POST['url'] : '';
            $name = isset($_POST['name']) ? $_POST['name'] : '';
            
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URLが指定されていません']);
                break;
            }
            
            $result = $manager->addWebhook($url, $name);
            echo json_encode([
                'success' => $result, 
                'message' => $result ? 'Webhookを追加しました' : 'Webhookの追加に失敗しました'
            ]);
            break;
            
        case 'remove_webhook':
            $url = isset($_POST['url']) ? $_POST['url'] : '';
            
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URLが指定されていません']);
                break;
            }
            
            $result = $manager->removeWebhook($url);
            echo json_encode([
                'success' => $result, 
                'message' => $result ? 'Webhookを削除しました' : '指定されたWebhookが見つかりません'
            ]);
            break;
            
        case 'toggle_webhook':
            $url = isset($_POST['url']) ? $_POST['url'] : '';
            $enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false;
            
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URLが指定されていません']);
                break;
            }
            
            $result = $manager->toggleWebhook($url, $enabled);
            $status = $enabled ? '有効' : '無効';
            echo json_encode([
                'success' => $result, 
                'message' => $result ? "Webhookを{$status}にしました" : '指定されたWebhookが見つかりません'
            ]);
            break;
            
        case 'test_webhook':
            $url = isset($_POST['url']) ? $_POST['url'] : '';
            
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URLが指定されていません']);
                break;
            }
            
            $result = $manager->sendTestNotification($url);
            echo json_encode([
                'success' => $result['success'], 
                'message' => $result['message']
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '不明なアクションです']);
            break;
    }
    
    exit;
} 