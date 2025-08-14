<?php
/**
 * Kitchen Monitor Main Interface
 * 
 * Main display interface for the Mobes Kitchen Monitor system
 */

require_once __DIR__ . '/includes/functions.php';

// Initialize kitchen monitor
$kitchen = new KitchenMonitorFunctions();

// Authenticate access
if (!$kitchen->authenticateKitchenAccess()) {
    // Redirect to login if authentication is required
    header('Location: login.php');
    exit();
}

// Get configuration
$config = include __DIR__ . '/includes/config.php';

// Get CSRF token
session_start();
$csrfToken = $kitchen->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Mobes Kitchen Monitor</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/monitor.css">
    <link rel="stylesheet" href="css/tablet.css">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Favicon -->
    <link rel="icon" href="ASSET1.svg" type="image/svg+xml">
    
    <!-- Meta tags for kiosk mode -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-fullscreen">
    
    <!-- Preload sound file -->
    <link rel="preload" href="js/sounds/order-chime.mp3" as="audio">
</head>
<body class="<?= $config['kiosk_mode'] ? 'kiosk-mode' : '' ?>">
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-title">
                <img src="../admin/images/mobes_logo.svg" alt="Mobes" class="header-logo">
            </div>
            
            <div class="header-stats">
                <div class="column-view-controls">
                    <button class="view-toggle" id="view-ordered" data-view="ordered" title="注文済みのみ">
                        <i data-lucide="clock" class="icon icon--md"></i>
                    </button>
                    <button class="view-toggle" id="view-ready" data-view="ready" title="調理済みのみ">
                        <i data-lucide="chef-hat" class="icon icon--md"></i>
                    </button>
                    <button class="view-toggle" id="view-both" data-view="both" title="両列表示">
                        <i data-lucide="columns" class="icon icon--md"></i>
                    </button>
                </div>
                <div class="connection-status">
                    <span class="connection-indicator" id="connection-indicator"></span>
                    <span>最終更新: <span id="last-update">--:--:--</span></span>
                </div>
                <button class="audio-toggle" id="audio-toggle" title="音声通知の切り替え">
                    <i id="audio-icon" data-lucide="volume-2" class="icon icon--md"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Controls -->
    <div class="controls">
        <div class="controls-left">
            <button class="btn btn-toggle" id="show-completed-btn" data-active="false">
                完了済み表示
            </button>
            <button class="btn btn-view-toggle" id="view-switch-btn" data-view="ordered">
                <i data-lucide="eye" class="icon icon--sm"></i>
                <span>表示切替</span>
            </button>
            <button class="btn btn-primary" id="refresh-btn">
                <i data-lucide="refresh-cw" class="icon icon--md"></i>
                <span>更新</span>
            </button>
        </div>
        
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Loading State -->
        <div class="loading" id="loading">
            <div class="loading-spinner"></div>
            <span>注文データを読み込み中...</span>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state" style="display: none;">
            <h2><i data-lucide="sparkles" class="icon icon--lg" style="margin-right: 8px;"></i> 現在アクティブな注文はありません</h2>
            <p>新しい注文が入るとここに表示されます</p>
        </div>

        <!-- Orders Columns -->
        <div class="orders-columns">
            <div class="orders-column" id="ordered-column">
                <div class="column-header">
                    <h3><i data-lucide="clock" class="icon icon--md"></i> 注文済み</h3>
                    <div class="column-header-right">
                        <span class="column-count" id="ordered-count">0</span>
                    </div>
                </div>
                <div class="orders-grid" id="ordered-grid">
                    <!-- Ordered items will be inserted here -->
                </div>
            </div>
            <div class="orders-column" id="ready-column">
                <div class="column-header">
                    <h3><i data-lucide="chef-hat" class="icon icon--md"></i> 調理済み</h3>
                    <div class="column-header-right">
                        <button class="btn btn-room-group" id="room-group-btn" style="display: none;">
                            <i data-lucide="building" class="icon icon--sm"></i>
                            部屋別まとめ
                        </button>
                        <span class="column-count" id="ready-count">0</span>
                    </div>
                </div>
                <div class="orders-grid" id="ready-grid">
                    <!-- Ready items will be inserted here -->
                </div>
            </div>
        </div>
    </main>

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <!-- New Order Notification Bar -->
    <div class="new-order-bar" id="new-order-bar" style="display: none;">
        <div class="new-order-content">
            <div class="new-order-icon">
                <i data-lucide="bell" class="icon icon--md"></i>
            </div>
            <div class="new-order-text">
                <span id="new-order-message">新しい注文が入りました</span>
            </div>
            <button class="new-order-close" id="new-order-close">
                <i data-lucide="x" class="icon icon--sm"></i>
            </button>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal" id="cancel-modal" style="display: none;">
        <div class="modal-content">
            <h3><i data-lucide="alert-triangle" class="icon icon--lg" style="margin-right: 8px; color: var(--color-cancelled);"></i> 注文キャンセル確認</h3>
            <p id="cancel-message">この注文をキャンセルしますか？</p>
            <p class="warning" style="color: var(--color-cancelled); font-size: 14px;">この操作は取り消せません。</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancel-modal-close">取り消し</button>
                <button class="btn btn-cancel" id="cancel-modal-confirm">キャンセル実行</button>
            </div>
        </div>
    </div>

    <!-- Completed Orders Modal -->
    <div class="modal" id="completed-modal" style="display: none;">
        <div class="modal-content modal-content--large">
            <div class="modal-header">
                <h3><i data-lucide="check-circle" class="icon icon--lg" style="margin-right: 8px; color: var(--color-delivered);"></i> 完了済み注文</h3>
                <button class="modal-close-btn" id="completed-modal-close">
                    <i data-lucide="x" class="icon icon--md"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="completed-stats">
                    <div class="stat-item">
                        <span>本日配達済み:</span>
                        <span class="stat-number" id="modal-delivered-count">-</span>
                        <span>件</span>
                    </div>
                    <div class="stat-item">
                        <span>本日キャンセル:</span>
                        <span class="stat-number" id="modal-cancelled-count">-</span>
                        <span>件</span>
                    </div>
                </div>
                <div class="completed-orders-container">
                    <div id="completed-loading" class="loading-small" style="display: none;">
                        <div class="loading-spinner"></div>
                        <span>完了済み注文を読み込み中...</span>
                    </div>
                    <div id="completed-empty" class="empty-state-small" style="display: none;">
                        <p>本日の完了済み注文はありません</p>
                    </div>
                    <div class="completed-orders-grid" id="completed-orders-grid">
                        <!-- Completed order cards will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Configuration -->
    <script>
        window.KITCHEN_MONITOR_CONFIG = {
            autoRefreshInterval: <?= $config['auto_refresh_interval'] ?>,
            audioEnabled: <?= $config['notification']['sound_enabled'] ? 'true' : 'false' ?>,
            chimeVolume: <?= $config['notification']['chime_volume'] ?>,
            csrfToken: '<?= $csrfToken ?>',
            apiBaseUrl: './api/',
            statusColors: <?= json_encode($config['status_colors']) ?>,
            priorityColors: <?= json_encode($config['priority_colors']) ?>,
            kioskMode: <?= $config['kiosk_mode'] ? 'true' : 'false' ?>
        };
    </script>

    <!-- JavaScript -->
    <script src="js/monitor.js"></script>
    <script src="js/status-update.js"></script>
    <script src="js/notifications.js"></script>

    <!-- Initialize when page loads -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Initialize kitchen monitor
            if (typeof KitchenMonitor !== 'undefined') {
                window.kitchenMonitor = new KitchenMonitor();
                window.kitchenMonitor.init();
            }

            // Enable kiosk mode if configured
            if (window.KITCHEN_MONITOR_CONFIG.kioskMode) {
                // Disable context menu
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                });

                // Disable text selection
                document.addEventListener('selectstart', function(e) {
                    e.preventDefault();
                });

                // Disable drag and drop
                document.addEventListener('dragstart', function(e) {
                    e.preventDefault();
                });

                // Prevent double-tap zoom
                let lastTouchEnd = 0;
                document.addEventListener('touchend', function(event) {
                    const now = (new Date()).getTime();
                    if (now - lastTouchEnd <= 300) {
                        event.preventDefault();
                    }
                    lastTouchEnd = now;
                }, false);

                // Request fullscreen on tablet
                if (window.screen && window.screen.width >= 768) {
                    setTimeout(() => {
                        if (document.documentElement.requestFullscreen) {
                            document.documentElement.requestFullscreen().catch(console.log);
                        }
                    }, 1000);
                }
            }
        });
    </script>

    <!-- Hidden audio element for chime -->
    <audio id="order-chime" preload="auto">
        <source src="js/sounds/order-chime.mp3" type="audio/mpeg">
    </audio>
</body>
</html>