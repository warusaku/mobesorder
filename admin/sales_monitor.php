<?php
/**
 * 販売情報モニター
 * バージョン: 2.1.0
 * ファイル説明: リファクタリング版 - 共通ヘッダー/フッターとの連携を修正
 * 
 * このスクリプトは、orders, orderdetails, line_room_links, room_ticketsテーブルを監視し、
 * リアルタイムの注文状況を表示します。
 * 
 * @author FG Development Team
 */

// エラー表示設定（デバッグ用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// 分離したモジュールを読み込み
require_once __DIR__ . '/sales_monitor_Orderview.php';
require_once __DIR__ . '/sales_monitor_Orderfunctions.php';

// ===== 共通ヘッダー読込 =====
$pageTitle = 'リアルタイム運用データ';
require_once __DIR__.'/inc/admin_header.php';

// 未ログインの場合は共通ヘッダーのログインフォームのみで終了
if (!$isLoggedIn) {
    require_once __DIR__.'/inc/admin_footer.php';
    return;
}

// ログイン状態を再確認
if (!isset($_SESSION['auth_user'])) {
$isLoggedIn = false;
    SalesMonitorOrderFunctions::log("未ログインのアクセス", "INFO");
    require_once __DIR__.'/inc/admin_footer.php';
    return;
}

    $currentUser = $_SESSION['auth_user'];
SalesMonitorOrderFunctions::log("ユーザー {$currentUser} がログイン中", "INFO");

try {
    // データベース接続
    $db = Database::getInstance();
    SalesMonitorOrderFunctions::log("データベース接続成功", "INFO");
} catch (Exception $e) {
    SalesMonitorOrderFunctions::log("データベース接続エラー: " . $e->getMessage(), "ERROR");
    echo "<div class=\"container\"><div class=\"alert alert-danger\">データベース接続エラーが発生しました。管理者に連絡してください。</div></div>";
    require_once __DIR__.'/inc/admin_footer.php';
    return;
}

// データ取得処理
$orderFunctions = new SalesMonitorOrderFunctions($db);
$result = $orderFunctions->fetchSalesData();
$salesData = $result['salesData'];
$dataErrors = $result['dataErrors'];

