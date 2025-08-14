以下は「ユーザーがフロントから注文を送信してからバックエンドが Square 連携・DB 書込み・Webhook 発行を行うまで」の 現状実装フロー です。  
分岐点（open_ticket／product モード）と「設定ファイル（adminsetting.json）の読み込み箇所」を軸に、できるだけ細かく追っています。

────────────────────  
1. API 入口  
────────────────────  
① フロントは `POST /api/v1/orders/` に JSON で  
```json
{ "items":[…], "notes":"…" } + X-LINE-USER-ID ヘッダ
```  
を送信。  

② 受信スクリプト `api/v1/orders/index.php` が起動し、最初に  
```38:45:fgsquare/api/v1/orders/index.php
// Square設定の open_ticket フラグ取得
$openTicketFlag = \OrderService::isSquareOpenTicketEnabled();
```  
で「open_ticket 設定」を読み出し、処理ルートを決定します。  

🔍 取得ロジックの内訳  
```
api/v1/orders/index.php                 // 呼び出し元
└─ OrderService::isSquareOpenTicketEnabled()
    └─ OrderService::getSquareSettings() // (静的キャッシュあり)
         └─ include_once admin/adminsetting_registrer.php
              └─ loadSettings()          // adminsetting.json をパース
```
* **設定ファイル実体**: `admin/adminpagesetting/adminsetting.json`  
* **参照キー**: `square_settings.open_ticket` (文字列 `'true'|'false'` または boolean)。  
* **判定基準**: `filter_var($value, FILTER_VALIDATE_BOOLEAN)` を使用し、  
  - キーが存在しない / 空の場合 → **true** (product モードよりも open_ticket を優先)  
  - `'false'`, `'0'`, `false` 等 → false  
  - それ以外 → true  
* 読み込み失敗時（adminsetting.json が無い 等）もデフォルト true で動作し、安全側に倒す実装。  

結果の `$openTicketFlag` が true/false を決定し、以降の処理が 2-A / 2-B に分岐します。  

③ `Auth::authenticateRequest()` でトークン／LINE ID 認証を行い、部屋番号を取得。  
　失敗すると 401 を返して終了。

────────────────────  
2-A. open_ticket=true の場合（保留伝票方式）  
────────────────────  
** 2025.05.14時点、Square側が保留伝票機能へのAPIアクセスを開通させていないためこのルートは将来的な実装として残す **

④ `RoomTicketService` を生成し、`getRoomTicketByRoomNumber()` で既存チケットを探す。  
　無ければ `createRoomTicket()` で Square 側に "Open Ticket" を新規作成。  

⑤ `addItemToRoomTicket()` で今回のアイテムをチケットに追加。  
　Square 連携内部では `SquareService` を利用 (catalog 価格更新等)。  
　設定ファイルは `SquareService::getSquareSettings()` から読まれます  
```2049:2073:fgsquare/api/lib/SquareService.php
include_once $regPath;  // adminsetting_registrer.php を実行
$all = loadSettings();
if(is_array($all) && isset($all['square_settings'])){
    return $settings = $all['square_settings'];
}
```  

⑥ 追加成功後、`{"success":true,"mode":"open_ticket",…}` を返却。  
　DB には orders/order_details も書かれます（order_status=OPEN）。  

────────────────────  
2-B. open_ticket=false の場合（catalog 商品方式＝「product モード」）  
────────────────────  
④ `OrderService::createOrder()` が呼ばれる。関数冒頭で再度  
```687:718:fgsquare/api/lib/OrderService.php
include_once $regPath;  // adminsetting_registrer.php
$all = loadSettings();
… return $settings = $all['square_settings'];
```  
が実行され、  
　・open_ticket（冗長だが再確認）  
　・mobile_order_category（Square へ送るカテゴリ名）  
　・order_webhooks（外部通知 URL 群）  
などを取得します。  

⑤ セッション管理  
　a. `order_sessions` 表に同一部屋のアクティブ session が無ければ  
　　`generateSessionId()` で 21桁 ID を作成して挿入。  
　b. 現在セッションの税抜小計を `order_details` から集計。  
　c. 合計(税抜) を `SquareService::createOrUpdateSessionProduct()` に渡し、  
　　• 既存 square_item_id があれば価格だけ UPDATE  
　　• 無ければ ITEM/CATEGORY/VARIATION を新規 UPSERT  
　　(カテゴリが無い場合は mobile_order_category 名で自動生成)。  
　d. 返ってきた square_item_id を `order_sessions` に保存／更新。  

