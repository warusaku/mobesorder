app.js
/**
 * モバイルオーダーアプリケーションのメインファイル
 * 各モジュールの連携や初期化処理を行う
 */

// 先頭に詳細なログ出力の設定を追加
// グローバルなDEBUG_LEVELを使用（error-handler.jsで定義）
// const DEBUG_LEVEL = 3; // 1: エラーのみ, 2: 警告含む, 3: すべてのログ

/**
 * 詳細なログ出力関数
 * @param {string} message ログメッセージ
 * @param {string} level ログレベル（ERROR, WARN, INFO, DEBUG）
 * @param {Object} [data] 追加データ（オプション）
 */
function detailedLog(message, level = 'INFO', data = null) {
    const timestamp = new Date().toISOString();
    const logPrefix = `[APP.JS][${timestamp}][${level}]`;
    
    switch(level) {
        case 'ERROR':
            if (window.DEBUG_LEVEL >= 1) console.error(`${logPrefix} ${message}`, data || '');
            break;
        case 'WARN':
            if (window.DEBUG_LEVEL >= 2) console.warn(`${logPrefix} ${message}`, data || '');
            break;
        case 'INFO':
            if (window.DEBUG_LEVEL >= 3) console.info(`${logPrefix} ${message}`, data || '');
            break;
        case 'DEBUG':
            if (window.DEBUG_LEVEL >= 3) console.debug(`${logPrefix} ${message}`, data || '');
            break;
    }
}

// app.js初期化ログ
detailedLog('アプリケーション初期化開始', 'INFO');

// アプリケーションの状態
const app = {
    initialized: false,
    loading: true,
    error: null
};

/**
 * アプリケーションの初期化
 * DOMコンテンツロード後に実行される
 */
async function initializeApp() {
    if (app.initialized) return;
    detailedLog('initializeApp 開始', 'DEBUG'); // 実行確認用ログ
    
    try {
        // APIインスタンスをグローバルに設定
        window.apiClient = new API('/fgsquare/api/v1');
        window.api = window.apiClient; // apiとapiClientを同じインスタンスに設定
        detailedLog('APIクライアント初期化完了', 'DEBUG');
        
        // LIFFの初期化が完了するまで待機
        await waitForLiffInitialization();
        detailedLog('LIFF初期化待機完了', 'DEBUG');
        
        // カートの初期化
        // loadCartFromStorage()はcart.jsで定義済み、DOMContentLoaded時に実行される
        
        // UI要素の初期化
        // initCartModal()とinitOrderCompleteModal()はui.jsで定義済み、DOMContentLoaded時に実行される
        
        // 画面サイズに応じたレイアウト調整
        if (typeof adjustLayoutForScreenSize === 'function') {
            adjustLayoutForScreenSize();
            detailedLog('adjustLayoutForScreenSize 完了', 'DEBUG');
        }
        
        // カテゴリをロード (旧実装と同様、UI初期化後に行う)
        if (typeof loadCategories === 'function') {
            detailedLog('loadCategories 呼び出し開始', 'DEBUG');
            await loadCategories(); // この中で商品リストの初回レンダリングも行われる想定
            detailedLog('loadCategories 呼び出し完了', 'DEBUG');
        } else {
            console.warn('loadCategories関数が見つかりません。');
        }
        
        // ★重要: ローディング非表示は全ての主要な初期化とデータロード後に行う
        if (typeof hideLoading === 'function') {
            detailedLog('hideLoading 呼び出し', 'DEBUG');
            hideLoading(); 
        }
        
        // ウィンドウリサイズ時の処理
        window.addEventListener('resize', adjustLayoutForScreenSize);
        
        app.initialized = true;
        app.loading = false;
        detailedLog('initializeApp 正常完了', 'INFO');
        
    } catch (error) {
        console.error('アプリケーション初期化エラー:', error);
        app.error = error.message || 'アプリケーションの初期化中にエラーが発生しました';
        app.loading = false;
        
        // エラー表示
        if (typeof showError === 'function') {
            detailedLog('showError 呼び出し (catchブロック)', 'DEBUG');
            showError(app.error);
        } else {
            alert("初期化エラー: " + app.error); // showErrorがない場合のフォールバック
        }
    }
}