?>
<!-- ページ固有スタイル -->
<link rel="stylesheet" href="css/sales_monitor.css?v=<?php echo filemtime(__DIR__.'/css/sales_monitor.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <div class="container">
        <!-- ヘッダーは admin_header.php で出力済み -->
        <?php if ($isLoggedIn): ?>
    <div class="user-meta mb-2">
        <span class="auto-refresh-status me-2" id="refreshStatus">自動更新: 有効</span>
    </div>

    <!-- エラー表示 -->
    <?php if (!empty($dataErrors)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>データ取得エラー:</strong>
        <ul class="mb-0">
            <?php foreach ($dataErrors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
    <?php endif; ?>

    <!-- 統計カード -->
    <?php SalesMonitorOrderView::renderStatistics($salesData); ?>

        <!-- セクション区切り -->
        <hr class="section-divider mb-4">

        <!-- アクティブな部屋情報 -->
    <?php SalesMonitorOrderView::renderActiveRooms($salesData); ?>

        <!-- 部屋ごとの注文 -->
    <?php SalesMonitorOrderView::renderRoomOrders($salesData); ?>

        <!-- システム情報 -->
    <?php SalesMonitorOrderView::renderSystemInfo(); ?>

    <!-- モーダル -->
    <?php SalesMonitorOrderView::renderModals(); ?>

        <?php endif; ?>
    </div>
    
<!-- スクリプト -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/salesMonitor_testSession.js?v=<?php echo filemtime(__DIR__.'/js/salesMonitor_testSession.js'); ?>"></script>
<script src="js/salesMonitor_orderEdit.js?v=<?php echo filemtime(__DIR__.'/js/salesMonitor_orderEdit.js'); ?>"></script>
    <script>
// 新規注文ポーリング（既存機能の簡略版）
    (function() {
        "use strict";
        
    // 保存されている前回のデータ
        let previousStats = {
        orderCount: <?php echo (int)($salesData['order_count'] ?? 0); ?>,
        totalAmount: <?php echo (float)($salesData['total_amount'] ?? 0.0); ?>,
        activeRooms: <?php echo (int)($salesData['active_rooms'] ?? 0); ?>
        };
        
    // ブラウザ通知の許可を求める
        if ('Notification' in window) {
            if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
                Notification.requestPermission();
            }
        }
    })();

    // ダッシュボード自動更新設定
    (function() {
        "use strict";
        
        const refreshStatus = document.getElementById('refreshStatus');
        const refreshIntervalSelect = document.getElementById('refreshInterval');
        
        let refreshTimer = null;
        let isAutoRefreshEnabled = true;
        let userActive = false;
        let userActivityTimer = null;
        let isEditMode = false; // 編集モードフラグを追加
    let REFRESH_INTERVAL = 60000; // 1分
        
        if (!refreshStatus) {
            console.warn('Auto-refresh status element not found');
        return;
        }
        
        // 保存された間隔を復元
        try {
            const savedInterval = localStorage.getItem('salesMonitorRefreshInterval');
            if (savedInterval) {
                const parsedInterval = parseInt(savedInterval, 10);
                if (!isNaN(parsedInterval)) {
                    REFRESH_INTERVAL = parsedInterval;
                    if (refreshIntervalSelect) {
                        refreshIntervalSelect.value = REFRESH_INTERVAL.toString();
                    }
                }
            }
        } catch (e) {
            console.warn('Error loading saved refresh interval:', e);
        }
        
        function startAutoRefresh() {
            stopAutoRefresh();
            
            if (REFRESH_INTERVAL > 0) {
                refreshTimer = setInterval(function() {
                    if (!userActive && !isEditMode) { // 編集モードでない場合のみ更新
                        window.location.reload();
                    }
                }, REFRESH_INTERVAL);
                console.log("自動更新を開始しました: " + REFRESH_INTERVAL + "ms");
            }
        }
        
        function stopAutoRefresh() {
            if (refreshTimer !== null) {
                clearInterval(refreshTimer);
                refreshTimer = null;
            }
        }
        
        // 編集モードの開始・終了を検知
        window.setEditMode = function(enabled) {
            isEditMode = enabled;
            if (enabled) {
                console.log("編集モード開始 - 自動更新を一時停止");
                refreshStatus.textContent = '自動更新: 編集中';
                refreshStatus.classList.add('text-warning');
            } else {
                console.log("編集モード終了 - 自動更新を再開");
                refreshStatus.textContent = '自動更新: 有効';
                refreshStatus.classList.remove('text-warning');
                if (isAutoRefreshEnabled) {
                    startAutoRefresh();
                }
            }
        };
        
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
            
            try {
                localStorage.setItem('salesMonitorAutoRefresh', isAutoRefreshEnabled ? 'enabled' : 'disabled');
            } catch (e) {
                console.warn('Error saving auto refresh setting:', e);
            }
        }
        
        // 自動更新間隔の変更
        if (refreshIntervalSelect) {
            refreshIntervalSelect.addEventListener('change', function() {
                try {
                    REFRESH_INTERVAL = parseInt(this.value, 10);
                    if (isNaN(REFRESH_INTERVAL)) {
                    REFRESH_INTERVAL = 60000;
                    }
                    
                    localStorage.setItem('salesMonitorRefreshInterval', REFRESH_INTERVAL.toString());
                    
                    if (isAutoRefreshEnabled && REFRESH_INTERVAL > 0) {
                        startAutoRefresh();
                    } else if (REFRESH_INTERVAL === 0) {
                        isAutoRefreshEnabled = false;
                        stopAutoRefresh();
                        refreshStatus.textContent = '自動更新: 無効';
                        refreshStatus.classList.add('disabled');
                        localStorage.setItem('salesMonitorAutoRefresh', 'disabled');
                    }
                } catch (e) {
                    console.warn('Error handling refresh interval change:', e);
                }
            });
        }
        
        // 保存された設定があれば復元
        try {
            if (localStorage.getItem('salesMonitorAutoRefresh') === 'disabled') {
                isAutoRefreshEnabled = false;
                refreshStatus.textContent = '自動更新: 無効';
                refreshStatus.classList.add('disabled');
            } else if (REFRESH_INTERVAL > 0) {
                startAutoRefresh();
            }
        } catch (e) {
            console.warn('Error loading saved auto refresh setting:', e);
        }
        
        // 自動更新トグルのクリックイベント
        if (refreshStatus) {
            refreshStatus.style.cursor = 'pointer';
            refreshStatus.addEventListener('click', toggleAutoRefresh);
        }
        
        // ユーザーの操作を検知
        function markUserActive() {
            userActive = true;
            
            if (userActivityTimer) {
                clearTimeout(userActivityTimer);
            }
            
            userActivityTimer = setTimeout(function() {
                userActive = false;
            }, 5000);
        }
        
        ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
            document.addEventListener(event, markUserActive, { passive: true });
        });
    })();

