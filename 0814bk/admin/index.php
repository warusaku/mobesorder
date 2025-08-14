<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
/**
 * 管理ダッシュボード
 * 
 * このスクリプトは、システム全体の概要情報を表示し、
 * 各管理機能へのリンクを提供します。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// 共通ヘッダーに認証処理・セッション開始を集約
$pageTitle = '管理ダッシュボード';
require_once __DIR__ . '/inc/admin_header.php'; // ☑ 共通ヘッダー読み込み

// ログ関数
if (!function_exists('dashboardLog')) {
    function dashboardLog($message, $level = 'INFO') {
        if (class_exists('Utils')) {
            Utils::log($message, $level, 'Dashboard');
        } else {
            error_log("[Dashboard][$level] $message");
        }
    }
}

// adminsetting_registrer.php からユーザー情報を取得
$users = getAdminUsers();
if(empty($users)){
    dashboardLog('adminsetting.json にユーザー設定がありません', 'ERROR');
}

// 認証処理
$isLoggedIn = false;
$loginError = '';

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['auth_user']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ログインフォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($users[$username]) && is_array($users[$username]) && $users[$username][0] === $password) {
        $_SESSION['auth_user'] = $username;
        $_SESSION['auth_token'] = $users[$username][1]; // トークンを保存
        dashboardLog("ユーザー '{$username}' がログインしました");
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'ユーザー名またはパスワードが正しくありません';
        dashboardLog("ログイン失敗: ユーザー '{$username}'", 'WARNING');
    }
}

// ログイン状態チェック
if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_token']) && array_key_exists($_SESSION['auth_user'], $users)) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['auth_user'];
    $authToken = $_SESSION['auth_token'];
} else {
    $isLoggedIn = false;
}

// データベース接続
$db = Database::getInstance();

// データ取得（ログイン済みの場合のみ）
$data = [
    'product_count' => 0,
    'category_count' => 0,
    'last_product_sync' => null,
    'last_category_sync' => null,
    'sync_interval' => 30,
    'room_count' => 0,
    'active_room_count' => 0
];

if ($isLoggedIn) {
    try {
        // 商品数を取得
        $productCount = $db->selectOne("SELECT COUNT(*) as count FROM products");
        if ($productCount) {
            $data['product_count'] = $productCount['count'];
        }
        
        // カテゴリ数を取得
        $categoryCount = $db->selectOne("SELECT COUNT(*) as count FROM category_descripter");
        if ($categoryCount) {
            $data['category_count'] = $categoryCount['count'];
        }
        
        // 最終商品同期日時
        $productSync = $db->selectOne(
            "SELECT last_sync_time FROM sync_status WHERE provider = ? AND table_name = ? ORDER BY last_sync_time DESC LIMIT 1",
            ['square', 'products']
        );
        if ($productSync) {
            $data['last_product_sync'] = $productSync['last_sync_time'];
        }
        
        // 最終カテゴリ同期日時
        $categorySync = $db->selectOne(
            "SELECT last_sync_time FROM sync_status WHERE provider = ? AND table_name = ? ORDER BY last_sync_time DESC LIMIT 1",
            ['square', 'category_descripter']
        );
        if ($categorySync) {
            $data['last_category_sync'] = $categorySync['last_sync_time'];
        }
        
        // 同期間隔を取得
        $syncInterval = $db->selectOne(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'product_sync_interval'"
        );
        if ($syncInterval) {
            $data['sync_interval'] = (int)$syncInterval['setting_value'];
        }
        
        // 部屋数の取得
        $roomCount = $db->selectOne("SELECT COUNT(*) as count FROM roomdatasettings");
        if ($roomCount) {
            $data['room_count'] = $roomCount['count'];
        }
        
        // 有効な部屋数を取得
        $activeRoomCount = $db->selectOne("SELECT COUNT(*) as count FROM roomdatasettings WHERE is_active = 1");
        if ($activeRoomCount) {
            $data['active_room_count'] = $activeRoomCount['count'];
        }
        
    } catch (Exception $e) {
        dashboardLog("データ取得エラー: " . $e->getMessage(), 'ERROR');
    }
}

// ログインしていない場合はヘッダーがログインフォームを表示済みのため、ページ本体を描画せず終了
if (!$isLoggedIn) {
    require_once __DIR__ . '/inc/admin_footer.php';
    return;
}

// ------------------------------------------------------------
// 以降 HTML はコンテンツ部分のみ（ヘッダー・ナビは共通ヘッダーで出力済み）
?>

<!-- 統計カード -->
<div class="row mb-2">
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="stat-value"><?php echo number_format($data['product_count']); ?></div>
            <div class="stat-label">登録商品数</div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="stat-value"><?php echo number_format($data['category_count']); ?></div>
            <div class="stat-label">カテゴリ数</div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="stat-value"><?php echo $data['sync_interval']; ?>分</div>
            <div class="stat-label">同期間隔<br><small>(lolipopのcronで10分間隔)</small></div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="stat-value">
                <?php 
                if ($data['last_product_sync']) {
                    echo date('m/d H:i', strtotime($data['last_product_sync']));
                } else {
                    echo "-";
                }
                ?>
            </div>
            <div class="stat-label">最終同期</div>
        </div>
    </div>
</div>

<!-- 部屋数統計カード (2行目) -->
<div class="row mt-4 mb-4">
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="stat-value">
                <?php echo number_format($data['active_room_count']); ?> / <?php echo number_format($data['room_count']); ?>
            </div>
            <div class="stat-label">利用可能部屋数 / 登録部屋数</div>
        </div>
    </div>
</div>

<!-- セクション区切り -->
<hr class="section-divider mb-4">

<!-- 機能カード -->
<h2 class="section-title mb-4">管理機能</h2>
<div class="row">
    <div class="col-md-3 mb-4">
        <a href="products_sync.php" class="card-link">
            <div class="card text-center p-4 h-100">
                <div class="card-icon">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <h3 class="card-title">商品同期</h3>
                <p class="card-text">Square APIから商品データを同期します。同期間隔の設定も可能です。</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-4">
        <a href="manage_categories.php" class="card-link">
            <div class="card text-center p-4 h-100">
                <div class="card-icon">
                    <i class="bi bi-tag"></i>
                </div>
                <h3 class="card-title">カテゴリ管理</h3>
                <p class="card-text">カテゴリの表示順やラストオーダー時間などを設定します。</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-4">
        <a href="product_display_util.php" class="card-link">
            <div class="card text-center p-4 h-100">
                <div class="card-icon">
                    <i class="bi bi-display"></i>
                </div>
                <h3 class="card-title">商品表示設定</h3>
                <p class="card-text">商品の表示/非表示や表示順を設定します。</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-4">
        <a href="sales_monitor.php" class="card-link">
            <div class="card text-center p-4 h-100">
                <div class="card-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <h3 class="card-title">販売情報</h3>
                <p class="card-text">販売データとオーダー情報をリアルタイムで監視します。</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-4">
        <a href="roomsetting.php" class="card-link">
            <div class="card text-center p-4 h-100">
                <div class="card-icon">
                    <i class="bi bi-house-door"></i>
                </div>
                <h3 class="card-title">部屋設定</h3>
                <p class="card-text">部屋情報の管理と利用状況の確認。登録部屋の追加や編集ができます。</p>
            </div>
        </a>
    </div>
</div>

<!-- システム情報 -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                システム情報
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>最終商品同期:</strong> 
                            <?php echo $data['last_product_sync'] ? date('Y-m-d H:i:s', strtotime($data['last_product_sync'])) : '-'; ?>
                        </p>
                        <p><strong>最終カテゴリ同期:</strong> 
                            <?php echo $data['last_category_sync'] ? date('Y-m-d H:i:s', strtotime($data['last_category_sync'])) : '-'; ?>
                        </p>
                        <p><strong>同期間隔:</strong> <?php echo $data['sync_interval']; ?>分</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Square環境:</strong> <?php echo defined('SQUARE_ENVIRONMENT') ? htmlspecialchars(SQUARE_ENVIRONMENT) : 'undefined'; ?></p>
                        <p><strong>PHPバージョン:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>サーバー時間:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Systemlogセクション -->
<section id="log-files-section" class="mt-4">
    <h2><i class="bi bi-journal-text"></i> システムログ</h2>
    
    <div class="log-toolbar">
        <button id="log-refresh-btn" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-clockwise"></i> 更新
        </button>
        <div class="log-status">
            最終更新: <span id="log-last-updated"><?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
    </div>
    
    <div id="log-error-message"></div>
    
    <table class="log-files-table">
        <thead>
            <tr>
                <th>ファイル名</th>
                <th>サイズ</th>
                <th>最終更新</th>
            </tr>
        </thead>
        <tbody id="log-files-tbody">
            <tr>
                <td colspan="3" class="text-center">ログファイルを読み込んでいます...</td>
            </tr>
        </tbody>
    </table>
    
    <div class="small text-muted">
        <i class="bi bi-info-circle"></i> ログファイルをクリックすると内容が表示されます。
        最新の更新がある場合は黄色でハイライト表示されます。
    </div>
</section>

<?php require_once __DIR__ . '/inc/admin_footer.php'; ?>

<script>
// ダッシュボード自動更新設定（1分間隔）
(function() {
    const REFRESH_INTERVAL = 60000; // 1分 = 60,000ミリ秒
    let refreshTimer = null;
    let isAutoRefreshEnabled = true;
    const refreshStatus = document.getElementById('refreshStatus');
    
    // ユーザーの操作中フラグ
    let userActive = false;
    let userActivityTimer = null;
    
    // 自動更新を開始する関数（Ajax方式）
    function startAutoRefresh() {
        if (refreshTimer === null) {
            refreshTimer = setInterval(function() {
                // ユーザーが操作中でなければページデータを更新
                // ログモーダルが開いている場合も更新しない
                if (!userActive && !window.logModalOpen) {
                    updateDashboardData();
                }
            }, REFRESH_INTERVAL);
        }
    }
    
    // ダッシュボードデータをAjaxで更新
    function updateDashboardData() {
        // 更新中表示
        const refreshStatus = document.getElementById('refreshStatus');
        if (refreshStatus) {
            refreshStatus.innerHTML = '更新中... <i class="bi bi-arrow-repeat spin"></i>';
        }
        
        // ダッシュボードデータを取得するAPIリクエスト
        fetch('dashboard_data.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // 統計カードの更新
                    updateStatCard('product_count', data.product_count);
                    updateStatCard('category_count', data.category_count);
                    updateStatCard('sync_interval', data.sync_interval + '分');
                    updateStatCard('last_sync', data.last_product_sync ? formatDate(data.last_product_sync) : '-');
                    
                    // システム情報の更新
                    updateSystemInfo('last_product_sync', data.last_product_sync);
                    updateSystemInfo('last_category_sync', data.last_category_sync);
                    updateSystemInfo('sync_interval', data.sync_interval);
                    updateSystemInfo('server_time', data.server_time);
                    
                    // 最終更新時刻の表示
                    const now = new Date();
                    if (refreshStatus) {
                        refreshStatus.innerHTML = '自動更新: 有効 <span class="small text-muted">(' + 
                            now.toLocaleTimeString() + ')</span>';
                    }
                } else {
                    console.error('ダッシュボードデータの更新に失敗しました:', data.error);
                    if (refreshStatus) {
                        refreshStatus.textContent = '自動更新: 有効 (更新失敗)';
                    }
                }
            })
            .catch(error => {
                console.error('ダッシュボード更新エラー:', error);
                if (refreshStatus) {
                    refreshStatus.textContent = '自動更新: 有効 (更新失敗)';
                }
            });
    }
    
    // 統計カードの値を更新
    function updateStatCard(id, value) {
        const elements = document.querySelectorAll('.stat-card');
        elements.forEach(element => {
            const label = element.querySelector('.stat-label');
            const valueElement = element.querySelector('.stat-value');
            
            if (label && valueElement) {
                const labelText = label.textContent.trim().toLowerCase();
                
                if (
                    (id === 'product_count' && labelText.includes('商品数')) ||
                    (id === 'category_count' && labelText.includes('カテゴリ数')) ||
                    (id === 'sync_interval' && labelText.includes('同期間隔')) ||
                    (id === 'last_sync' && labelText.includes('最終同期'))
                ) {
                    valueElement.textContent = value;
                }
            }
        });
    }
    
    // システム情報を更新
    function updateSystemInfo(id, value) {
        const systemInfoContainer = document.querySelector('.card-body');
        if (!systemInfoContainer) return;
        
        const paragraphs = systemInfoContainer.querySelectorAll('p');
        paragraphs.forEach(p => {
            const strong = p.querySelector('strong');
            if (strong) {
                const label = strong.textContent.trim();
                
                // 各フィールドを更新
                if (
                    (id === 'last_product_sync' && label === '最終商品同期:') ||
                    (id === 'last_category_sync' && label === '最終カテゴリ同期:')
                ) {
                    // ラベル以外のテキストを更新
                    const text = p.childNodes[1];
                    if (text) {
                        text.textContent = value ? ' ' + formatDateTime(value) : ' -';
                    }
                }
                else if (id === 'sync_interval' && label === '同期間隔:') {
                    const text = p.childNodes[1];
                    if (text) {
                        text.textContent = ' ' + value + '分';
                    }
                }
                else if (id === 'server_time' && label === 'サーバー時間:') {
                    const text = p.childNodes[1];
                    if (text) {
                        text.textContent = ' ' + value;
                    }
                }
            }
        });
    }
    
    // 日付フォーマット (MM/DD HH:MM)
    function formatDate(dateString) {
        const date = new Date(dateString);
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${month}/${day} ${hours}:${minutes}`;
    }
    
    // 日時フォーマット (YYYY-MM-DD HH:MM:SS)
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
    
    // 自動更新を停止する関数
    function stopAutoRefresh() {
        if (refreshTimer !== null) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }
    
    // 自動更新の状態を切り替える関数
    function toggleAutoRefresh() {
        isAutoRefreshEnabled = !isAutoRefreshEnabled;
        
        if (isAutoRefreshEnabled) {
            startAutoRefresh();
            refreshStatus.textContent = '自動更新: 有効';
            refreshStatus.classList.remove('disabled');
        } else {
            stopAutoRefresh();
            refreshStatus.textContent = '自動更新: 無効';
            refreshStatus.classList.add('disabled');
        }
        
        // 設定を保存
        localStorage.setItem('dashboardAutoRefresh', isAutoRefreshEnabled ? 'enabled' : 'disabled');
    }
    
    // 保存された設定があれば復元
    if (localStorage.getItem('dashboardAutoRefresh') === 'disabled') {
        isAutoRefreshEnabled = false;
        refreshStatus.textContent = '自動更新: 無効';
        refreshStatus.classList.add('disabled');
    } else {
        // デフォルトで自動更新を開始
        startAutoRefresh();
    }
    
    // 自動更新トグルのクリックイベント
    if (refreshStatus) {
        refreshStatus.style.cursor = 'pointer';
        refreshStatus.addEventListener('click', toggleAutoRefresh);
    }
    
    // ユーザーの操作を検知
    function markUserActive() {
        userActive = true;
        
        // 前のタイマーをクリア
        if (userActivityTimer) {
            clearTimeout(userActivityTimer);
        }
        
        // 操作が5秒間なければユーザーは非アクティブと判断
        userActivityTimer = setTimeout(function() {
            userActive = false;
        }, 5000);
    }
    
    // ユーザー操作イベントリスナー
    ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, markUserActive, { passive: true });
    });
    
    // ログモーダル用グローバルフラグの初期化
    window.logModalOpen = false;
})();
</script> 