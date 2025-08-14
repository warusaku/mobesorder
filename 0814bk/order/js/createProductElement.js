/**
 * 商品要素を作成する拡張機能
 * Square Item IDを適切に設定するための修正
 */

// 既存のcreateProductElement関数をオーバーライド
const originalCreateProductElement = window.createProductElement;

// 新しいcreateProductElement関数
window.createProductElement = function(product) {
    // 既存の関数を呼び出し
    const productElement = originalCreateProductElement ? 
                           originalCreateProductElement(product) : 
                           createProductElementFallback(product);
    
    // Square Item IDを設定
    if (product.square_item_id) {
        productElement.setAttribute('data-square-id', product.square_item_id);
        console.log(`商品ID=${product.id}にSquare Item ID=${product.square_item_id}を設定`);
    } else if (product.square_id) {
        productElement.setAttribute('data-square-id', product.square_id);
        console.log(`商品ID=${product.id}にSquare ID=${product.square_id}を設定`);
    }
    
    return productElement;
};

// フォールバック実装（元の関数が存在しない場合）
function createProductElementFallback(product) {
    console.log('商品カード作成: ID=', product.id);
    
    // 商品要素を作成
    const productElement = document.createElement('div');
    productElement.className = 'product-item';
    productElement.dataset.id = product.id;
    
    // 画像のHTMLを生成
    let imageHtml;
    if (product.image_url && product.image_url !== 'null' && product.image_url !== '') {
        imageHtml = `
            <div class="product-image">
                <img class="lazy-image" src="${product.image_url}" alt="${product.name || '商品画像'}" loading="lazy"
                     onerror="this.onerror=null;this.src='/order/images/no-image.png';">
            </div>
        `;
    } else {
        // デフォルト画像
        imageHtml = `
            <div class="product-image">
                <img src="/order/images/no-image.png" alt="画像なし" loading="lazy">
            </div>
        `;
    }
    
    // カート内商品数を取得
    const cartItem = window.findCartItemById ? window.findCartItemById(product.id) : null;
    const cartQty = cartItem ? cartItem.quantity : 0;
    
    // 商品情報のHTMLを生成
    const productHtml = `
        ${imageHtml}
        <div class="product-info">
            <div class="product-name">${product.name || '商品名なし'}</div>
            <div class="product-price">¥${Number(product.price || 0).toLocaleString()}</div>
            <div class="product-button-container">
                <button class="view-detail-btn" data-id="${product.id}">商品詳細</button>
            </div>
        </div>
    `;
    
    // HTMLをセット
    productElement.innerHTML = productHtml;
    
    // カートに既に商品がある場合はバッジを追加
    if (cartQty > 0) {
        const badge = document.createElement('div');
        badge.className = 'product-cart-badge';
        badge.textContent = cartQty;
        productElement.appendChild(badge);
    }
    
    // イベントリスナー設定は元のコードに任せる
    
    return productElement;
} 