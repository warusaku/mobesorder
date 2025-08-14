# プレースホルダー画像機能実装ガイド

## 1. 概要

本ドキュメントでは、FG Squareシステムにおける商品画像のプレースホルダー機能の実装方法について説明します。`products`テーブルの`image_url`フィールドに値がない場合や、指定されたURLから画像を取得できない場合に、代替画像（プレースホルダー）を表示する機能を実装します。

## 2. プレースホルダー画像の仕様

### 2.1 画像ファイル情報

- **ファイル名**: no-image.png
- **配置場所**: `/order/images/no-image.png`
- **推奨サイズ**: 300px × 300px（正方形）
- **解像度**: 72dpi
- **ファイル形式**: PNG（透過背景推奨）
- **最大ファイルサイズ**: 50KB以下

### 2.2 デザイン仕様

以下のデザイン要素を含めることを推奨します：

- 薄いグレーの背景（#f5f5f5など）
- 「画像準備中」や「No Image」などのテキスト表示
- FG Squareのブランドカラーを取り入れる
- 極力シンプルなデザイン（テキストのみも可）

## 3. フロントエンド実装方法

### 3.1 HTMLでの基本的な実装

商品画像を表示する際に、`onerror`属性を使用して画像読み込みエラー時の処理を指定します。

```html
<img 
  src="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>" 
  alt="<?php echo htmlspecialchars($product['name']); ?>"
  onerror="this.onerror=null; this.src='/order/images/no-image.png';"
  class="product-image"
>
```

### 3.2 フロントエンドでのチェック実装（JavaScript）

より堅牢な実装のために、商品データ取得時にJavaScriptで画像URLの存在確認を行います。

```javascript
function displayProducts(products) {
  products.forEach(function(product) {
    let imageUrl = product.image_url;
    
    // 画像URLが存在しない場合はプレースホルダーを設定
    if (!imageUrl || imageUrl.trim() === '') {
      imageUrl = '/order/images/no-image.png';
    }
    
    const productElement = `
      <div class="product-item" data-product-id="${product.id}">
        <div class="product-image-container">
          <img src="${imageUrl}" alt="${product.name}" 
               onerror="this.onerror=null; this.src='/order/images/no-image.png';"
               class="product-image">
        </div>
        <div class="product-details">
          <h3 class="product-name">${product.name}</h3>
          <p class="product-price">¥${product.price}</p>
        </div>
      </div>
    `;
    
    $('#products-container').append(productElement);
  });
}
```

### 3.3 CSS対応

商品画像の表示スタイルを統一するために、以下のCSSを適用します。

```css
.product-image-container {
  width: 300px;
  height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background-color: #f9f9f9;
  border-radius: 8px;
}

.product-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transition: transform 0.3s ease;
}

/* ホバーエフェクト（オプション） */
.product-image:hover {
  transform: scale(1.05);
}
```

## 4. バックエンド実装

### 4.1 PHP側での画像URL検証

商品データを取得する際に、サーバーサイドでも画像URLを検証することが推奨されます。

```php
function getProductWithValidImageUrl($product) {
  // 画像URLが存在しない場合
  if (empty($product['image_url'])) {
    $product['image_url'] = '/order/images/no-image.png';
    return $product;
  }
  
  // オプション: 画像URLの有効性チェック（パフォーマンスに影響する可能性あり）
  // $headers = @get_headers($product['image_url']);
  // if (!$headers || strpos($headers[0], '200') === false) {
  //   $product['image_url'] = '/order/images/no-image.png';
  // }
  
  return $product;
}

// 使用例
$products = $db->select("SELECT * FROM products WHERE category = ? AND is_active = 1 AND order_dsp = 1", [$categoryId]);
$productsWithValidImages = array_map('getProductWithValidImageUrl', $products);
```

### 4.2 API応答での対応

商品データをAPIで提供する場合は、画像URLの検証と置換を行います。

```php
// API応答例 (/api/products.php)
$products = $db->select("SELECT * FROM products WHERE category = ? AND is_active = 1 AND order_dsp = 1", [$categoryId]);

// 画像URLの検証と修正
foreach ($products as &$product) {
  if (empty($product['image_url'])) {
    $product['image_url'] = '/order/images/no-image.png';
  }
}

// JSONレスポンス
echo json_encode([
  'success' => true,
  'products' => $products
]);
```

## 5. 実装のベストプラクティス

### 5.1 パフォーマンス最適化

- プレースホルダー画像は小さいサイズ（50KB以下）に最適化する
- 画像の存在チェックはフロントエンドで行い、サーバー負荷を軽減する
- キャッシュヘッダーを適切に設定し、プレースホルダー画像の再読み込みを防止する

### 5.2 レスポンシブ対応

異なる画面サイズに対応するため、複数サイズのプレースホルダー画像を用意することも検討してください。

- no-image-sm.png (150×150px) - モバイル画面用
- no-image.png (300×300px) - 標準サイズ
- no-image-lg.png (600×600px) - 大画面用

レスポンシブ対応の実装例：

```html
<img 
  src="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>" 
  alt="<?php echo htmlspecialchars($product['name']); ?>"
  onerror="this.onerror=null; this.src='/order/images/no-image.png';"
  class="product-image"
  srcset="/order/images/no-image-sm.png 150w,
          /order/images/no-image.png 300w,
          /order/images/no-image-lg.png 600w"
  sizes="(max-width: 600px) 150px,
         (max-width: 1200px) 300px,
         600px"
>
```

## 6. 導入手順

1. プレースホルダー画像を作成し、`/order/images/no-image.png`に配置
2. 商品表示テンプレートにonerror属性を追加
3. 必要に応じてCSS調整を行う
4. API応答で画像URLがない場合の処理を追加

## 7. テスト方法

以下のケースでプレースホルダー画像が正しく表示されることを確認してください：

1. `image_url`が`NULL`の商品
2. `image_url`が空文字列('')の商品
3. `image_url`に存在しないURLが設定されている商品
4. `image_url`に有効だが読み込みに失敗するURLが設定されている商品

テスト用に様々なケースの商品データを用意し、表示を確認することをお勧めします。
