cart.js
/**
 * カート機能を管理するモジュール
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
    const logPrefix = `[CART.JS][${timestamp}][${level}]`;
    
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

// このスクリプト自体の読み込みをログ
if (typeof window.logScriptLoad === 'function') {
    window.logScriptLoad('cart.js');
}

// cart.js初期化ログ
detailedLog('カートモジュール読み込み開始', 'INFO');

// カート内商品を保持する配列
let cartItems = [];

// カートの状態管理
const cartState = {
    isLoading: false,
    initialized: false,
    lastUpdate: null
};

// 消費税率
const TAX_RATE = 0.1;

// 括弧チェック機能の追加
function checkBrackets(codeString, description) {
    const openingBrackets = { '(': 0, '{': 0, '[': 0 };
    const closingBrackets = { ')': '(', '}': '{', ']': '[' };
    const stack = [];
    let lineCount = 1;
    let charCount = 0;
    let errors = [];

    for (let i = 0; i < codeString.length; i++) {
        const char = codeString[i];
        charCount++;

        // 改行文字のカウント
        if (char === '\n') {
            lineCount++;
            charCount = 0;
            continue;
        }

        // 開き括弧の処理
        if (openingBrackets.hasOwnProperty(char)) {
            stack.push({ bracket: char, line: lineCount, char: charCount });
            openingBrackets[char]++;
            continue;
        }

        // 閉じ括弧の処理
        if (closingBrackets.hasOwnProperty(char)) {
            const matchingOpenBracket = closingBrackets[char];
            
            if (stack.length === 0) {
                errors.push({
                    type: '余分な閉じ括弧',
                    bracket: char,
                    line: lineCount,
                    char: charCount
                });
                continue;
            }

            const lastBracket = stack.pop();
            if (lastBracket.bracket !== matchingOpenBracket) {
                errors.push({
                    type: '括弧の不一致',
                    expected: matchingOpenBracket,
                    found: lastBracket.bracket,
                    line: lineCount,
                    char: charCount
                });
            }
            
            openingBrackets[matchingOpenBracket]--;
        }
    }

    // 閉じていない括弧の確認
    stack.forEach(item => {
        errors.push({
            type: '閉じていない括弧',
            bracket: item.bracket,
            line: item.line,
            char: item.char
        });
    });

    // 結果の出力
    console.log('[BRACKET_CHECK] ' + description + ' の括弧チェック結果:');
    console.log('- 丸括弧 \'()\': ' + openingBrackets['('] + ' 個');
    console.log('- 波括弧 \'{}\': ' + openingBrackets['{'] + ' 個');
    console.log('- 角括弧 \'[]\': ' + openingBrackets['['] + ' 個');
    
    if (errors.length > 0) {
        console.error('[BRACKET_CHECK] ' + errors.length + '個のエラーが見つかりました:', errors);
    } else {
        console.log('[BRACKET_CHECK] エラーなし: すべての括弧が正しく対応しています');
    }
    
    return { errors, counts: openingBrackets };
}

// グローバルアクセスのための初期化関数
function initCart() {
    console.log('カート初期化開始');
    
    // ローカルストレージからカートを読み込む
    loadCartFromStorage();
    
    // カート関連のイベントリスナーをセットアップ
    setupCartEventListeners();
    
    // カートの状態を更新
    cartState.initialized = true;
    cartState.lastUpdate = new Date();
    
    console.log('カート初期化完了');
}

/**
 * カート関連のイベントリスナーをセットアップ
 */
