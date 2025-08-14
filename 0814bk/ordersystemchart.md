# LacisMobileOrderシステム フローチャート

## システム構成図

[モバイルアプリ] <---> [API (v1/index.php)] <---> [サービスクラス] <---> [データベース]
      ^                       ^                          |
      |                       |                          v
      +-------+---------------+                    [外部サービス]
              |                                   (Square, LINE)
              v
[LINE公式アカウント] <---> [Webhook] <---> [サービスクラス]

## 主要なファイルの関係性

### 1. エントリーポイント
- **api/v1/index.php**: すべてのAPIリクエストのエントリーポイント
- **api/webhook/square.php**: Square Webhookのエントリーポイント
- **api/webhook/line.php**: LINE Webhookのエントリーポイント

### 2. 設定とユーティリティ
- **api/config/config.php**: システム全体の設定
- **api/lib/Utils.php**: 共通ユーティリティ関数
- **api/.htaccess**: URLルーティングとセキュリティ設定

### 3. データアクセス
- **api/lib/Database.php**: データベース接続と操作

### 4. サービスクラス
- **api/lib/Auth.php**: 認証処理
- **api/lib/ProductService.php**: 商品管理
- **api/lib/OrderService.php**: 注文管理
- **api/lib/SquareService.php**: Square API連携
- **api/lib/LineService.php**: LINE API連携

## 主要なデータフロー

### 1. 認証フロー
[モバイルアプリ] --> [/api/v1/auth/token] --> [Auth.php] --> [Database.php] --> [データベース]
                                                |
                                                v
                                          [トークン生成]
                                                |
                                                v
[モバイルアプリ] <-- [JSONレスポンス] <-- [Utils.php]

### 2. 商品表示フロー
[モバイルアプリ] --> [/api/v1/products] --> [Auth.php] --> [認証確認]
                                              |
                                              v
                                     [ProductService.php] --> [Database.php] --> [データベース]
                                              |
                                              v
[モバイルアプリ] <-- [JSONレスポンス] <-- [Utils.php]

### 3. 注文フロー
[モバイルアプリ] --> [/api/v1/orders] --> [Auth.php] --> [認証確認]
                                             |
                                             v
                                    [OrderService.php] --> [ProductService.php] --> [在庫確認]
                                             |
                                             v
                                    [SquareService.php] --> [Square API] --> [注文作成]
                                             |
                                             v
                                    [Database.php] --> [データベース] --> [注文保存]
                                             |
                                             v
                                    [LineService.php] --> [LINE API] --> [通知送信]
                                             |
                                             v
[モバイルアプリ] <-- [JSONレスポンス] <-- [Utils.php]

### 4. Square Webhookフロー
[Square] --> [/api/webhook/square] --> [SquareService.php] --> [署名検証]
                                              |
                                              v
                                     [イベントタイプ判定]
                                              |
                                              v
                                     [ProductService.php] --> [Database.php] --> [データベース]
                                              |
                                              v
                                     [200 OKレスポンス]

### 5. LINE Webhookフロー
[LINE] --> [/api/webhook/line] --> [LineService.php] --> [署名検証]
                                           |
                                           v
                                    [イベントタイプ判定]
                                           |
                                           v
                                    [Auth.php] --> [トークン生成]
                                           |
                                           v
                                    [Database.php] --> [データベース]
                                           |
                                           v
                                    [LINE API] --> [メッセージ送信]
                                           |
                                           v
                                    [200 OKレスポンス]

## 主要なユースケースフロー

### ユースケース1: ゲストがモバイルオーダーを利用する
1. ゲストがLINE公式アカウントを友だち追加
2. ゲストが「部屋101」などと入力
3. LINE Webhookが処理を受け取り、トークンを生成
4. ゲストにモバイルオーダーURLが送信される
5. ゲストがURLをタップしてアプリを開く
6. アプリがトークンを使って認証
7. ゲストが商品を閲覧・カートに追加
8. ゲストが注文を確定
9. APIが注文を処理し、Squareに送信
10. 注文完了通知がLINEで送信される
11. 施設スタッフがSquare POSで注文を確認

### ユースケース2: 商品在庫の更新
1. 施設スタッフがSquare POSで在庫を更新
2. Square WebhookがAPIに通知
3. APIが在庫情報をデータベースに更新
4. 次回アプリ起動時に最新の在庫情報が表示される

## 設定項目

システムを稼働させるには、`api/config/config.php`で以下の項目を設定する必要があります：

### データベース設定
- `DB_HOST`: データベースホスト
- `DB_NAME`: データベース名
- `DB_USER`: データベースユーザー名
- `DB_PASS`: データベースパスワード

### Square API設定
- `SQUARE_ACCESS_TOKEN`: Square APIアクセストークン
- `SQUARE_LOCATION_ID`: Square ロケーションID
- `SQUARE_ENVIRONMENT`: 'sandbox'または'production'
- `SQUARE_WEBHOOK_SIGNATURE_KEY`: Webhook署名検証キー

### LINE Messaging API設定
- `LINE_CHANNEL_SECRET`: LINEチャネルシークレット
- `LINE_CHANNEL_ACCESS_TOKEN`: LINEチャネルアクセストークン

### アプリケーション設定
- `BASE_URL`: APIのベースURL
- `CORS_ALLOWED_ORIGINS`: CORSで許可するオリジン

## システムの特徴

1. **疎結合アーキテクチャ**: 各コンポーネントは独立しており、変更の影響範囲が限定される
2. **サービス指向**: 機能ごとにサービスクラスに分割され、責務が明確
3. **外部サービス連携**: Square APIとLINE APIを活用し、既存システムとの統合を実現
4. **イベント駆動**: Webhookを活用して外部システムの変更をリアルタイムに反映
5. **セキュリティ対策**: トークン認証、署名検証、SQLインジェクション対策などを実装

このシステムは、モバイルアプリ、バックエンドAPI、外部サービス（Square、LINE）が連携して動作する総合的なソリューションです。各コンポーネントが適切に役割分担することで、ゲストの利便性と施設運営の効率化を実現しています。 