承知いたしました。LacisMobileOrderシステムの仕様書を、前回よりも約2倍詳細に記述します。各項目を掘り下げ、より具体的な内容を盛り込みます。

---

## **LacisMobileOrder システム仕様書 (詳細版)**

**1. システム概要**

*   **1.1 システム名:** LacisMobileOrder (ラシス モバイルオーダー)
*   **1.2 背景と目的:**
    *   **背景:** 多くの宿泊施設では、ルームサービス注文は電話や紙ベースで行われており、ゲストにとっては手間がかかり、施設側にとっては聞き間違いや注文管理の煩雑さといった課題が存在する。また、既存のレジシステム(Square)との連携が取れていない場合、売上や在庫の管理が二重になる非効率も発生している。
    *   **目的:**
        *   **ゲスト体験の向上:** ゲスト自身のスマートフォンから、時間や場所を選ばずに直感的なインターフェースでルームサービス等を注文できる環境を提供し、利便性と満足度を高める。部屋付け会計により、滞在中の支払いの手間を省く。
        *   **施設運営の効率化:** 注文受付からキッチンへの伝達、売上計上までをデジタル化し、人的ミスを削減、スタッフの業務負荷を軽減する。Squareとの連携により、施設全体の売上・在庫データを一元管理し、経営判断に必要な情報を正確かつ迅速に把握できるようにする。
        *   **コミュニケーション強化:** LINE公式アカウントを通じて、ゲストに必要な情報（注文完了通知、プロモーション等）を適切なタイミングで届け、エンゲージメントを高める。
*   **1.3 主要機能:**
    *   **ゲスト向けモバイルアプリ:** メニュー閲覧、カテゴリ別表示、商品検索(簡易)、カート操作(追加/削除/数量変更)、部屋付け注文確定、アレルギー情報等の備考入力、注文履歴確認(一覧/詳細)、認証(QR/トークン)。
    *   **施設向けバックエンド(API):** アプリ認証、Square連携(商品/在庫/注文/Webhook)、データベース管理(MySQL)、LINE連携(通知送信/Webhook受信)、（将来）管理者向けAPI。
    *   **施設向け運用(Square POS):** リアルタイムでの注文内容確認、キッチン プリンター等への注文伝票印刷、チェックアウト時の複数注文一括決済。
    *   **施設向け運用(LINE公式アカウント):** ゲストへの情報発信、自動応答設定、（将来）チャットサポート。
    *   **（将来）施設向けWeb管理画面:** ダッシュボード(売上概況、注文状況)、注文検索・詳細表示・ステータス確認、在庫状況確認・簡易調整、部屋トークン発行・管理・一括処理、システム設定(Square/LINE APIキー等)、LINEメッセージテンプレート管理・送信、ログ閲覧。
*   **1.4 ターゲットユーザー像:**
    *   **ゲスト:** スマートフォン操作に慣れており、部屋でゆっくり過ごしたい、非対面での注文を好む個人・カップル・ファミリー層。
    *   **フロントスタッフ:** チェックイン/アウト業務、ゲスト対応を行うスタッフ。システム操作(Square POS)に習熟している必要がある。
    *   **キッチン/ルームサービススタッフ:** 注文内容を正確に把握し、迅速に調理・提供を行うスタッフ。プリンターからの出力やSquare端末での確認が主。
    *   **システム管理者:** システム設定の変更、ユーザー管理、トラブルシューティングを行う担当者（ITリテラシーが必要）。

**2. システム構成**

*   **2.1 全体構成:**
    *   **ゲストUI:** React Native製モバイルアプリ。ゲストのスマートフォン上で動作。
    *   **アプリケーションサーバー:** Lolipopレンタルサーバー上で動作するPHPアプリケーション。APIエンドポイントを提供し、ビジネスロジックを実行。
    *   **データベースサーバー:** Lolipopレンタルサーバー上のMySQLデータベース。各種データを永続化。
    *   **決済・商品管理プラットフォーム:** Square。API連携により機能を利用。
    *   **コミュニケーションプラットフォーム:** LINE。Messaging APIを利用。