/**
 * LIFFの初期化完了を待機する
 * @returns {Promise} LIFF初期化完了時に解決されるPromise
 */
function waitForLiffInitialization() {
    return new Promise((resolve, reject) => {
        // LIFFが初期化されるまで定期的にチェック
        const checkInterval = setInterval(() => {
            // 複数の条件のいずれかが満たされた場合に初期化完了とみなす
            const isInitialized = 
                (typeof userProfile !== 'undefined' && userProfile) || 
                (window.appState && window.appState.liffInitialized) ||
                (typeof window.LINE_USER_ID !== 'undefined' && window.LINE_USER_ID);
                
            if (isInitialized) {
                console.log('LIFF初期化完了を検出: ' + 
                    (typeof userProfile !== 'undefined' ? 'userProfile あり' : '') +
                    (window.appState && window.appState.liffInitialized ? ', appState.liffInitialized=true' : '') + 
                    (typeof window.LINE_USER_ID !== 'undefined' ? ', LINE_USER_ID あり' : ''));
                clearInterval(checkInterval);
                resolve();
            }
        }, 100);
        
        // タイムアウト処理
        setTimeout(() => {
            clearInterval(checkInterval);
            reject(new Error('LIFF初期化のタイムアウト'));
        }, 10000); // 10秒でタイムアウト
    });
}

/**
 * 画面サイズに応じたレイアウト調整
 */
function adjustLayoutForScreenSize() {
    const isMobile = window.innerWidth <= 768;
    const isSmallScreen = window.innerWidth <= 480;
    
    // モバイル表示の場合の調整
    if (isMobile) {
        // スマートフォン向けのレイアウト調整
        document.body.classList.add('mobile-view');
        
        if (isSmallScreen) {
            document.body.classList.add('small-screen');
        } else {
            document.body.classList.remove('small-screen');
        }
    } else {
        // デスクトップ向けのレイアウト調整
        document.body.classList.remove('mobile-view', 'small-screen');
    }
}

/**
 * エラーメッセージをコンソールに記録
 * @param {string} message - エラーメッセージ
 * @param {Error} error - エラーオブジェクト
 */
function logError(message, error) {
    console.error(message, error);
    
    // 開発環境では詳細なエラー情報を表示
    if (isDevelopment()) {
        console.debug('エラー詳細:', {
            message: error.message,
            stack: error.stack,
            timestamp: new Date().toISOString()
        });
    }
}

/**
 * 開発環境かどうかを判定
 * @returns {boolean} 開発環境の場合はtrue
 */
function isDevelopment() {
    // URLから開発環境かどうかを判定
    return window.location.hostname === 'localhost' || 
           window.location.hostname === '127.0.0.1' ||
           window.location.hostname.includes('.local');
}

// ページ読み込み時にアプリケーションを初期化
document.addEventListener('DOMContentLoaded', initializeApp);

// サービスワーカー登録（PWA対応、オプション）
/* 一時的に無効化
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js')
            .then(registration => {
                console.log('ServiceWorker登録成功:', registration.scope);
            })
            .catch(error => {
                console.error('ServiceWorker登録失敗:', error);
            });
    });
}
*/

// アプリケーションのグローバルエラーハンドリング
window.addEventListener('error', (event) => {
    logError('グローバルエラー:', event.error || new Error(event.message));
    
    // エラー表示（UIが初期化されている場合）
    if (app.initialized) {
        showError('予期しないエラーが発生しました。ページを再読み込みしてください。');
    }
});

// カテゴリ選択時の処理
async function onCategorySelected(categoryId, categoryName) {
    try {
        currentCategory = categoryId;
        
        console.log(`カテゴリ選択: ID=${categoryId}, 名前=${categoryName}`);
        
        // カテゴリがアクティブであることを視覚的に表示
        const allCategoryItems = document.querySelectorAll('.category-item');
        allCategoryItems.forEach(item => {
            item.classList.remove('active');
        });
        
        const selectedCategory = document.querySelector(`[data-category-id="${categoryId}"]`);
        if (selectedCategory) {
            selectedCategory.classList.add('active');
        }
        
        // ローディング表示
        document.getElementById('product-list').innerHTML = `
            <div class="loading-indicator">
                <div class="spinner"></div>
                <p>商品を読み込み中...</p>
            </div>
        `;
        
        // 営業状態を確認
        const isOpen = await api.checkCategoryOpenStatus(categoryId);
        
        // 商品データ取得
        let products = [];
        try {
            products = await api.getProductsByCategory(categoryId);
        } catch (error) {
            console.error('商品データ取得エラー:', error);
            showError('商品情報の取得に失敗しました');
            return;
        }
        
        // 商品表示のHTML作成
        renderProductList(products, isOpen);
        
    } catch (error) {
        console.error('カテゴリ選択処理エラー:', error);
        showError('カテゴリ情報の処理中にエラーが発生しました');
    }
}

