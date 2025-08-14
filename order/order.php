<!-- 商品詳細モーダル -->
<div id="product-detail-modal" class="modal">
    <div class="modal-content product-detail-content">
        <div class="modal-header">
            <h2 id="product-detail-name">商品名</h2>
            <span class="close" id="close-product-detail">&times;</span>
        </div>
        <div id="product-detail-cart-message" class="cart-message"></div>
        <div class="modal-body">
            <div class="product-detail-main">
                <div class="product-detail-image">
                    <img id="product-detail-img" src="/fgsquare/order/images/no-image.png" alt="商品画像">
                </div>
                <div class="product-detail-info">
                    <div class="detail-row">
                        <div class="detail-label">価格:</div>
                        <div id="product-detail-price" class="detail-value">¥0</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">カテゴリ:</div>
                        <div id="product-detail-category" class="detail-value">-</div>
                    </div>
                    <div class="detail-row description-row">
                        <div class="detail-label">説明:</div>
                        <div id="product-detail-description" class="detail-value">商品説明はありません</div>
                    </div>
                    <div class="product-detail-quantity">
                        <div class="quantity-label">数量:</div>
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn quantity-minus">-</button>
                            <input type="text" id="product-detail-quantity" class="quantity-input" value="1" readonly>
                            <button type="button" class="quantity-btn quantity-plus">+</button>
                        </div>
                    </div>
                    <button id="add-to-cart-btn" class="add-to-cart-btn">カートに追加</button>
                </div>
            </div>
        </div>
    </div>
</div> 