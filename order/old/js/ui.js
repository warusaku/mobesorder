ui.js
/**
 * UI関連の関数をまとめたファイル
 * 
 * このファイルは以下の責務を持ちます:
 * - UIコンポーネントの初期化とイベントハンドリング
 * - 画面の表示/非表示の切り替え
 * - UIの状態管理
 */

// UIの状態
const uiState = {
    activeTab: 'menu',
    currentItemDetail: null, 
    isLoading: false,
    detailVisible: false,
    cartVisible: false,
    confirmationVisible: false,
    orderCompleteVisible: false
};

// グローバルアクセス用の初期化関数
function initUI() {
    console.log('UI初期化を開始します (旧仕様ベースに調整)');
    initTabs();
    initDetailModal(); 
    // initCartModal(); // 旧ui.jsの責務と異なる可能性。カートタブ内のボタンイベントはcart.js等で管理か？一旦コメントアウト
    initOrderConfirmationModal();
    initOrderCompleteModal();
    adjustLayoutForScreenSize();
    // initLazyLoading(); // 旧ui.jsに存在しない場合は削除またはapp.jsに移動
    setupEventHandlers(); // 旧ui.jsのイベントハンドラをここに集約
    console.log('UI初期化が完了しました (旧仕様ベースに調整)');
}

// 従来のDOMContentLoadedイベントリスナーは維持するが、内容を変更
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded');
    
    // LIFF初期化を待つため、ここではUI初期化を行わない
    // 代わりにapp-readyイベントを監視する（index.phpで発行）
    if (window.appState && window.appState.liffInitialized) {
        // すでに初期化済みの場合は直接初期化
        console.log('LIFF既に初期化済み - UI初期化を開始');
        initUI();
            } else {
        // LIFFの初期化完了イベントを待つ
        console.log('LIFF初期化待機中 - イベントをリッスン');
    }
});

// app-readyイベントを待機してUI初期化
document.addEventListener('app-ready', function(event) {
    console.log('app-readyイベント受信 - UI初期化チェック');
    
    // まだ初期化されていなければ初期化
    if (!window.appState || !window.appState.uiInitialized) {
        console.log('UIが未初期化のため初期化を開始');
        initUI();
    } else {
        console.log('UIは既に初期化済み');
            }
        });

// 以下は既存の関数の修正

/**
 * タブの初期化
 */
function initTabs() {
    try {
        const tabs = document.querySelectorAll('.tab'); // HTMLのクラス名に合わせる
        const tabContents = document.querySelectorAll('.tab-content'); // HTMLのクラス名に合わせる
    
        if (!tabs || tabs.length === 0) {
            console.warn('タブ要素 (.tab) が見つかりません');
            return;
        }
        if (!tabContents || tabContents.length === 0) {
            console.warn('タブコンテンツ要素 (.tab-content) が見つかりません');
            return;
        }
    
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.dataset.tab; // data-tab属性の値を取得
                
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
    
                tabContents.forEach(content => {
                    // IDが menu-container や cart-container になっている想定
                    if (content.id === tabName + '-container') { 
                        content.classList.add('active');
                    } else {
                        content.classList.remove('active');
                    }
                });
                uiState.activeTab = tabName;
                console.log('タブ切り替え:', tabName);
            });
        });
        // 初期表示でmenuタブをアクティブにする (app.js側で制御する方が良いかもしれない)
        const initialActiveTab = document.querySelector('.tab[data-tab="menu"]');
        if (initialActiveTab) initialActiveTab.click(); 

        console.log('タブ初期化完了 (旧仕様確認)');
    } catch (error) {
        console.error('タブ初期化エラー:', error);
    }
}
                    
/**
 * 商品詳細モーダルの初期化
 */
