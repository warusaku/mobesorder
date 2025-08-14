# 商品表示順機能に関するフロントエンド対応ガイド

## 概要

商品表示順管理機能を実装したため、フロントエンド（顧客向け購入画面）での対応が必要です。このドキュメントは、フロントエンド開発者が実装すべき変更点を説明します。

## データベースの変更点

`products` テーブルに以下の2つのカラムが追加されました：

1. `sort_order` (INT型、NULL可) - 商品の表示順序を指定します
2. `order_dsp` (TINYINT(1)型、デフォルト値 1) - 商品の表示/非表示を制御します（1=表示、0=非表示）

## 必要な変更点

### 1. 商品一覧取得のSQLを修正

商品を表示する際、以下の点を考慮してSQL文を修正してください：

1. `sort_order` の昇順で商品を表示
2. `order_dsp = 1` の商品のみ表示
3. `sort_order` が NULL の場合は、ID の昇順で表示

#### 修正後のSQL例：

```sql
SELECT * FROM products 
WHERE category = ? AND is_active = 1 AND order_dsp = 1
ORDER BY COALESCE(sort_order, 999999), id ASC
```

> 注：`COALESCE(sort_order, 999999)` は `sort_order` が NULL の場合に大きな値を使用し、リストの最後に表示するための処理です。

### 2. 処理ロジックの変更

現在の商品取得・表示ロジックに以下の変更を加えてください：

1. 商品取得時に `order_dsp = 1` の条件を追加
2. 商品の表示順序を `sort_order` の昇順に変更
3. 必要に応じて、`order_dsp` の値を確認するロジックを追加

### 3. UI変更点（任意）

これは必須ではありませんが、UI上で以下の変更を検討してください：

- カテゴリ内での商品表示順が管理者の意図した順序になっていることを確認
- 必要に応じて、ページネーションなどの機能を修正

## 実装例

```php
// カテゴリ内の商品を取得するコード例
function getCategoryProducts($categoryId) {
    global $db;
    
    return $db->select(
        "SELECT * FROM products 
        WHERE category = ? AND is_active = 1 AND order_dsp = 1
        ORDER BY COALESCE(sort_order, 999999), id ASC",
        [$categoryId]
    );
}
```

## テスト項目

実装後、以下の点をテストしてください：

1. 管理画面で設定した商品表示順が正しく反映されているか
2. 管理画面で非表示に設定した商品がフロントエンドに表示されていないか
3. カテゴリ切り替え時に正しい順序で商品が表示されるか

不明点があれば、バックエンド開発チームにお問い合わせください。 