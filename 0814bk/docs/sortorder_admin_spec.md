# 商品表示順管理機能仕様書

## 1. 概要

本仕様書は、FG Square管理システムに商品表示順の管理機能を追加するための仕様を定義します。この機能により、管理者はカテゴリごとに商品の表示順序および表示・非表示を設定できるようになります。

## 2. システム要件

### 2.1 データベース拡張
- `products`テーブルに以下のカラムを追加します：
  - `sort_order`: INT型、NULL可、デフォルト値なし（商品の表示順序を指定）
  - `order_dsp`: TINYINT(1)型、NULL可、デフォルト値1（表示=1、非表示=0）

```sql
ALTER TABLE products
ADD COLUMN sort_order INT NULL,
ADD COLUMN order_dsp TINYINT(1) NULL DEFAULT 1;
```

### 2.2 管理画面拡張
- 管理ダッシュボード（`/admin/index.php`）のナビゲーションに「商品表示設定」タブを追加
- 新規タブをクリックすると、商品表示順設定ページ（`/admin/product_display.php`）に遷移

### 2.3 API機能拡張
- `/api/admin/product_display.php`を新規作成
- 以下の機能を実装：
  - カテゴリ一覧の取得
  - カテゴリに属する商品一覧の取得
  - 商品の表示順序と表示設定の更新

## 3. 機能詳細

### 3.1 商品表示設定ページ (`/admin/product_display.php`)

#### 3.1.1 基本レイアウト
- 既存の管理画面と同じヘッダー、ナビゲーション、認証機能を継承
- メインコンテンツ部分に以下を配置：
  - 「商品読み込み」ボタン
  - カテゴリタブ表示エリア
  - 商品リスト表示エリア
  - 設定更新ボタン

#### 3.1.2 商品読み込みボタン
- クリックすると`category_descripter`テーブルからカテゴリ情報を取得
- カテゴリは`display_order`の値が少ない順に表示
- ローディングインジケータを表示し、読み込み完了後にタブとして表示

#### 3.1.3 カテゴリタブ表示
- 各カテゴリをタブとして表示
- タブの表示形式：`カテゴリ名 (カテゴリID)`
- `is_active`の値が0のカテゴリはグレーアウト表示し、選択不可

#### 3.1.4 商品リスト表示
- 選択されたカテゴリに属する商品をテーブル形式で表示
- 表示カラム：ID、商品名、Square Item ID、価格、有効/無効、更新日時、表示設定
- `sort_order`に値がない商品はIDの昇順でソート
- 各行をドラッグ可能にして、表示順の変更を可能に
- 最上位の商品を`sort_order=1`とし、以下順に番号を割り当て
- `order_dsp`はチェックボックスで表示（チェック有=表示、チェック無=非表示）
- `order_dsp=0`の商品行はグレー表示

### 3.2 API機能 (`/api/admin/product_display.php`)

#### 3.2.1 カテゴリ一覧の取得
- リクエスト：`action=get_categories`
- レスポンス：カテゴリ一覧（JSON形式）
```json
{
  "success": true,
  "categories": [
    {
      "id": 1,
      "category_id": "ABC123",
      "category_name": "飲み物",
      "display_order": 10,
      "is_active": 1
    },
    ...
  ]
}
```

#### 3.2.2 カテゴリ内商品一覧の取得
- リクエスト：`action=get_products&category=ABC123`
- レスポンス：商品一覧（JSON形式）
```json
{
  "success": true,
  "products": [
    {
      "id": 101,
      "square_item_id": "XYZ789",
      "name": "コーヒー",
      "price": 500,
      "is_active": 1,
      "updated_at": "2023-10-01 10:30:45",
      "sort_order": 1,
      "order_dsp": 1
    },
    ...
  ]
}
```

#### 3.2.3 商品表示設定の更新
- リクエスト：`action=update_settings`（POST、JSON形式のデータ）
```json
{
  "products": [
    {
      "id": 101,
      "sort_order": 1,
      "order_dsp": 1
    },
    ...
  ]
}
```
- レスポンス：更新結果（JSON形式）
```json
{
  "success": true,
  "message": "商品表示設定を更新しました",
  "updated_count": 10
}
```

## 4. フロントエンド対応

