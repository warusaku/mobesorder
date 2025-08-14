<?php
// Square_DB_Console.php
// ------------------------------------------------------------
// Order Sessions 管理コンソール
// order_sessions ごとの注文商品リストと詳細情報を管理
// ------------------------------------------------------------

require_once __DIR__ . '/../api/config/config.php';

// --------------- ログ送信関数定義 ---------------
function squareDbConsoleLog(string $message, string $level = 'INFO'): void
{
    $scriptName = basename(__FILE__, '.php');
    $logDir = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . "/{$scriptName}.log";

    // ローテーション：300KB を超えたら 20% 残して圧縮
    if (file_exists($logFile) && filesize($logFile) > 307200) {
        $content = file_get_contents($logFile);
        $retainSize = (int)(307200 * 0.2);
        $content = substr($content, -$retainSize);
        file_put_contents($logFile, $content, LOCK_EX);
    }

    $date = date('Y-m-d H:i:s');
    $line = "[$date][$level] $message" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// データベース接続
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // 接続テスト
    $pdo->query("SELECT 1");
    squareDbConsoleLog('Database connection successful');
    
} catch (PDOException $e) {
    $errorMsg = 'Database connection failed: ' . $e->getMessage() . 
                ' (Host: ' . DB_HOST . ', DB: ' . DB_NAME . ', User: ' . DB_USER . ')';
    squareDbConsoleLog($errorMsg, 'ERROR');
    
    // Ajax リクエストの場合はJSONを返す
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $errorMsg]);
        exit;
    } else {
        // 通常のページアクセスの場合はエラーページを表示
        echo "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($errorMsg) . "</div>";
        exit;
    }
}

