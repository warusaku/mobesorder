<?php
/**
 * カテゴリ管理ユーティリティ
 * 
 * このスクリプトは、カテゴリの表示順、ラストオーダー時間、アクティブ状態などを
 * 管理するための管理者向けインターフェースを提供します。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';
require_once $rootPath . '/api/lib/SquareService.php';

// ---------- 共通ヘッダー導入 ----------
$pageTitle = 'カテゴリ管理';
require_once __DIR__ . '/inc/admin_header.php';
// admin_header.php で認証処理済み。未ログインの場合はログインフォームが出力済みなので終了。
if (!$isLoggedIn) {
    require_once __DIR__ . '/inc/admin_footer.php';
    return;
}

// ログ関数
function logMessage($message, $level = 'INFO') {
    // カテゴリ管理用のログファイル
    $logFile = realpath(__DIR__ . '/..') . '/logs/manage_categories.log';
    
    // ログローテーションチェック
    if (file_exists($logFile) && filesize($logFile) > 300 * 1024) { // 300KB
        $content = file_get_contents($logFile);
        // 最後の20%程度を保持
        $keepSize = intval(300 * 1024 * 0.2);
        $newContent = substr($content, -$keepSize);
        file_put_contents($logFile, "--- ログローテーション " . date('Y-m-d H:i:s') . " ---\n" . $newContent);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // メインログにも記録
    Utils::log($message, $level, 'CategoryManager');
}

// adminsetting_registrer.php経由で設定を読み込む関数
function loadAdminSettings($section = null) {
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/adminsetting_registrer.php';
    
    if ($section) {
        $url .= '?section=' . urlencode($section);
    }
    
    logMessage("設定読み込み URL: " . $url);
    
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    
    if ($response === false) {
        logMessage("設定取得に失敗: " . curl_error($curl), 'ERROR');
        curl_close($curl);
        return null;
    }
    
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    logMessage("HTTP レスポンスコード: " . $httpCode);
    
    curl_close($curl);
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("JSONデコードエラー: " . json_last_error_msg(), 'ERROR');
        logMessage("レスポンス: " . substr($response, 0, 500), 'ERROR');
        return null;
    }
    
    if (!isset($data['success']) || $data['success'] !== true) {
        logMessage("設定取得エラー: " . ($data['message'] ?? 'Unknown error'), 'ERROR');
        return null;
    }
    
    // 読み込んだデータをログに記録
    logMessage("設定読み込み成功: " . json_encode($data['settings'], JSON_UNESCAPED_UNICODE));
    
    return $data['settings'];
}

// 営業時間設定をadminsetting_registrer.phpに保存する関数
function saveOpeningHours($settings) {
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/adminsetting_registrer.php?section=open_close';
    
    logMessage("営業時間設定保存 URL: " . $url);
    
    // 保存する前に値を確認・フォーマット
    if (isset($settings['Restrict individual'])) {
        // 文字列の'true'/'false'ではなく、Boolean値として保存
        $settings['Restrict individual'] = ($settings['Restrict individual'] === 'true');
    }
    
    logMessage("保存データ: " . json_encode($settings, JSON_UNESCAPED_UNICODE));
    
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($settings));
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($curl);
    
    if ($response === false) {
        logMessage("設定保存に失敗: " . curl_error($curl), 'ERROR');
        curl_close($curl);
        return false;
    }
    
    curl_close($curl);
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("JSONデコードエラー: " . json_last_error_msg(), 'ERROR');
        return false;
    }
    
    if (!isset($data['success']) || $data['success'] !== true) {
        logMessage("設定保存エラー: " . ($data['message'] ?? 'Unknown error'), 'ERROR');
        return false;
    }
    
    return true;
}

// ユーザー認証情報を読み込み
$userAuthFile = $rootPath . '/admin/user.json';
$users = [];

if (file_exists($userAuthFile)) {
    $jsonContent = file_get_contents($userAuthFile);
    $authData = json_decode($jsonContent, true);
    if (isset($authData['user'])) {
        $users = $authData['user'];
    }
} else {
    // ユーザーファイルが存在しない場合はデフォルト作成
    $defaultUsers = [
        'user' => [
            'fabula' => 'fg12345@',
            'admin' => 'admin12345@'
        ]
    ];
    file_put_contents($userAuthFile, json_encode($defaultUsers, JSON_PRETTY_PRINT));
    $users = $defaultUsers['user'];
    logMessage("ユーザー認証ファイルが見つからないため、デフォルトユーザーで作成しました", 'WARNING');
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
        logMessage("ユーザー '{$username}' がログインしました");
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'ユーザー名またはパスワードが正しくありません';
        logMessage("ログイン失敗: ユーザー '{$username}'", 'WARNING');
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

// アクション処理（ログイン済みの場合のみ）
$actionMessage = '';
$actionError = '';

// 営業時間設定を取得
$openingHoursSettings = loadAdminSettings('open_close');
logMessage("読み込んだ設定データ: " . json_encode($openingHoursSettings, JSON_UNESCAPED_UNICODE), 'DEBUG');

// 設定が正しく読み込めない場合、直接JSONファイルを読む（エラー回避用）
if (!$openingHoursSettings || !isset($openingHoursSettings['default_open'])) {
    logMessage("adminsetting_registrer.phpからの読み込みに失敗。直接JSONファイルを読み込みます", 'WARNING');
    $settingsPath = $rootPath . '/admin/adminpagesetting/adminsetting.json';
    
    if (file_exists($settingsPath)) {
        $jsonContent = file_get_contents($settingsPath);
        $allSettings = json_decode($jsonContent, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($allSettings['open_close'])) {
            $openingHoursSettings = $allSettings['open_close'];
            logMessage("JSONファイルから直接読み込みました: " . json_encode($openingHoursSettings, JSON_UNESCAPED_UNICODE));
        } else {
            logMessage("JSONファイルの解析に失敗しました: " . json_last_error_msg(), 'ERROR');
        }
    } else {
        logMessage("設定ファイルが見つかりません: " . $settingsPath, 'ERROR');
    }
}

// それでも読み込めない場合はデフォルト値を使用
if (!$openingHoursSettings || !isset($openingHoursSettings['default_open'])) {
    $openingHoursSettings = [
        'default_open' => '10:00',
        'default_close' => '22:00',
        'interval' => [],
        'Days off' => [],
        'Restrict individual' => 'true'
    ];
    logMessage("デフォルト営業時間設定を使用します", 'WARNING');
}

if ($isLoggedIn) {
    // 営業時間設定更新処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_opening_hours') {
        try {
            // POSTデータから営業時間設定を取得
            $defaultOpen = $_POST['default_open'] ?? '10:00';
            $defaultClose = $_POST['default_close'] ?? '22:00';
            $restrictIndividual = isset($_POST['restrict_individual']) ? 'true' : 'false';
            
            // 入力値のバリデーション
            if (empty($defaultOpen)) {
                $defaultOpen = '10:00';
            }
            if (empty($defaultClose)) {
                $defaultClose = '22:00';
            }
            
            // 休業日設定
            $daysOff = [];
            $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($weekdays as $day) {
                if (isset($_POST['day_off_' . strtolower($day)])) {
                    $daysOff[] = $day;
                }
            }
            
            // 休憩時間設定
            $intervals = [];
            if (isset($_POST['interval_name']) && is_array($_POST['interval_name'])) {
                foreach ($_POST['interval_name'] as $index => $name) {
                    if (!empty($name) && isset($_POST['interval_start'][$index]) && isset($_POST['interval_end'][$index])) {
                        $start = $_POST['interval_start'][$index];
                        $end = $_POST['interval_end'][$index];
                        if (!empty($start) && !empty($end)) {
                            $intervals[$name] = [$start, $end];
                        }
                    }
                }
            }
            
            // 既存の interval メタ情報を保持
            $intervalMeta = [];
            if (isset($openingHoursSettings['interval']['setting_name'])) {
                $intervalMeta['setting_name'] = $openingHoursSettings['interval']['setting_name'];
            }
            if (isset($openingHoursSettings['interval']['description'])) {
                $intervalMeta['description'] = $openingHoursSettings['interval']['description'];
            }

            // 設定を更新
            $openingHoursSettings = [
                'default_open' => $defaultOpen,
                'default_close' => $defaultClose,
                'interval' => $intervalMeta + ['interval' => $intervals],
                'Days off' => $daysOff,
                'Restrict individual' => $restrictIndividual
            ];
            
            logMessage("営業時間設定更新処理を開始します: " . json_encode($openingHoursSettings, JSON_UNESCAPED_UNICODE));
            
            // 設定を保存
            if (saveOpeningHours($openingHoursSettings)) {
                $actionMessage = '営業時間設定を更新しました';
                logMessage("営業時間設定が更新されました");
            } else {
                throw new Exception("営業時間設定の保存に失敗しました");
            }
            
        } catch (Exception $e) {
            $actionError = '営業時間設定更新エラー: ' . $e->getMessage();
            logMessage("営業時間設定更新エラー: " . $e->getMessage(), 'ERROR');
        }
    }

    // カテゴリ更新処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_categories') {
        try {
            // トランザクション開始
            $db->beginTransaction();
            
            // カテゴリIDの配列
            $categoryIds = $_POST['category_id'] ?? [];
            $displayOrders = $_POST['display_order'] ?? [];
            $lastOrderTimes = $_POST['last_order_time'] ?? [];
            $openOrderTimes = $_POST['open_order_time'] ?? [];
            $defaultOrderTimes = $_POST['default_order_time'] ?? [];
            
            // カテゴリの制限設定に基づいて営業時間を制限するかどうか
            $restrictIndividual = $openingHoursSettings['Restrict individual'] === 'true';
            $defaultOpen = $openingHoursSettings['default_open'] ?? '10:00';
            $defaultClose = $openingHoursSettings['default_close'] ?? '22:00';
            
            foreach ($categoryIds as $index => $categoryId) {
                $displayOrder = isset($displayOrders[$index]) ? (int)$displayOrders[$index] : ($index + 1);
                $isActive = isset($_POST['is_active_' . $categoryId]) ? 1 : 0;
                $lastOrderTime = $lastOrderTimes[$index] ?: null;
                $openOrderTime = $openOrderTimes[$index] ?: null;
                $defaultOrderTime = isset($defaultOrderTimes[$index]) ? 1 : 0;
                
                // 個別設定制限がオンで、かつデフォルト営業時間を使用しない場合
                if ($restrictIndividual && $defaultOrderTime == 0) {
                    // 営業開始時間と終了時間をデフォルト範囲内に制限
                    if ($openOrderTime && $lastOrderTime) {
                        $openTime = DateTime::createFromFormat('H:i', $openOrderTime);
                        $closeTime = DateTime::createFromFormat('H:i', $lastOrderTime);
                        $defaultOpenTime = DateTime::createFromFormat('H:i', $defaultOpen);
                        $defaultCloseTime = DateTime::createFromFormat('H:i', $defaultClose);
                        
                        if ($openTime && $closeTime && $defaultOpenTime && $defaultCloseTime) {
                            // 開始時間がデフォルト開始時間より早い場合
                            if ($openTime < $defaultOpenTime) {
                                $openOrderTime = $defaultOpen;
                            }
                            
                            // 終了時間がデフォルト終了時間より遅い場合
                            if ($closeTime > $defaultCloseTime) {
                                $lastOrderTime = $defaultClose;
                            }
                        }
                    }
                }
                
                // カテゴリ更新
                $result = $db->execute(
                    "UPDATE category_descripter SET 
                        display_order = ?, 
                        is_active = ?, 
                        last_order_time = ?, 
                        open_order_time = ?,
                        default_order_time = ?,
                        updated_at = NOW() 
                    WHERE category_id = ?",
                    [$displayOrder, $isActive, $lastOrderTime, $openOrderTime, $defaultOrderTime, $categoryId]
                );
                
                if (!$result) {
                    throw new Exception("カテゴリID '{$categoryId}' の更新に失敗しました");
                }
            }
            
            // トランザクションコミット
            $db->commit();
            $actionMessage = 'カテゴリ設定を更新しました';
            logMessage("カテゴリ設定が更新されました: " . count($categoryIds) . "件");
            
        } catch (Exception $e) {
            // エラー発生時はロールバック
            $db->rollback();
            $actionError = 'カテゴリ更新エラー: ' . $e->getMessage();
            logMessage("カテゴリ更新エラー: " . $e->getMessage(), 'ERROR');
        }
    }
    
    // 同期実行処理
    if (isset($_GET['action']) && $_GET['action'] === 'sync') {
        try {
            // 同期スクリプトのパス
            $syncScriptPath = $rootPath . '/api/sync/sync_categories.php';
            
            if (file_exists($syncScriptPath)) {
                // スクリプトの実行
                ob_start();
                include $syncScriptPath;
                $output = ob_get_clean();
                
                // 実行後のメッセージ表示
                $actionMessage = 'カテゴリ同期を実行しました。最新データを反映するにはページを更新してください。';
                logMessage("カテゴリ同期が実行されました: " . $currentUser . " によって手動実行");
            } else {
                $actionError = '同期スクリプトが見つかりません: ' . $syncScriptPath;
                logMessage("同期スクリプトが見つかりません: " . $syncScriptPath, 'ERROR');
            }
        } catch (Exception $e) {
            $actionError = '同期実行エラー: ' . $e->getMessage();
            logMessage("同期実行エラー: " . $e->getMessage(), 'ERROR');
        }
    }
}

// カテゴリデータを取得
$categories = [];
if ($isLoggedIn) {
    try {
        $categories = $db->select(
            "SELECT * FROM category_descripter WHERE presence = 1 ORDER BY display_order, category_name"
        );
        
        // ログにpresence=0の商品をリストに表示しない変更を記録
        logMessage("カテゴリリスト取得クエリにpresence=1の条件を追加しました。presence=0の商品はリストに表示されなくなります。", 'INFO');
    } catch (Exception $e) {
        $actionError = 'カテゴリデータ取得エラー: ' . $e->getMessage();
        logMessage("カテゴリデータ取得エラー: " . $e->getMessage(), 'ERROR');
    }
}

// 同期ステータスを取得
$syncStatus = null;
if ($isLoggedIn) {
    try {
        $syncStatus = $db->selectOne(
            "SELECT * FROM sync_status WHERE provider = ? AND table_name = ? ORDER BY last_sync_time DESC LIMIT 1",
            ['square', 'category_descripter']
        );
    } catch (Exception $e) {
        logMessage("同期ステータス取得エラー: " . $e->getMessage(), 'WARNING');
    }
}

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カテゴリ管理 - FG Square</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="js/sortable_manager.js"></script>
    <style>
        /* 追加のスタイル */
        .product-row {
            cursor: move;
        }
        
        .product-row.ui-sortable-helper {
            background-color: #e9ecef;
        }
        
        .operating-hours-card {
            margin-bottom: 20px;
        }
        
        .operating-hours-card .card-body {
            padding: 20px;
            position: relative;
            overflow: visible;
        }
        
        .interval-container {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        
        .interval-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .add-interval-btn {
            margin-top: 10px;
        }
        
        .delete-interval-btn {
            cursor: pointer;
            color: #dc3545;
            font-size: 1.2rem;
        }
        
        .days-off-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 10px;
            max-width: 100%;
            box-sizing: border-box;
            padding-left: 50px;
        }
        
        .day-off-item {
            display: flex;
            align-items: center;
            gap: 5px;
            width: 85px;
            margin: 0 10px 5px 0;
        }
        
        .day-off-item label {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60px;
        }
        
        .time-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn): ?>
        <!-- admin_header.php がログインフォームを出力するためここでは何も表示しません -->
        <?php else: ?>
        
        <!-- アクションメッセージ -->
        <?php if ($actionMessage): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($actionMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($actionError): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($actionError); ?>
            </div>
        <?php endif; ?>
        
        <!-- 営業時間設定カード -->
        <div class="card operating-hours-card">
            <div class="card-header">
                <h2>営業時間設定</h2>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_opening_hours">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="default-open" class="form-label">デフォルト営業開始時間</label>
                                <?php
                                // 開始時間のフォーマット調整
                                $defaultOpenValue = $openingHoursSettings['default_open'] ?? '10:00';
                                if (preg_match('/^(\d{1}):(\d{2})$/', $defaultOpenValue, $matches)) {
                                    $defaultOpenValue = sprintf('%02d:%02d', $matches[1], $matches[2]);
                                }
                                ?>
                                <input type="time" class="form-control" id="default-open" name="default_open" value="<?php echo htmlspecialchars($defaultOpenValue); ?>">
                                <div class="form-text">全カテゴリのデフォルト営業開始時間</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="default-close" class="form-label">デフォルト営業終了時間</label>
                                <?php
                                // 終了時間が「1:00」などの形式の場合、HTMLのtime入力用に「01:00」形式に変換
                                $defaultCloseValue = $openingHoursSettings['default_close'] ?? '22:00';
                                if (preg_match('/^(\d):(\d\d)$/', $defaultCloseValue, $matches)) {
                                    $defaultCloseValue = sprintf('%02d:%02d', $matches[1], $matches[2]);
                                }
                                ?>
                                <input type="time" class="form-control" id="default-close" name="default_close" value="<?php echo htmlspecialchars($defaultCloseValue); ?>">
                                <div class="form-text">全カテゴリのデフォルト営業終了時間（翌日の場合は24時間表記で入力）</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="restrict-individual" name="restrict_individual" <?php echo ($openingHoursSettings['Restrict individual'] === true || $openingHoursSettings['Restrict individual'] === 'true') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="restrict-individual">
                                個別営業時間をデフォルト営業時間内に制限する
                            </label>
                        </div>
                        <div class="form-text">オンにすると、各カテゴリの個別営業時間はデフォルト営業時間の範囲内に制限されます</div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label">休業日設定</label>
                        <div class="days-off-container">
                            <?php
                            $daysOff = $openingHoursSettings['Days off'] ?? [];
                            $weekdays = [
                                'monday' => '月曜日',
                                'tuesday' => '火曜日',
                                'wednesday' => '水曜日',
                                'thursday' => '木曜日',
                                'friday' => '金曜日',
                                'saturday' => '土曜日',
                                'sunday' => '日曜日'
                            ];
                            
                            foreach ($weekdays as $day => $label):
                                $isOff = in_array(ucfirst($day), $daysOff);
                            ?>
                            <div class="day-off-item">
                                <input class="form-check-input" type="checkbox" id="day-off-<?php echo $day; ?>" name="day_off_<?php echo $day; ?>" <?php echo $isOff ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="day-off-<?php echo $day; ?>"><?php echo $label; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">休憩時間設定</label>
                        <div id="intervals-container">
                            <?php
                            $intervals = $openingHoursSettings['interval'] ?? [];
                            // ネストされている場合は interval.interval から取得
                            if (isset($intervals['interval']) && is_array($intervals['interval'])) {
                                $intervals = $intervals['interval'];
                            }
                            if (empty($intervals)):
                            ?>
                            <div class="interval-container">
                                <div class="interval-controls">
                                    <input type="text" class="form-control" name="interval_name[]" placeholder="休憩名（例: 昼休み）">
                                    <div class="time-group">
                                        <input type="time" class="form-control" name="interval_start[]" placeholder="開始時間">
                                        <span>～</span>
                                        <input type="time" class="form-control" name="interval_end[]" placeholder="終了時間">
                                    </div>
                                    <i class="bi bi-trash delete-interval-btn"></i>
                                </div>
                            </div>
                            <?php else: 
                                foreach ($intervals as $name => $times):
                            ?>
                            <div class="interval-container">
                                <div class="interval-controls">
                                    <input type="text" class="form-control" name="interval_name[]" value="<?php echo htmlspecialchars($name); ?>" placeholder="休憩名">
                                    <div class="time-group">
                                        <input type="time" class="form-control" name="interval_start[]" value="<?php echo htmlspecialchars($times[0]); ?>" placeholder="開始時間">
                                        <span>～</span>
                                        <input type="time" class="form-control" name="interval_end[]" value="<?php echo htmlspecialchars($times[1]); ?>" placeholder="終了時間">
                                    </div>
                                    <i class="bi bi-trash delete-interval-btn"></i>
                                </div>
                            </div>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                        <button type="button" class="btn btn-outline-secondary add-interval-btn">
                            <i class="bi bi-plus-circle"></i> 休憩時間を追加
                        </button>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">営業時間設定を保存</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- 同期ステータス -->
        <div class="card">
            <div class="card-header">
                同期ステータス
            </div>
            <div class="card-body">
                <?php if ($syncStatus): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>最終同期日時:</strong> <?php echo htmlspecialchars($syncStatus['last_sync_time']); ?></p>
                        <p>
                            <strong>ステータス:</strong> 
                            <span class="status-badge <?php echo $syncStatus['status'] === 'success' ? 'success' : ($syncStatus['status'] === 'warning' ? 'warning' : 'error'); ?>">
                                <?php echo htmlspecialchars($syncStatus['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <?php
                        $details = json_decode($syncStatus['details'], true);
                        if ($details):
                        ?>
                        <p><strong>追加:</strong> <?php echo isset($details['created']) ? $details['created'] : 0; ?></p>
                        <p><strong>更新:</strong> <?php echo isset($details['updated']) ? $details['updated'] : 0; ?></p>
                        <p><strong>スキップ:</strong> <?php echo isset($details['skipped']) ? $details['skipped'] : 0; ?></p>
                        <p><strong>エラー:</strong> <?php echo isset($details['errors']) ? $details['errors'] : 0; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <p>同期ステータスが見つかりません。</p>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <strong>注意:</strong> 商品データ取得時にカテゴリリストは更新されます。
                </div>
            </div>
        </div>
        
        <!-- カテゴリ一覧 -->
        <form method="post">
            <input type="hidden" name="action" value="update_categories">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>カテゴリ設定</h2>
                <div>
                    <button type="submit" class="btn btn-success">変更を保存</button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-nowrap">ID<br><small>システムID</small></th>
                            <th class="text-nowrap">カテゴリID<br><small>Square内部ID</small></th>
                            <th class="text-nowrap">カテゴリ名<br><small>表示名称</small></th>
                            <th class="text-nowrap">表示順<br><small>ドラッグで並べ替え</small></th>
                            <th class="text-nowrap">有効<br><small>表示/非表示の切替</small></th>
                            <th class="text-nowrap">デフォルト<br><small>営業時間使用</small></th>
                            <th class="text-nowrap">営業開始<br><small>カテゴリ別開始時間</small></th>
                            <th class="text-nowrap">ラストオーダー<br><small>カテゴリ別締切時間</small></th>
                            <th class="text-nowrap">最終更新<br><small>設定変更日時</small></th>
                        </tr>
                    </thead>
                    <tbody id="sortable-categories">
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="9" class="text-center">カテゴリがありません。同期を実行してください。</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($categories as $index => $category): ?>
                        <tr class="product-row" data-id="<?php echo htmlspecialchars($category['id']); ?>">
                            <td><?php echo htmlspecialchars($category['id']); ?></td>
                            <td>
                                <?php echo htmlspecialchars(substr($category['category_id'], 0, 10) . '...'); ?>
                                <input type="hidden" name="category_id[]" value="<?php echo htmlspecialchars($category['category_id']); ?>">
                            </td>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td>
                                <input type="hidden" class="form-control form-control-sm" name="display_order[]" value="<?php echo htmlspecialchars($category['display_order']); ?>">
                                <span class="display-order-number"><?php echo htmlspecialchars($category['display_order']); ?></span>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active_<?php echo htmlspecialchars($category['category_id']); ?>" value="1" <?php echo $category['is_active'] ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input default-time-checkbox" type="checkbox" name="default_order_time[]" value="1" <?php echo ($category['default_order_time'] ?? 1) ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm" name="open_order_time[]" value="<?php echo htmlspecialchars($category['open_order_time'] ?? ''); ?>">
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm" name="last_order_time[]" value="<?php echo htmlspecialchars($category['last_order_time'] ?? ''); ?>">
                            </td>
                            <td><?php echo htmlspecialchars($category['updated_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-success">変更を保存</button>
            </div>
        </form>
        
        <?php endif; ?>
    </div>
    
    <script src="js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // 休憩時間追加処理
            $('.add-interval-btn').on('click', function() {
                const newInterval = `
                    <div class="interval-container">
                        <div class="interval-controls">
                            <input type="text" class="form-control" name="interval_name[]" placeholder="休憩名（例: 昼休み）">
                            <div class="time-group">
                                <input type="time" class="form-control" name="interval_start[]" placeholder="開始時間">
                                <span>～</span>
                                <input type="time" class="form-control" name="interval_end[]" placeholder="終了時間">
                            </div>
                            <i class="bi bi-trash delete-interval-btn"></i>
                        </div>
                    </div>
                `;
                $('#intervals-container').append(newInterval);
            });
            
            // 休憩時間削除処理（動的に追加された要素にも対応）
            $(document).on('click', '.delete-interval-btn', function() {
                $(this).closest('.interval-container').remove();
            });
            
            // --- 並び替えは外部 JS に移譲 ---
            if (window.SortableManager && typeof SortableManager.initCategorySort === 'function') {
                SortableManager.initCategorySort();
            }
            
            // デフォルト営業時間のチェック状態によって入力フィールドを活性化/非活性化
            $('.default-time-checkbox').on('change', function() {
                const row = $(this).closest('tr');
                const timeInputs = row.find('input[type="time"]');
                
                if ($(this).is(':checked')) {
                    // デフォルト営業時間を使用する場合は入力欄を薄くする
                    timeInputs.addClass('text-muted');
                } else {
                    // 使用しない場合は通常表示
                    timeInputs.removeClass('text-muted');
                }
            });
            
            // 初期表示時にもチェック状態を反映
            $('.default-time-checkbox').trigger('change');
        });
    </script>
<?php require_once __DIR__ . '/inc/admin_footer.php'; ?> 