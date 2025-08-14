/**
 * app.js
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

const app = {
    initialized: false,
    loading: true,
    error: null
};

// ================= URL パラメータ (item) 処理 =================
let _pendingItemParam = null;           // URL から取得した商品ID
let _itemParamModalOpened = false;      // モーダルが既に開かれたか
let _itemParamFetchAttempted = false;   // API 取得を試行済みか
(function(){
    try {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('item');
        if (id) {
            console.log('[APP.JS] URL パラメータ item を検出:', id);
            _pendingItemParam = id;
            // LINE 認証リダイレクトで item が欠落する場合に備えて一時保存
            try {
                sessionStorage.setItem('pending_item_param', id);
            } catch (ssErr) {
                console.warn('[APP.JS] sessionStorage 保存エラー:', ssErr);
            }
        }
        // ===== 追加: LIFF リダイレクトで item が liff.state に埋め込まれている場合の救済処理 =====
        if (!_pendingItemParam) {
            const rawLiffState = params.get('liff.state'); // URLSearchParams は key の . をエスケープしない
            if (rawLiffState) {
                try {
                    // LIFF SDK によりエンコードされた状態で渡ってくるため decode 必須
                    const decodedState = decodeURIComponent(rawLiffState);
                    const qIndex = decodedState.indexOf('?');
                    if (qIndex !== -1) {
                        const innerQuery = decodedState.substring(qIndex + 1);
                        const innerParams = new URLSearchParams(innerQuery);
                        const nestedId = innerParams.get('item');
                        if (nestedId) {
                            console.log('[APP.JS] liff.state 内で item パラメータを検出:', nestedId);
                            _pendingItemParam = nestedId;
                            try { sessionStorage.setItem('pending_item_param', nestedId); } catch(e){}
                        }
                    }
                } catch (err) {
                    console.warn('[APP.JS] liff.state 解析エラー:', err);
                }
            }
        }
        // ===== 追加: LINE 認証後の再遷移で liff.state が欠落しているケースへの対応 =====
        if (!_pendingItemParam) {
            try {
                const authStr = sessionStorage.getItem('line_auth_params');
                if (authStr) {
                    const authObj = JSON.parse(authStr);
                    if (authObj && authObj.liffState) {
                        const decodedState = decodeURIComponent(authObj.liffState);
                        const qIndex = decodedState.indexOf('?');
                        if (qIndex !== -1) {
                            const innerParams = new URLSearchParams(decodedState.substring(qIndex + 1));
                            const sid = innerParams.get('item');
                            if (sid) {
                                console.log('[APP.JS] sessionStorage.liffState から item パラメータを検出:', sid);
                                _pendingItemParam = sid;
                            }
                        }
                    }
                }
            } catch(e){
                console.warn('[APP.JS] sessionStorage line_auth_params 解析エラー:', e);
            }
        }
        // ===== 最後のフォールバック: 直前ページで保存した値を使用 =====
        if (!_pendingItemParam) {
            try {
                const stored = sessionStorage.getItem('pending_item_param');
                if (stored) {
                    console.log('[APP.JS] sessionStorage pending_item_param を利用:', stored);
                    _pendingItemParam = stored;
                    sessionStorage.removeItem('pending_item_param'); // 使い切り
                }
            } catch(se){
                console.warn('[APP.JS] sessionStorage 読み取りエラー:', se);
            }
        }
    } catch (e) {
        console.warn('[APP.JS] URL パラメータ解析エラー:', e);
    }
})();

/**
 * アプリケーションの初期化
 * DOMコンテンツロード後に実行される
 */