function setupCartEventListeners() {
    // カートアイコンのクリックイベント
    const cartBtn = document.querySelector('.tab[data-tab="cart"]');
    if (cartBtn) {
        cartBtn.addEventListener('click', function() {
            console.log('カートタブをクリック');
            updateCartUI();
        });
    } else {
        console.warn('カートタブボタンが見つかりません');
    }
    
    // 既存のカートアイテムの数量変更ボタンにイベントを設定
    if (typeof updateCartItemEvents === 'function') {
        console.log('カートアイテムイベントの初期化を開始します');
        try {
            updateCartItemEvents();
        } catch (error) {
            console.warn('カートアイテムイベント初期化中にエラーが発生しましたが、続行します:', error);
        }
    } else {
        console.warn('updateCartItemEvents関数が定義されていないため、スキップします');
    }
}

/**
 * カートに商品を追加
 * @param {Object} product - 追加する商品
 * @param {number} quantity - 数量
 */
function addToCart(product, quantity = 1) {
    if (!product || !product.id) {
        console.error('無効な商品データです');
        return;
    }
    
    console.log(`カートに商品を追加: ID=${product.id}, 名前=${product.name}, 数量=${quantity}`);
    
    // 既に商品がカートにあるか確認
    const existingItem = cartItems.find(item => item.id === product.id);
    
    if (existingItem) {
        // 既存アイテムの数量を更新
        existingItem.quantity += quantity;
        console.log(`既存商品の数量を更新: ID=${product.id}, 新数量=${existingItem.quantity}`);
    } else {
        // 新規アイテムとして追加
        cartItems.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image_url: product.image_url || '',
            quantity: quantity
        });
        console.log(`新規商品をカートに追加: ID=${product.id}`);
    }
    
    // カートの状態を保存
    saveCartToStorage();
    
    // UIを更新
    updateCartBadge();
    updateCartUI();
    
    // カスタムイベントを発行
    dispatchCartUpdatedEvent();
}

/**
 * カートから商品を削除
 * @param {string} productId - 削除する商品のID
 */
function removeFromCart(productId) {
    try {
        console.log('removeFromCart: 商品ID "' + productId + '" を削除開始');
        
        // 削除前のカートの状態を確認
        const beforeCount = cartItems.length;
        
        // 商品をカートから削除 - 文字列として比較
        cartItems = cartItems.filter(item => String(item.id) !== String(productId));
        
        const afterCount = cartItems.length;
        console.log(`removeFromCart: ${beforeCount} → ${afterCount} 件 (${beforeCount - afterCount}件削除)`);
        
        // カートの状態を保存
        saveCartToStorage();
        
        // カート表示を更新
        updateCartUI();
        
        // 商品カードのバッジを更新
        updateProductBadges();
        
        return true;
    } catch (error) {
        console.error(`removeFromCart エラー: 商品ID "${productId}" の削除中にエラーが発生しました`, error);
        // エラーが発生しても処理を続行するため、カートの同期を強制的に実行
        forceCartSync();
        return false;
    }
}

/**
 * カート内商品の数量を更新
 * @param {string} productId - 更新する商品のID
 * @param {number} quantity - 新しい数量
 */
function updateQuantity(productId, quantity) {
    try {
        console.log(`updateQuantity: 商品ID "${productId}" の数量を ${quantity} に更新`);
        
        // 数量が0以下の場合は商品を削除
        if (quantity <= 0) {
            return removeFromCart(productId);
        }
        
        // カート内の商品を検索して数量を更新 - 文字列として比較
        const itemIndex = cartItems.findIndex(item => String(item.id) === String(productId));
        
        if (itemIndex !== -1) {
            cartItems[itemIndex].quantity = quantity;
            
            // カートの状態を保存
            saveCartToStorage();
            
            // カート表示を更新
            updateCartUI();
            
            // 商品カードのバッジを更新
            updateProductBadges();
            
            return true;
        }
        
        console.warn(`updateQuantity: 商品ID "${productId}" がカートに見つかりません`);
        return false;
    } catch (error) {
        console.error(`updateQuantity エラー: 商品ID "${productId}" の数量更新中にエラーが発生しました`, error);
        // エラーが発生しても処理を続行するため、カートの同期を強制的に実行
        forceCartSync();
        return false;
    }
}