*   **2.2 コンポーネント詳細:**
    *   **React Nativeアプリ:**
        *   ゲスト向けUI/UXを提供。
        *   `axios`等のライブラリを使用し、PHP APIとHTTP(S)通信。
        *   QRコードスキャン機能 (`expo-barcode-scanner`または`react-native-camera`)。
        *   認証トークンをローカルストレージ (`AsyncStorage`) に保存。
        *   状態管理 (`React Context API` または `Redux`, `Zustand`等)。
        *   ナビゲーション (`@react-navigation/native`等)。
    *   **PHP API (Lolipop):**
        *   RESTful APIとして設計。
        *   フレームワークは利用せず、素のPHPまたは軽量フレームワーク(例: Slim Framework)で実装。
        *   Square SDK for PHP (`square/square`) を利用してSquare APIと連携。
        *   PDO (PHP Data Objects) を使用してMySQLと接続。プリペアドステートメントによるSQLインジェクション対策。
        *   LINE Messaging API SDK for PHP (`linecorp/line-bot-sdk`) を利用してLINEと連携。
        *   Composerによる依存関係管理。
    *   **MySQL (Lolipop):**
        *   InnoDBストレージエンジンを利用。文字コードは`utf8mb4`。
        *   テーブル設計は提供されたものをベースとする（後述のデータ仕様で詳細化）。
        *   適切なインデックス設定によるパフォーマンス確保。
    *   **Square API:**
        *   **Catalog API:** 商品情報(`items`, `categories`, `images`)の取得・同期。Webhook (`catalog.version.updated`)。
        *   **Inventory API:** 在庫情報(`inventory counts`)の取得・同期。Webhook (`inventory.count.updated`)。
        *   **Orders API:** 注文作成(`CreateOrder` - state: OPEN)、注文取得(`RetrieveOrder`, `SearchOrders`)、注文更新(`UpdateOrder` - Webhook経由でのステータス反映)。Webhook (`order.updated`)。
        *   **Webhooks API:** イベント通知の受信。署名検証によるセキュリティ確保。
    *   **LINE Developers:**
        *   **Messaging API:** テキストメッセージ送信(`Send push message`, `Send reply message`, `Send multicast message`)、プロフィール情報取得(`Get profile`)、Webhookイベント受信(Follow, Unfollow, Message, Postback)。
        *   **LINE Login (オプション):** 将来的にゲスト認証に利用可能。
        *   **LIFF (LINE Front-end Framework) (オプション):** 将来的にアプリの一部をLIFFアプリとして提供可能。
