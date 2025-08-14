# FGSquare プロジェクトファイル構成と機能説明

## 1. ルートディレクトリ

### 設定ファイル
- `fgsq.code-workspace`: VSCode用のワークスペース設定ファイル
- `.htaccess`: Apacheサーバーの設定ファイル（URL書き換えやアクセス制御を設定）

### 設計・仕様ドキュメント
- `実装計画.md`: プロジェクトの実装計画概要を記載
- `実装報告書.md`: 実装済み機能の報告書
- `mobileorder.md`: モバイルオーダーシステムの仕様書
- `ordersystemchart.md`: 注文システムのフロー図と説明
- `liff_mobile_order_spec.md`: LINE LIFF向けモバイルオーダー仕様書
- `README_TESTS.md`: テスト方法の説明

### データベース関連
- `LAA1207717-fgsquare.sql`: データベーススキーマ定義
- `LAA1207717-fgsquare (1).sql`: データベースのバックアップ/スキーマ定義
- `LAA1207717-fgsquare (2).sql`: 更新されたデータベースのバックアップ
- `sql_room_tickets_table.sql`: 部屋チケット用テーブル作成SQL
- `create_room_tickets_table.php`: 部屋チケットテーブル作成スクリプト

### テスト・デバッグツール
- `phpinfo.php`: PHP環境情報表示
- `check_php_info.php`: PHP情報確認用
- `debug.php`: デバッグ用スクリプト
- `debug_test.php`: 詳細なデバッグテスト
- `debug_log_viewer.php`: ログビューワーツール
- `check_logs.php`: ログ確認用スクリプト
- `logs_result.html`: ログ確認結果表示用HTML

### データベーステスト
- `db_test.php`: データベース接続テスト
- `db_test_result.html`: DB接続テスト結果表示用HTML
- `test_sqlite_connection.php`: SQLite接続テスト
- `sqlite_test_result.html`: SQLite接続結果
- `test_mysqli_connection.php`: MySQLi接続テスト
- `mysqli_test_result.html`: MySQLi接続結果
- `lolipop_test.php`: ロリポップサーバー向けテスト
- `check_database.php`: データベース状態確認
- `database_report.html`: データベース状態レポート
- `test_db_connection.php`: DB接続テスト（別バージョン）
- `test_categories.php`: カテゴリデータテスト
- `test_dashboard.php`: ダッシュボード機能テスト

### データ同期・処理
- `sync.html`: データ同期確認用HTML
- `sync_products.php`: 商品データ同期スクリプト
- `structure.sh`: ディレクトリ構造出力用シェルスクリプト

## 2. ディレクトリ構造

### `/api`
APIエンドポイントと関連ライブラリを管理するディレクトリ

#### `/api/config`
- `config.php`: APIの設定ファイル（DB接続情報、APIキー、定数など）
- `config.php.bak`: 設定ファイルのバックアップ

#### `/api/lib`
サービスロジックとユーティリティのライブラリ
- `Database.php`: データベース接続と操作を管理するクラス
- `OrderService.php`: 注文処理を扱うサービスクラス
- `Utils.php`: 共通ユーティリティ関数
- `Auth.php`: 認証処理を管理するクラス
- `RoomTicketService.php`: 部屋チケット管理サービス
- `SquareService.php`: Square API連携サービス
- `ProductService.php`: 商品管理サービス
- `LineService.php`: LINE連携サービス
- `CategoryService.php`: カテゴリ管理サービス

#### `/api/v1`
APIバージョン1のエンドポイント
- `index.php`: メインAPIエントリーポイント
- `checkout.php`: 決済処理用エンドポイント

##### `/api/v1/products`
商品関連APIエンドポイント

##### `/api/v1/categories`
カテゴリ関連APIエンドポイント
- `index.php`: カテゴリ一覧取得
- `update.php`: カテゴリ情報更新

##### `/api/v1/controllers`
APIコントローラクラス

##### `/api/v1/auth`
認証関連エンドポイント

##### `/api/v1/liff`
LINE LIFFアプリ向けAPI

##### `/api/v1/line`
LINE連携API

##### `/api/v1/test`
APIテスト用エンドポイント

#### `/api/sync`
データ同期スクリプトを管理するディレクトリ
- `sync_products.php`: 商品データ同期スクリプト
- `sync_categories.php`: カテゴリ情報同期スクリプト
- `sync_all.php`: 全データ同期スクリプト

#### `/api/webhook`
外部サービスからのWebhookを処理するエンドポイント

#### `/api/products`
商品管理関連のスクリプト

#### `/api/database`
データベース管理ツール
- `database_viewer_api.php`: データベース閲覧用API

#### `/api/images`
商品画像保存用ディレクトリ

#### `/api/logs`
APIログ保存用ディレクトリ

#### `/api/sql`
SQLスクリプト保存用ディレクトリ

#### その他
- `init.php`: API初期化ファイル
- `process_payment.php`: 決済処理用スクリプト
- `composer.json` & `composer.lock`: PHP依存関係管理ファイル
- `/api/vendor`: Composerでインストールされた依存ライブラリ