　e. **追加オーダー時の再計算フロー** （同一セッション中に createOrder() が再度呼ばれる度に実行）  
　　1. `order_details` から現セッション分の **税抜** 小計を再集計 → `sessionSubtotal`。  
　　2. 今回リクエストの小計 `subtotalAmount` を加算し `newSessionSubtotal` を算出。  
　　3. `SquareService::createOrUpdateSessionProduct()` に (sessionId, roomNumber, newSessionSubtotal, square_item_id) を渡す。  
　　　  * 既存 Variation があれば `price_money` を **丸ごと置換**（差分ではなく累計値を上書き）。  
　　　  * Variation が無い／取得失敗時は ITEM＋VARIATION を新規作成し、カテゴリも自動生成/ひも付け。  
　　4. API 成功時に返る `variationId` を再び order_sessions.square_item_id として保存。  
　　    このため **Square カタログ側の価格は常に「同セッション・税抜合計」** を表す。  
　　5. 以降のフロント会計では Square 注文画面上でこの 1 行商品を複数回上書きしながら合計を追従する設計。  
　　6. `orders` / `order_details` には個別商品が行単位で追加されるため、会計履歴の明細は DB 側で保持。  

⑥ 注文保存  
　`orders` / `order_details` 両表に書込み。order_session_id も紐付け。  

⑦ Webhook キュー  
　`queueOrderWebhook()` が square_settings["order_webhooks"] 配列を走査し、  
　非同期 cURL で各 URL に `{"event_type":"order_created", ...}` を POST。  
　同時に `webhook_events` テーブルへも記録し再送監視を可能にしています。  

⑧ LINE 通知（紐付いたユーザが居る場合のみ）を送信。  

⑨ 成功レスポンス  
　`{"success":true,"mode":"product","order_id":…,"session_id":…}` を返却。  

────────────────────  
3. その後の流れ（共通）  
────────────────────  
・Square 側決済完了時 Webhook (`/api/webhook/square`) が入り、  
　該当セッション／チケットの注文を `order_status=COMPLETED` にして  
　`order_sessions` を無効化。  
・管理画面からの「共生クローズ」も RoomTicketService が処理。  

────────────────────  
4. 設定ファイル読み込みのポイント  
────────────────────  
・実体は `admin/adminpagesetting/adminsetting.json`。  
・読み込み関数は **adminsetting_registrer.php** 内 `loadSettings()`。  
・各サービスは **include_once** で同 PHP を呼び出し  
　`$all['square_settings']` セクションだけを抽出。  
　これにより "一元管理" を崩さず設定値を共有しています。  
・ログは規約どおり `logs/adminsetting_registrer.log` に出力され、  
　300 KB ローテーションも同ファイル内で実装済みです。  

────────────────────  
5. まとめ  
────────────────────  
• 設定値は **必ず** `adminsetting.json → loadSettings()` 経由で取得しており、  
　open_ticket 判定・カテゴリ名・Webhook URL などに利用。  
• product モードでは order_session_id をキーに  
　Square カタログの商品単価を逐次「税抜累計額」に更新。  
• open_ticket モードでは保留伝票 API を使用し、  
　商品毎ではなくチケット単位で追加／決済。  
• どちらのモードでも DB への orders / order_details 書込み →  
　Webhook キュー → (必要なら)LINE 通知 まで一気通貫で実装されています。

ご質問の「設定ファイルからの設定値取得」が抜けている箇所は現状ありません。  
もし期待する square_settings のキーが adminsetting.json に未定義の場合のみ、  
該当機能（例: order_webhooks）がスキップされる形になります。

────────────────────  
6. 設定ファイル読み込みと判定フロー（詳細）  
────────────────────  
① adminsetting.json へのアクセス経路  
   * 実体: `admin/adminpagesetting/adminsetting.json`  
   * 読み込み関数: **adminsetting_registrer.php** 内 `loadSettings()`  
   * サービス層では以下 2 つのメソッドが **include_once** で同 PHP を呼び出し、`square_settings` セクションだけを抽出してキャッシュしています。  
     ```php
     // OrderService 側
     private static function getSquareSettings(){ … }
     // SquareService 側
     private static function getSquareSettings(){ … }
     ```  
   * 読み込み時に `settingsFilePath`／`logFile` を **$GLOBALS** 経由で注入し、ログローテーションも遵守。  