// Order Sessions サービスクラス
class OrderSessionService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Order Sessions のリストを取得
     */
    public function getOrderSessions($limit = 50) {
        try {
            $sql = "
                SELECT 
                    os.id,
                    os.room_number,
                    os.session_status,
                    os.opened_at,
                    os.closed_at,
                    os.is_active,
                    SUM(o.total_amount) as total_amount,
                    COUNT(DISTINCT o.id) as order_count,
                    COUNT(DISTINCT od.id) as item_count
                FROM order_sessions os
                LEFT JOIN orders o ON os.id = o.order_session_id
                LEFT JOIN order_details od ON os.id = od.order_session_id
                GROUP BY os.id
                ORDER BY os.opened_at DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            squareDbConsoleLog("Order Sessions取得: " . count($sessions) . "件");
            
            return $sessions;
        } catch (Exception $e) {
            squareDbConsoleLog("Order Sessions取得エラー: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * 特定セッションの注文詳細を取得
     */
    public function getSessionDetails($sessionId) {
        try {
            // セッション基本情報
            $sessionSql = "
                SELECT 
                    os.*,
                    SUM(o.total_amount) as total_amount,
                    COUNT(DISTINCT o.id) as order_count
                FROM order_sessions os
                LEFT JOIN orders o ON os.id = o.order_session_id
                WHERE os.id = ?
                GROUP BY os.id
            ";
            
            $stmt = $this->pdo->prepare($sessionSql);
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['error' => 'セッションが見つかりません'];
            }
            
            // 注文商品詳細
            $itemsSql = "
                SELECT 
                    od.id,
                    od.product_name,
                    od.unit_price,
                    od.quantity,
                    od.subtotal,
                    od.note,
                    od.status,
                    od.status_updated_at,
                    o.id as order_id,
                    o.order_datetime,
                    o.note as order_note,
                    o.line_user_id,
                    lrl.user_name as guest_name
                FROM order_details od
                LEFT JOIN orders o ON od.order_id = o.id
                LEFT JOIN line_room_links lrl ON o.line_user_id = lrl.line_user_id 
                    AND o.order_session_id = lrl.order_session_id
                WHERE od.order_session_id = ?
                ORDER BY od.created_at DESC
            ";
            
            $stmt = $this->pdo->prepare($itemsSql);
            $stmt->execute([$sessionId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // LINEユーザー情報
            $usersSql = "
                SELECT DISTINCT
                    lrl.line_user_id,
                    lrl.user_name,
                    lrl.check_in_date,
                    lrl.check_out_date,
                    COUNT(DISTINCT o.id) as order_count
                FROM line_room_links lrl
                LEFT JOIN orders o ON lrl.line_user_id = o.line_user_id AND lrl.order_session_id = o.order_session_id
                WHERE lrl.order_session_id = ?
                GROUP BY lrl.line_user_id
                ORDER BY lrl.created_at
            ";
            
            $stmt = $this->pdo->prepare($usersSql);
            $stmt->execute([$sessionId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            squareDbConsoleLog("セッション詳細取得: {$sessionId}, 商品" . count($items) . "件, ユーザー" . count($users) . "件");
            
            return [
                'session' => $session,
                'items' => $items,
                'users' => $users
            ];
            
        } catch (Exception $e) {
            squareDbConsoleLog("セッション詳細取得エラー: " . $e->getMessage(), 'ERROR');
            return ['error' => $e->getMessage()];
        }
    }
}

// Ajax リクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $service = new OrderSessionService($pdo);
    
    switch ($_POST['action']) {
        case 'get_sessions':
            $limit = intval($_POST['limit'] ?? 50);
            $result = $service->getOrderSessions($limit);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;
            
        case 'get_session_details':
            $sessionId = $_POST['session_id'] ?? '';
            if (empty($sessionId)) {
                echo json_encode(['success' => false, 'error' => 'セッションIDが必要です']);
                exit;
            }
            $result = $service->getSessionDetails($sessionId);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => '無効なアクション']);
            exit;
    }
}

// アクセスログを記録
squareDbConsoleLog('ページアクセス:' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

// ページタイトル
$pageTitle = 'Square DB Console - Order Sessions';

require_once __DIR__ . '/inc/admin_header.php';
?>

<?php if ($isLoggedIn): ?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2>Order Sessions Management</h2>
            <p class="text-muted">
                Order Sessionsごとの注文商品リストと利用者情報を管理します。
            </p>
        </div>
    </div>
    
    <!-- セッションリスト -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Order Sessions</h4>
                    <button type="button" class="btn btn-primary" onclick="loadSessions()">
                        <i class="fas fa-sync-alt"></i> 更新
                    </button>
                </div>
                <div class="card-body">
                    <div id="sessionsLoading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">セッション情報を読み込み中...</p>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="sessionsTable">
                            <thead>
                                <tr>
                                    <th>Session ID</th>
                                    <th>部屋番号</th>
                                    <th>ステータス</th>
                                    <th>合計金額</th>
                                    <th>注文数</th>
                                    <th>商品点数</th>
                                    <th>開始日時</th>
                                    <th>終了日時</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- セッション詳細モーダル -->
<div class="modal fade" id="sessionDetailModal" tabindex="-1" aria-labelledby="sessionDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sessionDetailModalLabel">セッション詳細</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sessionDetailLoading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">詳細情報を読み込み中...</p>
                </div>
                
                <div id="sessionDetailContent" style="display: none;">
                    <!-- セッション基本情報 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-muted">基本情報</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Session ID:</strong><br>
                                            <span id="detailSessionId"></span>
                                        </div>
                                        <div class="col-md-2">
                                            <strong>部屋番号:</strong><br>
                                            <span id="detailRoomNumber"></span>
                                        </div>
                                        <div class="col-md-2">
                                            <strong>ステータス:</strong><br>
                                            <span id="detailStatus" class="badge"></span>
                                        </div>
                                        <div class="col-md-2">
                                            <strong>合計金額:</strong><br>
                                            <span id="detailTotalAmount"></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>開始日時:</strong><br>
                                            <span id="detailOpenedAt"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- タブメニュー -->
                    <ul class="nav nav-tabs" id="detailTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="items-tab" data-bs-toggle="tab" data-bs-target="#items" type="button" role="tab">
                                注文商品 (<span id="itemsCount">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                                利用者 (<span id="usersCount">0</span>)
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="detailTabContent">
                        <!-- 注文商品タブ -->
                        <div class="tab-pane fade show active" id="items" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>商品名</th>
                                            <th>単価</th>
                                            <th>数量</th>
                                            <th>小計</th>
                                            <th>ステータス</th>
                                            <th>注文者</th>
                                            <th>注文日時</th>
                                            <th>備考</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- 利用者タブ -->
                        <div class="tab-pane fade" id="users" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>LINE User ID</th>
                                            <th>ユーザー名</th>
                                            <th>チェックイン</th>
                                            <th>チェックアウト</th>
                                            <th>注文回数</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ページ読み込み時にセッションリストを取得
document.addEventListener('DOMContentLoaded', function() {
    loadSessions();
});

// セッションリストを読み込み
function loadSessions() {
    document.getElementById('sessionsLoading').style.display = 'block';
    
    const formData = new FormData();
    formData.append('action', 'get_sessions');
    formData.append('limit', '100');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('sessionsLoading').style.display = 'none';
        
        if (data.success) {
            displaySessions(data.data);
        } else {
            alert('エラーが発生しました: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        document.getElementById('sessionsLoading').style.display = 'none';
        console.error('Error:', error);
        alert('通信エラーが発生しました');
    });
}

// セッションリストを表示
function displaySessions(sessions) {
    const tbody = document.querySelector('#sessionsTable tbody');
    tbody.innerHTML = '';
    
    sessions.forEach(session => {
        const row = tbody.insertRow();
        
        // ステータスのバッジクラス
        let statusClass = 'bg-secondary';
        if (session.session_status === 'active') statusClass = 'bg-success';
        else if (session.session_status === 'Completed') statusClass = 'bg-primary';
        else if (session.session_status === 'Force_closed') statusClass = 'bg-warning';
        
        row.innerHTML = `
            <td><span class="font-monospace">${session.id}</span></td>
            <td><strong>${session.room_number}</strong></td>
            <td><span class="badge ${statusClass}">${session.session_status}</span></td>
            <td class="text-end">¥${session.total_amount ? parseInt(session.total_amount).toLocaleString() : '0'}</td>
            <td class="text-center">${session.order_count || '0'}</td>
            <td class="text-center">${session.item_count || '0'}</td>
            <td>${formatDateTime(session.opened_at)}</td>
            <td>${session.closed_at ? formatDateTime(session.closed_at) : '-'}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showSessionDetails('${session.id}')">
                    <i class="fas fa-eye"></i> 詳細
                </button>
            </td>
        `;
    });
}

// セッション詳細を表示
function showSessionDetails(sessionId) {
    // モーダルを表示
    const modal = new bootstrap.Modal(document.getElementById('sessionDetailModal'));
    modal.show();
    
    // ローディング表示
    document.getElementById('sessionDetailLoading').style.display = 'block';
    document.getElementById('sessionDetailContent').style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'get_session_details');
    formData.append('session_id', sessionId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('sessionDetailLoading').style.display = 'none';
        
        if (data.success && !data.data.error) {
            displaySessionDetails(data.data);
        } else {
            alert('エラーが発生しました: ' + (data.data?.error || data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        document.getElementById('sessionDetailLoading').style.display = 'none';
        console.error('Error:', error);
        alert('通信エラーが発生しました');
    });
}

// セッション詳細データを表示
function displaySessionDetails(data) {
    const session = data.session;
    const items = data.items;
    const users = data.users;
    
    // 基本情報を表示
    document.getElementById('detailSessionId').textContent = session.id;
    document.getElementById('detailRoomNumber').textContent = session.room_number;
    document.getElementById('detailTotalAmount').textContent = '¥' + (session.total_amount ? parseInt(session.total_amount).toLocaleString() : '0');
    document.getElementById('detailOpenedAt').textContent = formatDateTime(session.opened_at);
    
    // ステータスバッジ
    const statusBadge = document.getElementById('detailStatus');
    statusBadge.textContent = session.session_status;
    statusBadge.className = 'badge';
    if (session.session_status === 'active') statusBadge.classList.add('bg-success');
    else if (session.session_status === 'Completed') statusBadge.classList.add('bg-primary');
    else if (session.session_status === 'Force_closed') statusBadge.classList.add('bg-warning');
    else statusBadge.classList.add('bg-secondary');
    
    // カウント表示
    document.getElementById('itemsCount').textContent = items.length;
    document.getElementById('usersCount').textContent = users.length;
    
    // 商品テーブル
    const itemsTableBody = document.querySelector('#itemsTable tbody');
    itemsTableBody.innerHTML = '';
    
    items.forEach(item => {
        const row = itemsTableBody.insertRow();
        
        // ステータスのバッジクラス
        let statusClass = 'bg-secondary';
        if (item.status === 'ordered') statusClass = 'bg-info';
        else if (item.status === 'ready') statusClass = 'bg-warning';
        else if (item.status === 'delivered') statusClass = 'bg-success';
        else if (item.status === 'cancelled') statusClass = 'bg-danger';
        
        row.innerHTML = `
            <td>${item.product_name || '-'}</td>
            <td class="text-end">¥${item.unit_price ? parseInt(item.unit_price).toLocaleString() : '0'}</td>
            <td class="text-center">${item.quantity}</td>
            <td class="text-end">¥${item.subtotal ? parseInt(item.subtotal).toLocaleString() : '0'}</td>
            <td><span class="badge ${statusClass}">${item.status}</span></td>
            <td>${item.guest_name || '-'}</td>
            <td>${formatDateTime(item.order_datetime)}</td>
            <td><small>${(item.note || item.order_note || '').substring(0, 30)}</small></td>
        `;
    });
    
    // ユーザーテーブル
    const usersTableBody = document.querySelector('#usersTable tbody');
    usersTableBody.innerHTML = '';
    
    users.forEach(user => {
        const row = usersTableBody.insertRow();
        row.innerHTML = `
            <td><span class="font-monospace small">${user.line_user_id.substring(0, 20)}...</span></td>
            <td>${user.user_name || '-'}</td>
            <td>${user.check_in_date || '-'}</td>
            <td>${user.check_out_date || '-'}</td>
            <td class="text-center">${user.order_count || '0'}</td>
        `;
    });
    
    document.getElementById('sessionDetailContent').style.display = 'block';
}

// 日時のフォーマット
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP', {hour: '2-digit', minute: '2-digit'});
}
</script>

<?php endif; ?>

<?php
require_once __DIR__ . '/inc/admin_footer.php';
?>