/**
 * UI関連の関数をまとめたファイル
 * 
 * このファイルは以下の責務を持ちます:
 * - UIコンポーネントの初期化とイベントハンドリング
 * - 画面の表示/非表示の切り替え
 * - UIの状態管理
 */

// グローバルアクセス用のUI関連ユーティリティ関数（どこからでも安全に呼び出せるようにする）
window.uiUtils = {
    // ローディング表示・非表示
    showLoading: function(message) {
        const loadingDiv = document.getElementById('loading');
        if(loadingDiv) {
            loadingDiv.style.display = 'flex';
            
            // メッセージ要素があれば更新
            const msgEl = loadingDiv.querySelector('p');
            if (msgEl && message) {
                msgEl.textContent = message;
            }
        }
    },
    
    hideLoading: function() {
        const loadingDiv = document.getElementById('loading');
        if(loadingDiv) {
            loadingDiv.style.display = 'none';
        }
    },
    
    // エラー表示
    showError: function(message) {
        const errorContainer = document.getElementById('error-container');
        const errorMessageEl = document.getElementById('error-message-text');
        
        // ローディングは非表示に
        this.hideLoading();
        
        if (errorContainer && errorMessageEl) {
            errorMessageEl.textContent = message;
            errorContainer.style.display = 'flex';
        } else {
            alert("エラー: " + message);
        }
    }
};

// 互換性のために従来の関数名でも提供
function hideLoading() {
    window.uiUtils.hideLoading();
}

function showLoading(message) {
    window.uiUtils.showLoading(message);
}

function showError(message) {
    window.uiUtils.showError(message);
}

// UIの状態（この変数は常にグローバル関数の後に宣言）
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
    
    try {
        /* タブ機能は廃止 */
    initDetailModal(); 
        initCartModal(); // カートモーダルの開閉を初期化
        initOrderHistoryModal(); // 注文履歴モーダルの開閉を初期化
    initOrderConfirmationModal();
    initOrderCompleteModal();
    adjustLayoutForScreenSize();
    // initLazyLoading(); // 旧ui.jsに存在しない場合は削除またはapp.jsに移動
    setupEventHandlers(); // 旧ui.jsのイベントハンドラをここに集約
    
        // 初期化完了後にローディングを非表示
        hideLoading();
    
    console.log('UI初期化が完了しました (旧仕様ベースに調整)');
        
        // uiStateにローディング状態を反映
        uiState.isLoading = false;
        
        const refreshBtn = document.getElementById('refresh-button');
        if (refreshBtn && !refreshBtn.dataset.bound) {
            refreshBtn.addEventListener('click', () => location.reload());
            refreshBtn.dataset.bound = 'true';
        }
        
        return true;
    } catch (error) {
        console.error('UI初期化エラー:', error);
        showError('UI初期化エラー: ' + error.message);
        return false;
    }
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
 * 商品詳細モーダルの初期化
 */
function initDetailModal() {
    try {
        const itemDetailModal = document.getElementById('item-detail');
        if (!itemDetailModal) {
            console.warn('商品詳細モーダル要素 (#item-detail) が見つかりません');
            return;
        }
        const closeButton = itemDetailModal.querySelector('.item-detail-close');
        const addToCartButton = itemDetailModal.querySelector('#add-to-cart-detail-btn');
        const quantityInput = itemDetailModal.querySelector('#detail-quantity');
        const minusButton = itemDetailModal.querySelector('.quantity-button.minus, .quantity-control .minus');
        const plusButton = itemDetailModal.querySelector('.quantity-button.plus, .quantity-control .plus');
        // 数字表示用 span を準備
        let qtyDisplay = itemDetailModal.querySelector('.quantity-display');
        if(!qtyDisplay){
            qtyDisplay = document.createElement('span');
            qtyDisplay.className = 'quantity-display';
            // input の後ろに挿入
            if(quantityInput && quantityInput.parentNode){
                quantityInput.parentNode.insertBefore(qtyDisplay, quantityInput.nextSibling);
            }
        }
        qtyDisplay.textContent = '1';
        if(quantityInput) quantityInput.style.display = 'none'; // input を視覚的に隠す

        if (closeButton) {
            closeButton.addEventListener('click', hideProductDetail);
        }
        itemDetailModal.addEventListener('click', function(event) {
            if (event.target === itemDetailModal) {
                hideProductDetail();
            }
        });
        
        if (addToCartButton && qtyDisplay) {
            addToCartButton.addEventListener('click', function() {
                if (!uiState.currentItemDetail) return;
                const quantity = parseInt(qtyDisplay.textContent, 10) || 1;
                if (typeof addToCart === 'function') { 
                    addToCart(uiState.currentItemDetail, quantity);
                    hideProductDetail();
                    showNotification('カートに追加しました', 'success');
                } else {
                    console.error('addToCart関数 (cart.js内想定) が見つかりません');
                }
            });
        }

        if (minusButton && qtyDisplay) {
            minusButton.addEventListener('click', function() {
                let value = parseInt(qtyDisplay.textContent, 10);
                if (value > 1) value -= 1;
                qtyDisplay.textContent = value;
            });
        }
        if (plusButton && qtyDisplay) {
            plusButton.addEventListener('click', function() {
                let value = parseInt(qtyDisplay.textContent, 10);
                value += 1;
                qtyDisplay.textContent = value;
            });
        }
        console.log('商品詳細モーダル初期化完了 (旧仕様確認)');
    } catch (error) {
        console.error('商品詳細モーダル初期化エラー:', error);
    }
}