*   **2.3 連携フロー詳細 (主要なケース):**
    *   **初回アクセス/認証:**
        1.  ゲストが客室内のQRコードをスキャン or LINE等で配布されたURLをタップ。
        2.  URLに含まれるトークン情報と共にReact Nativeアプリが起動。
        3.  アプリはトークンをPHP APIの `/api/auth` に送信。
        4.  PHP APIはMySQLの `room_tokens` テーブルでトークンを検証。
        5.  検証成功なら、部屋番号等の情報と認証成功ステータスをアプリに返す。アプリはトークンをローカルに保存。
        6.  検証失敗なら、エラーステータスを返す。アプリはエラー表示。
    *   **メニュー表示:**
        1.  アプリがPHP APIの `/api/categories` (または `/api/products`) にリクエスト。
        2.  PHP APIはMySQLの `products` テーブルから商品情報を取得 (キャッシュがあればそれを利用)。
        3.  (キャッシュがない or 古い場合) PHP APIはSquare Catalog APIで最新情報を取得し、MySQLを更新後、アプリに返す。
        4.  アプリは取得した商品情報をカテゴリ別に整形して表示。
    *   **注文確定:**
        1.  アプリがカート情報(商品ID、数量、備考)と認証トークンをPHP APIの `/api/orders` (POST) に送信。
        2.  PHP APIはトークンを検証。
        3.  PHP APIは注文内容をSquare Orders APIに送信し、`OPEN`ステータスの注文を作成。`reference_id`等に部屋番号を付与。
        4.  Square APIから注文IDが返る。
        5.  PHP APIはSquare注文ID、部屋番号、注文明細、合計金額等をMySQLの `orders`, `order_details` テーブルに保存。
        6.  PHP APIは注文成功ステータスとローカル注文IDをアプリに返す。
        7.  アプリは注文完了画面を表示。
        8.  (同時に) Squareシステムが注文情報を店舗端末/プリンターに通知。
        9.  (同時に/非同期で) PHP APIがLINE Messaging APIに指示を出し、ゲストに注文完了通知を送信（LINE連携有効時）。
    *   **Square Webhook (在庫更新):**
        1.  Squareで在庫数が変更される。
        2.  SquareがPHP APIの `/api/webhook_handler.php` に `inventory.count.updated` イベントを送信。
        3.  Webhookハンドラーは署名を検証。
        4.  イベントデータから商品IDと新しい在庫数を取得。
        5.  MySQLの `products` テーブルの該当商品の在庫数を更新。
        6.  Squareに `200 OK` レスポンスを返す。
    *   **チェックアウト:**
        1.  フロントスタッフがSquare POSでゲストの部屋番号を検索 (または `reference_id` を利用)。
        2.  Square POS上に該当部屋の `OPEN` ステータスの注文が表示される。
        3.  スタッフは他の宿泊費等と合わせて、Square POSの機能で一括決済を行う。
        4.  Squareは決済完了後、該当注文のステータスを `COMPLETED` に更新。
        5.  (Webhook設定があれば) Squareが `/api/webhook_handler.php` に `order.updated` イベントを送信。WebhookハンドラーがMySQLの注文ステータスを `COMPLETED` に更新。

**3. 機能仕様 (詳細)**

*   **3.1 ゲスト向け機能 (ReactNativeアプリ)**
    *   **認証:**
        *   **画面:** 起動時にトークン入力画面またはQRスキャン画面を表示。認証済みならメニュー画面へ遷移。
        *   **QRスキャン:** `expo-barcode-scanner`等を使用。読み取り成功時、自動でトークンをAPIに送信。カメラ権限要求。
        *   **トークン入力:** 6桁程度の英数字入力フィールド。入力完了後、認証APIを呼び出し。
        *   **エラー処理:** 無効なトークン、API通信エラー、カメラ権限拒否などの場合に適切なエラーメッセージを表示。
        *   **状態保持:** `AsyncStorage`に認証トークンと部屋番号を保存。アプリ再起動時に自動認証試行。
    *   **商品閲覧:**
        *   **画面:** メイン画面にカテゴリタブと商品リストを表示。
        *   **表示要素:** 商品画像、商品名、価格(税込目安)、簡単な説明、在庫状況（品切れ表示）、カート追加ボタン。
        *   **UI:** スクロール可能なリスト。画像は遅延読み込み/キャッシュ考慮。ローディングインジケーター表示。
        *   **在庫:** 在庫ゼロの商品はカート追加ボタンを無効化、またはグレーアウト表示。
        *   **エラー処理:** 商品情報の取得失敗時にエラーメッセージとリトライボタンを表示。
    *   **カート機能:**
        *   **画面:** カートアイコンタップでカート画面へ遷移。またはメニュー画面下部に簡易表示。
        *   **表示要素:** カート内商品リスト（商品名、単価、数量、小計）、数量変更ボタン(+/-)、削除ボタン、合計金額（税抜/税込目安）、備考入力欄、注文確定ボタン。
        *   **操作:** 数量変更は即時に小計・合計金額に反映。
        *   **状態保持:** カート内容は `AsyncStorage` または状態管理ライブラリで永続化。
    *   **注文機能:**
        *   **画面:** カート画面から注文確定ボタンタップで注文内容確認モーダル表示。
        *   **確認:** 部屋番号、注文内容、合計金額、備考を最終確認。
        *   **備考入力:** 複数行入力可能なテキストエリア。最大文字数制限（例: 200文字）。
        *   **注文確定:** 確定後、API通信中はローディング表示。
        *   **完了:** 成功時は完了画面へ遷移。失敗時はエラーメッセージ表示（例:「注文処理に失敗しました。時間をおいて再度お試しください」）。
    *   **注文履歴:**
        *   **画面:** メニュー画面等のタブやメニューから遷移。
        *   **表示要素(一覧):** 注文日時、注文ID(一部)、合計金額、ステータス(処理中/完了/キャンセル)。新しい順に表示。
        *   **表示要素(詳細):** 注文日時、注文ID、ステータス、部屋番号、注文商品リスト(商品名、単価、数量、小計)、備考、合計金額。
        *   **データ取得:** APIから取得。ローディング表示。履歴がない場合は「注文履歴はありません」と表示。