/**
 * カートをクリア
 */
function clearCart() {
    cartItems = [];
    
    // カートの状態を保存
    saveCartToStorage();
    
    // カート表示を更新
    updateCartUI();
    
    // 商品カードのバッジを更新
    updateProductBadges();
    
    // 任意: サーバーにカート内容を保存（APIがある場合）
    // saveCartToServer();
    
    return true;
}

/**
 * カート内の商品数を取得
 * @returns {number} 商品数
 */
function getCartItemCount() {
    return cartItems.reduce((total, item) => total + item.quantity, 0);
}

/**
 * カート内の小計（税抜き）を計算
 * @returns {number} 小計
 */
function calculateSubtotal() {
    return cartItems.reduce((total, item) => {
        return total + (item.price * item.quantity);
    }, 0);
}

/**
 * 消費税額を計算
 * @param {number} subtotal - 小計
 * @returns {number} 消費税額
 */
function calculateTax(subtotal) {
    return Math.round(subtotal * TAX_RATE);
}

/**
 * 合計金額（税込み）を計算
 * @returns {number} 合計金額
 */
function calculateTotal() {
    const subtotal = calculateSubtotal();
    const tax = calculateTax(subtotal);
    return subtotal + tax;
}

/**
 * カートの内容をローカルストレージに保存
 */
function saveCartToStorage() {
    try {
        localStorage.setItem('cartItems', JSON.stringify(cartItems));
    } catch (error) {
        console.error('カートデータの保存に失敗しました:', error);
    }
}

/**
 * ローカルストレージからカートの内容を読み込み
 */
function loadCartFromStorage() {
    try {
        const storedCart = localStorage.getItem('cartItems');
        if (storedCart) {
            cartItems = JSON.parse(storedCart);
            updateCartUI();
        }
    } catch (error) {
        console.error('カートデータの読み込みに失敗しました:', error);
    }
}

/**
 * カートデータの整合性をチェックし強制的に同期する
 * 無効なアイテムを削除し、UIを最新状態に更新する
 * @param {boolean} resetIfEmpty 同期後カートが空であるかエラーが発生した場合に完全リセットするかどうか
 */
function forceCartSync(resetIfEmpty = false) {
    try {
        console.log('カート同期開始 - 現在のアイテム:', JSON.stringify(cartItems));
        
        // 配列でない場合は初期化
        if (!Array.isArray(cartItems)) {
            console.warn('カートデータが配列ではありません。カートを初期化します。');
            cartItems = [];
        }
        
        // デバッグ: 各商品のID型を確認
        if (cartItems.length > 0) {
            console.log('カート内商品IDの型チェック:');
            cartItems.forEach(item => {
                console.log(`商品ID: ${item.id}, 型: ${typeof item.id}`);
            });
        }
        
        // カート内で有効なアイテムのみを保持（無効なデータを除去）
        const originalCount = cartItems.length;
        cartItems = cartItems.filter(item => 
            item && 
            typeof item === 'object' && 
            item.id && 
            item.name && 
            typeof item.price !== 'undefined' && 
            typeof item.quantity === 'number' && 
            item.quantity > 0
        );
        
        // フィルタリング結果をログに出力
        console.log(`カートデータフィルタリング: ${originalCount}件中${cartItems.length}件が有効 (${originalCount - cartItems.length}件削除)`);
        
        // 重複するIDがある場合は最新のもののみ残す
        const uniqueItems = {};
        cartItems.forEach(item => {
            // 文字列キーとして保存して型の違いによる重複を防止
            uniqueItems[String(item.id)] = item;
        });
        
        const beforeDedup = cartItems.length;
        cartItems = Object.values(uniqueItems);
        
        if (beforeDedup !== cartItems.length) {
            console.log(`重複データ除去: ${beforeDedup}件から${cartItems.length}件に削減 (${beforeDedup - cartItems.length}件の重複を削除)`);
        }
        
        // カートが空になった場合のリセット
        if (resetIfEmpty && cartItems.length === 0) {
            console.warn('カートが空になりました。カートをリセットします。');
            resetCart();
            return;
        }
        
        // 変更をストレージに保存
        saveCartToStorage();
        
        // UIを最新の状態に更新
        updateCartUI();
        
        // 商品カードのバッジを更新
        updateProductBadges();
        
        console.log('カート同期完了 - 現在のアイテム:', JSON.stringify(cartItems));
    } catch (error) {
        console.error('カート同期中にエラーが発生しました:', error);
        if (resetIfEmpty) {
            console.warn('エラーが発生したためカートをリセットします');
            resetCart();
        }
    }
}

