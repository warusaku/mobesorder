<?php
/**
 * 商品表示順管理ユーティリティ
 * 
 * このスクリプトは、商品の表示順序と表示/非表示設定を管理するための
 * 管理者向けインターフェースを提供します。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// セッション開始
session_start();

// ログ関数
function logMessage($message, $level = 'INFO') {
    Utils::log($message, $level, 'ProductDisplayManager');
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

// HTMLヘッダー
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品表示設定 - FG Square</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        .disabled-tab {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .category-tabs {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            border-bottom: 1px solid #dee2e6;
        }
        
        .category-tab {
            padding: 10px 15px;
            margin-right: 5px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
        }
        
        .category-tab.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .product-list {
            width: 100%;
            border-collapse: collapse;
        }
        
        .product-list th, .product-list td {
            padding: 10px;
            border: 1px solid #dee2e6;
        }
        
        .product-list thead {
            background-color: #f8f9fa;
        }
        
        .product-row {
            cursor: move;
        }
        
        .product-row.ui-sortable-helper {
            background-color: #e9ecef;
        }
        
        .product-row.inactive {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .loading-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 50px 0;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #007bff;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>商品表示設定</h1>
            <?php if ($isLoggedIn): ?>
            <div class="user-info">
                <span class="me-2">ユーザー: <?php echo htmlspecialchars($currentUser); ?></span>
                <a href="?logout=1" class="btn btn-sm btn-outline-secondary">ログアウト</a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$isLoggedIn): ?>
        <!-- ログインフォーム -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-form">
                    <div class="card-header">
                        管理者ログイン
                    </div>
                    <div class="card-body">
                        <?php if ($loginError): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($loginError); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">ユーザー名</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">パスワード</label>
                                <input type="password" class="form-control" id="password" name="password" required>
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
                <a class="nav-link active" href="product_display.php">商品表示設定</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../order/" target="_blank">注文画面</a>
            </li>
        </ul>
        
        <!-- メインコンテンツ -->
        <div class="main-content">
            <div class="action-buttons mb-4">
                <button id="loadProductsBtn" class="btn btn-primary">
                    <i class="bi bi-cloud-download"></i> 商品読み込み
                </button>
                <button id="saveSettingsBtn" class="btn btn-success" disabled>
                    <i class="bi bi-save"></i> 設定を更新
                </button>
            </div>
            
            <div id="content-area">
                <div class="alert alert-info">
                    「商品読み込み」ボタンをクリックして、カテゴリと商品データを読み込んでください。
                </div>
            </div>
        </div>
        
        <!-- JavaScript処理 -->
        <script>
            $(document).ready(function() {
                const contentArea = $('#content-area');
                const loadProductsBtn = $('#loadProductsBtn');
                const saveSettingsBtn = $('#saveSettingsBtn');
                
                let categories = [];
                let currentCategory = null;
                let productsData = {};
                
                // 商品読み込みボタンのイベント
                loadProductsBtn.on('click', function() {
                    loadCategories();
                });
                
                // 設定保存ボタンのイベント
                saveSettingsBtn.on('click', function() {
                    saveProductSettings();
                });
                
                // カテゴリデータ読み込み
                function loadCategories() {
                    contentArea.html(createLoadingIndicator());
                    saveSettingsBtn.prop('disabled', true);
                    
                    $.ajax({
                        url: '../api/admin/product_display.php?action=get_categories',
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.categories) {
                                categories = response.categories;
                                renderCategoryTabs();
                            } else {
                                showError('カテゴリデータの取得に失敗しました: ' + (response.message || '不明なエラー'));
                            }
                        },
                        error: function(xhr, status, error) {
                            showError('サーバーとの通信に失敗しました: ' + error);
                        }
                    });
                }
                
                // カテゴリタブの表示
                function renderCategoryTabs() {
                    if (categories.length === 0) {
                        contentArea.html('<div class="alert alert-warning">カテゴリが見つかりませんでした。</div>');
                        return;
                    }
                    
                    let tabsHtml = '<div class="category-tabs">';
                    
                    categories.forEach(function(category, index) {
                        const isActive = index === 0;
                        const isDisabled = category.is_active !== 1;
                        
                        tabsHtml += `
                            <div class="category-tab ${isActive ? 'active' : ''} ${isDisabled ? 'disabled-tab' : ''}"
                                data-category-id="${category.category_id}">
                                ${category.category_name} (${category.category_id})
                            </div>
                        `;
                        
                        if (isActive && !isDisabled) {
                            currentCategory = category.category_id;
                        }
                    });
                    
                    tabsHtml += '</div>';
                    
                    // 商品リスト表示エリア
                    tabsHtml += '<div id="product-list-container"></div>';
                    
                    contentArea.html(tabsHtml);
                    
                    // タブクリックイベントを設定
                    $('.category-tab:not(.disabled-tab)').on('click', function() {
                        $('.category-tab').removeClass('active');
                        $(this).addClass('active');
                        
                        currentCategory = $(this).data('category-id');
                        loadProductsForCategory(currentCategory);
                    });
                    
                    // 最初のアクティブカテゴリの商品を読み込み
                    if (currentCategory) {
                        loadProductsForCategory(currentCategory);
                    }
                }
                
                // カテゴリの商品データ取得
                function loadProductsForCategory(categoryId) {
                    const container = $('#product-list-container');
                    container.html(createLoadingIndicator());
                    
                    $.ajax({
                        url: `../api/admin/product_display.php?action=get_products&category=${categoryId}`,
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.products) {
                                productsData[categoryId] = response.products;
                                renderProductList(categoryId, response.products);
                                saveSettingsBtn.prop('disabled', false);
                            } else {
                                container.html('<div class="alert alert-warning">商品データの取得に失敗しました: ' + (response.message || '不明なエラー') + '</div>');
                            }
                        },
                        error: function(xhr, status, error) {
                            container.html('<div class="alert alert-danger">サーバーとの通信に失敗しました: ' + error + '</div>');
                        }
                    });
                }
                
                // 商品リストの表示
                function renderProductList(categoryId, products) {
                    const container = $('#product-list-container');
                    
                    if (products.length === 0) {
                        container.html('<div class="alert alert-info">このカテゴリには商品がありません。</div>');
                        return;
                    }
                    
                    let tableHtml = `
                        <table class="product-list">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>商品名</th>
                                    <th>Square Item ID</th>
                                    <th>価格</th>
                                    <th>有効/無効</th>
                                    <th>更新日時</th>
                                    <th>表示</th>
                                </tr>
                            </thead>
                            <tbody id="sortable-products">
                    `;
                    
                    products.forEach(function(product, index) {
                        const isInactive = product.order_dsp === '0' || product.order_dsp === 0;
                        
                        tableHtml += `
                            <tr class="product-row ${isInactive ? 'inactive' : ''}" data-product-id="${product.id}">
                                <td>${product.id}</td>
                                <td>${product.name}</td>
                                <td>${product.square_item_id}</td>
                                <td>${product.price}</td>
                                <td>${product.is_active === '1' || product.is_active === 1 ? '有効' : '無効'}</td>
                                <td>${product.updated_at}</td>
                                <td>
                                    <input type="checkbox" class="display-checkbox" ${isInactive ? '' : 'checked'}>
                                </td>
                            </tr>
                        `;
                    });
                    
                    tableHtml += `
                            </tbody>
                        </table>
                        <p class="mt-3 text-muted">ドラッグ＆ドロップで商品の表示順を変更できます。チェックボックスで表示/非表示を設定できます。</p>
                    `;
                    
                    container.html(tableHtml);
                    
                    // ソート可能なテーブルを初期化
                    $('#sortable-products').sortable({
                        axis: 'y',
                        cursor: 'move',
                        update: function(event, ui) {
                            updateProductOrder();
                        }
                    });
                    
                    // チェックボックスの変更イベント
                    $('.display-checkbox').on('change', function() {
                        const row = $(this).closest('tr');
                        if ($(this).is(':checked')) {
                            row.removeClass('inactive');
                        } else {
                            row.addClass('inactive');
                        }
                    });
                    
                    // 初期の商品順序を設定
                    updateProductOrder();
                }
                
                // 商品の表示順を更新
                function updateProductOrder() {
                    const rows = $('#sortable-products tr');
                    
                    rows.each(function(index) {
                        const productId = $(this).data('product-id');
                        const sortOrder = index + 1; // 1から始まる序数
                        
                        // データモデルを更新
                        if (productsData[currentCategory]) {
                            const product = productsData[currentCategory].find(p => p.id == productId);
                            if (product) {
                                product.sort_order = sortOrder;
                            }
                        }
                        
                        // 行に順序番号を表示（オプション）
                        // $(this).find('.sort-indicator').text(sortOrder);
                    });
                }
                
                // 商品表示設定の保存
                function saveProductSettings() {
                    if (!currentCategory || !productsData[currentCategory]) {
                        showError('保存するデータがありません');
                        return;
                    }
                    
                    const products = [];
                    
                    // テーブルから現在の表示設定を取得
                    $('#sortable-products tr').each(function(index) {
                        const productId = $(this).data('product-id');
                        const sortOrder = index + 1;
                        const orderDsp = $(this).find('.display-checkbox').is(':checked') ? 1 : 0;
                        
                        products.push({
                            id: productId,
                            sort_order: sortOrder,
                            order_dsp: orderDsp
                        });
                    });
                    
                    // 保存処理中の表示
                    saveSettingsBtn.prop('disabled', true);
                    saveSettingsBtn.html('<i class="bi bi-hourglass-split"></i> 保存中...');
                    
                    // APIにデータを送信
                    $.ajax({
                        url: '../api/admin/product_display.php?action=update_settings',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ products: products }),
                        success: function(response) {
                            if (response.success) {
                                showSuccess('設定を更新しました（' + response.updated_count + '件）');
                            } else {
                                showError('設定の更新に失敗しました: ' + (response.message || '不明なエラー'));
                            }
                            
                            saveSettingsBtn.prop('disabled', false);
                            saveSettingsBtn.html('<i class="bi bi-save"></i> 設定を更新');
                        },
                        error: function(xhr, status, error) {
                            showError('サーバーとの通信に失敗しました: ' + error);
                            
                            saveSettingsBtn.prop('disabled', false);
                            saveSettingsBtn.html('<i class="bi bi-save"></i> 設定を更新');
                        }
                    });
                }
                
                // ユーティリティ関数
                function createLoadingIndicator() {
                    return '<div class="loading-indicator"><div class="spinner"></div></div>';
                }
                
                function showError(message) {
                    $('<div class="alert alert-danger">')
                        .text(message)
                        .prependTo('.main-content')
                        .delay(5000)
                        .fadeOut(function() {
                            $(this).remove();
                        });
                }
                
                function showSuccess(message) {
                    $('<div class="alert alert-success">')
                        .text(message)
                        .prependTo('.main-content')
                        .delay(3000)
                        .fadeOut(function() {
                            $(this).remove();
                        });
                }
            });
        </script>
        <?php endif; ?>
    </div>
</body>
</html> 