*   **3.2 バックエンド機能 (PHP API)**
    *   **認証API (`/api/auth` POST):**
        *   Request: `{ "token": "xxxxxx" }`
        *   Response (Success): `{ "success": true, "room": { "room_number": "101", "guest_name": "山田太郎" } }`
        *   Response (Fail): `{ "success": false, "error": "Invalid token" }` (401 Unauthorized)
        *   処理: `room_tokens`テーブルで `access_token` と `is_active=true` を検証。
    *   **商品/カテゴリAPI (`/api/products` GET, `/api/categories` GET):**
        *   Response (`/categories`): `{ "categories": { "食事": [ {...product...}, ... ], "飲み物": [ {...product...}, ... ] } }`
        *   処理: MySQLから取得。画像URLはキャッシュされたローカルパスまたはSquare画像IDを返す。在庫数を付与。カテゴリ名でグループ化。
    *   **在庫API (`/api/inventory/sync` POST - 管理者用, Webhook内部処理):**
        *   処理: Square Inventory APIから最新在庫を取得し、MySQLの `products.stock_quantity` を更新。
    *   **注文API (`/api/orders` POST, GET; `/api/order_details/{id}` GET):**
        *   **POST:**
            *   Request: `{ "items": [ { "square_item_id": "...", "quantity": 1, "note": "アレルギー" }, ... ], "total_amount": 5000, "note": "全体備考" }` (Authorization: Bearer {token})
            *   Response (Success): `{ "success": true, "order_id": 123, "square_order_id": "sq_order_..." }`
            *   処理: トークン検証→Square注文作成(OPEN)→MySQL保存→LINE通知(非同期)。在庫引き当てはSquare側で行われる前提。
        *   **GET (`/api/orders`):**
            *   Response: `{ "orders": [ { "id": 123, "order_datetime": "...", "total_amount": 5000, "order_status": "OPEN", "item_count": 2 }, ... ] }` (Authorization: Bearer {token})
            *   処理: トークンから部屋番号を特定し、MySQLの `orders` テーブルから該当部屋の注文を取得。
        *   **GET (`/api/order_details/{id}`):**
            *   Response: `{ "order": { "id": 123, ..., "items": [ { "product_name": "...", ... }, ... ] } }` (Authorization: Bearer {token})
            *   処理: トークン検証＆注文IDの部屋番号一致確認後、MySQLから注文詳細取得。
    *   **Webhookハンドラー (`/api/webhook_handler.php` POST):**
        *   処理: SquareからのPOSTリクエスト受信→署名検証→イベントタイプ判別→対応処理（在庫更新、注文ステータス更新）→MySQL更新→200 OK応答。
    *   **トークン管理API (例: `/admin/api/rooms` POST - 管理者用):**
        *   処理: 部屋番号、ゲスト名、期間等を受け取り、ユニークなトークン生成/更新/無効化。
    *   **共通処理:**
        *   入力バリデーション: 各APIで必須パラメータ、データ型、文字数等を検証。
        *   エラーハンドリング: APIエラー、DBエラー、外部APIエラー等を捕捉し、適切なHTTPステータスコードとエラーメッセージを返す。ログ出力。
        *   認証: 認証が必要なAPIはHTTPヘッダーの `Authorization: Bearer {token}` を検証。

