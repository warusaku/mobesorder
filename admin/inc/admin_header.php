<?php
// ============================================================
// 共通管理ヘッダ  admin/inc/admin_header.php
// ------------------------------------------------------------
// すべての管理ページで共通使用するログイン判定＋ナビ出力スクリプト
// ============================================================

// セッション開始（未開始の場合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ルートパス解決（fgsquare ディレクトリ）
$rootPath = realpath(__DIR__ . '/../..');

// 必要ライブラリ読み込み
require_once $rootPath . '/api/lib/Utils.php';
require_once __DIR__ . '/../lib/auth_helper.php';

// --------------------- ページデザイン設定読み込み ---------------------
if (!function_exists('loadAdminPageDesign')) {
    /**
     * adminsetting.json から pagedesign 設定を返す
     * auth_helper.php 経由ですでに loadSettings() が存在する想定
     */
    function loadAdminPageDesign(): array {
        if (function_exists('loadSettings')) {
            $settings = loadSettings();
            if ($settings && isset($settings['admin_setting']['adminpage_setting']['pagedesign'])) {
                return $settings['admin_setting']['adminpage_setting']['pagedesign'];
            }
        }
        return [];
    }
}

$pageDesign = loadAdminPageDesign();
$headerDesign = $pageDesign['header'] ?? [];
$footerDesign = $pageDesign['footer'] ?? [];

$headerColor          = $headerDesign['header_color'] ?? '#333333';
$headerLogo           = $headerDesign['header_logo'] ?? '';
$headerHeight         = $headerDesign['header_height'] ?? '60px';
$headerLogoHeight     = $headerDesign['header_logo_height'] ?? '40px';
$headerLogoWidth      = $headerDesign['header_logo_width']  ?? 'auto';
$headerTitle          = $headerDesign['header_title'] ?? $pageTitle;
$headerTitleColor     = $headerDesign['header_title_color'] ?? '#FFFFFF';
$headerTitleFontSize  = $headerDesign['header_title_font_size'] ?? '20px';
$headerTitleFontWeight= $headerDesign['header_title_font_weight'] ?? 'bold';
$headerTitleFontFamily= $headerDesign['header_title_font_family'] ?? 'Arial, sans-serif';
$headerTitleTextAlign = $headerDesign['header_title_text_align'] ?? 'left';
$headerTitleMarginLeft= $headerDesign['header_title_position_holizontal(origin_margin_toLofgo)'] ?? '15px';
$headerTitleBottom    = $headerDesign['header_title_position_vertical(origin_header_bottom)'] ?? '0';

// 簡易ログ関数（ヘッダー共通）
if (!function_exists('adminHeaderLog')) {
    function adminHeaderLog(string $message, string $level = 'INFO'): void
    {
        if (class_exists('Utils')) {
            Utils::log($message, $level, 'AdminHeader');
        }
    }
}

// 管理ユーザー一覧取得
$users = getAdminUsers();
if (empty($users)) {
    adminHeaderLog('adminsetting.json にユーザー設定がありません', 'ERROR');
}

// ------------------------------------------------------------
// 認証処理
// ------------------------------------------------------------
$loginError = '';

// ログアウト
if (isset($_GET['logout'])) {
    unset($_SESSION['auth_user'], $_SESSION['auth_token']);
    adminHeaderLog('ユーザーがログアウトしました', 'INFO');
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); // query 無しでリダイレクト
    exit;
}

// ログイン試行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (isset($users[$username]) && is_array($users[$username]) && $users[$username][0] === $password) {
        $_SESSION['auth_user']  = $username;
        $_SESSION['auth_token'] = $users[$username][1];
        adminHeaderLog("ユーザー '{$username}' がログインしました", 'INFO');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $loginError = 'ユーザー名またはパスワードが正しくありません';
    adminHeaderLog("ログイン失敗: ユーザー '{$username}'", 'WARNING');
}

// ログイン状態確認
$isLoggedIn = isset($_SESSION['auth_user'], $_SESSION['auth_token']) && array_key_exists($_SESSION['auth_user'], $users);