### `/admin`
管理画面関連ファイル
- `index.php`: 管理画面トップページ
- `login.php`: 管理者ログイン
- `manage_categories.php`: カテゴリ管理画面
- `products_sync.php`: 商品同期管理画面
- `logs_viewer.php`: ログ閲覧画面

#### `/admin/css`
管理画面用スタイルシート
- `admin.css`: 管理画面共通スタイル

#### `/admin/js`
管理画面用JavaScriptファイル
- `admin.js`: 管理画面共通スクリプト
- `categories.js`: カテゴリ管理スクリプト

#### `/admin/images`
管理画面用画像

### `/config`
プロジェクト全体の設定
- `LIFF_config.php`: LINE LIFFアプリの設定
- `user.json`: 管理者ユーザー情報

### `/docs`
プロジェクトドキュメント用ディレクトリ
- `filelist_description.md`: プロジェクトファイル構成説明（本ファイル）
- `Changes_and_Fixes_History.md`: 変更・修正履歴
- `My_SQL_structure.md`: データベース構造定義
- `squarre_MySQL_connect_spec.md`: Square-MySQL連携仕様

### `/logs`
アプリケーションログ保存用ディレクトリ

### `/order`
モバイルオーダーフロントエンド

#### `/order/js`
JavaScriptファイル
- `liff-config.json`: LINE LIFF設定
- `api.js`: API通信を処理するスクリプト
- `room-data.json`: 部屋データのサンプル
- `app.js`: メインアプリケーションロジック
- `cart.js`: カート機能ロジック
- `liff-init.js`: LINE LIFFの初期化
- `ui.js`: UI操作に関するスクリプト
- `categories.js`: カテゴリ表示制御

#### `/order/css`
スタイルシート

#### `/order/images`
フロントエンド用画像

- `index.php`: モバイルオーダー用メインページ
- `README.md`: フロントエンドの説明ドキュメント

### `/test`
テスト関連ファイル
- `debug_test.php`: デバッグ機能テスト
- `/test/views`: テスト用ビューファイル

## 3. 主要機能説明

### モバイルオーダーシステム
- LINE LIFFアプリを活用したモバイルオーダー機能
- 部屋チケットによる認証システム
- カート管理と注文処理
- Square決済連携
- カテゴリ管理と表示順制御

### バックエンドAPI
- RESTful API設計
- 認証と権限管理
- データベース操作の抽象化
- Square API連携
- LINE Messaging API連携
- カテゴリ情報自動同期

### カテゴリ管理システム
- カテゴリ情報のSquare APIからの自動同期
- カテゴリ表示順の設定
- カテゴリ別ラストオーダー時間設定
- カテゴリの有効/無効切り替え
- カテゴリ管理インターフェース

### データベース
- 商品管理（カテゴリ、価格、在庫など）
- 注文管理（注文詳細、ステータス、履歴）
- 部屋チケット管理
- ユーザー情報管理
- カテゴリ設定管理（カテゴリID、名前、表示順など）

### 管理機能
- カテゴリ管理画面
- ログ管理と閲覧
- データベース状態確認
- 商品データ同期

## 4. データベース構造

### 主要テーブル

#### `products`
商品情報を管理するテーブル
- `id`: 一意識別子（自動採番）
- `square_item_id`: Square内部商品ID
- `name`: 商品名
- `description`: 商品説明
- `price`: 価格
- `image_url`: 商品画像URL
- `stock_quantity`: 在庫数
- `local_stock_quantity`: ローカル在庫数
- `category`: カテゴリID
- `category_name`: カテゴリ名
- `is_active`: 有効フラグ
- `created_at`: 作成日時
- `updated_at`: 更新日時

#### `orders`
注文情報を管理するテーブル

#### `order_items`
注文明細を管理するテーブル

#### `room_tickets`
部屋チケット情報を管理するテーブル

#### `category_descripter`
カテゴリ情報を管理するテーブル
- `id`: 一意識別子（自動採番）
- `category_id`: Square内部カテゴリID
- `category_name`: 表示用カテゴリ名
- `display_order`: 表示順序（値が小さいほど先頭に表示）
- `is_active`: アクティブフラグ（1=表示、0=非表示）
- `last_order_time`: カテゴリ別ラストオーダー時間
- `created_at`: 作成日時
- `updated_at`: 更新日時

#### `sync_status`
同期状態を管理するテーブル
- `id`: 一意識別子（自動採番）
- `provider`: データプロバイダ（例：square）
- `table_name`: 同期対象テーブル名
- `last_sync_time`: 最終同期日時
- `status`: 同期ステータス
- `details`: 詳細情報
- `created_at`: 作成日時
- `updated_at`: 更新日時

## 5. 開発・デバッグツール
- 各種テストスクリプト
- データベース接続テスト
- ログビューワー
- デバッグ機能
- カテゴリ同期スクリプト
- 同期ステータス管理

## 6. 定期実行タスク
- 商品データ同期（30分間隔）
- カテゴリデータ同期（30分間隔）
- ログローテーション（日次） 