// 商品リストを表示
function renderProductList(products, isOpen = true) {
    const productListElement = document.getElementById('product-list');
    
    if (!isOpen) {
        // 営業時間外の場合は、次の営業時間を取得して表示
        api.getNextOpeningTime().then(nextOpenTime => {
            let message = '現在すべての販売が終了している時間帯となります。';
            if (nextOpenTime) {
                message += `次の営業開始時間は${nextOpenTime}です。`;
            }
            
            // 営業時間外モーダルに表示
            showClosedTimeModal(message);
            
            // 商品リストにも表示（モーダルが閉じられてもわかるように）
            productListElement.innerHTML = `
                <div class="closed-message">
                    <i class="fas fa-clock"></i>
                    <h3>営業時間外</h3>
                    <p>${message}</p>
                </div>
            `;
        });
        return;
    }
    
    // 商品が見つからない場合
    if (!products || products.length === 0) {
        productListElement.innerHTML = `
            <div class="no-products">
                <i class="fas fa-exclamation-circle"></i>
                <p>このカテゴリには商品がありません</p>
            </div>
        `;
        return;
    }
    
    let productHTML = '';
    
    // 商品カードの作成 (商品詳細ボタンを使用)
    products.forEach(product => {
        const productId = product.id;
        const name = product.name;
        const price = product.price;
        const image = product.image_url || 'images/no-image.png';
        
        // ラベル情報
        const label1 = product.label1 || null;
        const label2 = product.label2 || null;
        
        let labelsHTML = '';
        if (label1) {
            labelsHTML += `<span class="product-label" style="background-color: ${label1.color}">${label1.text}</span>`;
        }
        if (label2) {
            labelsHTML += `<span class="product-label" style="background-color: ${label2.color}">${label2.text}</span>`;
        }
        
        // 商品カードHTMLの作成
        productHTML += `
            <div class="product-item" data-product-id="${productId}">
                <div class="product-image">
                    <img src="${image}" alt="${name}" onerror="this.src='images/no-image.png'">
                </div>
                <div class="product-info">
                    <h3 class="product-name">${name}</h3>
                    <div class="product-labels">${labelsHTML}</div>
                    <div class="product-price">¥${price.toLocaleString()}</div>
                    <button class="view-detail-btn" data-product-id="${productId}">商品詳細</button>
                </div>
            </div>
        `;
    });
    
    // HTMLをページに追加
    productListElement.innerHTML = productHTML;
    
    // 商品クリックイベント設定
    setupProductClickEvents();
}

// 商品クリックイベント設定
function setupProductClickEvents() {
    const viewDetailButtons = document.querySelectorAll('.view-detail-btn');
    const productItems = document.querySelectorAll('.product-item');
    
    // 商品詳細ボタンのイベント
    viewDetailButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation(); // 親要素へのイベント伝播を防止
            const productId = this.getAttribute('data-product-id');
            if (typeof showProductDetail === 'function') {
                showProductDetail(productId);
            } else {
                console.warn('showProductDetail関数が見つかりません。');
            }
        });
    });
    
    // 商品クリックで詳細表示 (オプション)
    productItems.forEach(item => {
        item.addEventListener('click', function(event) {
            if (!event.target.classList.contains('view-detail-btn')) {
                const productId = this.getAttribute('data-product-id');
                if (typeof showProductDetail === 'function') {
                    showProductDetail(productId);
                }
            }
        });
    });
    
    console.log('商品詳細ボタンのクリックイベント設定完了');
}

