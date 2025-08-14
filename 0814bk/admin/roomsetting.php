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

// ログファイルの設定
$logDir = $rootPath . '/logs';
$logFile = $logDir . '/roomsetting.log';

// ログディレクトリの存在確認と作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// =====================
// adminsetting_registrer.php から register_settings を取得
// =====================
$registerSettings = loadAdminSettings('register_settings');
// デフォルト URL（取得失敗時用）
$defaultRegisterBase = 'https://mobes.online/register';
$qrBaseUrlSetting   = $registerSettings['base_url'] ?? $defaultRegisterBase;
// index.php を末尾に付ける（付いていなければ）
if (!preg_match('/\/index\.php$/', $qrBaseUrlSetting)) {
    $qrBaseUrlSetting = rtrim($qrBaseUrlSetting, '/') . '/index.php';
}

// adminsetting_registrer.php経由で設定を読み込む関数
function loadAdminSettings($section = null) {
    global $logFile;
    
    $timestamp = date('Y-m-d H:i:s');
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/adminsetting_registrer.php';
    
    if ($section) {
        $url .= '?section=' . urlencode($section);
    }
    
    $logMessage = "[$timestamp] [INFO] 設定読み込み URL: $url" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    
    if ($response === false) {
        $logMessage = "[$timestamp] [ERROR] 設定取得に失敗: " . curl_error($curl) . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        curl_close($curl);
        return null;
    }
    
    curl_close($curl);
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logMessage = "[$timestamp] [ERROR] JSONデコードエラー: " . json_last_error_msg() . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return null;
    }
    
    if (!isset($data['success']) || $data['success'] !== true) {
        $logMessage = "[$timestamp] [ERROR] 設定取得エラー: " . ($data['message'] ?? 'Unknown error') . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return null;
    }
    
    return $data['settings'];
}

// ログ関数
function writeLog($message, $level = 'INFO') {
    global $logFile;
    
    // ファイルサイズをチェック
    if (file_exists($logFile) && filesize($logFile) > 204800) { // 200KB
        // ファイルを削除して新規作成
        unlink($logFile);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ログイン状態チェック
$isLoggedIn = false;
if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_token'])) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['auth_user'];
    $authToken = $_SESSION['auth_token'];
    writeLog("ユーザーがログイン: $currentUser");
} else {
    // 未ログインの場合はログインページにリダイレクト
    writeLog("未ログインアクセス - index.phpにリダイレクト");
    header('Location: index.php');
    exit;
}

// ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['auth_user']);
    writeLog("ユーザーがログアウト: $currentUser");
    header('Location: index.php');
    exit;
}

// データベース接続
try {
    $db = Database::getInstance();
    writeLog("データベース接続成功");
} catch (Exception $e) {
    writeLog("データベース接続エラー: " . $e->getMessage(), 'ERROR');
}

// 部屋数のカウント
$roomCount = 0;
try {
    $roomCountResult = $db->selectOne("SELECT COUNT(*) as count FROM roomdatasettings");
    if ($roomCountResult) {
        $roomCount = $roomCountResult['count'];
    }
    writeLog("部屋数カウント: $roomCount");
} catch (Exception $e) {
    writeLog("部屋数カウントエラー: " . $e->getMessage(), 'ERROR');
}