function initDetailModal() {
    try {
        const itemDetailModal = document.getElementById('productDetailModal'); // HTMLのIDに合わせる
        if (!itemDetailModal) {
            console.warn('商品詳細モーダル要素 (#productDetailModal) が見つかりません');
            return;
        }
        // 閉じるボタンは data-modal-close 属性を持つものを探す (旧実装に合わせる想定)
        const closeButton = itemDetailModal.querySelector('[data-modal-close]'); 
        const addToCartButton = itemDetailModal.querySelector('#addToCartDetailBtn');
        const quantityInput = itemDetailModal.querySelector('#productDetailQuantity'); // IDをHTMLに合わせる
        const minusButton = itemDetailModal.querySelector('.quantity-btn.minus');
        const plusButton = itemDetailModal.querySelector('.quantity-btn.plus');

        if (closeButton) {
            closeButton.addEventListener('click', hideProductDetail); // 関数名を変更
        }
        // モーダル外クリックで閉じる (旧実装にあれば)
        itemDetailModal.addEventListener('click', function(event) {
            if (event.target === itemDetailModal) {
                hideProductDetail();
            }
        });
        
        if (addToCartButton && quantityInput) {
            addToCartButton.addEventListener('click', function() {
                if (!uiState.currentItemDetail) return;
                const quantity = parseInt(quantityInput.value, 10) || 1;
                if (typeof addToCart === 'function') { 
                    addToCart(uiState.currentItemDetail, quantity); // cart.jsの関数を呼ぶ想定
                    hideProductDetail();
                    showNotification('カートに追加しました', 'success');
                } else {
                    console.error('addToCart関数 (cart.js内想定) が見つかりません');
                }
            });
        }

        if (minusButton && quantityInput) {
            minusButton.addEventListener('click', function() {
                let value = parseInt(quantityInput.value, 10);
                if (value > 1) quantityInput.value = value - 1;
            });
        }
        if (plusButton && quantityInput) {
            plusButton.addEventListener('click', function() {
                let value = parseInt(quantityInput.value, 10);
                // 在庫上限なども考慮する場合はここにロジック追加
                quantityInput.value = value + 1;
            });
        }
        console.log('商品詳細モーダル初期化完了 (旧仕様確認)');
    } catch (error) {
        console.error('商品詳細モーダル初期化エラー:', error);
    }
}

/**
 * カートモーダルの初期化 (旧実装に合わせたカートタブの制御)
 */
function initCartModal() {
    console.log("initCartModal は旧仕様に基づき再検討中");
}

/**
 * 注文確認モーダルの初期化
 */
function initOrderConfirmationModal() {
    try {
        const modal = document.getElementById('orderConfirmationModal'); // HTMLのIDに合わせる
        if (!modal) {
            console.warn('注文確認モーダル (#orderConfirmationModal) が見つかりません');
            return;
        }
        const closeButton = modal.querySelector('[data-modal-close]');
        const confirmButton = modal.querySelector('#confirmOrderBtn'); // 仮のID
        const backButton = modal.querySelector('.back-button'); // 仮のクラス

        if (closeButton) closeButton.addEventListener('click', hideOrderConfirmation);
        if (backButton) backButton.addEventListener('click', hideOrderConfirmation); // 戻るボタンも閉じる動作
        
        if (confirmButton) {
            confirmButton.addEventListener('click', function() {
                if (typeof placeOrder === 'function') { 
                    // showLoading(); // 必要なら
                    placeOrder().then(result => {
                        // hideLoading(); // 必要なら
                        hideOrderConfirmation();
                        if (result && result.success) {
                            showOrderComplete(result.orderNumber);
                        } else {
                            showError(result.message || '注文処理に失敗しました。');
                        }
                    }).catch(err => {
                        // hideLoading();
                        showError('注文処理中にエラーが発生しました。');
                        console.error(err);
                    });
                } else {
                    console.error('placeOrder関数が見つかりません。');
                }
            });
        }
        console.log('注文確認モーダル初期化完了 (旧仕様確認)');
    } catch (error) {
        console.error('注文確認モーダル初期化エラー:', error);
    }
}

