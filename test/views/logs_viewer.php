<div class="card">
    <h2>システムログ閲覧</h2>
    <p>アプリケーションのログを閲覧・分析できます。</p>
    
    <form action="/fgsquare/test_dashboard.php?action=logs" method="get" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="logs">
        
        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; align-items: flex-end;">
            <div>
                <label for="log_level" style="display: block; margin-bottom: 5px;">ログレベル:</label>
                <select id="log_level" name="level" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="all" <?php echo (!isset($_GET['level']) || $_GET['level'] == 'all') ? 'selected' : ''; ?>>全て</option>
                    <option value="debug" <?php echo (isset($_GET['level']) && $_GET['level'] == 'debug') ? 'selected' : ''; ?>>DEBUG</option>
                    <option value="info" <?php echo (isset($_GET['level']) && $_GET['level'] == 'info') ? 'selected' : ''; ?>>INFO</option>
                    <option value="warning" <?php echo (isset($_GET['level']) && $_GET['level'] == 'warning') ? 'selected' : ''; ?>>WARNING</option>
                    <option value="error" <?php echo (isset($_GET['level']) && $_GET['level'] == 'error') ? 'selected' : ''; ?>>ERROR</option>
                    <option value="critical" <?php echo (isset($_GET['level']) && $_GET['level'] == 'critical') ? 'selected' : ''; ?>>CRITICAL</option>
                </select>
            </div>
            
            <div>
                <label for="log_context" style="display: block; margin-bottom: 5px;">コンテキスト:</label>
                <select id="log_context" name="context" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="all" <?php echo (!isset($_GET['context']) || $_GET['context'] == 'all') ? 'selected' : ''; ?>>全て</option>
                    <option value="database" <?php echo (isset($_GET['context']) && $_GET['context'] == 'database') ? 'selected' : ''; ?>>データベース</option>
                    <option value="api" <?php echo (isset($_GET['context']) && $_GET['context'] == 'api') ? 'selected' : ''; ?>>API</option>
                    <option value="square" <?php echo (isset($_GET['context']) && $_GET['context'] == 'square') ? 'selected' : ''; ?>>Square</option>
                    <option value="order" <?php echo (isset($_GET['context']) && $_GET['context'] == 'order') ? 'selected' : ''; ?>>注文処理</option>
                    <option value="auth" <?php echo (isset($_GET['context']) && $_GET['context'] == 'auth') ? 'selected' : ''; ?>>認証</option>
                    <option value="system" <?php echo (isset($_GET['context']) && $_GET['context'] == 'system') ? 'selected' : ''; ?>>システム</option>
                </select>
            </div>
            
            <div>
                <label for="date_range" style="display: block; margin-bottom: 5px;">日付範囲:</label>
                <select id="date_range" name="date" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="today" <?php echo (!isset($_GET['date']) || $_GET['date'] == 'today') ? 'selected' : ''; ?>>今日</option>
                    <option value="yesterday" <?php echo (isset($_GET['date']) && $_GET['date'] == 'yesterday') ? 'selected' : ''; ?>>昨日</option>
                    <option value="week" <?php echo (isset($_GET['date']) && $_GET['date'] == 'week') ? 'selected' : ''; ?>>過去7日</option>
                    <option value="month" <?php echo (isset($_GET['date']) && $_GET['date'] == 'month') ? 'selected' : ''; ?>>過去30日</option>
                    <option value="all" <?php echo (isset($_GET['date']) && $_GET['date'] == 'all') ? 'selected' : ''; ?>>全期間</option>
                </select>
            </div>
            
            <div>
                <label for="search_query" style="display: block; margin-bottom: 5px;">検索:</label>
                <input type="text" id="search_query" name="query" value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>" placeholder="キーワード検索" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd; width: 200px;">
            </div>
            
            <div>
                <label for="limit" style="display: block; margin-bottom: 5px;">表示件数:</label>
                <select id="limit" name="limit" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="50" <?php echo (!isset($_GET['limit']) || $_GET['limit'] == '50') ? 'selected' : ''; ?>>50件</option>
                    <option value="100" <?php echo (isset($_GET['limit']) && $_GET['limit'] == '100') ? 'selected' : ''; ?>>100件</option>
                    <option value="200" <?php echo (isset($_GET['limit']) && $_GET['limit'] == '200') ? 'selected' : ''; ?>>200件</option>
                    <option value="500" <?php echo (isset($_GET['limit']) && $_GET['limit'] == '500') ? 'selected' : ''; ?>>500件</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button">フィルター適用</button>
            </div>
        </div>
    </form>
    
    <div class="log-controls" style="margin-bottom: 15px; display: flex; gap: 10px;">
        <button id="autoRefresh" class="button secondary" onclick="toggleAutoRefresh()">自動更新: OFF</button>
        <button id="clearLogs" class="button danger" onclick="if(confirm('ログをクリアしますか？この操作は元に戻せません。')) clearLogs()">ログをクリア</button>
        <button id="downloadLogs" class="button" onclick="downloadLogs()">ログをダウンロード</button>
    </div>
    
    <div id="log_container" style="height: 500px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background-color: #f8f8f8; font-family: monospace; font-size: 0.9rem; white-space: pre-wrap;">
        <?php
        // サンプルログデータを表示（実際の実装ではデータベースやファイルからログを取得）
        $level = $_GET['level'] ?? 'all';
        $context = $_GET['context'] ?? 'all';
        $date = $_GET['date'] ?? 'today';
        $query = $_GET['query'] ?? '';
        $limit = intval($_GET['limit'] ?? 50);
        
        $logs = getLogEntries($level, $context, $date, $query, $limit);
        
        if (empty($logs)) {
            echo '<div style="padding: 20px; color: #666; text-align: center;">条件に一致するログが見つかりませんでした。</div>';
        } else {
            foreach ($logs as $log) {
                $logClass = '';
                $logColor = '#333';
                
                switch (strtolower($log['level'])) {
                    case 'debug':
                        $logClass = 'log-debug';
                        $logColor = '#6c757d';
                        break;
                    case 'info':
                        $logClass = 'log-info';
                        $logColor = '#0275d8';
                        break;
                    case 'warning':
                        $logClass = 'log-warning';
                        $logColor = '#f0ad4e';
                        break;
                    case 'error':
                        $logClass = 'log-error';
                        $logColor = '#d9534f';
                        break;
                    case 'critical':
                        $logClass = 'log-critical';
                        $logColor = '#d9534f';
                        $logStyle = 'font-weight: bold; background-color: #ffeeee;';
                        break;
                }
                
                echo '<div class="log-entry ' . $logClass . '" style="padding: 5px 10px; border-bottom: 1px solid #eee; color: ' . $logColor . ';">';
                echo '<span style="color: #666;">[' . htmlspecialchars($log['timestamp']) . ']</span> ';
                echo '<span style="font-weight: bold;">[' . htmlspecialchars($log['level']) . ']</span> ';
                echo '<span>[' . htmlspecialchars($log['context']) . ']</span> ';
                echo htmlspecialchars($log['message']);
                echo '</div>';
            }
        }
        ?>
    </div>