フロントエンド（顧客向け購入画面）では以下の変更が必要です：

- 商品一覧取得時、`sort_order`の昇順で商品を表示
- `order_dsp=0`の商品は表示しない
- 商品表示のSQL例：

```sql
SELECT * FROM products 
WHERE category = ? AND is_active = 1 AND order_dsp = 1
ORDER BY COALESCE(sort_order, 999999), id ASC
```


## 7. UI修正と実装手順書

### 7.1 実施済みのUI改善

管理画面の使いやすさを向上させるため、以下のUI改善を実施しました：

1. **自動データ読み込み**:
   - ページ読み込み時に自動的にカテゴリと商品データを読み込む
   - 読み込み中はローディングインジケータを表示

2. **ボタン名称と配置変更**:
   - 「商品読み込み」ボタンを「商品の再読み込み」に変更
   - 「設定を更新」ボタンを商品リストの見出し横に配置

3. **カテゴリ表示の改善**:
   - カテゴリタブからID表示を削除し、名前のみ表示
   - カテゴリ名でタブを構成し、視認性を向上

4. **レスポンシブ対応**:
   - 画面幅700px以上: タブ形式でカテゴリを表示
   - 画面幅700px以下: ドロップダウンメニューでカテゴリを選択

5. **リスト表示の改善**:
   - リスト上部に「〇〇商品リスト」という見出しを追加
   - 非表示設定の商品行は背景色を#a9a9a9、文字色を#FFFFFFに変更

### 7.2 フロントエンド開発者向け実装手順

フロントエンド（顧客向け購入画面）での商品表示順と表示/非表示機能の実装手順：

#### 7.2.1 データベースカラムの確認

`products`テーブルに追加された2つのカラムを活用します：
- `sort_order` (INT): 商品の表示順序を指定
- `order_dsp` (TINYINT): 表示(1)または非表示(0)を指定

#### 7.2.2 商品取得SQLの修正

既存の商品取得SQLに以下の条件を追加します：

```sql
-- 修正前
SELECT * FROM products 
WHERE category = ? AND is_active = 1
ORDER BY id ASC

-- 修正後
SELECT * FROM products 
WHERE category = ? AND is_active = 1 AND order_dsp = 1
ORDER BY COALESCE(sort_order, 999999), id ASC
```

重要なポイント:
- `AND order_dsp = 1` で表示対象の商品のみ選択
- `ORDER BY COALESCE(sort_order, 999999), id ASC` で表示順を指定
  - `sort_order`がNULLの場合は大きな値(999999)を使用し、リスト末尾に表示
  - 同じ`sort_order`の商品は`id`順に表示

#### 7.2.3 PHP実装例

```php
function getProductsByCategory($categoryId) {
    global $db;
    
    $products = $db->select(
        "SELECT * FROM products 
         WHERE category = ? AND is_active = 1 AND order_dsp = 1
         ORDER BY COALESCE(sort_order, 999999), id ASC",
        [$categoryId]
    );
    
    return $products;
}
```

#### 7.2.4 JavaScript実装例（Ajaxで商品取得）

```javascript
function loadProductsForCategory(categoryId) {
    $.ajax({
        url: 'api/products.php',
        method: 'GET',
        data: { category: categoryId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 商品は既にsort_orderでソート済み、order_dsp=1のみ含まれている
                displayProducts(response.products);
            }
        }
    });
}
```

#### 7.2.5 実装時の注意点

1. **既存のソート機能との統合**:
   - すでに別のソート機能が実装されている場合は、`sort_order`による優先度を最高に設定

2. **カテゴリ表示順との連携**:
   - カテゴリ自体の表示順（`category_descripter`テーブルの`display_order`）と
     商品の表示順（`products`テーブルの`sort_order`）は独立して機能します

3. **パフォーマンス考慮**:
   - 商品数が多い場合、適切なインデックスを設定することを推奨
     ```sql
     ALTER TABLE products ADD INDEX idx_category_active_display (category, is_active, order_dsp);
     ALTER TABLE products ADD INDEX idx_sort_order (sort_order);
     ```

4. **キャッシュ戦略**:
   - 管理画面で表示設定が更新された場合、フロントエンド側のキャッシュを適切に更新