/**
 * カートを完全にリセット（同期エラー時の最終手段）
 */
function resetCart() {
    console.warn('カートを完全にリセットします');
    
    // カートを空にする
    cartItems = [];
    
    // ローカルストレージからもカートデータを削除
    try {
        localStorage.removeItem('cartItems');
    } catch (error) {
        console.error('カートデータの削除に失敗しました:', error);
    }
    
    // カート表示を更新
    updateCartUI();
    
    // 商品カードのバッジを更新
    updateProductBadges();
    
    console.log('カートリセット完了');
}

/**
 * カートの表示を更新
 */
function updateCartUI() {
    // カート内商品数の表示更新
    const itemCount = getCartItemCount();
    const cartBadge = document.getElementById('cart-badge');
    if (cartBadge) {
        cartBadge.textContent = itemCount;
    }
    
    // 合計金額の表示更新
    const total = calculateTotal();
    const cartTotal = document.getElementById('cart-total');
    if (cartTotal) {
        cartTotal.textContent = formatPrice(total);
    }
    
    // 注文ボタンの有効/無効状態を更新
    const orderButton = document.getElementById('order-button');
    if (orderButton) {
        orderButton.disabled = itemCount === 0;
    }
    
    // カートモーダル内の内容を更新（モーダルが開いている場合）
    updateCartModal();
}

/**
 * カートモーダル内の内容を更新
 */
function updateCartModal() {
    const cartItemsContainer = document.getElementById('cart-items');
    if (!cartItemsContainer) return;
    
    // カート内アイテムリストを更新
    cartItemsContainer.innerHTML = '';
    
    if (cartItems.length === 0) {
        cartItemsContainer.innerHTML = '<p class="empty-cart">カートに商品がありません</p>';
    } else {
        cartItems.forEach(item => {
            const itemElement = createCartItemElement(item);
            cartItemsContainer.appendChild(itemElement);
        });
    }
    
    // 金額情報を更新
    const subtotal = calculateSubtotal();
    const tax = calculateTax(subtotal);
    const total = subtotal + tax;
    
    document.getElementById('cart-subtotal').textContent = formatPrice(subtotal);
    document.getElementById('cart-tax').textContent = formatPrice(tax);
    document.getElementById('cart-modal-total').textContent = formatPrice(total);
    
    // 数量調整ボタンとカートアイテムの削除ボタンにイベントリスナーを追加
    // 安全に共通関数を呼び出す
    if (typeof updateCartItemEvents === 'function') {
        updateCartItemEvents(cartItemsContainer);
    } else {
        console.warn('updateCartItemEvents関数が見つかりません。カート項目のイベントを個別に設定します。');
        // フォールバックコード（必要な場合）
    }
}

/**
 * カート内商品のHTML要素を作成
 * @param {Object} item - カート内商品
 * @returns {HTMLElement} 商品要素
 */