// 通知ユーティリティ
    function showNotification(message) {
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notification-message');

        if (!notification || !notificationMessage) return;

        notificationMessage.textContent = message;
        notification.style.display = 'block';

        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }

// ユーザー強制削除処理
    (function() {
        "use strict";
        
        document.querySelectorAll('.deactivate-user').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.getAttribute('data-id');
                const roomNumber = this.getAttribute('data-room');
                const userName = this.getAttribute('data-user');
                
                if (!userId || !roomNumber) {
                    showNotification('ユーザーIDまたは部屋番号が不明です');
                    return;
                }
                
                if (!confirm(`${roomNumber}の利用者「${userName}」を強制削除しますか？\nこの操作は元に戻せません。`)) {
                    return;
                }
                
                fetch('api_deactivate_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: userId,
                        room_number: roomNumber
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`ユーザーを強制削除しました: ${roomNumber}`);
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotification('エラー: ' + (data.message || '強制削除に失敗しました'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('エラーが発生しました: ' + error.message);
                });
            });
        });
    })();

// セッションクローズ処理
    (function(){
        const modalEl = document.getElementById('closeSessionModal');
        let bsModal = null;
        let pendingSessionId = null;
        let pendingRoom = null;
    
    // 注文明細データを格納
    let currentOrderDetails = {};

        function ensureModal(){
            if(!bsModal && typeof bootstrap!=='undefined'){
                bsModal = new bootstrap.Modal(modalEl);
            }
        }

        // ボタンクリック → モーダル表示
        document.querySelectorAll('.close-session-btn').forEach(function(button){
            button.addEventListener('click',function(e){
                e.preventDefault();
                pendingSessionId = this.dataset.sessionId;
            pendingRoom = this.dataset.room;
                if(!pendingSessionId){
                    showNotification('session_id が取得できません');
                    return;
                }

                // モーダルに情報セット
                document.getElementById('csRoom').textContent = pendingRoom;
                document.getElementById('csSessionId').textContent = pendingSessionId;
                document.getElementById('csOpenTime').textContent = this.dataset.openTime || '--:--';
                document.getElementById('csOrderCount').textContent = this.dataset.orderCount || '0';
                document.getElementById('csTotal').textContent = Number(this.dataset.roomTotal||0).toLocaleString('ja-JP',{style:'currency',currency:'JPY'});
            document.getElementById('csDetailTotal').textContent = Number(this.dataset.roomTotal||0).toLocaleString('ja-JP',{style:'currency',currency:'JPY'});

            // 注文明細を表示
            displayOrderDetails(pendingRoom);

                // モーダル表示
                ensureModal();
                if(bsModal){
                    bsModal.show();
            }
        });
    });

    // 注文明細を表示する関数
    function displayOrderDetails(roomNumber) {
        const tbody = document.getElementById('csOrderDetails');
        tbody.innerHTML = '';

        // PHP側で生成されたデータをJSで利用（PHPでJSON化が必要）
        const orderDetails = window.orderDetailsData && window.orderDetailsData[roomNumber] || [];
        
        if (orderDetails.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">明細データがありません</td></tr>';
            return;
        }

        orderDetails.forEach(function(detail) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(detail.product_name || '不明な商品')}</td>
                <td class="text-center">${detail.quantity || 0}</td>
                <td class="text-end">${formatJPY(detail.unit_price || 0)}</td>
                <td class="text-end">${formatJPY(detail.subtotal || 0)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 未会計クローズボタン
    const pendingCloseBtn = document.getElementById('pendingCloseBtn');
    if(pendingCloseBtn){
        pendingCloseBtn.addEventListener('click',function(){
            if(confirm('このセッションを未会計状態でクローズします。\nSquareの商品は残り、後から会計可能です。\nよろしいですか？')){
                doCloseSession(false);
                }
            });
    }

    // 強制クローズボタン
    const forceCloseBtn = document.getElementById('forceCloseBtn');
    if(forceCloseBtn){
        forceCloseBtn.addEventListener('click',function(){
            if(confirm('警告：このセッションを強制的にクローズします。\nSquareの商品は無効化され、会計できなくなります。\n本当によろしいですか？')){
                if(confirm('最終確認：\nこの操作は取り消せません。\n強制クローズを実行しますか？')){
                    doCloseSession(true);
                }
            }
            });
        }

    function doCloseSession(forceClose = false){
            if(!pendingSessionId) return;
        const payload = {
            session_id: pendingSessionId,
            force: forceClose
        };
        
            fetch('close_order_session.php',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify(payload)
            }).then(r=>r.json()).then(d=>{
                if(d.success){
                const message = forceClose ? 'セッションを強制クローズしました' : 'セッションを未会計クローズしました';
                showNotification(message);
                    if(bsModal) bsModal.hide();
                    setTimeout(()=>window.location.reload(),1000);
                }else{
                    showNotification('エラー:'+ (d.message||'クローズに失敗'));
                }
            }).catch(err=>{
                console.error(err);
                showNotification('通信エラー:'+err.message);
            });
        }

    // HTMLエスケープ関数
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // 日本円フォーマット関数
    function formatJPY(amount) {
        return '¥' + Number(amount).toLocaleString('ja-JP');
    }
    })();
    
    // 手動注文追加機能
    (function() {
        "use strict";
        
        let manualOrderItems = [];
        let manualOrderModal = null;
        let selectedRoom = '';
        let selectedSessionId = '';
        
        // HTMLエスケープ関数（既存の関数と同じ内容をローカルに定義）
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        // 日本円フォーマット関数（既存の関数と同じ内容をローカルに定義）
        function formatJPY(amount) {
            return '¥' + Number(amount).toLocaleString('ja-JP');
        }
        
        // モーダルの初期化
        const modalEl = document.getElementById('manualOrderModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            manualOrderModal = new bootstrap.Modal(modalEl);
        }
        
        // オーダー追加ボタンのクリックイベント
        document.querySelectorAll('.add-order-btn').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                selectedRoom = this.dataset.room;
                selectedSessionId = this.dataset.sessionId;
                
                if (!selectedSessionId || selectedSessionId === '') {
                    if (confirm('このルームにはアクティブなセッションがありません。\n新しいセッションを作成しますか？')) {
                        // 新しいセッションを作成
                        createNewSession(selectedRoom);
                    }
                    return;
                }
                
                // モーダルの初期化
                document.getElementById('manualOrderRoom').textContent = selectedRoom;
                document.getElementById('manualOrderRoomInput').value = selectedRoom;
                document.getElementById('manualOrderSessionId').value = selectedSessionId;
                resetManualOrderForm();
                
                // カテゴリ一覧を取得
                fetchCategories();
                
                if (manualOrderModal) {
                    manualOrderModal.show();
                }
            });
        });
        
        // カテゴリ選択時の処理
        document.getElementById('categorySelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const categoryName = selectedOption.textContent; // カテゴリ名を取得
            const productSelect = document.getElementById('productSelect');
            
            if (!this.value) {
                productSelect.disabled = true;
                productSelect.innerHTML = '<option value="">商品を選択</option>';
                document.getElementById('addProductBtn').disabled = true;
                return;
            }
            
            // 商品一覧を取得（カテゴリ名を送信）
            fetchProducts(categoryName);
        });
        
        // 商品選択時の処理
        document.getElementById('productSelect').addEventListener('change', function() {
            document.getElementById('addProductBtn').disabled = !this.value;
        });
        
        // 商品追加ボタン
        document.getElementById('addProductBtn').addEventListener('click', function() {
            const productSelect = document.getElementById('productSelect');
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const quantity = parseInt(document.getElementById('productQuantity').value) || 1;
            
            if (!selectedOption.value) return;
            
            const item = {
                id: 'prod_' + Date.now(),
                square_item_id: selectedOption.value,
                name: selectedOption.textContent,
                price: parseFloat(selectedOption.dataset.price) || 0,
                quantity: quantity,
                type: 'product'
            };
            
            addItemToList(item);
            
            // フォームをリセット
            document.getElementById('productSelect').value = '';
            document.getElementById('productQuantity').value = 1;
            document.getElementById('addProductBtn').disabled = true;
        });
        
        // カスタム商品追加ボタン
        document.getElementById('addCustomProductBtn').addEventListener('click', function() {
            const name = document.getElementById('customProductName').value.trim();
            const price = parseFloat(document.getElementById('customProductPrice').value) || 0;
            
            if (!name) {
                showNotification('商品名を入力してください');
                return;
            }
            
            const item = {
                id: 'custom_' + Date.now(),
                square_item_id: null,
                name: name,
                price: price,
                quantity: 1,
                type: 'custom'
            };
            
            addItemToList(item);
            
            // フォームをリセット
            document.getElementById('customProductName').value = '';
            document.getElementById('customProductPrice').value = '';
        });
        
        // 注文送信ボタン
        document.getElementById('submitManualOrderBtn').addEventListener('click', function() {
            if (manualOrderItems.length === 0) {
                showNotification('商品を追加してください');
                return;
            }
            
            const memo = document.getElementById('manualOrderMemo').value.trim();
            
            if (!confirm('この注文を追加しますか？')) {
                return;
            }
            
            submitManualOrder(selectedSessionId, selectedRoom, manualOrderItems, memo);
        });
        
        // カテゴリ一覧を取得
        function fetchCategories() {
            fetch('api/get_categories.php')
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            // レスポンスが空の場合の処理
                            if (!text || text.trim() === '') {
                                throw new Error('サーバーから空のレスポンスが返されました（PHPエラーの可能性があります）');
                            }
                            try {
                                const data = JSON.parse(text);
                                throw new Error(data.message || 'カテゴリ取得エラー');
                            } catch (e) {
                                if (e instanceof SyntaxError) {
                                    throw new Error('サーバーエラー: ' + text.substring(0, 200));
                                }
                                throw e;
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('categorySelect');
                        select.innerHTML = '<option value="">カテゴリを選択</option>';
                        data.categories.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.name;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching categories:', error);
                    showNotification('カテゴリの取得に失敗しました: ' + error.message);
                });
        }
        
        // 商品一覧を取得
        function fetchProducts(categoryName) {
            const productSelect = document.getElementById('productSelect');
            productSelect.innerHTML = '<option value="">読み込み中...</option>';
            productSelect.disabled = true;
            
            fetch(`api/get_products.php?category_name=${encodeURIComponent(categoryName)}`)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            // レスポンスが空の場合の処理
                            if (!text || text.trim() === '') {
                                throw new Error('サーバーから空のレスポンスが返されました（PHPエラーの可能性があります）');
                            }
                            try {
                                const data = JSON.parse(text);
                                throw new Error(data.message || '商品取得エラー');
                            } catch (e) {
                                if (e instanceof SyntaxError) {
                                    throw new Error('サーバーエラー: ' + text.substring(0, 200));
                                }
                                throw e;
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        productSelect.innerHTML = '<option value="">商品を選択</option>';
                        data.products.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.square_item_id;
                            option.textContent = product.name;
                            option.dataset.price = product.price;
                            productSelect.appendChild(option);
                        });
                        productSelect.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error fetching products:', error);
                    showNotification('商品の取得に失敗しました: ' + error.message);
                    productSelect.innerHTML = '<option value="">エラー</option>';
                });
        }
        
        // 商品をリストに追加
        function addItemToList(item) {
            manualOrderItems.push(item);
            renderItemList();
        }
        
        // 商品リストを表示
        function renderItemList() {
            const tbody = document.getElementById('manualOrderItems');
            const totalEl = document.getElementById('manualOrderTotal');
            
            if (manualOrderItems.length === 0) {
                tbody.innerHTML = '<tr class="no-items"><td colspan="5" class="text-center text-muted">商品が追加されていません</td></tr>';
                totalEl.textContent = '¥0';
                return;
            }
            
            let total = 0;
            tbody.innerHTML = '';
            
            manualOrderItems.forEach((item, index) => {
                const subtotal = item.price * item.quantity;
                total += subtotal;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(item.name)}</td>
                    <td class="text-center">${item.quantity}</td>
                    <td class="text-end">${formatJPY(item.price)}</td>
                    <td class="text-end">${formatJPY(subtotal)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeManualOrderItem(${index})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            totalEl.textContent = formatJPY(total);
        }
        
        // 商品を削除
        window.removeManualOrderItem = function(index) {
            manualOrderItems.splice(index, 1);
            renderItemList();
        };
        
        // フォームをリセット
        function resetManualOrderForm() {
            manualOrderItems = [];
            renderItemList();
            document.getElementById('categorySelect').value = '';
            document.getElementById('productSelect').innerHTML = '<option value="">商品を選択</option>';
            document.getElementById('productSelect').disabled = true;
            document.getElementById('productQuantity').value = 1;
            document.getElementById('customProductName').value = '';
            document.getElementById('customProductPrice').value = '';
            document.getElementById('manualOrderMemo').value = '';
            document.getElementById('addProductBtn').disabled = true;
        }
        
        // 手動注文を送信
        function submitManualOrder(sessionId, roomNumber, items, memo) {
            const payload = {
                session_id: sessionId,
                room_number: roomNumber,
                items: items,
                memo: memo
            };
            
            fetch('api/add_manual_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        // レスポンスが空の場合の処理
                        if (!text || text.trim() === '') {
                            throw new Error('サーバーから空のレスポンスが返されました（PHPエラーの可能性があります）');
                        }
                        try {
                            const data = JSON.parse(text);
                            throw new Error(data.message || '注文追加エラー');
                        } catch (e) {
                            if (e instanceof SyntaxError) {
                                throw new Error('サーバーエラー: ' + text.substring(0, 200));
                            }
                            throw e;
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('注文を追加しました');
                    if (manualOrderModal) {
                        manualOrderModal.hide();
                    }
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('エラー: ' + (data.message || '注文の追加に失敗しました'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('通信エラー: ' + error.message);
            });
        }
        
        // 新しいセッションを作成
        function createNewSession(roomNumber) {
            fetch('api/create_order_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ room_number: roomNumber })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        if (!text || text.trim() === '') {
                            throw new Error('サーバーから空のレスポンスが返されました');
                        }
                        try {
                            const data = JSON.parse(text);
                            throw new Error(data.message || 'セッション作成エラー');
                        } catch (e) {
                            if (e instanceof SyntaxError) {
                                throw new Error('サーバーエラー: ' + text.substring(0, 200));
                            }
                            throw e;
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('新しいセッションを作成しました');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('エラー: ' + (data.message || 'セッションの作成に失敗しました'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('通信エラー: ' + error.message);
            });
        }
    })();
    </script>

<!-- 注文明細データをJSに渡す -->
<script>
window.orderDetailsData = <?php 
    // 各部屋の注文明細をJSONとして出力
    $orderDetailsForJs = [];
    if (!empty($salesData['room_orders'])) {
        foreach ($salesData['room_orders'] as $roomNumber => $roomOrderList) {
            $details = [];
            foreach ($roomOrderList as $order) {
                $orderId = $order['id'] ?? 0;
                if (isset($salesData['order_details'][$orderId])) {
                    foreach ($salesData['order_details'][$orderId] as $detail) {
                        $details[] = [
                            'product_name' => $detail['product_name'] ?? '不明な商品',
                            'quantity' => $detail['quantity'] ?? 0,
                            'unit_price' => $detail['unit_price'] ?? 0,
                            'subtotal' => $detail['subtotal'] ?? 0
                        ];
                    }
                }
            }
            $orderDetailsForJs[$roomNumber] = $details;
        }
    }
    echo json_encode($orderDetailsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;
</script>

<?php require_once __DIR__.'/inc/admin_footer.php'; ?> 