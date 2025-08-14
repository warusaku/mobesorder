# FG Square UI 修正指示書

本文書は、FG Squareのモバイルオーダーシステム(/order/)のUI改善に関する修正指示をまとめたものです。フロントエンド担当者は、以下の指示に従って修正を行ってください。

## 準備項目

以下のSVGファイルを作成し、指定のディレクトリに配置してください。

- **配置ディレクトリ**: `/order/images/`
- **必要なファイル**:
  - `logo.svg` - FG Squareロゴ画像 (高さ50px)
  - `title.svg` - 「モバイルオーダー」タイトル画像 (高さ40px)

## 目次

1. [ヘッダー関連の修正](#1-ヘッダー関連の修正)
2. [サイドバー関連の修正](#2-サイドバー関連の修正)
3. [背景色の変更](#3-背景色の変更)
4. [商品カード表示の修正](#4-商品カード表示の修正)

## 1. ヘッダー関連の修正

### 1.1 修正ファイル: `/order/css/style.css`

```css
/* 1. ヘッダーの高さを70→75pxに変更 */
:root {
    /* 既存の変数定義 */
    --header-height: 75px; /* 70pxから変更 */
    /* その他の変数はそのまま */
}

/* 2. ヘッダーの背景色変更 */
header {
    /* 既存のスタイル */
    background-color: #658BC1; /* primary-colorから変更 */
    /* その他のスタイルはそのまま */
}

/* 3. ヘッダーコンテンツのマージン調整 */
.header-content {
    /* 既存のスタイル */
    margin: 0 15px; /* 追加 */
    /* その他のスタイルはそのまま */
}

/* 4. ロゴとタイトルのスタイル追加 */
.header-logo {
    height: 50px;
    padding: 0;
    margin: 0;
}

.header-title {
    height: 40px;
    margin-left: 15px;
    margin-bottom: 12px; /* ヘッダー下端から12px上の位置に配置 */
}

/* 5. 部屋番号と更新ボタンのスタイル */
.room-info {
    display: flex;
    align-items: center;
}

#room-number {
    margin-right: 13px;
}

.refresh-button {
    height: 40px;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
```

### 1.2 修正ファイル: `/order/index.php`

ヘッダー部分を以下のように修正します：

```html
<!-- ヘッダー -->
<header>
    <div class="header-content">
        <div class="header-left">
            <img src="images/logo.svg" alt="FG Square Logo" class="header-logo">
            <img src="images/title.svg" alt="モバイルオーダー" class="header-title">
        </div>
        <div class="room-info">
            <span id="room-number">読込中...</span>
            <button class="refresh-button" id="refresh-button"><i class="fas fa-sync-alt"></i></button>
        </div>
    </div>
</header>
```

### 1.3 修正ファイル: `/order/js/ui.js`

部屋番号取得処理の修正：

```javascript
// 部屋番号のセット処理を修正
function setRoomNumber(roomId) {
    const roomNumberElement = document.getElementById('room-number');
    if (roomNumberElement) {
        // 取得できない場合は「未設定」と表示
        roomNumberElement.textContent = roomId || '未設定';
    }
}

// 更新ボタンのイベントリスナーを追加
function initRefreshButton() {
    const refreshButton = document.getElementById('refresh-button');
    if (refreshButton) {
        refreshButton.addEventListener('click', async () => {
            try {
                // 再読み込み処理
                await loadCategories();
                // その他の必要な再読み込み処理
            } catch (error) {
                console.error('更新エラー:', error);
                showError('データの更新に失敗しました');
            }
        });
    }
}

// 初期化処理に追加
document.addEventListener('DOMContentLoaded', () => {
    // 既存の初期化処理
    
    // 更新ボタン初期化
    initRefreshButton();
});
```

## 2. サイドバー関連の修正

### 2.1 修正ファイル: `/order/css/style.css`

```css
/* 1. サイドバー表示条件の変更（769px→600px） */
@media (max-width: 600px) { /* 768pxから変更 */
    main {
        /* 既存のスタイル */
    }
    
    .category-sidebar {
        /* 既存のスタイル */
    }
    
    /* その他のメディアクエリ内のスタイル */
}

/* 2. アクティブなカテゴリのハイライト色変更 */
.category-item.active {
    border-left-color: #CCB289; /* primary-colorから変更 */
    background-color: rgba(204, 178, 137, 0.2); /* primary-lightから変更 */
    font-weight: 500;
}
```

## 3. 背景色の変更

### 3.1 修正ファイル: `/order/css/style.css`

```css
/* 1. 商品表示部の背景色変更 */
body {
    /* 既存のスタイル */
    background-color: #FEFAFC; /* bg-colorから変更 */
}

/* カテゴリサイドバーと商品表示部の背景色も統一 */
.category-sidebar {
    /* 既存のスタイル */
    background-color: #FEFAFC; /* card-colorから変更 */
}

.product-content {
    /* 既存のスタイル */
    background-color: #FEFAFC; /* 追加 */
}
```

## 4. 商品カード表示の修正

### 4.1 修正ファイル: `/order/css/style.css`

```css
/* 1. 商品名を太字に */
.product-name {
    font-weight: bold; /* 通常or500から変更 */
}

/* 2. 商品価格を太字に */
.product-price {
    font-weight: bold; /* 500から変更 */
    /* その他のスタイルはそのまま */
}

/* 3. カートに追加ボタンの背景色変更 */
.add-to-cart-btn, .btn-add-to-cart {
    /* 既存のスタイル */
    background-color: #CCB289; /* primary-colorから変更 */
    /* その他のスタイルはそのまま */
}

.add-to-cart-btn:hover, .btn-add-to-cart:hover {
    /* 既存のスタイル */
    background-color: #B09A77; /* 暗めの色に変更 */
    /* その他のスタイルはそのまま */
}
```

### 4.2 修正ファイル: `/order/js/ui.js`

商品要素の生成部分を修正：

```javascript
function createProductElement(product) {
    // 既存のコード
    
    // 商品情報のHTMLを生成する際に、クラス名を確認
    const productHtml = `
        ${imageHtml}
        <div class="product-info">
            <div class="product-name">${product.name}</div>
            <div class="product-price">¥${Number(product.price).toLocaleString()}</div>
            <button class="add-to-cart-btn" data-id="${product.id}" onclick="handleAddToCart(this)">カートに追加</button>
        </div>
    `;
    
    // 既存のコード
}
```

## 実装手順と注意点

1. ヘッダーの修正
   - まず、CSSでルート変数とヘッダー関連のスタイルを変更
   - 次に、HTMLテンプレートのヘッダー部分を修正
   - 最後に、JavaScript側で部屋番号表示と更新ボタンの処理を追加

2. サイドバーの修正
   - メディアクエリのブレークポイントを768pxから600pxに変更
   - アクティブなカテゴリのハイライト色を変更

3. 背景色の変更
   - body要素の背景色を変更
   - サイドバーと商品表示部の背景色も同じ色に統一

4. 商品カード表示の修正
   - 商品名と価格のフォントウェイトを太字に変更
   - カートに追加ボタンの背景色を変更

### 注意点

- メディアクエリの変更により、タブレットサイズでのレイアウトが変わります。動作確認を十分に行ってください。
- 色の変更は、関連するすべての要素で一貫して適用されていることを確認してください。
- ボタンのホバー状態やアクティブ状態のスタイルも適切に調整してください。

## 動作確認項目

1. 異なる画面サイズでの表示確認
   - デスクトップ（1024px以上）
   - タブレット（601px〜1023px）
   - モバイル（600px以下）

2. 部屋番号表示の確認
   - 正常に取得できる場合
   - 取得できない場合は「未設定」と表示されるか

3. 更新ボタンの動作確認
   - クリックでデータが再読み込みされるか

4. カテゴリ選択のハイライト表示確認
   - 選択中のカテゴリが正しく#CCB289色でハイライトされるか

5. 商品カードの表示確認
   - 商品名と価格が太字で表示されるか
   - カートに追加ボタンが#CCB289色で表示されるか