function createCartItemElement(item) {
    const itemElement = document.createElement('div');
    itemElement.className = 'cart-item';
    
    const subtotal = item.price * item.quantity;
    
    itemElement.innerHTML = `
        <div class="cart-item-image">
            <img src="${item.image || 'images/no-image.png'}" alt="${item.name}">
        </div>
        <div class="cart-item-details">
            <div class="cart-item-title">${item.name}</div>
            <div class="cart-item-price">${formatPrice(item.price)}</div>
            <div class="cart-item-actions">
                <div class="cart-quantity-control">
                    <button class="cart-quantity-btn minus" data-id="${item.id}">-</button>
                    <span class="cart-quantity-value">${item.quantity}</span>
                    <button class="cart-quantity-btn plus" data-id="${item.id}">+</button>
                </div>
                <button class="cart-item-delete" data-id="${item.id}">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <div class="cart-item-subtotal">${formatPrice(subtotal)}</div>
    `;
    
    // 削除ボタンにイベントリスナーを追加
    const deleteButton = itemElement.querySelector('.cart-item-delete');
    if (deleteButton) {
        deleteButton.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            if (productId) {
                console.log('商品ID=' + productId + 'を削除します');
                removeFromCart(productId);
            } else {
                console.error('削除ボタンに商品IDが設定されていません');
            }
        });
    }
    
    return itemElement;
}

/**
 * 価格を表示用にフォーマット
 * @param {number} price - 価格
 * @returns {string} フォーマットされた価格
 */
function formatPrice(price) {
    return `¥${price.toLocaleString()}`;
}

/**
 * カートの内容をサーバーに保存（APIがある場合）
 */
async function saveCartToServer() {
    try {
        if (typeof saveCart === 'function') {
            await saveCart(cartItems);
        }
    } catch (error) {
        console.error('サーバーへのカート保存に失敗しました:', error);
    }
}

/**
 * サーバーからカートの内容を読み込み（APIがある場合）
 */
async function loadCartFromServer() {
    try {
        if (typeof getCart === 'function') {
            const serverCart = await getCart();
            if (serverCart && serverCart.length > 0) {
                cartItems = serverCart;
                updateCartUI();
            }
        }
    } catch (error) {
        console.error('サーバーからのカート読み込みに失敗しました:', error);
    }
}

/**
 * 注文データの作成
 * @param {string} notes - 注文備考
 * @returns {Object} 注文データ
 */
function createOrderData(notes = '') {
    // LINE User IDがある場合は使用、ない場合はnull
    const lineUserId = typeof userProfile !== 'undefined' && userProfile ? userProfile.userId : null;
    
    return {
        line_user_id: lineUserId, // 明示的にLINE User IDを含める
        room_number: roomInfo ? roomInfo.room_number : null,
        items: cartItems.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            price: item.price
        })),
        subtotal: calculateSubtotal(),
        tax: calculateTax(calculateSubtotal()),
        total: calculateTotal(),
        notes: notes
    };
}

/**
 * 商品カードのバッジを更新するヘルパー関数
 * ui.jsで定義されたグローバル関数を呼び出す
 */
function updateProductBadges() {
    console.log('カートバッジ更新を要求');
    
    try {
        // ui.jsで定義されたグローバル関数が利用可能な場合
        if (typeof window.onCartUpdated === 'function') {
            // onCartUpdated関数を介してバッジを更新
            setTimeout(() => {
                window.onCartUpdated();
                console.log('onCartUpdated経由でバッジ更新');
            }, 100);
        } else if (typeof window.updateProductCartBadges === 'function') {
            // 直接updateProductCartBadgesを呼び出す
            setTimeout(() => {
                window.updateProductCartBadges();
                console.log('updateProductCartBadges経由でバッジ更新');
            }, 100);
        } else {
            console.warn('バッジ更新関数が見つかりません');
        }
    } catch (error) {
        console.error('バッジ更新中にエラーが発生しました:', error);
    }
}

// DOMContentLoadedイベントのリスナーを修正
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM読み込み完了 - カート初期化を待機中');
    
    // LIFF初期化を待機してからカートを初期化
    if (window.appState && window.appState.liffInitialized) {
        // すでに初期化済みの場合は直接初期化
        console.log('LIFF既に初期化済み - カート初期化を開始');
        initCart();
    }
});