// カテゴリを読み込む関数
function loadCategories() {
    console.log('カテゴリ読み込み開始');
    
    return new Promise((resolve, reject) => {
        // APIクライアントが利用可能かチェック
        if (!window.apiClient) {
            console.warn('APIクライアントが初期化されていません。再試行します...');
            
            // APIクライアントの初期化を待つ
            setTimeout(() => {
                if (window.apiClient) {
                    console.log('APIクライアントが利用可能になりました');
                    loadCategoriesFromAPI(resolve, reject);
                } else {
                    console.error('APIクライアントの初期化に失敗しました');
                    reject(new Error('API通信モジュールの初期化に失敗しました'));
                }
            }, 500);
        } else {
            // APIクライアントが利用可能な場合は直接読み込み
            loadCategoriesFromAPI(resolve, reject);
        }
    });
}

// APIからカテゴリを読み込む実際の処理
function loadCategoriesFromAPI(resolve, reject) {
    const categoryListElement = document.getElementById('category-list');
    if (!categoryListElement) {
        console.error('カテゴリリスト要素が見つかりません');
        reject(new Error('カテゴリリスト要素が見つかりません'));
        return;
    }
    
    // カテゴリリストをクリア
    categoryListElement.innerHTML = '';
    
    // ローディング表示
    const loadingElement = document.createElement('div');
    loadingElement.className = 'category-loading';
    loadingElement.innerHTML = '<div class="spinner"></div><span>カテゴリを読み込み中...</span>';
    categoryListElement.appendChild(loadingElement);
    
    // カテゴリを取得
    window.apiClient.getCategories()
        .then(categories => {
            console.log('カテゴリ取得成功:', categories.length + '件');
            
            // ローディング表示を削除
            categoryListElement.removeChild(loadingElement);
            
            // カテゴリがない場合
            if (!categories || categories.length === 0) {
                const emptyElement = document.createElement('div');
                emptyElement.className = 'empty-message';
                emptyElement.textContent = 'カテゴリが見つかりません';
                categoryListElement.appendChild(emptyElement);
                resolve([]);
                return;
            }
            
            // カテゴリをリストに追加
            categories.forEach(category => {
                const categoryElement = createCategoryElement(category);
                categoryListElement.appendChild(categoryElement);
            });
            
            // 最初のカテゴリを選択
            if (categories.length > 0) {
                const firstCategory = categories[0];
                selectCategory(firstCategory.id);
            }
            
            resolve(categories);
        })
        .catch(error => {
            console.error('カテゴリ読み込みエラー:', error);
            
            // ローディング表示を削除
            if (loadingElement.parentNode === categoryListElement) {
                categoryListElement.removeChild(loadingElement);
            }
            
            // エラーメッセージを表示
            const errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            errorElement.textContent = 'カテゴリの読み込みに失敗しました';
            categoryListElement.appendChild(errorElement);
            
            reject(error);
        });
}

// カテゴリ要素を作成
function createCategoryElement(category) {
    const element = document.createElement('div');
    element.className = 'category-item';
    element.dataset.categoryId = category.id;
    element.textContent = category.name;
    
    // カテゴリクリック時の処理
    element.addEventListener('click', function() {
        // 現在選択されているカテゴリから選択状態を削除
        const currentSelected = document.querySelector('.category-item.active');
        if (currentSelected) {
            currentSelected.classList.remove('active');
        }
        
        // クリックされたカテゴリを選択状態に
        this.classList.add('active');
        
        // 選択されたカテゴリの商品を表示
        selectCategory(category.id);
    });
    
    return element;
}