/**
 * カートモーダルの初期化
 */
function initCartModal() {
    try {
        const cartModal = document.getElementById('cartModal');
        if (!cartModal) {
            console.warn('#cartModal が見つかりません');
            return;
        }
        const closeBtn = document.getElementById('closeCartModal');
        const checkoutBtn = document.getElementById('finalCheckoutBtn');
        const backBtn = document.getElementById('backToCartTabBtn');
        // × ボタン
        if (closeBtn) {
            closeBtn.addEventListener('click', () => hideModal(cartModal));
        }
        // バックドロップクリック
        cartModal.addEventListener('click', (e) => {
            if (e.target === cartModal) hideModal(cartModal);
        });
        // 注文を追加する (カートモーダルを閉じるだけ)
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                hideModal(cartModal);
            });
        }
        // 注文へ進む（確認モーダルへ）
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', async () => {
                try {
                    // 二重送信防止
                    if (checkoutBtn.disabled) return;

                    const originalHtml = checkoutBtn.innerHTML;
                    checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    checkoutBtn.disabled = true;
                    checkoutBtn.classList.add('loading');

                    if (typeof placeOrder !== 'function') {
                        showError('注文処理機能が利用できません');
                        return;
                    }
                    showLoading('注文を送信しています...');
                    const result = await placeOrder();
                    hideLoading();
                    if (result && result.success) {
                        hideModal(cartModal);
                        showOrderComplete(result.orderNumber, result.sessionId);
                    } else {
                        showError(result.message || '注文処理に失敗しました');
                    }
                } catch (e) {
                    hideLoading();
                    showError('注文処理中にエラーが発生しました');
                    console.error(e);
                } finally {
                    // ボタン状態を復元
                    checkoutBtn.disabled = false;
                    checkoutBtn.classList.remove('loading');
                    checkoutBtn.innerHTML = originalHtml;
                }
            });
        }
        console.log('カートモーダル初期化完了');
    } catch (err) {
        console.error('カートモーダル初期化エラー:', err);
    }
}

/**
 * 注文確認モーダルの初期化
 */