// app-readyイベントリスナーを追加
document.addEventListener('app-ready', function(event) {
    console.log('app-readyイベント受信 - カート初期化チェック');
    
    // まだ初期化されていなければ初期化
    if (!cartState.initialized) {
        console.log('カートが未初期化のため初期化を開始');
        initCart();
    } else {
        console.log('カートは既に初期化済み');
    }
});

// グローバルスコープに関数を明示的に追加（コンソールでのデバッグ用）
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.updateQuantity = updateQuantity;
window.clearCart = clearCart;
window.updateCartUI = updateCartUI;
window.updateCartModal = updateCartModal;
window.getCartItemCount = getCartItemCount;
window.calculateTotal = calculateTotal;
window.forceCartSync = forceCartSync;
window.resetCart = resetCart;
window.updateProductBadges = updateProductBadges; // バッジ更新関数もグローバルに公開

// カートの状態をコンソールから確認するための関数
window.getCartItems = function() {
    return [...cartItems]; // 配列のコピーを返す
};

// 括弧不足のエラーが報告されている付近（55〜65行付近）に以下のようなログを追加
// 例: 行番号55〜60付近
detailedLog('チェックポイント: #1 - 55行付近', 'DEBUG');
// [実際のコード]
detailedLog('チェックポイント: #2 - 60行付近', 'DEBUG');
// [実際のコード]
detailedLog('チェックポイント: #3 - 65行付近', 'DEBUG');

// ファイル末尾
detailedLog('カートモジュール読み込み完了', 'INFO');

/**
 * カート内のアイテム操作イベントを更新する関数
 * @param {HTMLElement} parent - イベントを適用する親要素（デフォルトはdocument）
 */
function updateCartItemEvents(parent = document) {
    try {
        // カート内の数量調整ボタンにイベントリスナーを設定
        const quantityButtons = parent.querySelectorAll('.cart-quantity-btn');
        
        if (quantityButtons.length === 0) {
            console.log('カート内に数量調整ボタンが見つかりません');
            return;
        }
        
        console.log(`カート内数量調整ボタン: ${quantityButtons.length}個見つかりました`);
        
        quantityButtons.forEach(btn => {
            const productId = btn.getAttribute('data-id');
            if (!productId) {
                console.warn('数量ボタンに商品IDがありません');
                return;
            }
            
            btn.addEventListener('click', function() {
                // 文字列として比較
                const currentItem = cartItems.find(item => String(item.id) === String(productId));
                
                // 商品が見つからない場合は処理を中止（エラー防止）
                if (!currentItem) {
                    console.error(`カートに商品ID "${productId}" が見つかりません`);
                    return;
                }
                
                if (this.classList.contains('minus')) {
                    updateQuantity(productId, currentItem.quantity - 1);
                } else if (this.classList.contains('plus')) {
                    updateQuantity(productId, currentItem.quantity + 1);
                }
            });
        });
        
        // 削除ボタンにイベントリスナーを設定
        const deleteButtons = parent.querySelectorAll('.cart-item-delete');
        if (deleteButtons.length > 0) {
            console.log(`カート内削除ボタン: ${deleteButtons.length}個見つかりました`);
            deleteButtons.forEach(btn => {
                const productId = btn.getAttribute('data-id');
                if (!productId) {
                    console.warn('削除ボタンに商品IDがありません');
                    return;
                }
                
                btn.addEventListener('click', function() {
                    console.log('商品ID=' + productId + 'を削除します');
                    removeFromCart(productId);
                });
            });
        }
        
        console.log('カートアイテムイベント更新完了');
    } catch (error) {
        console.error('カートアイテムイベント更新エラー:', error);
        // エラーが発生しても続行できるようにする
    }
} 