async function initializeApp() {
    if (app.initialized) return;
    
    try {
        // APIインスタンスをグローバルに設定
        window.apiClient = new API('/api/v1');
        window.api = window.apiClient; // apiとapiClientを同じインスタンスに設定
        
        // LIFFの初期化が完了するまで待機
        await waitForLiffInitialization();
        
        // カートの初期化
        // loadCartFromStorage()はcart.jsで定義済み、DOMContentLoaded時に実行される
        
        // UI要素の初期化
        // initCartModal()とinitOrderCompleteModal()はui.jsで定義済み、DOMContentLoaded時に実行される
        
        // 画面サイズに応じたレイアウト調整
        adjustLayoutForScreenSize();
        
        // ウィンドウリサイズ時の処理
        window.addEventListener('resize', adjustLayoutForScreenSize);
        
        // カテゴリを読み込み（初回表示用）
        try {
            await loadCategories();
        } catch (e) {
            console.error('カテゴリ初期読み込みエラー:', e);
        }
        
        // 部屋連携必須で情報が無い場合モーダル
        if (window.ROOM_LINK_REQUIRED && (!window.roomInfo) && window.informModal && typeof window.informModal.showRoomRegisterInform==='function') {
            window.informModal.showRoomRegisterInform();
        }
        
        app.initialized = true;
        app.loading = false;
        
    } catch (error) {
        console.error('アプリケーション初期化エラー:', error);
        app.error = error.message || 'アプリケーションの初期化中にエラーが発生しました';
        app.loading = false;
        
        // エラー表示
        showError(app.error);
    }
}

/**
 * LIFFの初期化完了を待機する
 * @returns {Promise} LIFF初期化完了時に解決されるPromise
 */