/**
 * 注文完了モーダルの初期化
 */
function initOrderCompleteModal() {
    try {
        const modal = document.getElementById('orderCompleteModal'); // HTMLのIDに合わせる
        if (!modal) {
            console.warn('注文完了モーダル (#orderCompleteModal) が見つかりません');
            return;
        }
        const closeButton = modal.querySelector('[data-modal-close]');
        // 旧実装で「メニューに戻る」ボタンなどがあれば、それにもイベント設定

        if (closeButton) closeButton.addEventListener('click', () => {
            hideOrderComplete();
            // 必要ならカートクリアやメニュースクリーンへの遷移
            const menuTab = document.querySelector('.tab[data-tab="menu"]');
            if (menuTab && !menuTab.classList.contains('active')) menuTab.click();
        });
        console.log('注文完了モーダル初期化完了 (旧仕様確認)');
    } catch (error) {
        console.error('注文完了モーダル初期化エラー:', error);
    }
}

// --- 以下、旧ui.jsになかったヘルパー関数や表示制御関数を追加・調整 ---

function showProductDetail(productId) {
    const product = getProductByIdLocal(productId); // 仮にローカルのitemDataから取得
    if (!product) {
        console.error('商品データ(ID: ' + productId + ')が見つかりません (ui.js)');
        return;
    }
    uiState.currentItemDetail = product;
    const modal = document.getElementById('productDetailModal');
    if (!modal) return;

    // モーダル内容の設定 (セレクタはindex.phpのHTMLに合わせる)
    modal.querySelector('#productDetailName').textContent = product.name;
    modal.querySelector('#productDetailImage').src = product.image_url || 'images/no-image.png';
    modal.querySelector('#productDetailImage').alt = product.name;
    modal.querySelector('#productDetailPrice').textContent = `¥${product.price.toLocaleString()}`;
    modal.querySelector('#productDetailCategory').textContent = product.category_name || 'カテゴリ未設定';
    modal.querySelector('#productDetailDescription').textContent = product.description || '商品説明はありません。';
    modal.querySelector('#productDetailQuantity').value = 1;

    const labelsContainer = modal.querySelector('#productDetailLabelsContainer');
    labelsContainer.innerHTML = ''; // ラベルをクリア
    if (product.labels && Array.isArray(product.labels)) {
        product.labels.forEach(label => {
            const labelEl = document.createElement('span');
            labelEl.className = 'product-label'; // CSSに合わせたクラス名
            labelEl.textContent = label.text;
            if(label.color) labelEl.style.backgroundColor = label.color;
            labelsContainer.appendChild(labelEl);
        });
    }
    // 在庫表示など (必要なら)
    showModal(modal);
    uiState.detailVisible = true;
}

function hideProductDetail() {
    hideModal(document.getElementById('productDetailModal'));
    uiState.detailVisible = false;
    uiState.currentItemDetail = null;
}

function showOrderConfirmation() {
    // renderOrderConfirmationItems(); // カート内容を描画する関数 (cart.js等で実装想定)
    showModal(document.getElementById('orderConfirmationModal'));
    uiState.confirmationVisible = true;
}

function hideOrderConfirmation() {
    hideModal(document.getElementById('orderConfirmationModal'));
    uiState.confirmationVisible = false;
}

function showOrderComplete(orderNumber) {
    const modal = document.getElementById('orderCompleteModal');
    if(modal) {
        const receiptEl = modal.querySelector('#receiptNumber');
        if(receiptEl) receiptEl.textContent = orderNumber || 'N/A';
        showModal(modal);
    }
    uiState.orderCompleteVisible = true;
}

function hideOrderComplete() {
    hideModal(document.getElementById('orderCompleteModal'));
    uiState.orderCompleteVisible = false;
}