</div>

<script>
// 自動更新機能
let autoRefreshInterval;
let isAutoRefreshEnabled = false;

function toggleAutoRefresh() {
    const button = document.getElementById('autoRefresh');
    
    if (isAutoRefreshEnabled) {
        clearInterval(autoRefreshInterval);
        button.textContent = '自動更新: OFF';
        button.classList.remove('danger');
        button.classList.add('secondary');
        isAutoRefreshEnabled = false;
    } else {
        button.textContent = '自動更新: ON (10秒)';
        button.classList.remove('secondary');
        button.classList.add('danger');
        isAutoRefreshEnabled = true;
        
        // 10秒ごとに更新
        autoRefreshInterval = setInterval(() => {
            refreshLogs();
        }, 10000);
    }
}

function refreshLogs() {
    // 現在のURLを取得し、キャッシュバスター（タイムスタンプ）をクエリに追加
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('_', Date.now());
    
    fetch(currentUrl)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newLogContainer = doc.getElementById('log_container');
            
            if (newLogContainer) {
                document.getElementById('log_container').innerHTML = newLogContainer.innerHTML;
            }
        })
        .catch(error => {
            console.error('ログ更新エラー:', error);
        });
}

// ログクリア機能
function clearLogs() {
    fetch('/fgsquare/test_dashboard.php?action=logs&clear=1', {
        method: 'POST'
    })
    .then(response => {
        if (response.ok) {
            document.getElementById('log_container').innerHTML = 
                '<div style="padding: 20px; color: #666; text-align: center;">ログがクリアされました。</div>';
        }
    });
}