// 利用中の部屋数
$activeRoomCount = 0;
try {
    $activeRoomResult = $db->selectOne("
        SELECT COUNT(*) as count FROM line_room_links 
        WHERE is_active = 1
    ");
    if ($activeRoomResult) {
        $activeRoomCount = $activeRoomResult['count'];
    }
    writeLog("利用中の部屋数カウント: $activeRoomCount");
} catch (Exception $e) {
    writeLog("利用中の部屋数カウントエラー: " . $e->getMessage(), 'ERROR');
}

// ===== 共通ヘッダー読込 =====
$pageTitle = '部屋設定';
require_once __DIR__.'/inc/admin_header.php';

// 未ログインの場合は共通ヘッダー側のログインフォームのみ表示して終了
if (!$isLoggedIn) {
    require_once __DIR__.'/inc/admin_footer.php';
    return;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>部屋設定 - FG Square</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- キャッシュ制御 -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body data-auth-token="<?php echo $authToken; ?>">
    <div class="container">
        <!-- 統計カード -->
        <div class="row mb-4">
            <div class="col-md-4 offset-md-4">
                <div class="card stat-card">
                    <div class="stat-value"><?php echo number_format($activeRoomCount); ?> / <?php echo number_format($roomCount); ?></div>
                    <div class="stat-label">利用中の部屋数 / 登録部屋数</div>
                </div>
            </div>
        </div>
        
        <!-- セパレータ追加 - マージンクラスを修正 -->
        <div class="section-divider section-spacer"></div>
        
        <!-- QRコードカード -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card qr-card">
                    <div class="card-header">
                        <h3>部屋登録用QRコード設定</h3>
                    </div>
                    <div class="card-body qr-settings-body">
                        <div class="mb-3">
                            <label for="qr-base-url" class="form-label">ベースURL</label>
                            <input type="text" class="form-control" id="qr-base-url" value="<?php echo htmlspecialchars($qrBaseUrlSetting); ?>">
                            <div class="form-text">登録ページのURLを入力してください</div>
                        </div>
                        <div class="mb-3">
                            <label for="qr-type-select" class="form-label">QRコードタイプ</label>
                            <select id="qr-type-select" class="form-control">
                                <option value="common">共通登録QR（パラメータなし）</option>
                                <option value="room">部屋別QR（パラメータ付き）</option>
                            </select>
                        </div>
                        <div class="mb-3" id="room-select-container" style="display: none;">
                            <label for="qr-room-select" class="form-label">部屋選択</label>
                            <select id="qr-room-select" class="form-control">
                                <option value="">部屋を選択してください</option>
                                <!-- 部屋データをJSで動的生成 -->
                            </select>
                        </div>
                        <div class="mb-3 text-center">
                            <button id="generate-qrcode" class="btn btn-primary btn-lg btn-xl">
                                <i class="bi bi-qr-code"></i> 登録用QRコードを生成
                            </button>
                        </div>
                        
                        <!-- QRコード説明セクション -->
                        <div class="qr-description">
                            <h4>共通登録QR</h4>
                            <p>このQRコードは登録画面を開くためのリンクです。部屋番号はユーザーが手動で選択する必要があります。</p>
                            
                            <h4 class="mt-3">部屋別QR</h4>
                            <p>このQRコードをスキャンすると、選択した部屋番号があらかじめ入力された状態で登録画面を開きます。</p>
                            <p>各部屋のQRコードを印刷して部屋に設置することをお勧めします。</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card qr-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3>QRプレビュー</h3>
                        <div>
                            <button id="print-qrcode" class="btn btn-outline-primary me-2">
                                <i class="bi bi-printer"></i> 印刷
                            </button>
                            <button id="share-qrcode" class="btn btn-outline-success">
                                <i class="bi bi-share"></i> 共有
                            </button>
                        </div>
                    </div>
                    <div class="card-body qr-preview-body">
                        <div id="qrcode-container" class="qr-preview-container">
                            <img src="../order/images/no-image.png" alt="QRコードプレビュー" class="img-fluid qr-preview-image">
                            <div class="qr-loading-overlay" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">読み込み中...</span>
                                </div>
                                <div class="mt-2">QRコード生成中...</div>
                            </div>
                        </div>
                        <div id="qr-url-display" class="mt-3 small text-muted" style="word-break: break-all;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 部屋一覧セクション -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>部屋一覧</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 60px">ID</th>
                                <th style="width: 120px">部屋番号</th>
                                <th style="width: 30%">説明</th>
                                <th style="width: 120px">利用状況</th>
                                <th style="width: 100px">状態</th>
                                <th style="width: 150px">最終更新</th>
                                <th style="width: 60px">操作</th>
                            </tr>
                        </thead>
                        <tbody id="rooms-table-body">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <i class="bi bi-arrow-repeat spin"></i> 読み込み中...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-primary" id="add-room-btn">
                        <i class="bi bi-plus-circle"></i> 部屋を追加
                    </button>
                    <button type="button" class="btn btn-success" id="save-all-btn">
                        <i class="bi bi-save"></i> 設定を反映
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
                            <div class="form-text">例: fg#01, R-102 など</div>
                        </div>
                        <div class="mb-3">
                            <label for="room-description" class="form-label">説明</label>
                            <input type="text" class="form-control" id="room-description" name="description">
                            <div class="form-text">例: 101号室, VIPルーム など</div>
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
        
        <!-- 成功・エラーメッセージモーダル -->
        <div class="modal" id="message-modal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h4 id="message-title">メッセージ</h4>
                    <button type="button" class="close-message-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="message-text"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary close-message-modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/roomsetting.js"></script>
    
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
    
    .close-modal, .close-message-modal {
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
    
    /* テーブル行スタイル */
    .table th, .table td {
        padding: 0.5rem; /* 通常の70%程度にパディングを縮小 */
    }
    
    .table .form-control {
        padding: 0.25rem 0.5rem; /* フォーム入力欄も縮小 */
        height: calc(1.5em + 0.5rem + 2px); /* 高さも調整 */
    }
    
    /* 無効な部屋の行スタイル */
    .inactive-row {
        background-color: #a9a9a9 !important; /* グレー背景色 */
        color: white; /* テキストを白に */
    }
    
    .inactive-row .form-control {
        background-color: #b8b8b8; /* フォーム入力欄の背景色も少し明るく */
        border-color: #999;
        color: #fff;
    }
    
    .inactive-row .btn-danger {
        background-color: #c82333; /* 削除ボタンは目立たせる */
        border-color: #bd2130;
    }
    
    /* QRコードプレビュースタイル */
    .qr-preview-container {
        position: relative;
        width: 100%;
        max-width: 500px;
        min-height: 600px;
        margin: 0 auto;
        background-color: #f8f9fa;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .qr-preview-image {
        max-width: 100%;
        max-height: 600px;
        object-fit: contain;
    }
    
    .qr-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(255, 255, 255, 0.8);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10;
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
    
    /* ステータスバッジ */
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .status-badge.success {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    
    .status-badge.info {
        background-color: #cff4fc;
        color: #055160;
    }
    
    .status-badge.warning {
        background-color: #fff3cd;
        color: #664d03;
    }
    </style>
</body>
</html>
<?php require_once __DIR__.'/inc/admin_footer.php'; ?>
