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
// ラベル編集機能のインクルード
require_once __DIR__ . '/product_labelediter.php';

// ---------------- ログ関数 & ローテーション ----------------
if (!function_exists('logMessage')) {
    function logMessage($message, $level = 'INFO') {
        $logFile = realpath(__DIR__ . '/../logs') . '/product_display_util.log';
        // ログローテーション
        checkLogRotation($logFile);
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] [{$level}] {$message}\n", FILE_APPEND);
        if (class_exists('Utils')) {
            Utils::log($message, $level, 'ProductDisplayManager');
        }
    }

    function checkLogRotation($logFile) {
        if (!file_exists($logFile)) return;
        $max = 307200; // 300KB
        $size = filesize($logFile);
        if ($size <= $max) return;
        $keep = intval($max * 0.2); // 20% 残す
        $content = file_get_contents($logFile);
        $newContent = substr($content, -$keep);
        file_put_contents($logFile, "--- ログローテーション " . date('Y-m-d H:i:s') . " ---\n" . $newContent);
    }
}

// ---------- 共通ヘッダー導入 ----------
$pageTitle = '商品表示設定';
require_once __DIR__ . '/inc/admin_header.php';
// admin_header.php にて認証処理済み。未ログインの場合はログインフォーム表示済みにつき処理終了
if (!$isLoggedIn) {
    require_once __DIR__ . '/inc/admin_footer.php';
    return;
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

// presence=0の商品が非表示になるよう修正した内容をログに記録
logMessage("商品リスト表示時にpresence=0の商品を非表示にする条件を追加しました。", 'INFO');
logMessage("修正ファイル: /api/admin/product_display.php", 'INFO');
logMessage("修正内容: get_productsアクションのSQLクエリに「AND presence = 1」条件を追加", 'INFO');

// リファクタリングに関するログ
logMessage("ファイル構造をリファクタリングしました。ラベル管理機能を分離。", 'INFO');
logMessage("分離ファイル: product_labelediter.php", 'INFO');
logMessage("コードの冗長性を削減し、単一責務の原則に沿ってモジュール化しました。", 'INFO');

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
    <script src="js/sortable_manager.js"></script>
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
            background-color: #a9a9a9;
            color: #ffffff;
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

        .category-dropdown {
            display: none;
            width: 100%;
            margin-bottom: 20px;
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .list-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0;
        }

        @media (max-width: 700px) {
            .category-tabs {
                display: none;
            }
            .category-dropdown {
                display: block;
            }
        }
        
        /* 直リンク関連のスタイル */
        .copy-link-button {
            cursor: pointer;
            color: #007bff;
        }
        
        .copy-link-button:hover {
            color: #0056b3;
        }
        
        /* コピーモーダルのスタイル */
        .copy-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .copy-modal.active {
            display: flex !important;
        }
        
        .copy-modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 600px;
            position: relative;
            z-index: 1001;
        }
        
        .copy-modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            cursor: pointer;
        }
        
        .copy-url {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin: 10px 0;
            word-break: break-all;
        }
        
        /* ベースURL設定セクション */
        .baseurl-setting {
            margin: 15px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .baseurl-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        
        .baseurl-form label {
            font-weight: bold;
            margin-right: 10px;
        }
        
        .baseurl-form input {
            flex-grow: 1;
            min-width: 250px;
            padding: 5px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        /* ラベル選択のスタイル */
        .label-select option {
            padding: 5px;
        }
        
        /* モバイル対応 */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
            }
            
            .product-list th, .product-list td {
                padding: 5px;
                font-size: 0.9rem;
            }
            
            .label-select {
                max-width: 100px;
            }
        }
        
        <?php 
        // ラベル編集用のCSSをインクルード
        echo getLabelEditorCSS(); 
        ?>
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn): ?>
        <!-- admin_header.php がログインフォームを出力するためここでは何も表示しません -->
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
                <a class="nav-link active" href="product_display_util.php">商品表示設定</a>
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
        
        <!-- メインコンテンツ -->
        <div class="main-content">
            <div class="action-buttons mb-4">
                <button id="loadProductsBtn" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> 商品の再読み込み
                </button>
                
                <button id="manageLabelBtn" class="btn btn-info">
                    <i class="bi bi-tags"></i> ラベル管理
                </button>
            </div>
            
            <!-- 直リンクベースURL設定セクション -->
            <div class="baseurl-setting">
                <h4>直リンク設定</h4>
                <div class="baseurl-form">
                    <label for="base-url-input">モバイルオーダーのベースURL:</label>
                    <input type="text" id="base-url-input" value="https://test-mijeos.but.jp/fgsquare/order" placeholder="https://test-mijeos.but.jp/fgsquare/order">
                    <button id="save-baseurl-btn" class="btn btn-primary">
                        <i class="bi bi-save"></i> 更新
                    </button>
                </div>
            </div>
            
            <div id="content-area">
                <div class="loading-indicator">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
        
        <!-- コピーモーダル -->
        <div id="copy-modal" class="copy-modal">
            <div class="copy-modal-content">
                <span class="copy-modal-close">&times;</span>
                <h4 id="copy-modal-title"></h4>
                <p>このリンクを共有することで商品への直接リンクを提供できます。</p>
                <div id="copy-modal-url" class="copy-url"></div>
                <button id="close-copy-modal" class="btn btn-primary">閉じる</button>
            </div>
        </div>
        
        <?php 
        // ラベル管理モーダルを表示
        echo getLabelEditorHTML(); 
        ?>
        
        <!-- JavaScript処理 -->
        <script>
            $(document).ready(function() {
                const contentArea = $('#content-area');
                const loadProductsBtn = $('#loadProductsBtn');
                const copyModal = $('#copy-modal');
                const copyModalTitle = $('#copy-modal-title');
                const copyModalUrl = $('#copy-modal-url');
                const closeModalBtn = $('#close-copy-modal');
                const modalClose = $('.copy-modal-close');
                const baseUrlInput = $('#base-url-input');
                const saveBaseUrlBtn = $('#save-baseurl-btn');
                const manageLabelBtn = $('#manageLabelBtn');
                const labelModal = $('#label-modal');
                const editLabelModal = $('#edit-label-modal');
                const closeLabelModalBtn = $('#close-label-modal');
                const addLabelBtn = $('#add-label-btn');
                const cancelEditLabelBtn = $('#cancel-edit-label-btn');
                const updateLabelBtn = $('#update-label-btn');
                const deleteLabelBtn = $('#delete-label-btn');
                const labelListBody = $('#label-list-body');
                const newLabelText = $('#new-label-text');
                const newLabelColor = $('#new-label-color');
                const editLabelId = $('#edit-label-id');
                const editLabelText = $('#edit-label-text');
                const editLabelColor = $('#edit-label-color');
                
                let categories = [];
                let currentCategory = null;
                let productsData = {};
                let currentCategoryName = '';
                let baseUrl = 'https://test-mijeos.but.jp/fgsquare/order';
                let urlParam = '?item=';
                
                // 設定ファイルからベースURLを読み込み
                loadDirectLinkSettings();
                
                // 自動読み込み
                loadCategories();
                
                // 商品読み込みボタンのイベント
                loadProductsBtn.on('click', function() {
                    loadCategories();
                });
                
                // ベースURL保存ボタンのイベント
                saveBaseUrlBtn.on('click', function() {
                    const newBaseUrl = baseUrlInput.val().trim();
                    if (!newBaseUrl) {
                        showError('ベースURLを入力してください');
                        return;
                    }
                    
                    // 設定を保存
                    saveDirectLinkSettings(newBaseUrl);
                });
                
                // モーダルを閉じるイベント（閉じるボタン）
                closeModalBtn.on('click', function() {
                    copyModal.removeClass('active');
                });
                
                // モーダルの×ボタンクリック時の処理
                modalClose.on('click', function() {
                    $(this).closest('.copy-modal').removeClass('active');
                });
                
                // モーダルの外側クリック時の処理
                $('.copy-modal').on('click', function(e) {
                    if (e.target === this) {
                        $(this).removeClass('active');
                    }
                });
                
                // 編集モーダルの×ボタン特別処理
                $('#edit-label-modal .copy-modal-close').on('click', function() {
                    editLabelModal.removeClass('active');
                    labelModal.addClass('active');
                });
                
                // 編集モーダルの外側クリック時にも閉じる
                editLabelModal.on('click', function(e) {
                    if (e.target === this) {
                        editLabelModal.removeClass('active');
                        labelModal.addClass('active');
                    }
                });
                
                // adminsetting.jsonからベースURLを読み込み
                function loadDirectLinkSettings() {
                    $.ajax({
                        url: 'adminpagesetting/adminsetting.json',
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response && response.product_display_util && response.product_display_util.directlink_baseURL) {
                                baseUrl = response.product_display_util.directlink_baseURL;
                                baseUrlInput.val(baseUrl);
                            } else {
                                baseUrlInput.val(baseUrl);
                            }
                        },
                        error: function() {
                            // ファイルが存在しない場合はデフォルト値を使用
                            baseUrlInput.val(baseUrl);
                        }
                    });
                }
                
                // adminsetting.jsonにベースURLを保存
                function saveDirectLinkSettings(newBaseUrl) {
                    // 設定オブジェクトを作成
                    const settings = {
                        product_display_util: {
                            directlink_baseURL: newBaseUrl
                        }
                    };
                    
                    // 保存処理
                    $.ajax({
                        url: 'save_admin_settings.php',
                        method: 'POST',
                        data: {
                            settings: JSON.stringify(settings)
                        },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    baseUrl = newBaseUrl;
                                    showSuccess('ベースURLを更新しました');
                                } else {
                                    showError('設定の保存に失敗しました: ' + (result.message || '不明なエラー'));
                                }
                            } catch (e) {
                                showError('応答の解析に失敗しました');
                            }
                        },
                        error: function(xhr, status, error) {
                            showError('サーバーとの通信に失敗しました: ' + error);
                        }
                    });
                }
                
                // URLをクリップボードにコピー
                function copyToClipboard(text, productName) {
                    navigator.clipboard.writeText(text).then(function() {
                        copyModalTitle.text(productName + 'への直接リンクがクリップボードにコピーされました');
                        copyModalUrl.text(text);
                        // addClass で表示
                        copyModal.addClass('active');
                    }).catch(function(err) {
                        showError('クリップボードへのコピーに失敗しました: ' + err);
                    });
                }
                
                // カテゴリデータ読み込み
                function loadCategories() {
                    contentArea.html(createLoadingIndicator());
                    
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
                    
                    // タブ表示用HTML
                    let tabsHtml = '<div class="category-tabs">';
                    
                    // ドロップダウン表示用HTML
                    let dropdownHtml = '<select class="form-select category-dropdown" id="category-dropdown">';
                    
                    categories.forEach(function(category, index) {
                        const isActive = index === 0;
                        const isDisabled = category.is_active !== 1;
                        
                        // タブ用HTML
                        tabsHtml += `
                            <div class="category-tab ${isActive ? 'active' : ''} ${isDisabled ? 'disabled-tab' : ''}"
                                data-category-id="${category.category_id}" data-category-name="${category.category_name}">
                                ${category.category_name}
                            </div>
                        `;
                        
                        // ドロップダウン用HTML
                        dropdownHtml += `
                            <option value="${category.category_id}" data-category-name="${category.category_name}" 
                                ${isDisabled ? 'disabled' : ''} ${isActive ? 'selected' : ''}>
                                ${category.category_name}
                            </option>
                        `;
                        
                        if (isActive && !isDisabled) {
                            currentCategory = category.category_id;
                            currentCategoryName = category.category_name;
                        }
                    });
                    
                    tabsHtml += '</div>';
                    dropdownHtml += '</select>';
                    
                    // 商品リスト表示エリア
                    let contentHtml = tabsHtml + dropdownHtml + '<div id="product-list-container"></div>';
                    
                    contentArea.html(contentHtml);
                    
                    // タブクリックイベントを設定
                    $('.category-tab:not(.disabled-tab)').on('click', function() {
                        $('.category-tab').removeClass('active');
                        $(this).addClass('active');
                        
                        currentCategory = $(this).data('category-id');
                        currentCategoryName = $(this).data('category-name');
                        
                        // ドロップダウンの選択も同期
                        $('#category-dropdown').val(currentCategory);
                        
                        loadProductsForCategory(currentCategory);
                    });
                    
                    // ドロップダウン変更イベント
                    $('#category-dropdown').on('change', function() {
                        const selectedCategory = $(this).val();
                        const selectedOption = $(this).find('option:selected');
                        
                        currentCategory = selectedCategory;
                        currentCategoryName = selectedOption.data('category-name');
                        
                        // タブの選択も同期
                        $('.category-tab').removeClass('active');
                        $(`.category-tab[data-category-id="${currentCategory}"]`).addClass('active');
                        
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
                                renderProductList(categoryId, response.products, response.labels || []);
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
                function renderProductList(categoryId, products, labels) {
                    const container = $('#product-list-container');
                    
                    if (products.length === 0) {
                        container.html('<div class="alert alert-info">このカテゴリには商品がありません。</div>');
                        return;
                    }
                    
                    // リストヘッダー (カテゴリ名と更新ボタン)
                    let listHeader = `
                        <div class="list-header">
                            <h3 class="list-title">${currentCategoryName} 商品リスト</h3>
                            <button id="saveSettingsBtn" class="btn btn-success">
                                <i class="bi bi-save"></i> 設定を更新
                            </button>
                        </div>
                    `;
                    
                    let tableHtml = `
                        <table class="product-list">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>商品名</th>
                                    <th>Square Item ID</th>
                                    <th>価格</th>
                                    <th>有効/無効</th>
                                    <th>直リンクURL</th>
                                    <th>Pickup</th>
                                    <th>ラベル1</th>
                                    <th>ラベル2</th>
                                    <th>更新日時</th>
                                    <th>表示</th>
                                </tr>
                            </thead>
                            <tbody id="sortable-products">
                    `;
                    
                    products.forEach(function(product, index) {
                        const isInactive = product.order_dsp === '0' || product.order_dsp === 0;
                        const isPickup = product.item_pickup === '1' || product.item_pickup === 1;
                        const directLinkUrl = baseUrl + urlParam + product.square_item_id;
                        
                        tableHtml += `
                            <tr class="product-row ${isInactive ? 'inactive' : ''}" data-product-id="${product.id}">
                                <td>${product.id}</td>
                                <td>${product.name}</td>
                                <td>${product.square_item_id}</td>
                                <td>${product.price}</td>
                                <td>${product.is_active === '1' || product.is_active === 1 ? '有効' : '無効'}</td>
                                <td>
                                    <span class="copy-link-button" data-product-id="${product.id}" data-product-name="${product.name}" data-url="${directLinkUrl}">
                                        <i class="bi bi-clipboard"></i> コピー
                                    </span>
                                </td>
                                <td>
                                    <input type="checkbox" class="pickup-checkbox" ${isPickup ? 'checked' : ''}>
                                </td>
                                <td>
                                    <select class="label-select label1-select">
                                        <option value="">なし</option>
                                        ${labels.map(label => `
                                            <option value="${label.label_id}" 
                                                data-color="${label.label_color}" 
                                                ${product.item_label1 == label.label_id ? 'selected' : ''}>
                                                ${label.label_text}
                                            </option>
                                        `).join('')}
                                    </select>
                                </td>
                                <td>
                                    <select class="label-select label2-select">
                                        <option value="">なし</option>
                                        ${labels.map(label => `
                                            <option value="${label.label_id}" 
                                                data-color="${label.label_color}" 
                                                ${product.item_label2 == label.label_id ? 'selected' : ''}>
                                                ${label.label_text}
                                            </option>
                                        `).join('')}
                                    </select>
                                </td>
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
                    
                    container.html(listHeader + tableHtml);
                    
                    // --- 並び替えは外部 JS に移譲 ---
                    if (window.SortableManager && typeof SortableManager.initProductSort === 'function') {
                        SortableManager.initProductSort();
                    }
                    
                    // チェックボックスの変更イベント
                    $('.display-checkbox').on('change', function() {
                        const row = $(this).closest('tr');
                        if ($(this).is(':checked')) {
                            row.removeClass('inactive');
                        } else {
                            row.addClass('inactive');
                        }
                    });
                    
                    // コピーボタンのクリックイベント
                    $('.copy-link-button').on('click', function() {
                        const productName = $(this).data('product-name');
                        const url = $(this).data('url');
                        copyToClipboard(url, productName);
                    });
                    
                    // 初期の商品順序を設定
                    updateProductOrder();
                    
                    // 設定保存ボタンのイベント
                    $('#saveSettingsBtn').on('click', function() {
                        saveProductSettings();
                    });
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
                        const itemPickup = $(this).find('.pickup-checkbox').is(':checked') ? 1 : 0;
                        const itemLabel1 = $(this).find('.label1-select').val();
                        const itemLabel2 = $(this).find('.label2-select').val();
                        
                        products.push({
                            id: productId,
                            sort_order: sortOrder,
                            order_dsp: orderDsp,
                            item_pickup: itemPickup,
                            item_label1: itemLabel1,
                            item_label2: itemLabel2
                        });
                    });
                    
                    // 保存処理中の表示
                    const saveBtn = $('#saveSettingsBtn');
                    saveBtn.prop('disabled', true);
                    saveBtn.html('<i class="bi bi-hourglass-split"></i> 保存中...');
                    
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
                            
                            saveBtn.prop('disabled', false);
                            saveBtn.html('<i class="bi bi-save"></i> 設定を更新');
                        },
                        error: function(xhr, status, error) {
                            showError('サーバーとの通信に失敗しました: ' + error);
                            
                            saveBtn.prop('disabled', false);
                            saveBtn.html('<i class="bi bi-save"></i> 設定を更新');
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

                // ラベル管理モーダルを開く
                manageLabelBtn.on('click', function() {
                    loadLabels();
                    labelModal.addClass('active');
                });
                
                // ラベル管理モーダルを閉じる
                closeLabelModalBtn.on('click', function() {
                    labelModal.removeClass('active');
                });
                
                // ラベル追加ボタン
                addLabelBtn.on('click', function() {
                    const labelText = newLabelText.val().trim();
                    const labelColor = newLabelColor.val().replace('#', '');
                    
                    if (!labelText) {
                        showError('ラベル名を入力してください');
                        return;
                    }
                    
                    addNewLabel(labelText, labelColor);
                });
                
                // ラベル編集モーダルを閉じる
                cancelEditLabelBtn.on('click', function() {
                    editLabelModal.removeClass('active');
                    labelModal.addClass('active');
                });

                // ラベル更新ボタン
                updateLabelBtn.on('click', function() {
                    const labelId = editLabelId.val();
                    const labelText = editLabelText.val().trim();
                    const labelColor = editLabelColor.val().replace('#', '');
                    
                    if (!labelText) {
                        showError('ラベル名を入力してください');
                        return;
                    }
                    
                    updateLabel(labelId, labelText, labelColor);
                });
                
                // ラベル削除ボタン
                deleteLabelBtn.on('click', function() {
                    const labelId = editLabelId.val();
                    if (confirm('このラベルを削除してもよろしいですか？\n関連付けられた商品からもラベルが削除されます。')) {
                        deleteLabel(labelId);
                    }
                });
                
                // ラベル一覧を読み込む
                function loadLabels() {
                    $.ajax({
                        url: './api/label_management.php',
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                renderLabelList(response.labels);
                            } else {
                                showError('ラベル一覧の取得に失敗しました: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg += ' - ' + xhr.responseJSON.message;
                            }
                            showError(errorMsg);
                            
                            console.error('ラベル一覧取得エラー:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText
                            });
                        }
                    });
                }
                
                // ラベル一覧を表示
                function renderLabelList(labels) {
                    labelListBody.empty();
                    
                    if (labels.length === 0) {
                        labelListBody.html('<tr><td colspan="5" class="text-center">ラベルはありません</td></tr>');
                        return;
                    }
                    
                    labels.forEach(function(label) {
                        const labelRow = `
                            <tr>
                                <td>${label.label_id}</td>
                                <td>
                                    <span class="label-preview" style="background-color: #${label.label_color}; color: white; padding: 2px 8px; border-radius: 4px;">
                                        ${label.label_text}
                                    </span>
                                </td>
                                <td>#${label.label_color}</td>
                                <td>${label.last_update}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-label-btn" data-id="${label.label_id}" data-text="${label.label_text}" data-color="${label.label_color}">
                                        編集
                                    </button>
                                </td>
                            </tr>
                        `;
                        
                        labelListBody.append(labelRow);
                    });
                    
                    // 編集ボタンのイベント
                    $('.edit-label-btn').on('click', function() {
                        const id = $(this).data('id');
                        const text = $(this).data('text');
                        const color = $(this).data('color');
                        
                        editLabelId.val(id);
                        editLabelText.val(text);
                        editLabelColor.val('#' + color);
                        
                        // activeクラスの切り替えでモーダル表示を制御
                        labelModal.removeClass('active');
                        editLabelModal.addClass('active');
                    });
                }
                
                // 新しいラベルを追加
                function addNewLabel(text, color) {
                    // リクエストデータを作成
                    const requestData = {
                        label_text: text,
                        label_color: color
                    };
                    
                    // ラベル追加前のローディング表示
                    addLabelBtn.prop('disabled', true);
                    addLabelBtn.html('<i class="spinner-border spinner-border-sm"></i> 処理中...');
                    
                    // ローカルパスを使用して、絶対URLではなく相対URLを指定
                    $.ajax({
                        url: './api/label_management.php?action=add_label',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(requestData),
                        success: function(response) {
                            if (response.success) {
                                showSuccess('ラベルを追加しました');
                                newLabelText.val('');
                                loadLabels();
                                // 商品一覧も再読み込み
                                if (currentCategory) {
                                    loadProductsForCategory(currentCategory);
                                }
                            } else {
                                showError('ラベルの追加に失敗しました: ' + response.message);
                            }
                            
                            // ボタンを元に戻す
                            addLabelBtn.prop('disabled', false);
                            addLabelBtn.html('追加');
                        },
                        error: function(xhr, status, error) {
                            // より詳細なエラー情報を表示
                            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg += ' - ' + xhr.responseJSON.message;
                            }
                            showError(errorMsg);
                            
                            console.error('ラベル追加エラー:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText
                            });
                            
                            // ボタンを元に戻す
                            addLabelBtn.prop('disabled', false);
                            addLabelBtn.html('追加');
                        }
                    });
                }
                
                // ラベルを更新
                function updateLabel(id, text, color) {
                    // ボタンを無効化して処理中表示
                    updateLabelBtn.prop('disabled', true);
                    updateLabelBtn.html('<i class="spinner-border spinner-border-sm"></i> 処理中...');
                    
                    $.ajax({
                        url: './api/label_management.php',
                        method: 'PUT',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            label_id: id,
                            label_text: text,
                            label_color: color
                        }),
                        success: function(response) {
                            if (response.success) {
                                showSuccess('ラベルを更新しました');
                                editLabelModal.removeClass('active');
                                loadLabels();
                                labelModal.addClass('active');
                                
                                // 商品一覧も再読み込み
                                if (currentCategory) {
                                    loadProductsForCategory(currentCategory);
                                }
                            } else {
                                showError('ラベルの更新に失敗しました: ' + response.message);
                            }
                            
                            // ボタンを元に戻す
                            updateLabelBtn.prop('disabled', false);
                            updateLabelBtn.html('更新');
                        },
                        error: function(xhr, status, error) {
                            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg += ' - ' + xhr.responseJSON.message;
                            }
                            showError(errorMsg);
                            
                            console.error('ラベル更新エラー:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText
                            });
                            
                            // ボタンを元に戻す
                            updateLabelBtn.prop('disabled', false);
                            updateLabelBtn.html('更新');
                        }
                    });
                }
                
                // ラベルを削除
                function deleteLabel(id) {
                    // 削除ボタンを無効化して処理中表示
                    deleteLabelBtn.prop('disabled', true);
                    deleteLabelBtn.html('<i class="spinner-border spinner-border-sm"></i> 処理中...');
                    
                    $.ajax({
                        url: './api/label_management.php?id=' + id,
                        method: 'DELETE',
                        success: function(response) {
                            if (response.success) {
                                showSuccess('ラベルを削除しました');
                                editLabelModal.removeClass('active');
                                loadLabels();
                                labelModal.addClass('active');
                                
                                // 商品一覧も再読み込み
                                if (currentCategory) {
                                    loadProductsForCategory(currentCategory);
                                }
                            } else {
                                showError('ラベルの削除に失敗しました: ' + response.message);
                            }
                            
                            // ボタンを元に戻す
                            deleteLabelBtn.prop('disabled', false);
                            deleteLabelBtn.html('削除');
                        },
                        error: function(xhr, status, error) {
                            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg += ' - ' + xhr.responseJSON.message;
                            }
                            showError(errorMsg);
                            
                            console.error('ラベル削除エラー:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText
                            });
                            
                            // ボタンを元に戻す
                            deleteLabelBtn.prop('disabled', false);
                            deleteLabelBtn.html('削除');
                        }
                    });
                }
            });
        </script>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/inc/admin_footer.php'; ?> 