function initOrderConfirmationModal() {
    try {
        const modal = document.getElementById('order-confirmation');
        if (!modal) {
            console.warn('注文確認モーダル (#order-confirmation) が見つかりません');
            return;
        }
        const closeButton = modal.querySelector('#cancel-order-button');
        const confirmButton = modal.querySelector('#confirm-order-button');
        const backButton = modal.querySelector('#cancel-confirmation-action-btn');

        if (closeButton) closeButton.addEventListener('click', hideOrderConfirmation);
        if (backButton) backButton.addEventListener('click', hideOrderConfirmation);
        
        if (confirmButton) {
            confirmButton.addEventListener('click', function() {
                if (typeof placeOrder === 'function') { 
                    placeOrder().then(result => {
                        hideOrderConfirmation();
                        if (result && result.success) {
                            showOrderComplete(result.orderNumber, result.sessionId);
                        } else {
                            showError(result.message || '注文処理に失敗しました。');
                        }
                    }).catch(err => {
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
        const modal = document.getElementById('order-complete');
        if (!modal) {
            console.warn('注文完了モーダル (#order-complete) が見つかりません');
            return;
        }
        const closeButton = modal.querySelector('#return-to-menu-button');

        if (closeButton) closeButton.addEventListener('click', () => {
            hideOrderComplete();
        });
        // バックドロップクリックで閉じる
        modal.addEventListener('click', (e)=>{
            if(e.target===modal) hideOrderComplete();
        });
        console.log('注文完了モーダル初期化完了 (旧仕様確認)');
    } catch (error) {
        console.error('注文完了モーダル初期化エラー:', error);
    }
}

// --- 以下、旧ui.jsになかったヘルパー関数や表示制御関数を追加・調整 ---

function showProductDetail(productId) {
    const product = getProductByIdLocal(productId);
    console.log('[showProductDetail] 取得商品データ:', product);
    if (!product) {
        console.error('商品データ(ID: ' + productId + ')が見つかりません (ui.js)');
        return;
    }
    uiState.currentItemDetail = product;
    const modal = document.getElementById('item-detail');
    if (!modal) return;

    // 基本情報
    modal.querySelector('#productDetailName').textContent = product.name;
    modal.querySelector('#productDetailImage').src = product.image_url || 'images/no-image.png';
    modal.querySelector('#productDetailImage').alt = product.name;
    modal.querySelector('#productDetailPrice').textContent = `¥${Math.round(parseFloat(product.price)).toLocaleString()}-(税抜)`;

    // カテゴリ
    const categoryEl = modal.querySelector('#productDetailCategory');
    if (categoryEl) {
        categoryEl.textContent = product.category_name || 'カテゴリ未設定';

        /* ラベルをカテゴリ横に表示 */
        const formatLabel = (lbl) => {
            if (!lbl || !lbl.text) return '';
            let color = lbl.color || '';
            if (color && !color.startsWith('#')) color = '#' + color;
            return `<span class="product-detail-label" style="background-color:${color};">${lbl.text}</span>`;
        };
        let labelHTML = '';
        if (product.item_label1 && product.label1) labelHTML += formatLabel(product.label1);
        if (product.item_label2 && product.label2) labelHTML += formatLabel(product.label2);
        if (labelHTML === '' && Array.isArray(product.labels) && product.labels.length > 0) {
            const maxLabels = 2;
            for (let i = 0; i < Math.min(maxLabels, product.labels.length); i++) {
                labelHTML += formatLabel(product.labels[i]);
            }
        }
        console.log('[showProductDetail] 生成したラベルHTML:', labelHTML);
        // 既存のラベルをクリアして再挿入
        const existingSpans = categoryEl.querySelectorAll('.product-detail-label');
        existingSpans.forEach(s => s.remove());
        categoryEl.insertAdjacentHTML('beforeend', labelHTML);
    }

    // 説明
    const descriptionEl = modal.querySelector('#productDetailDescription');
    if (descriptionEl) {
        descriptionEl.textContent = product.description || '商品説明はありません。';
    }

    // 数量初期化
    const qtyInput = modal.querySelector('#detail-quantity');
    if (qtyInput) qtyInput.value = 1;
    const qtyDisplay = modal.querySelector('.quantity-display');
    if (qtyDisplay) qtyDisplay.textContent = '1';

    /* カート内に既にある数量を表示 (メッセージを数量コントロールの右側に表示) */
    const qtyCtrl = modal.querySelector('.quantity-control');
    if (qtyCtrl) {
        let existMsgEl = modal.querySelector('#detailCartExistMessage');
        if (!existMsgEl) {
            existMsgEl = document.createElement('span');
            existMsgEl.id = 'detailCartExistMessage';
            existMsgEl.className = 'cart-exist-message';
            qtyCtrl.appendChild(existMsgEl);
        }
        const inCartItem = (typeof window.getCartItems === 'function') ?
            (window.getCartItems().find(it => String(it.id) === String(product.id)) || null) : null;
        if (inCartItem && inCartItem.quantity > 0) {
            existMsgEl.textContent = `現在カート内に ${inCartItem.quantity} つあります`;
            existMsgEl.style.display = 'inline-block';
        } else {
            existMsgEl.style.display = 'none';
        }
    }

    showModal(modal);
    uiState.detailVisible = true;
}

function hideProductDetail() {
    hideModal(document.getElementById('item-detail'));
    uiState.detailVisible = false;
    uiState.currentItemDetail = null;
}

function showOrderConfirmation() {
    showModal(document.getElementById('order-confirmation'));
    uiState.confirmationVisible = true;
}

function hideOrderConfirmation() {
    hideModal(document.getElementById('order-confirmation'));
    uiState.confirmationVisible = false;
}

function showOrderComplete(orderNumber, sessionId) {
    const modal = document.getElementById('order-complete');
    if(modal) {
        const receiptEl = modal.querySelector('#receiptNumber');
        if(receiptEl) {
            const idText = sessionId ? `ID:${sessionId}` : '';
            const numText = orderNumber ? `Order No:${orderNumber}` : 'N/A';
            receiptEl.innerHTML = `<span>${idText}</span><span class="order-no">${numText}</span>`;
        }
        showModal(modal);
    }
    uiState.orderCompleteVisible = true;
}

function hideOrderComplete() {
    hideModal(document.getElementById('order-complete'));
    uiState.orderCompleteVisible = false;
}

function showNotification(message, type = 'info') {
    console.log(`[${type.toUpperCase()}] ${message}`);
}

function getProductByIdLocal(productId) {
    // productId が null/undefined の場合は即座に null を返す (toString エラー回避)
    if (productId === null || typeof productId === 'undefined') {
        console.warn('getProductByIdLocal: productId が null/undefined です');
        return null;
    }
    if (window.itemData && window.itemData.products && Array.isArray(window.itemData.products)) {
        return window.itemData.products.find(p => {
            const idMatch = p.id && p.id.toString() === productId.toString();
            const sqIdMatch = p.square_item_id && p.square_item_id.toString() === productId.toString();
            return idMatch || sqIdMatch;
        });
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
}

/**
 * その他のイベントハンドラーをセットアップ (旧ui.jsから)
 */
function setupEventHandlers() {
    const historyButton = document.getElementById('order-history-button');
    if (historyButton) {
        historyButton.addEventListener('click', () => {
            console.log("注文履歴ボタンクリック (旧仕様ではui.jsで処理しない可能性あり)");
        });
    }

    /* タブ関連イベントは廃止 */

    console.log('UIイベントハンドラー設定完了 (旧仕様確認)');
}

// モーダル表示/非表示関数
function showModal(modalElement) {
    if (modalElement) {
        modalElement.classList.add('active');
    } else {
        console.warn("表示対象のモーダル要素がありません。", modalElement);
    }
}
function hideModal(modalElement) {
    if (modalElement) {
        modalElement.classList.remove('active');
    } else {
        console.warn("非表示対象のモーダル要素がありません。", modalElement);
    }
}

/**
 * 注文履歴モーダル初期化
 */
function initOrderHistoryModal() {
    try {
        const historyModal = document.getElementById('orderHistoryModal');
        if (!historyModal) {
            console.warn('#orderHistoryModal が見つかりません');
            return;
        }
        const closeBtn = document.getElementById('closeOrderHistoryModal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => hideModal(historyModal));
        }
        // バックドロップクリック
        historyModal.addEventListener('click', (e) => {
            if (e.target === historyModal) hideModal(historyModal);
        });
        // 表示トリガー（注文履歴ボタン）
        const historyButton = document.getElementById('order-history-button');
        if (historyButton) {
            historyButton.addEventListener('click', () => showModal(historyModal));
        }
        console.log('注文履歴モーダル初期化完了');
    } catch (err) {
        console.error('注文履歴モーダル初期化エラー:', err);
    }
}

// ===== カートバッジ更新 =====
function onCartUpdated() {
    try {
        const cartItems = (typeof window.getCartItems === 'function') ? window.getCartItems() : [];
        const productCards = document.querySelectorAll('.product-item');
        productCards.forEach(card => {
            const productId = card.getAttribute('data-product-id');
            const cartItem = cartItems.find(it => String(it.id) === String(productId));
            let badge = card.querySelector('.product-cart-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'product-cart-badge';
                card.appendChild(badge);
            }
            if (cartItem && cartItem.quantity > 0) {
                badge.textContent = cartItem.quantity;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
    } catch (e) {
        console.error('onCartUpdated error', e);
    }
}
window.onCartUpdated = onCartUpdated;

document.querySelectorAll('.add-to-cart-button').forEach(button => {
    const productId = button.getAttribute('data-product-id');
    // data-product-id が無い (詳細モーダルなど) ボタンは対象外
    if (!productId) return;

    button.addEventListener('click', (event) => {
        event.preventDefault();
        const product = (typeof getProductByIdLocal === 'function') ? getProductByIdLocal(productId) : null;
        if (product) {
            addToCart(product, 1);
        } else {
            console.error('add-to-cart: 商品データが取得できません', productId);
        }
    });
});