$currentScript = basename($_SERVER['PHP_SELF']);
$pageTitle     = $pageTitle ?? 'FG Square 管理';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - FG Square</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/logscan.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body{display:flex; flex-direction:column; min-height:100vh; margin:0;}
        .container{flex:1 0 auto;}

        .site-header{
            background-color: <?php echo $headerColor; ?>;
            height: <?php echo $headerHeight; ?>;
            display:flex; align-items:center; justify-content:space-between; padding:0 20px;
        }
        .site-header img{ height: <?php echo $headerLogoHeight; ?>; width: <?php echo $headerLogoWidth; ?>; }
        .site-header .site-title{ position:relative; bottom: <?php echo $headerTitleBottom; ?>; margin-left: <?php echo $headerTitleMarginLeft; ?>; color: <?php echo $headerTitleColor; ?>; font-size: <?php echo $headerTitleFontSize; ?>; font-weight: <?php echo $headerTitleFontWeight; ?>; font-family: <?php echo $headerTitleFontFamily; ?>; text-align: <?php echo $headerTitleTextAlign; ?>; }
        .user-info-header{ background-color:#FFFFFF; color:#000; padding:4px 8px; border-radius:4px; display:flex; align-items:center; gap:8px; }
        .user-info-header .btn{ padding:2px 8px; font-size:12px; }
        .site-footer{ background-color: <?php echo $footerDesign['footer_color'] ?? '#333333'; ?>; color: <?php echo $footerDesign['footer_text_color'] ?? '#FFFFFF'; ?>; text-align:center; padding:10px; font-size: <?php echo $footerDesign['footer_text_font_size'] ?? '12px'; ?>; font-family: <?php echo $footerDesign['footer_text_font_family'] ?? 'Arial, sans-serif'; ?>; margin-top:auto; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="d-flex align-items-center">
            <?php if($headerLogo): ?>
            <img src="<?php echo htmlspecialchars($headerLogo); ?>" alt="logo">
            <?php endif; ?>
            <span class="site-title ms-2"><?php echo htmlspecialchars($headerTitle); ?></span>
        </div>
        <?php if ($isLoggedIn): ?>
        <div class="user-info-header">
            <span>ユーザー: <?php echo htmlspecialchars($_SESSION['auth_user']); ?></span>
            <a href="?logout=1" class="btn btn-sm btn-outline-secondary">ログアウト</a>
        </div>
        <?php endif; ?>
    </header>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>

<?php if (!$isLoggedIn): ?>
        <!-- ログインフォーム -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-form">
                    <div class="card-header">管理者ログイン</div>
                    <div class="card-body">
                        <?php if ($loginError): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($loginError); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">ユーザー名</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">パスワード</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">ログイン</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
<?php else: ?>
        <!-- ナビゲーション -->
        <ul class="nav-pills">
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='index.php'                ? 'active' : ''; ?>" href="index.php">ダッシュボード</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='products_sync.php'       ? 'active' : ''; ?>" href="products_sync.php">商品同期</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='manage_categories.php'  ? 'active' : ''; ?>" href="manage_categories.php">カテゴリ管理</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='product_display_util.php'? 'active' : ''; ?>" href="product_display_util.php">商品表示設定</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='sales_monitor.php'       ? 'active' : ''; ?>" href="sales_monitor.php">リアルタイム運用データ</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='roomsetting.php'         ? 'active' : ''; ?>" href="roomsetting.php">部屋設定</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='global_Settings.php'    ? 'active' : ''; ?>" href="global_Settings.php">詳細設定</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='Lumos_Lite_Console.php' ? 'active' : ''; ?>" href="Lumos_Lite_Console.php">Lumos_lite_Console</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $currentScript==='Square_DB_Console.php' ? 'active' : ''; ?>" href="Square_DB_Console.php">SquareDB_console</a></li>
            <li class="nav-item"><a class="nav-link" href="../order/" target="_blank">注文画面</a></li>
        </ul>
<?php endif; ?>

<!-- ページ固有コンテンツはこの下に配置する --> 