② open_ticket 判定  
   * 取得キー: `square_settings["open_ticket"]` (boolean)。  
   * 判定場所:  
     ```php
     38:45:api/v1/orders/index.php
     $openTicketFlag = \OrderService::isSquareOpenTicketEnabled();
     ```  
   * `isSquareOpenTicketEnabled()` は上記設定値を `filter_var(..., FILTER_VALIDATE_BOOLEAN)` で評価し、デフォルト true。  
   * 以降のフロー分岐はこの bool に完全依存。  

③ mobile_order_category 利用箇所  
   * 取得キー: `square_settings["mobile_order_category"]` (string)。  
   * 使用場所: `SquareService::createOrUpdateSessionProduct()`  
     * Square Catalog へアップサートする前に同名カテゴリーが存在するか `listCatalog('CATEGORY')` で走査。  
     * 無ければ即座に `CatalogCategory` を作成し、ITEM の `category_id` に紐付け。  

④ order_webhooks 利用箇所  
   * 取得キー: `square_settings["order_webhooks"]` (string[])。  
   * キュー処理: `OrderService::queueOrderWebhook()`  
     * 発火タイミング: `createOrder()` 成功直後。  
     * 1 件でも URL があれば非同期 cURL(1.5s timeout) で JSON を送付し、テーブル `webhook_events` にも同時記録。  

⑤ 価格反映設定（open_ticket には未使用）  
   * 将来拡張予定の `square_settings["tax_rate"]` 等はまだ参照されていない → **未実装**。  

────────────────────  
7. 価格情報の決定ロジック  
────────────────────  
(1) クライアント → サーバ受信時点  
   * `items[].price` が **正数** なら最優先。  
   * そうでなければ `ProductService::getProductById()` で DB から取得。  
   * いずれも無ければ 0 円（ログに ERROR）。  

(2) Subtotal／Tax 計算  
   * `subtotal` = Σ(unit_price × quantity) **税抜**。  
   * `tax` = `subtotal × 0.1` (固定 10%、※`adminsetting.json` 未連携)。  
   * `totalAmount` = `subtotal + tax`。  

(3) Square 価格更新（product モード）  
   * `OrderService` から `SquareService::createOrUpdateSessionProduct()` へ **税抜累計 (newSessionSubtotal)** を渡す。  
   * Square API では `Money::amount` に `total` を設定。  
   * 既に Variation が存在すれば価格だけ更新、無ければ ITEM＋VARIATION を新規作成。  

(4) open_ticket モード  
   * `RoomTicketService::addItemToRoomTicket()` が `Money` 単価を **item.price** で決定し、保留伝票に LineItem として追加。  
   * 伝票全体の合計は Square 側 UI／決済時に確定。（現段階で税率 10% 前提）  

────────────────────  
8. 仕様との差異・未実装ポイント  
────────────────────  
* **tax_rate の可変化**: adminsetting.json にキーは想定されているが、`createOrder()` 内では固定 10%。  
* **Square open_ticket API**: 実装はあるが Square 側の正式サポート待ちとコメントあり。エラー処理は最低限。  
* **Webhook ペイロード**: 指定の `square_item_id` は product モードのみ含まれる。open_ticket では null。  
* **Square 商品無効化 (会計完了後)**: RoomTicketService での disable 処理は未確認。webhook 側の follow-up 実装要。  

🚨 **実装上の問題点（2025-05-14 時点）**  
* `SquareService::createOrUpdateSessionProduct()` 内で `Money` の `amount` を **`$totalAmount * 100`** として送信している。  
  ```php
  $money = new Money();
  $money->setAmount(intval($totalAmount * 100)); // ← JPY では余計な ×100
  $money->setCurrency('JPY');
  ```  
* Square API の `amount` は「通貨の最小単位」で指定する。JPY の最小単位は **1 円** で小数点を持たないため、掛け算は不要。  
  - 例）セッション税抜合計 5,000 円 → 正しくは 5000 を送るべきだが、現在は 500000 が送信され価格が 100 倍化。  
* これが「関係ない値を Square に送っている」症状の原因。  
* 修正案:  
  ```diff
- $money->setAmount(intval($totalAmount * 100));
+ $money->setAmount(intval($totalAmount));
  ```  
  （米ドル等の小数通貨の場合は掛け算が必要なので、通貨によって条件分岐するのが望ましい）  
