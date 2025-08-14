<?php
/**
 * RTSP_Reader Test Framework - Main Dashboard (Lolipop Server)
 * 
 * テストダッシュボードのメインページ
 */

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// インクルードパス設定
$includePath = __DIR__ . '/includes';
set_include_path(get_include_path() . PATH_SEPARATOR . $includePath);

// 共通ライブラリの読み込み
require_once 'test_logger.php';
require_once 'test_runner.php';
require_once 'db_analyzer.php';

// ロガーの初期化
$logFile = __DIR__ . '/logs/test_' . date('Y-m-d') . '.log';
$logger = new TestLogger($logFile);

// タイトル設定
$pageTitle = 'RTSP_Reader テストダッシュボード (Lolipopサーバー)';

// アクティブなタブ管理
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// データベース接続情報
$dbConfig = [
    'host' => 'mysql323.phy.lolipop.lan',
    'dbname' => 'LAA1207717-rtspreader',
    'username' => 'LAA1207717',
    'password' => 'mijeos12345'
];

// データベース接続を試行
$dbConnection = null;
$dbError = null;

try {
    $dsn = "mysql:host={$dbConfig['host']};charset=utf8mb4";
    $dbConnection = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $logger->info('データベース接続成功');
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $logger->error('データベース接続エラー', ['error' => $dbError]);
}

// テストモジュールの読み込み
$testModules = [];
$modulesDir = __DIR__ . '/modules';

if (is_dir($modulesDir)) {
    $moduleFiles = glob($modulesDir . '/*.php');
    foreach ($moduleFiles as $moduleFile) {
        require_once $moduleFile;
        $moduleName = basename($moduleFile, '.php');
        $className = str_replace('_', '', ucwords($moduleName, '_')) . 'Module';
        
        if (class_exists($className)) {
            $testModules[$moduleName] = new $className($logger, $dbConnection);
            $logger->info("モジュール読み込み: {$moduleName}");
        }
    }
}

// テストランナーの初期化
$testRunner = new TestRunner($logger);

// 各モジュールをテストランナーに登録
foreach ($testModules as $name => $module) {
    $testRunner->registerTestModule($name, $module);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/test-dashboard.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">RTSP_Reader テストフレームワーク</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">
                            <i class="fas fa-tachometer-alt"></i> ダッシュボード
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'api_tests' ? 'active' : ''; ?>" href="?tab=api_tests">
                            <i class="fas fa-server"></i> APIテスト
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'ui_tests' ? 'active' : ''; ?>" href="?tab=ui_tests">
                            <i class="fas fa-window-restore"></i> UIテスト
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'db_tests' ? 'active' : ''; ?>" href="?tab=db_tests">
                            <i class="fas fa-database"></i> DBテスト
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'e2e_tests' ? 'active' : ''; ?>" href="?tab=e2e_tests">
                            <i class="fas fa-exchange-alt"></i> E2Eテスト
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'logs' ? 'active' : ''; ?>" href="?tab=logs">
                            <i class="fas fa-clipboard-list"></i> ログ
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="fas fa-cloud"></i> Lolipopサーバー
                    </span>
                    <a href="http://192.168.3.57/dev/RTSPserver/test/" class="btn btn-outline-light btn-sm" target="_blank">
                        <i class="fas fa-external-link-alt"></i> ローカルテスト
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($dbError): ?>
        <div class="alert alert-danger">
            <strong>データベース接続エラー:</strong> <?php echo htmlspecialchars($dbError); ?>
        </div>
        <?php endif; ?>

        <?php
        // タブに応じたコンテンツをインクルード
        switch ($activeTab) {
            case 'api_tests':
                include 'tabs/api_tests.php';
                break;
            case 'ui_tests':
                include 'tabs/ui_tests.php';
                break;
            case 'db_tests':
                include 'tabs/db_tests.php';
                break;
            case 'e2e_tests':
                include 'tabs/e2e_tests.php';
                break;
            case 'logs':
                include 'tabs/logs.php';
                break;
            default:
                include 'tabs/dashboard.php';
                break;
        }
        ?>
    </div>

    <footer class="footer mt-5 py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">RTSP_Reader テストフレームワーク &copy; <?php echo date('Y'); ?></span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">サーバー時間: <?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="js/test-dashboard.js"></script>
</body>
</html> 
 
 
 
 