// 選択されたカテゴリの商品を表示
function selectCategory(categoryId) {
    console.log('カテゴリ選択:', categoryId);
    
    // メニューリスト要素 (product-listに統一)
    const menuListElement = document.getElementById('product-list');
    if (!menuListElement) {
        console.error('メニューリスト要素が見つかりません');
        return;
    }
    
    // ローディング表示
    menuListElement.innerHTML = '<div class="loading-spinner"></div>';
    
    // APIが利用可能かチェック
    if (!window.apiClient) {
        console.error('APIクライアントが初期化されていません');
        menuListElement.innerHTML = '<div class="error-message">システムエラーが発生しました。ページを再読み込みしてください。</div>';
        return;
    }
    
    // 商品を取得
    window.apiClient.getProducts(categoryId)
        .then(products => {
            console.log('商品取得成功:', products.length + '件');
            
            // メニューリストをクリア
            menuListElement.innerHTML = '';
            
            // 商品がない場合
            if (!products || products.length === 0) {
                menuListElement.innerHTML = '<div class="empty-message">この分類の商品はありません</div>';
                return;
            }
            
            // 商品をリストに追加（改修：直接renderProductListを呼び出す）
            renderProductList(products);
            
            // 遅延読み込みを開始
            if (typeof initLazyLoading === 'function') {
                initLazyLoading();
            }
        })
        .catch(error => {
            console.error('商品読み込みエラー:', error);
            menuListElement.innerHTML = '<div class="error-message">商品の読み込みに失敗しました</div>';
        });
}

/**
 * LIFFの初期化状態をチェック
 * @returns {Object} LIFFの状態情報
 */
function checkLiffInitialization() {
    return {
        defined: typeof liff !== 'undefined',
        initialized: typeof liff !== 'undefined' && liff.isApiAvailable && liff.isApiAvailable('shareTargetPicker'),
        loggedIn: typeof liff !== 'undefined' && liff.isLoggedIn && liff.isLoggedIn(),
        inClient: typeof liff !== 'undefined' && liff.isInClient && liff.isInClient()
    };
}

/**
 * ロードされたスクリプトをチェックする関数
 * @returns {Object} ロードされたスクリプトの状態
 */
function checkScriptsLoaded() {
    const scripts = document.querySelectorAll('script');
    const scriptUrls = Array.from(scripts).map(script => script.src || 'インラインスクリプト');
    
    return {
        total: scripts.length,
        loaded: scriptUrls,
        hasError: window.onerror !== null
    };
}

/**
 * 重要なDOM要素をチェックする関数
 * @returns {Object} DOM要素の存在状態
 */
function checkDOMElements() {
    return {
        productList: document.getElementById('product-list') !== null,
        categoryList: document.getElementById('category-list') !== null,
        cartBadge: document.getElementById('cart-badge') !== null,
        cartModal: document.getElementById('cart-modal') !== null,
        productDetailModal: document.getElementById('product-detail-modal') !== null
    };
}

// デバッグ用関数をグローバルスコープへ追加
window.debugApp = {
    logLevel: window.DEBUG_LEVEL,
    log: detailedLog,
    checkLiff: checkLiffInitialization,
    checkScripts: checkScriptsLoaded,
    checkDOM: checkDOMElements,
    dumpState: function() {
        this.log('アプリケーション診断を実行', 'INFO');
        this.checkLiff();
        this.checkScripts();
        this.checkDOM();
        
        // カート情報の表示
        if (typeof window.getCartItems === 'function') {
            const cartItems = window.getCartItems();
            this.log('現在のカート内容', 'INFO', cartItems);
        } else {
            this.log('カート情報取得関数が利用できません', 'WARN');
        }
        
        return 'アプリケーション診断完了';
    }
};

// ファイル末尾
detailedLog('アプリケーション初期化完了', 'INFO');

/**
 * カートに追加ボタンのクリック処理
 * @param {HTMLElement} button - クリックされたボタン要素
 */
function handleAddToCart(button) {
    try {
        const productId = button.getAttribute('data-product-id');
        if (!productId) {
            console.error('商品IDが見つかりません');
            return;
        }
        
        // 商品データを取得（window.apiClient.productCacheに保持されているはず）
        const product = window.apiClient.productCache[productId];
        if (!product) {
            console.error('商品データが見つかりません:', productId);
            return;
        }
        
        // カートに追加（cart.jsのグローバル関数を呼び出し）
        if (typeof addToCart === 'function') {
            addToCart(product, 1); // 数量は1固定
            showNotification('カートに追加しました', 'success');
        } else {
            console.error('addToCart関数が見つかりません');
            alert('商品をカートに追加できません。ページを再読み込みしてください。');
        }
    } catch (error) {
        console.error('カート追加処理エラー:', error);
        alert('商品をカートに追加できません。ページを再読み込みしてください。');
    }
} 