function showLoading(message = '読み込み中...') {
    const loadingDiv = document.getElementById('loading');
    if(loadingDiv) {
        // 必要ならメッセージを設定
        loadingDiv.style.display = 'flex';
    }
    uiState.isLoading = true;
}

function hideLoading() {
    const loadingDiv = document.getElementById('loading');
    if(loadingDiv) loadingDiv.style.display = 'none';
    uiState.isLoading = false;
}

function showError(message) {
    const errorContainer = document.getElementById('error-container');
    const errorMessageEl = document.getElementById('error-message-text');
    if (errorContainer && errorMessageEl) {
        errorMessageEl.textContent = message;
        errorContainer.style.display = 'flex';
        hideLoading(); 
    } else {
        alert("エラー: " + message); 
    }
}

function showNotification(message, type = 'info') {
    // 簡単な通知バーやトースト通知を実装 (オプション)
    console.log(`[${type.toUpperCase()}] ${message}`);
    // 例: const notification = document.createElement('div'); ...
}

function getProductByIdLocal(productId) {
    if (window.itemData && window.itemData.products && Array.isArray(window.itemData.products)) {
        return window.itemData.products.find(p => p.id && p.id.toString() === productId.toString());
    }
    console.warn("window.itemData.products が見つからないか、配列ではありません。");
    return null;
}

/**
 * 画面サイズに応じたレイアウト調整 (旧ui.jsから持ってきたもの)
 */
function adjustLayoutForScreenSize() {
    const isMobile = window.innerWidth <= 768;
    const isSmallScreen = window.innerWidth <= 480;
        
    if (isMobile) {
        document.body.classList.add('mobile-view');
        if (isSmallScreen) {
            document.body.classList.add('small-screen');
        } else {
            document.body.classList.remove('small-screen');
        }
    } else {
        document.body.classList.remove('mobile-view', 'small-screen');
    }
}
window.addEventListener('resize', adjustLayoutForScreenSize);


/**
 * 遅延読み込みの初期化 (旧ui.jsに存在しないが、app.jsで呼び出されているため仮定義)
 */
function initLazyLoading() {
    console.log("遅延読み込み初期化 (仮)");
    // Intersection Observerを使った遅延読み込み処理などをここに実装
}

/**
 * その他のイベントハンドラーをセットアップ (旧ui.jsから)
 */
function setupEventHandlers() {
    // フッターの注文履歴ボタン
    const historyButton = document.getElementById('order-history-button');
    if (historyButton) {
        historyButton.addEventListener('click', () => {
            // showOrderHistoryModal(); // この関数を別途定義する必要あり
            console.log("注文履歴ボタンクリック (旧仕様ではui.jsで処理しない可能性あり)");
        });
    }

    // フッターの「注文へ進む」ボタン
    const viewCartButton = document.getElementById('view-cart-button');
    if (viewCartButton) {
        viewCartButton.addEventListener('click', () => {
            const cartTab = document.querySelector('.tab[data-tab="cart"]');
            if (cartTab) {
                cartTab.click(); // カートタブをアクティブにする
            } else {
                console.warn("カートタブが見つかりません。");
                // showCartModal(); // 直接カートモーダルを表示する場合 (HTML構造による)
            }
        });
    }
    console.log('UIイベントハンドラー設定完了 (旧仕様確認)');
}

// モーダル表示/非表示関数 (旧実装の制御方法に合わせる。クラス付与かstyle.displayか)
// ここでは仮にクラス active で制御する形とする。
function showModal(modalElement) {
    if (modalElement) {
        modalElement.classList.add('active'); // または modalElement.style.display = 'flex';
    } else {
        console.warn("表示対象のモーダル要素がありません。", modalElement);
    }
}
function hideModal(modalElement) {
    if (modalElement) {
        modalElement.classList.remove('active'); // または modalElement.style.display = 'none';
    } else {
        console.warn("非表示対象のモーダル要素がありません。", modalElement);
    }
}