*   **3.3 Square連携機能 (詳細)**
    *   **データマッピング例:**
        *   Square Item Name -> `products.name`
        *   Square Item Variation Price -> `products.price` (セントから円に変換)
        *   Square Item Category ID -> `products.category` (カテゴリ名に変換 or ID保持)
        *   Square Item Image IDs[0] -> `products.image_url` (IDとして保存し、表示時にURL変換)
        *   Square Inventory Count -> `products.stock_quantity`
        *   Square Order ID -> `orders.square_order_id`
        *   Square Order state -> `orders.order_status` (OPEN, COMPLETED, CANCELEDにマッピング)
        *   アプリからの部屋番号 -> Square Order `reference_id` または `metadata.room_number`
    *   **決済フロー (スタッフ操作):**
        1.  ゲストがチェックアウトを申し出る。
        2.  スタッフはSquare POSを開き、ゲストの部屋番号に関連する注文を検索（`reference_id`等で）。
        3.  `OPEN`状態のモバイルオーダー注文がリストされることを確認。
        4.  Square POSの機能を使用し、これらの注文を他の宿泊費等とまとめて会計処理（カード決済、現金等）。
        5.  決済完了後、Squareシステムが自動で注文ステータスを`COMPLETED`に変更。
    *   **エラーハンドリング:**
        *   Square API呼び出し時のエラー（認証エラー、レート制限、サーバーエラー等）を検知し、ログ記録とリトライ処理（必要な場合）、またはユーザーへのエラー通知。
        *   注文時の在庫不足: Square Orders APIは通常、在庫不足でも注文を作成できる場合がある（設定による）。アプリ側での事前チェックと、注文後のSquare側での処理（キャンセル等）に依存。Webhookでの在庫更新を迅速に行うことが重要。

*   **3.4 LINE連携機能 (詳細)**
    *   **連携フロー例:**
        1.  ゲストがLINE公式アカウントを友だち追加。自動応答メッセージ送信。
        2.  リッチメニュー等から「ルームサービスを注文」をタップ。
        3.  トークン入力/QRスキャンを促すメッセージと、モバイルオーダーWebページへのリンク（またはLIFF起動リンク）を送信。
        4.  ゲストがリンクを開き、認証してアプリ（またはLIFF）で注文。
        5.  注文完了後、PHP APIがMessaging API経由で完了通知を送信。
    *   **ユーザー紐付け:**
        *   現状は部屋トークンでの認証のみ。
        *   将来拡張: LINEログインを導入し、LINE User IDとシステムのアカウント（または部屋トークン）を紐付けることで、よりパーソナルな通知や機能（例:「○○様、ご注文ありがとうございます」）が可能になる。`line_room_links`テーブルを利用。
    *   **送信メッセージ例:**
        *   注文完了: 「ご注文ありがとうございます。注文番号: {order_id} 合計金額: ¥{total_amount}。お部屋({room_number})までお届けします。」（プレースホルダはPHP側で置換）
    *   **Webhook処理 (LINE):** `/api/line_webhook.php` でイベント受信。署名検証後、フォロー/アンフォロー/メッセージ/ポストバック等のイベントに応じて処理（DB保存、自動応答）。

**4. データ仕様 (詳細)**

