# LIFF モバイルオーダーフロントエンド

LINE Front-end Framework（LIFF）を使用したモバイルオーダーシステムのフロントエンド実装です。宿泊施設の利用者がLINEアプリからルームサービスの注文を行い、チェックアウト時に一括精算できる仕組みを提供します。

## 機能

- LINE認証による簡単アクセス
- 部屋番号との自動紐づけ
- カテゴリ別商品表示
- カートへの商品追加・数量変更
- 注文の確定・履歴表示

## ディレクトリ構造

```
order/
├── css/                # スタイルシート
│   └── style.css       # メインのCSSファイル
├── js/                 # JavaScriptファイル
│   ├── liff-init.js    # LIFF初期化処理
│   ├── api.js          # APIとの通信
│   ├── cart.js         # カート機能
│   ├── ui.js           # UI操作
│   └── app.js          # アプリケーションのメインコード
├── images/             # 画像ファイル
├── index.php           # メインのHTMLファイル
└── README.md           # このファイル
```

## セットアップ手順

1. LINE Developersコンソールで新しいLIFFアプリを作成
2. LIFFのエンドポイントURLに本システムのURLを設定
3. 取得したLIFF IDを `config/LIFF_config.php` に設定
4. APIエンドポイントの動作確認

## 必要な環境

- PHP 7.2以上
- MySQL 5.7以上
- LINE公式アカウント
- LINE Messaging API有効化済みのチャネル

## API依存関係

このフロントエンドは以下のAPIエンドポイントを使用します：

- `/api/v1/liff/config` - LIFF設定取得
- `/api/v1/auth` - LINE UserIDと部屋番号の紐づけ確認・設定
- `/api/v1/categories` - カテゴリ一覧取得
- `/api/v1/products` - 商品一覧取得
- `/api/v1/orders` - 注文作成・履歴取得
- `/api/v1/cart` - カート内容の保存・取得

## 注意事項

- LIFFアプリはLINEアプリ内ブラウザでのみ完全に動作します
- チェックアウト後は部屋との紐づけが解除され利用できなくなります
- 実際の運用では適切なCSRF対策・認証トークン検証を実装してください 