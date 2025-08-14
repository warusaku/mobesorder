# LINE部屋番号登録モジュール修正仕様書

## 修正対象ファイル

1. **新規作成ファイル**
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/admin/roomsetting.php`

2. **修正ファイル**
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/admin/index.php` - 上部ナビゲーションと管理機能カードに部屋設定を追加
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/register/index.php` - UI改善
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/register/js/app.js` - ドラムロール実装
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/register/css/style.css` - 新UIスタイル

3. **APIファイル修正**
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/register/api/register.php` - 登録処理の修正
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/register/api/room.php` - 部屋情報取得修正
   - `/Volumes/crucial_MX500/Materials/works/fabula/fgsquare/api/admin/roomdata.php` - 管理者用API新規作成

## 1. データベース構造の変更

### 1.1 新規テーブル作成

```sql
CREATE TABLE `roomdatasettings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(5) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_number` (`room_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.2 初期データ投入

```sql
INSERT INTO `roomdatasettings` (`room_number`, `description`, `is_active`) VALUES
('fg#01', '101号室', 1),
('fg#02', '102号室', 1),
('fg#03', '103号室', 1),
('fg#04', '104号室', 1),
('fg#05', '105号室', 1),
('fg#06', '106号室', 1),
('fg#07', '107号室', 1),
('fg#08', '201号室', 1),
('fg#09', '202号室', 1),
('fg#10', '203号室', 1),
('fg#11', '204号室', 1),
('fg#12', '205号室', 1),
('fg#13', '206号室', 1),
('fg#14', '207号室', 1);
```

## 2. 管理画面の実装

### 2.1 管理者メニューの追加（index.php）

以下のナビゲーションリンクを`admin/index.php`の「ナビゲーション」セクションに追加します：

```php
<!-- ナビゲーション -->
<ul class="nav-pills">
    <li class="nav-item">
        <a class="nav-link active" href="index.php">ダッシュボード</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="products_sync.php">商品同期</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="manage_categories.php">カテゴリ管理</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="product_display_util.php">商品表示設定</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="sales_monitor.php">リアルタイム運用データ</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="roomsetting.php">部屋設定</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="../order/" target="_blank">注文画面</a>
    </li>
</ul>
```

### 2.2 管理機能カードの追加（index.php）

以下のカードを`admin/index.php`の「機能カード」セクションに追加します：

```php
<div class="col-md-3">
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
```

### 2.3 部屋設定画面（roomsetting.php）の実装

新規ファイル`roomsetting.php`は以下の内容で作成します：

```php
<?php
/**
 * 部屋設定画面
 * 
 * 部屋番号の管理と利用状況を表示するための管理画面です。
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// セッション開始
session_start();

// ログイン状態チェック
$isLoggedIn = false;
if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_token'])) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['auth_user'];
    $authToken = $_SESSION['auth_token'];
} else {
    // 未ログインの場合はログインページにリダイレクト
    header('Location: index.php');
    exit;
}

// データベース接続
$db = Database::getInstance();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>部屋設定 - FG Square</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>管理ダッシュボード</h1>
            <div class="user-info">
                <span class="me-2">ユーザー: <?php echo htmlspecialchars($currentUser); ?></span>
                <span class="auto-refresh-status me-2" id="refreshStatus">自動更新: 有効</span>
                <a href="?logout=1" class="btn btn-sm btn-outline-secondary">ログアウト</a>
            </div>
        </div>
        
        <!-- ナビゲーション -->
        <ul class="nav-pills">
            <li class="nav-item">
                <a class="nav-link" href="index.php">ダッシュボード</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products_sync.php">商品同期</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_categories.php">カテゴリ管理</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="product_display_util.php">商品表示設定</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sales_monitor.php">リアルタイム運用データ</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="roomsetting.php">部屋設定</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../order/" target="_blank">注文画面</a>
            </li>
        </ul>
        
        <!-- ページヘッダー -->
        <div class="page-header mb-4">
            <h2>部屋設定</h2>
            <p class="text-secondary">部屋情報の管理と利用状況の確認</p>
        </div>
        
        <!-- 部屋一覧セクション -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3>部屋一覧</h3>
                <button type="button" class="btn btn-primary btn-sm" id="add-room-btn">
                    <i class="bi bi-plus-circle"></i> 部屋を追加
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 60px">ID</th>
                                <th style="width: 120px">部屋番号</th>
                                <th>説明</th>
                                <th style="width: 100px">状態</th>
                                <th style="width: 150px">最終更新</th>
                                <th style="width: 120px">操作</th>
                            </tr>
                        </thead>
                        <tbody id="rooms-table-body">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <i class="bi bi-arrow-repeat spin"></i> 読み込み中...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="button" class="btn btn-success" id="save-all-btn">
                        <i class="bi bi-save"></i> 一括更新
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 利用状況セクション -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3>利用状況</h3>
                <button type="button" class="btn btn-outline-primary btn-sm" id="refresh-usage-btn">
                    <i class="bi bi-arrow-clockwise"></i> 最新情報に更新
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 100px">部屋番号</th>
                                <th>利用者</th>
                                <th style="width: 150px">チェックイン</th>
                                <th style="width: 150px">チェックアウト</th>
                                <th style="width: 100px">状態</th>
                            </tr>
                        </thead>
                        <tbody id="usage-table-body">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <i class="bi bi-arrow-repeat spin"></i> 読み込み中...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- 部屋追加モーダル -->
        <div class="modal" id="add-room-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>部屋を追加</h4>
                    <button type="button" class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="add-room-form">
                        <div class="mb-3">
                            <label for="room-number" class="form-label">部屋番号 (5文字以内)</label>
                            <input type="text" class="form-control" id="room-number" name="room_number" maxlength="5" required>
                        </div>
                        <div class="mb-3">
                            <label for="room-description" class="form-label">説明</label>
                            <input type="text" class="form-control" id="room-description" name="description">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="room-active" name="is_active" checked>
                            <label class="form-check-label" for="room-active">
                                有効にする
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" id="save-room-btn">追加</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // 部屋データ
    let roomsData = [];
    let changedRooms = [];
    
    // 初期化処理
    document.addEventListener('DOMContentLoaded', function() {
        loadRoomData();
        loadUsageData();
        
        // 部屋追加ボタン
        document.getElementById('add-room-btn').addEventListener('click', function() {
            document.getElementById('add-room-modal').style.display = 'block';
        });
        
        // モーダルを閉じるボタン
        document.querySelectorAll('.close-modal').forEach(function(button) {
            button.addEventListener('click', function() {
                document.getElementById('add-room-modal').style.display = 'none';
            });
        });
        
        // 部屋追加保存ボタン
        document.getElementById('save-room-btn').addEventListener('click', saveNewRoom);
        
        // 一括更新ボタン
        document.getElementById('save-all-btn').addEventListener('click', saveAllChanges);
        
        // 利用状況更新ボタン
        document.getElementById('refresh-usage-btn').addEventListener('click', loadUsageData);
    });
    
    // 部屋データ読み込み
    function loadRoomData() {
        fetch('../api/admin/roomdata.php?token=<?php echo $authToken; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    roomsData = data.rooms;
                    renderRoomsTable();
                } else {
                    showError('部屋データの読み込みに失敗しました: ' + data.message);
                }
            })
            .catch(error => {
                showError('部屋データの読み込み中にエラーが発生しました: ' + error.message);
            });
    }
    
    // 利用状況データ読み込み
    function loadUsageData() {
        fetch('../api/admin/roomdata.php?token=<?php echo $authToken; ?>&usage=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderUsageTable(data.usage);
                } else {
                    showError('利用状況データの読み込みに失敗しました: ' + data.message);
                }
            })
            .catch(error => {
                showError('利用状況データの読み込み中にエラーが発生しました: ' + error.message);
            });
    }
    
    // 部屋テーブル描画
    function renderRoomsTable() {
        const tbody = document.getElementById('rooms-table-body');
        tbody.innerHTML = '';
        
        roomsData.forEach(room => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${room.id}</td>
                <td>
                    <span class="room-number">${room.room_number}</span>
                </td>
                <td>
                    <input type="text" class="form-control room-description" value="${room.description || ''}" data-id="${room.id}">
                </td>
                <td>
                    <div class="form-check">
                        <input class="form-check-input room-active" type="checkbox" ${room.is_active == 1 ? 'checked' : ''} data-id="${room.id}">
                        <label class="form-check-label">${room.is_active == 1 ? '有効' : '無効'}</label>
                    </div>
                </td>
                <td>${formatDateTime(room.last_update)}</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm delete-room-btn" data-id="${room.id}">
                        <i class="bi bi-trash"></i> 削除
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        // 編集イベント
        document.querySelectorAll('.room-description').forEach(input => {
            input.addEventListener('change', function() {
                const roomId = this.getAttribute('data-id');
                markAsChanged(roomId, 'description', this.value);
            });
        });
        
        document.querySelectorAll('.room-active').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const roomId = this.getAttribute('data-id');
                const label = this.nextElementSibling;
                label.textContent = this.checked ? '有効' : '無効';
                markAsChanged(roomId, 'is_active', this.checked ? 1 : 0);
            });
        });
        
        // 削除ボタン
        document.querySelectorAll('.delete-room-btn').forEach(button => {
            button.addEventListener('click', function() {
                const roomId = this.getAttribute('data-id');
                deleteRoom(roomId);
            });
        });
    }
    
    // 利用状況テーブル描画
    function renderUsageTable(usageData) {
        const tbody = document.getElementById('usage-table-body');
        tbody.innerHTML = '';
        
        if (usageData.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="5" class="text-center">利用中の部屋はありません</td>';
            tbody.appendChild(tr);
            return;
        }
        
        usageData.forEach(usage => {
            if (!usage.room_number) return;
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${usage.room_number}</td>
                <td>${usage.user_name || '-'}</td>
                <td>${usage.check_in_date || '-'}</td>
                <td>${usage.check_out_date || '-'}</td>
                <td>
                    <span class="status-badge ${usage.is_active == 1 ? 'success' : 'info'}">
                        ${usage.is_active == 1 ? '利用中' : '未使用'}
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
    
    // 変更をマーク
    function markAsChanged(roomId, field, value) {
        const index = changedRooms.findIndex(item => item.id == roomId);
        
        if (index === -1) {
            changedRooms.push({
                id: roomId,
                [field]: value
            });
        } else {
            changedRooms[index][field] = value;
        }
    }
    
    // 新規部屋の保存
    function saveNewRoom() {
        const roomNumber = document.getElementById('room-number').value;
        const description = document.getElementById('room-description').value;
        const isActive = document.getElementById('room-active').checked ? 1 : 0;
        
        if (!roomNumber) {
            showError('部屋番号を入力してください');
            return;
        }
        
        if (roomNumber.length > 5) {
            showError('部屋番号は5文字以内で入力してください');
            return;
        }
        
        const formData = new FormData();
        formData.append('token', '<?php echo $authToken; ?>');
        formData.append('action', 'add');
        formData.append('room_number', roomNumber);
        formData.append('description', description);
        formData.append('is_active', isActive);
        
        fetch('../api/admin/saveroom.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('部屋を追加しました');
                document.getElementById('add-room-modal').style.display = 'none';
                document.getElementById('add-room-form').reset();
                loadRoomData();
            } else {
                showError('部屋の追加に失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            showError('部屋の追加中にエラーが発生しました: ' + error.message);
        });
    }
    
    // 全ての変更を保存
    function saveAllChanges() {
        if (changedRooms.length === 0) {
            showInfo('変更はありません');
            return;
        }
        
        const formData = new FormData();
        formData.append('token', '<?php echo $authToken; ?>');
        formData.append('action', 'update');
        formData.append('room_data', JSON.stringify(changedRooms));
        
        fetch('../api/admin/saveroom.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('部屋設定を更新しました');
                changedRooms = [];
                loadRoomData();
            } else {
                showError('部屋設定の更新に失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            showError('部屋設定の更新中にエラーが発生しました: ' + error.message);
        });
    }
    
    // 部屋の削除
    function deleteRoom(roomId) {
        if (!confirm('この部屋を削除してもよろしいですか？\n※利用中の部屋は削除できません。')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('token', '<?php echo $authToken; ?>');
        formData.append('action', 'delete');
        formData.append('room_id', roomId);
        
        fetch('../api/admin/saveroom.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('部屋を削除しました');
                loadRoomData();
            } else {
                showError('部屋の削除に失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            showError('部屋の削除中にエラーが発生しました: ' + error.message);
        });
    }
    
    // 日時フォーマット
    function formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('ja-JP', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // メッセージ表示
    function showSuccess(message) {
        alert('成功: ' + message);
    }
    
    function showError(message) {
        alert('エラー: ' + message);
    }
    
    function showInfo(message) {
        alert('情報: ' + message);
    }
    </script>
    
    <style>
    /* モーダルスタイル */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }
    
    .modal-content {
        background-color: white;
        margin: 10% auto;
        padding: 0;
        border-radius: 8px;
        width: 500px;
        max-width: 90%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
        padding: 16px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h4 {
        margin: 0;
        font-weight: 600;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #888;
    }
    
    .modal-body {
        padding: 16px;
    }
    
    .modal-footer {
        padding: 16px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    
    /* アニメーション */
    .spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</body>
</html>
```

## 3. 利用者登録画面（register/index.php）の改修

### 3.1 UIデザイン変更

- `order/index.php` と同様のヘッダースタイル
- ブランドロゴ（SVG）の統一
- ウェルカムメッセージの追加
- スタイリッシュなカードレイアウト

### 3.2 部屋選択UIの改善

- 中央配置の大型ドラムロール（スピナー）形式
- roomdatasettingsテーブルから取得した有効な部屋のみ表示
- 視認性向上のための大きなフォントサイズ

### 3.3 日付入力の簡略化

- チェックイン日：当日の日付を自動設定（編集不可）
- チェックアウト日：日付選択画面（最大7日後まで選択可能）
- チェックアウト時刻：時間選択（00:00～23:00、1時間単位）

### 3.4 ユーザー情報入力

- 予約者名入力欄（必須）
- LINEプロフィール情報表示（アイコン、表示名）

### 3.5 登録ボタン

- 大型の「設定する」ボタン
- 登録処理中のローディング表示
- 登録完了モーダル

## 4. JavaScript実装詳細

### 4.1 部屋選択ドラムロールの実装

新しいJSライブラリ「Picker.js」を使用して部屋選択ドラムロールを実装：

```javascript
// 部屋データの動的取得
async function fetchRoomData() {
    try {
        const response = await fetch(`${apiUrl}/api/admin/roomdata.php?active=1`);
        const data = await response.json();
        return data.rooms || [];
    } catch (error) {
        console.error('部屋データ取得エラー:', error);
        return [];
    }
}

// ドラムロール初期化
async function initRoomPicker() {
    const rooms = await fetchRoomData();
    
    if (rooms.length === 0) {
        showError('利用可能な部屋がありません。管理者にお問い合わせください。');
        return;
    }
    
    const roomData = rooms.map(room => ({ text: room.room_number, value: room.room_number }));
    
    const picker = new Picker({
        data: [roomData],
        selectedIndex: [0],
        title: '部屋を選択',
        onConfirm: (indexes, values) => {
            document.getElementById('room-number').value = values[0];
            document.getElementById('selected-room').textContent = values[0];
        }
    });
    
    document.getElementById('room-select-button').addEventListener('click', () => {
        picker.show();
    });
}
```

### 4.2 日付・時間選択の実装

チェックアウト日の制約（最大7日後まで）と時間選択の実装：

```javascript
// 日付選択の初期化
function initDatePickers() {
    // 本日の日付をチェックイン日として設定
    const today = new Date();
    const formattedToday = formatDate(today);
    document.getElementById('check-in-date').value = formattedToday;
    
    // チェックアウト日の最大値（7日後）を設定
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 7);
    const formattedMaxDate = formatDate(maxDate);
    
    // 明日の日付をデフォルトに設定
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const formattedTomorrow = formatDate(tomorrow);
    
    const checkoutDateInput = document.getElementById('check-out-date');
    checkoutDateInput.value = formattedTomorrow;
    checkoutDateInput.min = formattedToday;
    checkoutDateInput.max = formattedMaxDate;
}

// 時間選択のセットアップ
function setupTimeSelector() {
    const timeSelector = document.getElementById('checkout-time');
    
    for (let i = 0; i <= 23; i++) {
        const hour = i.toString().padStart(2, '0');
        const option = document.createElement('option');
        option.value = `${hour}:00`;
        option.textContent = `${hour}:00`;
        timeSelector.appendChild(option);
    }
    
    // デフォルトを11:00に設定
    timeSelector.value = '11:00';
}
```

## 5. API実装詳細

### 5.1 部屋データ取得API

管理者用および登録画面用の部屋データ取得API：

```php
// api/admin/roomdata.php - 管理者用部屋データAPI
try {
    require_once '../../config/config.php';
    require_once '../lib/Utils.php';
    
    // 管理者認証
    if (!isset($_GET['token']) || $_GET['token'] !== ADMIN_KEY) {
        throw new Exception('認証エラー');
    }
    
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 部屋データ取得
    $activeOnly = isset($_GET['active']) && $_GET['active'] == '1';
    
    $sql = "SELECT * FROM roomdatasettings";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY room_number";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 利用状況も含める場合
    if (isset($_GET['usage']) && $_GET['usage'] == '1') {
        $sql = "SELECT r.room_number, l.user_name, l.check_in_date, l.check_out_date, l.is_active 
                FROM roomdatasettings r
                LEFT JOIN line_room_links l ON r.room_number = l.room_number
                ORDER BY r.room_number";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $usageData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'usage' => $usageData
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'rooms' => $rooms
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

### 5.2 部屋設定保存API

管理者用の部屋設定保存API：

```php
// api/admin/saveroom.php - 部屋設定保存API
try {
    require_once '../../config/config.php';
    require_once '../lib/Utils.php';
    
    // POSTリクエストのみ許可
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('不正なリクエストメソッド');
    }
    
    // 管理者認証
    if (!isset($_POST['token']) || $_POST['token'] !== ADMIN_KEY) {
        throw new Exception('認証エラー');
    }
    
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 操作タイプにより処理分岐
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // 新規部屋追加
            $roomNumber = trim($_POST['room_number']);
            $description = trim($_POST['description']);
            
            // バリデーション
            if (empty($roomNumber)) {
                throw new Exception('部屋番号は必須です');
            }
            
            if (strlen($roomNumber) > 5) {
                throw new Exception('部屋番号は5文字以内で入力してください');
            }
            
            // 重複チェック
            $stmt = $db->prepare("SELECT COUNT(*) FROM roomdatasettings WHERE room_number = ?");
            $stmt->execute([$roomNumber]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('この部屋番号は既に存在します');
            }
            
            // 登録
            $stmt = $db->prepare("INSERT INTO roomdatasettings (room_number, description, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$roomNumber, $description]);
            
            echo json_encode([
                'success' => true,
                'message' => '部屋を追加しました'
            ]);
            break;
            
        case 'update':
            // 部屋データ更新
            $roomData = json_decode($_POST['room_data'], true);
            
            if (!$roomData || !is_array($roomData)) {
                throw new Exception('不正なデータ形式です');
            }
            
            $db->beginTransaction();
            
            foreach ($roomData as $room) {
                $stmt = $db->prepare("UPDATE roomdatasettings SET description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([
                    $room['description'],
                    $room['is_active'] ? 1 : 0,
                    $room['id']
                ]);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => '部屋設定を更新しました'
            ]);
            break;
            
        case 'delete':
            // 部屋削除
            $roomId = $_POST['room_id'] ?? 0;
            
            // 使用中チェック
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM roomdatasettings r
                JOIN line_room_links l ON r.room_number = l.room_number
                WHERE r.id = ? AND l.is_active = 1
            ");
            $stmt->execute([$roomId]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('この部屋は現在使用中のため削除できません');
            }
            
            $stmt = $db->prepare("DELETE FROM roomdatasettings WHERE id = ?");
            $stmt->execute([$roomId]);
            
            echo json_encode([
                'success' => true,
                'message' => '部屋を削除しました'
            ]);
            break;
            
        default:
            throw new Exception('不明な操作です');
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

### 5.3 登録処理の変更

更新された部屋登録処理（register.php）：

```php
// 既存の部分は省略...

// リクエストボディからJSONデータを取得
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// データのバリデーション
if (!isset($data['userId']) || !isset($data['roomNumber']) || 
    !isset($data['checkInDate']) || !isset($data['checkOutDate']) ||
    !isset($data['userName']) || !isset($data['checkoutTime'])) {
    throw new Exception('必須パラメータが不足しています');
}

$userId = $data['userId'];
$userName = $data['userName'];
$roomNumber = $data['roomNumber'];
$checkInDate = $data['checkInDate'];
$checkOutDate = $data['checkOutDate'];
$checkoutTime = $data['checkoutTime'];

// 部屋の有効性チェック
$stmt = $pdo->prepare("SELECT id FROM roomdatasettings WHERE room_number = ? AND is_active = 1");
$stmt->execute([$roomNumber]);
if (!$stmt->fetch()) {
    throw new Exception('無効な部屋番号が指定されました');
}

// チェックアウト日時をフォーマット
$checkOutDateTime = $checkOutDate . ' ' . $checkoutTime;

// RoomDataRegisterインスタンスを作成
$roomDataRegister = new RoomDataRegister();

// ユーザーと部屋情報を保存 - is_active=1 に明示的に設定
$roomDataRegister->saveRoomLink($userId, $roomNumber, $userName, $checkInDate, $checkOutDate, $checkoutTime, 1);

// 既存のレスポンスコード...
```

## 6. 新しいUI要素とスタイル

### 6.1 ドラムロールUI用スタイル

```css
/* Picker.js用スタイル */
.picker-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background-color: #fff;
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
    box-shadow: 0px -4px 16px rgba(0, 0, 0, 0.15);
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.picker-container.visible {
    transform: translateY(0);
}

.picker-header {
    display: flex;
    justify-content: space-between;
    padding: 16px;
    border-bottom: 1px solid #eee;
}

.picker-title {
    font-weight: bold;
    font-size: 18px;
}

.picker-close, .picker-confirm {
    padding: 8px 16px;
    border: none;
    background: none;
    font-size: 16px;
    cursor: pointer;
}

.picker-confirm {
    color: #4CAF50;
    font-weight: bold;
}

.picker-columns {
    display: flex;
    height: 200px;
    overflow: hidden;
    text-align: center;
    padding: 16px 0;
}

.picker-column {
    flex: 1;
    position: relative;
    overflow: hidden;
}

.picker-column ul {
    list-style: none;
    padding: 0;
    margin: 0;
    position: absolute;
    width: 100%;
    top: 0;
    transition: transform 0.3s ease;
}

.picker-column li {
    padding: 10px 0;
    font-size: 20px;
    height: 40px;
    line-height: 40px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.picker-highlight {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 40px;
    margin-top: -20px;
    pointer-events: none;
    border-top: 1px solid #ddd;
    border-bottom: 1px solid #ddd;
    background-color: rgba(76, 175, 80, 0.05);
}
```

### 6.2 新しいフォームレイアウト

```css
/* 新しいフォームレイアウト */
.register-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 20px;
}

.register-card {
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.register-header {
    background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.register-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 500;
}

.register-header p {
    margin: 10px 0 0 0;
    font-size: 16px;
    opacity: 0.9;
}

.form-section {
    padding: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    border-color: #4CAF50;
    outline: none;
}

.room-select-button {
    width: 100%;
    padding: 16px;
    font-size: 18px;
    font-weight: 500;
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.room-select-button:hover {
    background-color: #eeeeee;
}

.selected-room-display {
    margin-top: 12px;
    text-align: center;
    font-size: 20px;
    font-weight: 500;
    color: #4CAF50;
}

.checkout-time-container {
    display: flex;
    gap: 16px;
}

.checkout-time-container .form-control {
    flex: 1;
}

.register-button {
    display: block;
    width: 100%;
    padding: 16px;
    margin-top: 24px;
    background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 500;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.register-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.25);
}

.register-button:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.15);
}

.user-info {
    display: flex;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #eee;
}

.profile-image {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 16px;
}

.profile-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-name {
    font-size: 18px;
    font-weight: 500;
}
```

## 7. キャッシュ制御の実装

すべてのページでキャッシュを無効化するため、以下のヘッダーを追加：

```php
// キャッシュ制御ヘッダー
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
```

JavaScript側でも強制リロード関数を実装：

```javascript
// キャッシュをバイパスする完全リロード関数
function forceReload() {
    const now = new Date().getTime();
    window.location.href = window.location.pathname + '?nocache=' + now;
}
```

## 8. QRコードによる再アクセス時の処理

QRコードを読み込んで再度アクセスした場合は、LINE IDをベースに登録情報を更新する機能：

```javascript
// アプリ初期化時に既存登録を確認し、QRコードからのアクセスなら更新モードにする
async function initializeApp() {
    try {
        console.log('アプリ初期化開始');
        
        // APIのベースURLを設定
        apiUrl = window.liffInfo ? window.liffInfo.apiUrl : 'https://test-mijeos.but.jp/fgsquare';
        api.setBaseUrl(apiUrl);
        
        // URLパラメータからQRコードスキャンを検出
        const urlParams = new URLSearchParams(window.location.search);
        const isQrScan = urlParams.has('qr');
        
        // 既存の登録状況を確認
        const existingRoom = await api.getRoomInfo();
        
        if (existingRoom && !isQrScan) {
            // 既に登録があり、QRスキャンでない場合は登録完了モーダルを表示
            showRegistrationComplete(existingRoom);
        } else if (existingRoom && isQrScan) {
            // 既に登録があるが、QRスキャンの場合は更新モードで表示
            setupFormForUpdate(existingRoom);
        } else {
            // 新規登録
            setupFormForNewRegistration();
        }
        
        console.log('アプリ初期化完了');
    } catch (error) {
        console.error('アプリ初期化エラー:', error);
        showError('アプリの初期化に失敗しました。');
    }
}

// 更新モード用のフォーム設定
function setupFormForUpdate(roomInfo) {
    // フォームの表示
    document.getElementById('content-container').style.display = 'block';
    document.getElementById('loading').style.display = 'none';
    
    // 既存の値を設定
    if (roomInfo.roomNumber) {
        document.getElementById('room-number').value = roomInfo.roomNumber;
        document.getElementById('selected-room').textContent = roomInfo.roomNumber;
    }
    
    if (roomInfo.userName) {
        document.getElementById('user-name-input').value = roomInfo.userName;
    }
    
    // チェックイン日は今日に設定
    const today = new Date();
    document.getElementById('check-in-date').value = formatDate(today);
    
    // チェックアウト日は既存の値または設定
    if (roomInfo.checkOutDate) {
        document.getElementById('check-out-date').value = roomInfo.checkOutDate;
    }
    
    // チェックアウト時間
    if (roomInfo.checkoutTime) {
        document.getElementById('checkout-time').value = roomInfo.checkoutTime;
    }
    
    // ボタンテキストを「更新する」に変更
    document.getElementById('register-button').textContent = '更新する';
    
    // その他UIセットアップ
    setupFormListeners();
}
```