*   **テーブル定義:** (提供されたSQLをベースに、型や制約を明確化)
    *   `products`:
        *   `id`: INT AUTO_INCREMENT PRIMARY KEY
        *   `square_item_id`: VARCHAR(255) UNIQUE NOT NULL (Squareの商品ID)
        *   `name`: VARCHAR(255) NOT NULL
        *   `description`: TEXT
        *   `price`: DECIMAL(10,2) NOT NULL (税抜価格 - 円単位)
        *   `image_url`: VARCHAR(1024) (Square画像ID or キャッシュURL)
        *   `stock_quantity`: INT DEFAULT 0 (Squareから同期した在庫数)
        *   `local_stock_quantity`: INT DEFAULT 0 (未使用 or 予約在庫等に利用可)
        *   `category`: VARCHAR(255) (SquareカテゴリID or カテゴリ名)
        *   `is_active`: BOOLEAN DEFAULT TRUE (表示フラグ)
        *   `created_at`, `updated_at`: TIMESTAMP
        *   INDEX (`square_item_id`), INDEX (`category`), INDEX (`is_active`)
    *   `orders`:
        *   `id`: INT AUTO_INCREMENT PRIMARY KEY
        *   `square_order_id`: VARCHAR(255) UNIQUE (Square注文ID)
        *   `room_number`: VARCHAR(20) NOT NULL
        *   `guest_name`: VARCHAR(255)
        *   `order_status`: ENUM('OPEN', 'COMPLETED', 'CANCELED') DEFAULT 'OPEN' NOT NULL
        *   `total_amount`: DECIMAL(10,2) DEFAULT 0 (税抜合計金額)
        *   `note`: TEXT (ゲストからの備考)
        *   `order_datetime`: DATETIME DEFAULT CURRENT_TIMESTAMP
        *   `checkout_datetime`: DATETIME DEFAULT NULL
        *   `created_at`, `updated_at`: TIMESTAMP
        *   INDEX (`square_order_id`), INDEX (`room_number`), INDEX (`order_status`)
    *   `order_details`:
        *   `id`: INT AUTO_INCREMENT PRIMARY KEY
        *   `order_id`: INT NOT NULL, FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
        *   `square_item_id`: VARCHAR(255) NOT NULL
        *   `product_name`: VARCHAR(255) (注文時点の商品名)
        *   `unit_price`: DECIMAL(10,2) (注文時点の単価)
        *   `quantity`: INT NOT NULL DEFAULT 1
        *   `subtotal`: DECIMAL(10,2) (単価 * 数量)
        *   `note`: TEXT (商品ごとの備考)
        *   `created_at`: TIMESTAMP
        *   INDEX (`order_id`), INDEX (`square_item_id`)
    *   `room_tokens`:
        *   `id`: INT AUTO_INCREMENT PRIMARY KEY
        *   `room_number`: VARCHAR(20) UNIQUE NOT NULL
        *   `access_token`: VARCHAR(64) UNIQUE NOT NULL (認証用トークン)
        *   `is_active`: BOOLEAN DEFAULT TRUE (トークン有効フラグ)
        *   `guest_name`: VARCHAR(255)
        *   `check_in_date`, `check_out_date`: DATE
        *   `created_at`, `updated_at`: TIMESTAMP
        *   INDEX (`access_token`), INDEX (`room_number`), INDEX (`is_active`)
    *   `system_settings`:
        *   `setting_key`: VARCHAR(255) PRIMARY KEY
        *   `setting_value`: TEXT
        *   `updated_at`: TIMESTAMP
    *   (その他、ログテーブル、カテゴリーテーブル、LINE関連テーブル等)
*   **データのライフサイクル:**
    *   **トークン:** チェックイン時に生成・有効化、チェックアウト時に無効化。
    *   **注文ステータス:** 作成時 `OPEN` -> (Squareで決済) -> `COMPLETED` または `CANCELED` (Webhookで更新)。

**5. 非機能要件 (詳細)**

*   **パフォーマンス:**
    *   アプリ起動時間: 5秒以内 (コールドスタート)
    *   メニュー表示: 初回3秒以内、2回目以降1秒以内 (キャッシュ利用)。
    *   カート操作(追加/変更/削除): 0.5秒以内にUI反映。
    *   注文確定処理: 5秒以内に完了画面遷移。
