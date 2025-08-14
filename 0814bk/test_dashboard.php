<?php
require_once 'api/config/config.php';
require_once 'api/lib/Utils.php';
require_once 'api/lib/Database.php';

// 認証チェック
if (!isset($_POST['admin_key']) || $_POST['admin_key'] !== ADMIN_KEY) {
    if (!isset($_COOKIE['admin_session']) || $_COOKIE['admin_session'] !== hash('sha256', ADMIN_KEY)) {
        // 認証フォーム表示
        include 'test/views/auth_form.php';
        exit;
    }
}

// 認証成功時はクッキーを設定
if (isset($_POST['admin_key']) && $_POST['admin_key'] === ADMIN_KEY) {
    setcookie('admin_session', hash('sha256', ADMIN_KEY), time() + 3600, '/');
}

// テストダッシュボード表示
$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    case 'unittest':
        include 'test/views/unit_test.php';
        break;
    case 'integrationtest':
        include 'test/views/integration_test.php';
        break;
    case 'e2etest':
        include 'test/views/e2e_test.php';
        break;
    case 'logs':
        include 'test/views/logs_viewer.php';
        break;
    case 'database':
        include 'test/views/database_viewer.php';
        break;
    case 'square':
        include 'test/views/square_debug.php';
        break;
    case 'room_tickets':
        include 'test/views/room_tickets.php';
        break;
    default:
        include 'test/views/dashboard.php';
        break;
}
?> 