// ログダウンロード機能
function downloadLogs() {
    // 現在のフィルター条件を維持したままダウンロードURLを作成
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('download', '1');
    
    window.location.href = currentUrl.toString();
}
</script>

<?php
/**
 * モックログデータを生成する関数
 */
function getLogEntries($level = 'all', $context = 'all', $date = 'today', $query = '', $limit = 50) {
    // 実際の実装ではデータベースからログを取得する
    // ここではサンプルデータを生成
    
    $levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    $contexts = ['database', 'api', 'square', 'order', 'auth', 'system'];
    
    // フィルター条件に基づくレベルとコンテキストの設定
    $filteredLevels = ($level == 'all') ? $levels : [strtoupper($level)];
    $filteredContexts = ($context == 'all') ? $contexts : [$context];
    
    // 日付範囲の設定
    $startDate = new DateTime();
    switch ($date) {
        case 'yesterday':
            $startDate->modify('-1 day');
            $startDate->setTime(0, 0, 0);
            $endDate = clone $startDate;
            $endDate->setTime(23, 59, 59);
            break;
        case 'week':
            $startDate->modify('-7 days');
            break;
        case 'month':
            $startDate->modify('-30 days');
            break;
        case 'all':
            $startDate->modify('-1 year'); // 適当な過去日付
            break;
        default: // today
            $startDate->setTime(0, 0, 0);
            break;
    }
    
    $logs = [];
    
    // サンプルログエントリ生成（実際のシステムログ的なメッセージを含む）
    $sampleMessages = [
        'database' => [
            'DEBUG' => [
                'データベース接続を確立しました',
                'クエリ実行時間: 0.023秒',
                'SQLクエリ: SELECT * FROM orders WHERE order_date > ?',
                '接続プールから接続を取得しました',
                'トランザクション開始'
            ],
            'INFO' => [
                'データベースマイグレーションが完了しました',
                '5つのテーブルが更新されました',
                'バックアップが正常に完了しました',
                'テーブルorders_itemsのインデックスが作成されました',
                'データベース最適化が完了しました'
            ],
            'WARNING' => [
                'クエリ実行に時間がかかっています（3.2秒）',
                '接続プールが上限に近づいています（18/20）',
                'データベース容量が90%に達しています',
                'サーバーレプリケーション遅延が検出されました',
                'データベースクエリキャッシュが無効になっています'
            ],
            'ERROR' => [
                'データベース接続エラー: Connection refused',
                'SQLエラー: Duplicate entry for key PRIMARY',
                'トランザクションのロールバックが発生しました',
                'クエリタイムアウト（5秒）',
                'テーブルlocks獲得に失敗しました'
            ],
            'CRITICAL' => [
                'データベースサーバーがダウンしています',
                'ディスク容量が不足しています',
                'データ破損が検出されました: orders テーブル',
                'マスターサーバーとの同期が失敗しました',
                'バックアップ処理が失敗しました'
            ]
        ],
        'square' => [
            'DEBUG' => [
                'Square API リクエスト: GET /v2/catalog/list',
                'OAuth2トークン検証を実行しています',
                'APIレスポンスを解析中',
                'Square APIレート制限: 残り18/20リクエスト',
                'ロケーションID: L8MCHB4P3ZDDJ を使用'
            ],
            'INFO' => [
                'カタログデータを同期しました（15アイテム）',
                'Square支払い作成: ord_5f7e9d2ca8294',
                'Webhook受信: payment.created',
                '注文が正常に作成されました',
                'カタログが更新されました'
            ],
            'WARNING' => [
                'Square APIレート制限に近づいています（2/20残り）',
                'APIリクエストに時間がかかっています（3.4秒）',
                'OAuth2トークンの期限が近づいています（残り1日）',
                '未処理のWebhookイベントが10件あります',
                '商品データに不整合があります'
            ],
            'ERROR' => [
                'Square API エラー: Authentication failed',
                'Webhookシグネチャ検証に失敗しました',
                'Payment作成に失敗: Card declined',
                'API接続タイムアウト（10秒）',
                'カタログ同期エラー: Rate limit exceeded'
            ],
            'CRITICAL' => [
                'Square API連携が完全に失敗しました',
                'Webhookエンドポイントが応答していません',
                '支払い処理システムがダウンしています',
                'Square設定が見つかりません',
                'アカウント認証情報が無効です'
            ]
        ],
        'order' => [
            'DEBUG' => [
                '注文データを検証しています',
                '注文商品数: 5',
                '注文合計金額計算: 7,500円',
                '税額計算: 750円',
                '部屋番号: 101 の注文を処理中'
            ],
            'INFO' => [
                '新規注文を受け付けました: ORD12345',
                '注文ステータスを変更: PENDING → CONFIRMED',
                '注文 ORD12345 が完了しました',
                '通知が送信されました: LINE, 宛先: U123456789',
                '注文 ORD12345 がキャンセルされました'
            ],
            'WARNING' => [
                '注文の有効期限が近づいています',
                '同じ部屋から短時間に複数の注文があります',
                '大量注文が検出されました: 20アイテム以上',
                '在庫が少ない商品が注文されました: 残り2個',
                '注文処理に時間がかかっています（8秒）'
            ],
            'ERROR' => [
                '注文作成に失敗しました: データベースエラー',
                '支払い確認ができませんでした',
                '商品が見つかりません: ITEM789',
                '注文ステータス更新に失敗しました',
                '通知送信に失敗しました'
            ],
            'CRITICAL' => [
                '注文システムがダウンしています',
                '複数のトランザクションで不整合が発生しています',
                '注文データ破損が検出されました',
                'マルチスレッド競合状態が発生しました',
                '注文同期処理が完全に失敗しました'
            ]
        ],
        'api' => [
            'DEBUG' => [
                'APIリクエスト: GET /api/products',
                'リクエストヘッダー検証',
                'レスポンス生成: 200 OK',
                'API呼び出し時間: 0.123秒',
                'ペイロードサイズ: 25KB'
            ],
            'INFO' => [
                'APIリクエスト成功: POST /api/orders',
                'APIレスポンスキャッシュを更新しました',
                '新しいAPIトークンが生成されました',
                'APIバージョン1.2.3が使用されています',
                '外部APIとの同期が完了しました'
            ],
            'WARNING' => [
                'APIレート制限に近づいています（5/分）',
                'APIレスポンスが遅延しています（2.5秒）',
                'リクエストボディが大きすぎます（2MB）',
                '非推奨のAPIエンドポイントが使用されています',
                'API認証トークンの有効期限が近づいています'
            ],
            'ERROR' => [
                'APIリクエスト失敗: 401 Unauthorized',
                'JWTトークン検証エラー',
                'APIエンドポイントが見つかりません: 404',
                'リクエスト形式が無効です',
                'APIサーバーからの応答がありません'
            ],
            'CRITICAL' => [
                'APIサーバーがダウンしています',
                '複数のエンドポイントで障害が発生しています',
                'APIゲートウェイ接続が失われました',
                'APIサービス全体が応答していません',
                'APIキー漏洩が検出されました'
            ]
        ],
        'auth' => [
            'DEBUG' => [
                'ユーザー認証を試行しています',
                'セッショントークン検証',
                'リクエストIPアドレス: 192.168.1.1',
                'JWTペイロード解析',
                'CSRF対策トークン生成'
            ],
            'INFO' => [
                'ユーザーがログインしました: user@example.com',
                'ユーザーがログアウトしました: user@example.com',
                '新しいセッションが作成されました',
                'パスワードがリセットされました',
                '2要素認証が完了しました'
            ],
            'WARNING' => [
                '複数回のログイン失敗: user@example.com',
                'セッション期限切れが間近です',
                '通常と異なるIPからのログイン試行',
                '複数のデバイスからのログイン',
                '管理者権限でのアクション実行'
            ],
            'ERROR' => [
                'ログイン失敗: 認証情報が無効です',
                'セッショントークンが無効です',
                '権限がありません: admin_area',
                'アカウントがロックされています',
                'OAuth認証サーバーエラー'
            ],
            'CRITICAL' => [
                '管理者アカウントへの不正アクセス試行',
                '大量の認証失敗が検出されました',
                'セキュリティ侵害の可能性があります',
                '認証サーバーがダウンしています',
                'データベース認証情報が漏洩した可能性があります'
            ]
        ],
        'system' => [
            'DEBUG' => [
                'システム起動シーケンス開始',
                'キャッシュストレージをクリア',
                'メモリ使用状況: 1.2GB/4GB',
                'バックグラウンドジョブスケジュール更新',
                'システム時間同期チェック'
            ],
            'INFO' => [
                'アプリケーションが起動しました（バージョン1.2.3）',
                'メンテナンスモードを開始しました',
                'メンテナンスモードが終了しました',
                'システムバックアップが開始されました',
                'システムアップデートが完了しました'
            ],
            'WARNING' => [
                'サーバーCPU使用率が高くなっています（85%）',
                'ディスク容量が少なくなっています（85%使用）',
                'メモリ使用量が増加しています',
                'キャッシュヒット率が低下しています（60%）',
                'バックグラウンドジョブのキューが増加しています'
            ],
            'ERROR' => [
                'キャッシュサーバー接続エラー',
                'ファイルシステム書き込みエラー: アクセス拒否',
                'システムサービスが応答していません: redis',
                'クロンジョブの実行に失敗しました',
                'ロードバランサー接続が失われました'
            ],
            'CRITICAL' => [
                'サーバー過負荷: CPU 100%, メモリ 95%',
                'アプリケーションがクラッシュしました',
                'データ破損が検出されました',
                'セキュリティ侵害の検出: 不正なファイル変更',
                'システムがダウンしています'
            ]
        ]
    ];
    
    // 現在の時刻から開始して、ランダムなログを生成
    $currentTime = new DateTime();
    
    // ランダムに重複しないログを生成するためのメタデータ追跡
    $usedMessages = [];
    
    // 生成するログの数（タイプによって異なるべき）
    $logCount = min($limit, 500); // 最大500件まで
    
    // 降順でログを生成（新しい順）
    for ($i = 0; $i < $logCount; $i++) {
        // ランダムなタイムスタンプ（最新から過去へ）
        $timestamp = clone $currentTime;
        $timestamp->modify('-' . rand(0, min(30, $logCount)) . ' minutes');
        $timestamp->modify('-' . rand(0, 59) . ' seconds');
        
        // 日付フィルタリング
        if ($timestamp < $startDate) {
            continue;
        }
        
        // ランダムなレベルとコンテキスト
        $randomLevel = $filteredLevels[array_rand($filteredLevels)];
        $randomContext = $filteredContexts[array_rand($filteredContexts)];
        
        // ランダムなメッセージ
        $messagesForType = $sampleMessages[$randomContext][$randomLevel] ?? ['テストメッセージ'];
        $randomMessage = $messagesForType[array_rand($messagesForType)];
        
        // 検索クエリによるフィルタリング
        if (!empty($query) && stripos($randomMessage, $query) === false) {
            // もう1つログを生成するためにカウンタを戻す
            $i--;
            continue;
        }
        
        // ログエントリ作成
        $logEntry = [
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'level' => $randomLevel,
            'context' => $randomContext,
            'message' => $randomMessage
        ];
        
        // 既に使ったメッセージと同じものは使わない（バリエーションのため）
        $messageKey = $randomContext . '-' . $randomLevel . '-' . $randomMessage;
        if (!isset($usedMessages[$messageKey])) {
            $logs[] = $logEntry;
            $usedMessages[$messageKey] = true;
        } else {
            // もう1つログを生成するためにカウンタを戻す
            $i--;
        }
    }
    
    // タイムスタンプでソート（新しい順）
    usort($logs, function($a, $b) {
        return strcmp($b['timestamp'], $a['timestamp']);
    });
    
    return $logs;
}
?> 