*   **セキュリティ:**
    *   HTTPS通信の常時利用。HSTSヘッダー推奨。
    *   機密情報(APIキー, DBパスワード)は環境変数または `.env` ファイルで管理し、コードリポジトリに含めない。`.env` ファイルは公開ディレクトリ外に配置。
    *   SQLインジェクション対策: 全てのDBクエリでPDOプリペアドステートメントを使用。
    *   XSS対策: ユーザー入力は適切にエスケープ処理してから表示 (`htmlspecialchars`)。
    *   CSRF対策: 管理画面等の状態を変更するリクエストにはCSRFトークンを使用。
    *   Square Webhook署名検証: SquareからのWebhookリクエストの正当性を必ず検証。
    *   パスワード管理: 管理者パスワードは `password_hash()` でハッシュ化して保存。適切なパスワードポリシー適用推奨。
    *   アクセス制御: APIエンドポイント、管理画面機能ごとに適切な認証・認可チェック。
    *   ファイルアップロード: （将来的に実装する場合）ファイル形式、サイズ制限、ウィルススキャン等を実装。
*   **可用性/信頼性:**
    *   LolipopサーバーのSLAに準拠。
    *   データベースの定期的なバックアップ (Lolipopの機能を利用または独自スクリプト)。
    *   致命的なエラー発生時のログ記録と管理者への通知（メール等）検討。
*   **保守性/運用性:**
    *   コーディング規約 (PSR-12等) を遵守。
    *   主要な処理や複雑なロジックにはコメントを付与。
    *   READMEファイルにセットアップ手順、設定方法、運用手順を記述。
    *   ログレベル設定: 開発/本番環境でログレベルを切り替え可能にする (例: DEBUG, INFO, WARNING, ERROR)。
    *   監視: サーバーリソース (CPU, メモリ, ディスク)、API応答時間、エラー発生率などを監視 (Lolipopの機能または外部ツール)。
*   **スケーラビリティ:**
    *   現状はLolipopスタンダードプランでの運用を想定。アクセス増加時にはプランアップやサーバー構成の見直しが必要。
    *   DBクエリの最適化、キャッシュ活用により負荷軽減を図る。

**6. UI/UX要件**

*   **ゲストアプリ:**
    *   シンプルで直感的な操作性。初めて使うゲストでも迷わず注文できること。
    *   視認性の高いデザイン。商品画像は大きく、文字情報は読みやすく。
    *   ブランドイメージに合わせた配色・フォント。
    *   主要な操作（カート追加、注文確定等）には明確なフィードバック（アニメーション、メッセージ等）を与える。
    *   アクセシビリティ: フォントサイズ変更への対応（限定的）、適切なコントラスト比。
*   **管理画面:**
    *   情報が見やすく、目的の操作に素早くアクセスできること。
    *   重要な操作（削除、一括処理等）には確認ダイアログを表示。

**7. テスト要件**

*   **単体テスト:** PHPのクラス・メソッド単位でのテスト (PHPUnit)。JS/React Nativeコンポーネントのテスト (Jest)。
*   **結合テスト:** APIエンドポイントのテスト（リクエスト→レスポンス検証）。アプリとAPI間の連携テスト。
*   **システムテスト:** 実際のシナリオ（認証→メニュー閲覧→注文→Square確認→チェックアウト）に基づいた通しテスト。
*   **受け入れテスト:** 施設スタッフによる実際の運用を想定したテスト。
*   **テスト環境:** Square Sandbox環境、テスト用LINEアカウント、開発用DBサーバーを用意。

**8. 制約事項**

*   (前回同様) Square POS標準機能の利用制限、PMS連携なし、Lolipop環境前提。
*   オフライン機能は基本的に考慮しない。アプリ操作にはインターネット接続が必要。

**9. （参考）将来的な拡張案**

*   (前回同様に加え)
*   レコメンデーション機能（「こちらもいかがですか？」）。
*   時間帯別メニュー（朝食、ランチ、ディナー）。
*   複数言語対応の詳細化（DB設計、翻訳管理）。
*   テーブルオーダー機能への拡張（レストラン併設の場合）。

---

この詳細仕様書が、プロジェクトの進行と関係者間の認識合わせに役立つことを願っています。