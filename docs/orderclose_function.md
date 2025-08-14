# オーダーセッション終了処理仕様書 (fgsquare)

**version: 1.0.0 – 2025-05-XX**

---

## 1. 用語定義
| 用語 | 説明 |
| ---- | ---- |
| **order_session** | 部屋ごとに 1 つ発行される売上セッション。room チェックイン～チェックアウトの期間を束ねる。 |
| **products-type 方式** | Square 側に「部屋番号-セッションID」名義のダミー商品を 1 つだけ作り、その価格を都度更新していく方式。本仕様では **products-type** のみを対象とする。 |
| **open-ticket 方式** | Square の OPEN-TICKET 機能で会計を行う方式。将来対応予定。本仕様では対象外。 |
| **強制クローズ(Force-close)** | Square 端末で会計が行われなかった (=食い逃げ等) 場合に、管理画面から手動でセッションを終了すること。 |

## 2. テーブル定義変更
```sql
-- order_sessions
ALTER TABLE order_sessions
  ADD COLUMN session_status VARCHAR(20) NOT NULL
  DEFAULT 'active'
  COMMENT 'active|Completed|Force_closed'
  AFTER is_active;

-- Webhook ログ保存テーブル

-- 1) square_transactions … 会計確定（order.created）専用
CREATE TABLE square_transactions (
  id                    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  square_transaction_id VARCHAR(64) NOT NULL,
  square_order_id       VARCHAR(64) NOT NULL,
  location_id           VARCHAR(32) NOT NULL,
  amount                BIGINT       NOT NULL,
  currency              CHAR(3)      NOT NULL DEFAULT 'JPY',
  order_session_id      VARCHAR(32)  NULL,
  room_number           VARCHAR(16)  NULL,
  payload               JSON         NOT NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_session(order_session_id),
  KEY idx_square_tx(square_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) square_webhooks … order.updated / inventory / catalog など全イベントを網羅
CREATE TABLE square_webhooks (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  event_type    VARCHAR(64) NOT NULL,
  square_order_id VARCHAR(64) NULL,
  location_id   VARCHAR(32) NULL,
  payload       JSON NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **注意**: products-type 処理と open-ticket 処理は完全に別系統。<br>
> 本ドキュメントで扱うのは **products-type** のみであり、open-ticket 用の Webhook 保存は square_webhooks 側にだけ記録する。

## 3. 順方向フロー (正常会計)
1. `order/index.php` → `OrderService::createOrder()`
2. `order_sessions` に `is_active=1, session_status='active'` でレコード生成。
3. `SquareService::createOrUpdateSessionProduct()` でダミー商品を Square カタログに Upsert。
4. 部屋内で追加注文されるたびに 3 の価格を更新。
5. Square 端末でダミー商品を決済。
6. Square Webhook `order.created` 受信（ここで実売上が確定）→ **`square_transactions`** に INSERT。
7. Webhook `order.updated(state=COMPLETED)` 受信 → **`square_webhooks`** に INSERT 後、
   `order_sessions.is_active=0, session_status='Completed', closed_at=NOW()` へ更新。
8. 関連 `orders.order_status` を `COMPLETED` へ更新。`line_room_links.is_active=0`。
9. `disableSessionProduct()` でダミー商品を *非公開* にする。

## 4. 強制クローズ・フロー (未会計)
1. 管理画面 `admin/sales_monitor.php` の <クローズ> ボタン押下。
2. `close_order_session.php` へ `session_id` POST。
3. スクリプトが `square_transactions` に当該 `order_session_id` の記録有無を確認。
   * レコード **有** → 通常クローズ (=Webhook取込遅延)。`session_status='Completed'`。
   * レコード **無** → 強制クローズ。`session_status='Force_closed'`。
4. `order_sessions.is_active=0, closed_at=NOW()` 更新。
5. `orders.order_status='COMPLETED'`, `line_room_links.is_active=0` 更新。
6. 強制クローズ時のみ `disableSessionProduct()` で Square 側ダミー商品を非公開化。

## 5. 逆方向フロー (処理チェーン逆引き)
* Square → Webhook → `square_transactions` → `order_sessions` → `orders/line_room_links`
* sales_monitor → DB → クローズボタン → `close_order_session.php` → (前記強制クローズ判定)

## 6. API / スクリプト対応表
| フェーズ | 関連ファイル | 主なロジック | 変更点 |
| --- | --- | --- | --- |
| セッション生成 | `api/lib/OrderService.php` | `INSERT order_sessions` | `session_status='active'` を追加 |
| Webhook取込 | `api/webhook/square.php` | `handleOrderUpdated()` | ~~`square_transactions` へ INSERT …~~ → `square_webhooks` へ保存、session 完了処理 |
| 決済確定(Webhook) | `api/webhook/square.php` | `handleOrderCreated()` | `square_transactions` へ INSERT |
| 強制クローズ | `admin/close_order_session.php` | 手動終了 | `square_transactions` 照会と `session_status` 更新追加 |
| 商品無効化 | `api/lib/SquareService.php` | `disableSessionProduct()` | *新規関数* |
| 画面表示 | `admin/sales_monitor.php` | ダッシュボード | `session_status` バッジ表示、非 Active セッションのクローズボタン非表示 |

## 7. ログ & エラー処理
* すべての更新系スクリプトは専用ログに追記。ファイル名は `<script>.log`、300 KB ローテ。
* Webhook 処理で Square API エラーが発生した際は `SquareWebhook.log` に詳細を記録し、復旧タスクを `devTasks.md` へ追加する。

## 8. 移行手順
1. 本ドキュメントの SQL を実行して DB スキーマを更新。
2. 既存 `order_sessions` レコードの `session_status` を `active` で初期化。例:
   ```sql
   UPDATE order_sessions SET session_status='active' WHERE session_status IS NULL;
   ```
3. 新コードをデプロイし、Square Webhook URL が正しいことを確認。

---
*created by FG Development Team / 2025-05-XX*