function waitForLiffInitialization() {
    return new Promise((resolve, reject) => {
        console.log('[APP.JS] LIFF初期化待機開始...');
        
        // すでに liff 初期化が完了していれば即時解決
        if (window.appState && window.appState.liffInitialized) {
            console.log('[APP.JS] LIFF既に初期化済み');
            resolve();
            return;
        }
        
        // イベントリスナーで待機
        const handler = () => {
            console.log('[APP.JS] liff-initializedイベントを受信');
            document.removeEventListener('liff-initialized', handler);
            resolve();
        };
        document.addEventListener('liff-initialized', handler);
        
        // タイムアウト処理（20秒に延長）
        const timeoutId = setTimeout(() => {
            console.error('[APP.JS] LIFF初期化タイムアウト（20秒経過）');
            console.log('[APP.JS] window.appState:', window.appState);
            console.log('[APP.JS] typeof liff:', typeof liff);
            document.removeEventListener('liff-initialized', handler);
            reject(new Error('LIFF初期化のタイムアウト'));
        }, 20000); // 10秒から20秒に変更
        
        // デバッグ用：5秒ごとに状態をチェック
        const checkInterval = setInterval(() => {
            console.log('[APP.JS] LIFF初期化待機中... appState:', window.appState);
            if (window.appState && window.appState.liffInitialized) {
                clearInterval(checkInterval);
                clearTimeout(timeoutId);
            }
        }, 5000);
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
function onCategorySelected(categoryId) {
    // 選択中のカテゴリをハイライト
    document.querySelectorAll('.category-item').forEach(item => {
        item.classList.toggle('active', item.dataset.categoryId === categoryId);
    });

    // ログ出力
    console.log(`カテゴリ選択: ${categoryId}`);
        
        // ローディング表示
    document.getElementById('product-list').innerHTML = '<div class="loading">読み込み中...</div>';
        
    // 商品リストのスクロール位置をリセット
    document.querySelector('.product-content').scrollTop = 0;

    // カテゴリの営業状態を確認
    window.apiClient.checkCategoryOpenStatus(categoryId)
        .then(isOpen => {
            window.isStoreOpen = isOpen;
            
            // 営業時間設定を取得しログ
            fetchOpenCloseSettings().then(cfg=>{
                if(cfg){
                    console.log('[営業時間設定]', cfg);
                    console.log(`[営業時間判定結果] カテゴリID=${categoryId} open=${cfg.default_open} close=${cfg.default_close} 判定=${isOpen}`);
                }
            });
    
    if (!isOpen) {
                // 営業時間外の場合、次の営業開始時間を取得
                return window.apiClient.getNextOpeningTime()
                    .then(nextOpenTime => {
                        window.nextOpenTime = nextOpenTime || '情報がありません';
                        // デバッグ情報の出力
                        console.log(`次回営業開始: ${nextOpenTime}`);
                        // モーダル表示
                        if (window.informModal && typeof window.informModal.showStoreClosedInform === 'function') {
                            window.informModal.showStoreClosedInform(window.nextOpenTime);
                        }

                        document.getElementById('product-list').innerHTML = `
                <div class="closed-message">
                                <p>現在、営業時間外です。</p>
                                <p>次回の営業開始: ${window.nextOpenTime}</p>
                            </div>
                        `;
                        return null; // 商品取得をスキップ
                    });
            }
            
            // 営業中の場合は商品データを取得
            return window.apiClient.getProducts(categoryId);
        })
        .then(products => {
            if (!products) return; // 営業時間外の場合は処理終了
            
            console.log(`カテゴリ ${categoryId} の商品数: ${products.length}`);
            renderProductList(products);
        })
        .catch(error => {
            console.error('データ取得に失敗しました:', error);
            document.getElementById('product-list').innerHTML = `
                <div class="error-message">
                    <p>データの取得に失敗しました。</p>
                    <p>しばらく経ってから再度お試しください。</p>
                </div>
            `;
        });
    }
    
// 商品リストの表示
function renderProductList(products) {
    console.log('取得した商品データ:', products);
    
    // UI側で詳細モーダルを開く際に参照できるよう、グローバルに保持
    if (!window.itemData) window.itemData = {};
    window.itemData.products = Array.isArray(products) ? products : [];
    console.log('itemData.products を更新: 件数', window.itemData.products.length);

    const productListEl = document.getElementById('product-list');
    
    if (window.isStoreOpen === false) {
        productListEl.innerHTML = `
            <div class="closed-message">
                <p>現在、営業時間外です。</p>
                <p>次回の営業開始: ${window.nextOpenTime || '情報がありません'}</p>
            </div>
        `;
        return;
    }
    
    if (!products || products.length === 0) {
        productListEl.innerHTML = '<div class="no-products">商品がありません</div>';
        return;
    }

    // sort_orderで並び替え
    products.sort((a, b) => {
        // nullチェック
        const sortA = a.sort_order !== null ? a.sort_order : 9999;
        const sortB = b.sort_order !== null ? b.sort_order : 9999;
        return sortA - sortB;
    });
    
    console.log('並び替え後の商品データ:', products);
    
    let productListHTML = '';
    
    products.forEach(product => {
        console.log(`商品ID: ${product.id}, 名前: ${product.name}, pickup: ${product.item_pickup}`);
        
        // 画像のパスを設定
        const imagePath = product.image_url ? product.image_url : 'images/no-image.png';
        
        // 価格は整数表示（小数点不要）
        const priceDisplay = `¥${Math.round(product.price).toLocaleString()}`;
        
        // ピックアップアイテムかどうかを判断するクラス
        const pickupClass = product.item_pickup == 1 ? 'pickup' : '';
        
        // ラベル情報生成
        const formatLabel = (lbl) => {
            if (!lbl || !lbl.text) return '';
            let color = lbl.color || '';
            if (color && !color.startsWith('#')) color = '#' + color;
            return `<span class="product-label" style="background-color:${color};">${lbl.text}</span>`;
        };
        
        let labelHTML = '';
        
        // 通常ラベル - item_label1を表示
        if (product.item_label1 && product.label1) {
            labelHTML += formatLabel(product.label1);
        }
        
        // ピックアップはitem_label2も表示
        if (pickupClass && product.item_label2 && product.label2) {
            labelHTML += formatLabel(product.label2);
        }
        
        // 代替: 設定がなければlabels配列から取得
        if (labelHTML === '' && Array.isArray(product.labels) && product.labels.length > 0) {
            const maxLabels = pickupClass ? 2 : 1;
            for (let i = 0; i < Math.min(maxLabels, product.labels.length); i++) {
                labelHTML += formatLabel(product.labels[i]);
            }
        }
        
        // pickup商品と通常商品でHTMLを分ける
        if (pickupClass) {
            // pickup商品（2列幅）
            const descriptionText = product.description && product.description.trim().length > 0 
                ? product.description 
                : '商品説明はありません';
                
            productListHTML += `
                <div class="product-item pickup" data-product-id="${product.id}">
                <div class="product-image">
                        <img src="${imagePath}" alt="${product.name}" onerror="this.src='images/no-image.png'">
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">${product.name}</h3>
                        <div class="product-price">${priceDisplay}</div>
                        <p class="product-description">${descriptionText}</p>
                        <div class="product-button-container">
                            <button class="view-detail-btn" data-product-id="${product.id}">商品詳細</button>
                            <div class="product-labels">${labelHTML}</div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // 通常商品（1列幅）
            productListHTML += `
                <div class="product-item" data-product-id="${product.id}">
                    <div class="product-image">
                        <img src="${imagePath}" alt="${product.name}" onerror="this.src='images/no-image.png'">
                </div>
                <div class="product-info">
                        <h3 class="product-name">${product.name}</h3>
                        <div class="product-price-container">
                            <div class="product-price">${priceDisplay}</div>
                            <div class="product-labels">${labelHTML}</div>
                        </div>
                        <button class="view-detail-btn" data-product-id="${product.id}">商品詳細</button>
                </div>
            </div>
        `;
        }
    });
    
    productListEl.innerHTML = productListHTML;
    
    // 商品カードのバッジを初期化
    if (typeof window.onCartUpdated === 'function') {
        window.onCartUpdated();
    }

    // URLパラメータ (item) が指定されている場合、該当商品の詳細モーダルを表示
    if (_pendingItemParam && !_itemParamModalOpened) {
        let targetProduct = null;
        if (typeof getProductByIdLocal === 'function') {
            targetProduct = getProductByIdLocal(_pendingItemParam);
        }
        if (targetProduct) {
            // square_item_id 経由でヒットした場合でも内部 id を使用してモーダルを開く
            const internalId = targetProduct.id ? targetProduct.id.toString() : _pendingItemParam;
            showProductDetail(internalId);
            _itemParamModalOpened = true; // 表示済みフラグをセット
        } else if (!_itemParamFetchAttempted) {
            // ローカルに無ければ専用APIから詳細を取得してモーダルを開く
            _itemParamFetchAttempted = true;
            const detailUrl = `api/get-product-details.php?square_item_id=${encodeURIComponent(_pendingItemParam)}`;
            fetch(detailUrl)
                .then(resp => resp.json())
                .then(res => {
                    if (res && res.success && res.data) {
                        const p = res.data;

                        if (!window.itemData) window.itemData = { products: [] };
                        if (!Array.isArray(window.itemData.products)) window.itemData.products = [];

                        // ID または square_item_id が一致する商品が既にあれば置換、なければ追加
                        const existingIdx = window.itemData.products.findIndex(pr => {
                            const idMatch = pr.id && p.id && pr.id.toString() === p.id.toString();
                            const sqIdMatch = pr.square_item_id && pr.square_item_id.toString() === p.square_item_id?.toString();
                            return idMatch || sqIdMatch;
                        });
                        if (existingIdx >= 0) {
                            window.itemData.products[existingIdx] = p;
                        } else {
                            window.itemData.products.push(p);
                        }

                        const internalId = p.id ? p.id.toString() : _pendingItemParam;
                        showProductDetail(internalId);
                        _itemParamModalOpened = true; // 表示済みフラグをセット
                    } else {
                        console.error('[APP.JS] 商品詳細取得失敗: ', res);
                    }
                })
                .catch(err => console.error('[APP.JS] 商品詳細API通信エラー:', err));
        }
    }

    // 商品カード全体クリックで詳細を開くリスナーを追加
    document.querySelectorAll('.product-item').forEach(item => {
        item.addEventListener('click', (event) => {
            // 既にボタン要素がクリックされた場合は二重呼び出しを避ける
            if (event.target.closest('.view-detail-btn') || event.target.closest('.add-to-cart-button')) {
                return;
            }
            const productId = item.getAttribute('data-product-id');
            if (productId) {
                showProductDetail(productId);
            }
        });
    });
    
    // 商品詳細ボタンのイベントリスナーを設定
    document.querySelectorAll('.view-detail-btn').forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const productId = button.getAttribute('data-product-id');
            showProductDetail(productId);
        });
    });
    
    // カートに追加ボタンのイベントリスナーを設定
    document.querySelectorAll('.add-to-cart-button').forEach(button => {
        const productId = button.getAttribute('data-product-id');
        // data-product-id が無いボタンはスキップ（モーダル内など専用ハンドラがあるため）
        if (!productId) return;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (typeof getProductByIdLocal === 'function') {
                const product = getProductByIdLocal(productId);
                if (product) {
                    addToCart(product, 1);
                } else {
                    console.error('add-to-cart: 商品データが取得できません (productId=' + productId + ')');
                }
            } else {
                console.error('getProductByIdLocal 関数が未定義です');
            }
        });
    });
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
                onCategorySelected(firstCategory.id);
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
        onCategorySelected(category.id);
    });
    
    return element;
}

// 選択されたカテゴリの商品を表示
function selectCategory(categoryId) {
    console.log('カテゴリ選択:', categoryId);
    
    // メニューリスト要素
    const menuListElement = document.getElementById('product-list');
    if (!menuListElement) {
        console.error('商品リスト要素が見つかりません');
        return;
    }
    
    // 商品リストのスクロール位置をリセット
    document.querySelector('.product-content').scrollTop = 0;
    
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
            
            // 商品のsort_orderで並び替え
            products.sort((a, b) => {
                // nullチェック
                const sortA = a.sort_order !== null ? a.sort_order : 9999;
                const sortB = b.sort_order !== null ? b.sort_order : 9999;
                return sortA - sortB;
            });
            
            // 並び替えたプロダクトリストをレンダリング
            renderProductList(products);
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

// ===== 営業時間設定を取得し保持 =====
let storeOpenCloseSettings = null;
function fetchOpenCloseSettings(){
    if(storeOpenCloseSettings) return Promise.resolve(storeOpenCloseSettings);
    const url = '../admin/adminsetting_registrer.php?section=open_close';
    return fetch(url).then(r=>r.json()).then(j=>{
        if(j && j.success && j.settings){
            storeOpenCloseSettings = j.settings;
            console.log('[営業時間設定取得]', storeOpenCloseSettings);
        } else {
            console.warn('[営業時間設定取得失敗]', j);
        }
        return storeOpenCloseSettings;
    }).catch(err=>{
        console.error('[営業時間設定取得エラー]', err);
        return null;
    });
}

// URLパラメータ(item)に基づく商品詳細モーダル自動オープン用の変数とヘルパー
// let _pendingItemParam = null;           // 重複定義のためコメントアウト
// let _itemParamModalOpened = false;      // 重複定義のためコメントアウト
// let _itemParamFetchAttempted = false;   // 重複定義のためコメントアウト
// (function(){
//     try {
//         const params = new URLSearchParams(window.location.search);
//         const id = params.get('item');
//         if (id) {
//             console.log('[APP.JS] URL パラメータ item を検出:', id);
//             _pendingItemParam = id;
//         }
//     } catch (e) {
//         console.warn('[APP.JS] URL パラメータ解析エラー:', e);
//     }
// })(); 
