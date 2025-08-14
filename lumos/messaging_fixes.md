# Lumosモジュール修正調整案



以下に、現在の `messages` テーブルとAPI構成を踏まえた **修正指示書（改修仕様書）** を作成しました。開発者が段階的に実装・テスト・マイグレーション可能なよう、設計・目的・変更点・互換性・UI連携・テスト計画まで網羅しています。

---

# 📘 Lumos Lite Console メッセージ構造 改修指示書

**対象：** `messages`テーブル + `message_Transmission.php` API
**目的：** マルチプラットフォーム対応とメッセージ拡張に備えた統一メッセージモデルの導入
**日付：** 2025-05-28
**作成者：** FG Dev Team

---

## ✅ 1. 改修の目的

現在の `messages` テーブルは以下のような制限があります：

* LINEプラットフォームに依存したデータ構造
* `room_number`, `sender_type`, `platform`, `message_type`, `status` 等の情報がないため、複数メッセンジャー・多様な送受信形態への対応が困難
* 履歴検索、フィルタ、UI描画の柔軟性が乏しい

この改修は将来的な WhatsApp、Messenger、WeChat、社内Bot などの **拡張性** を見据えた基盤再設計です。

---

## 📐 2. 新メッセージテーブル構成（`messages_v2`）

```sql
CREATE TABLE messages_v2 (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_number   VARCHAR(20) NOT NULL,                         -- 部屋番号（表示・スレッド単位）
    user_id       VARCHAR(255) NOT NULL,                        -- 宿泊者ID（LINE ID等）
    sender_type   ENUM('guest', 'staff', 'system') NOT NULL,    -- 誰が送ったか
    platform      VARCHAR(20) DEFAULT 'LINE',                   -- LINE / WhatsApp / Messenger / etc.
    message_type  ENUM('text', 'image', 'template', 'rich') DEFAULT 'text',
    message       TEXT NOT NULL,                                -- テキスト or JSON（テンプレート等）
    status        ENUM('sent', 'delivered', 'read', 'error') DEFAULT 'sent',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 🧩 各列の解説

| 列名             | 意味         | 備考                                |
| -------------- | ---------- | --------------------------------- |
| `room_number`  | 部屋番号       | `line_room_links.room_number` に一致 |
| `user_id`      | LINE ID など | 宿泊者の識別に使用                         |
| `sender_type`  | メッセージ送信者種別 | UI上の左右分岐や送信方向判定に使用                |
| `platform`     | メッセンジャーの種類 | 拡張性対応（LINE以外に備える）                 |
| `message_type` | メッセージ形式    | テキスト・テンプレート・画像など                  |
| `status`       | 配信状態       | 今後 read/delivered 等の拡張可能          |

---

## 🔁 3. マイグレーション方針（現行からの移行）

### 一括移行スクリプト（PHP or SQL）

```sql
INSERT INTO messages_v2 (room_number, user_id, sender_type, platform, message_type, message, status, created_at)
SELECT
    r.room_number,
    m.user_id,
    'guest' AS sender_type,
    'LINE' AS platform,
    'text' AS message_type,
    m.message,
    'sent' AS status,
    m.created_at
FROM messages m
JOIN line_room_links r ON m.user_id = r.line_user_id
WHERE r.is_active = 1;
```

> ⚠️ メッセージ起点で `room_number` を復元しているため、`line_room_links` に未登録の旧データは除外されます。

---

## 🔧 4. APIルーティングの改修（`message_Transmission.php`）

### 修正対象関数

* `fetchAllMessages($roomNumber)`
* `fetchLatestMessages($roomNumber, $limit)`

### 改修案（例）

```php
function fetchAllMessages(string $roomNumber): array
{
    $db = Database::getInstance();
    $rows = $db->select(
        "SELECT * FROM messages_v2 WHERE room_number = :room ORDER BY created_at ASC",
        ['room' => $roomNumber]
    );
    return array_map(function ($row) {
        return [
            'id'           => $row['id'],
            'user_id'      => $row['user_id'],
            'sender_type'  => $row['sender_type'],
            'platform'     => $row['platform'],
            'message_type' => $row['message_type'],
            'message'      => $row['message'],
            'status'       => $row['status'],
            'created_at'   => $row['created_at']
        ];
    }, $rows);
}
```

---

## 💻 5. フロント側表示調整案（JS）

### 修正ポイント（`message_console.js`）

| 対象ID               | 修正内容                        |
| ------------------ | --------------------------- |
| `messageContainer` | `sender_type` に応じて左右レイアウト分け |
| `message_type`     | テンプレート・画像の場合の描画条件分岐         |
| `platform`         | バッジ表示等で送信元プラットフォームを表示（任意）   |

---

## 🧪 6. テスト項目（ローカル・ステージング）

| テスト内容                        | 期待結果                                    |
| ---------------------------- | --------------------------------------- |
| テキスト送信                       | 正常に `messages_v2` に記録され、UIに即時反映         |
| LINE ID複数ユーザー同室              | スレッドが統合され、ユーザー名一覧に表示される                 |
| テンプレート送信（仮）                  | `message_type='template'` として記録、描画制御が効く |
| 旧データのマイグレーション                | 正常に `messages` → `messages_v2` に移行される   |
| APIが `messages_v2` を使用していること | SQLログ or ダンプで確認可能                       |

---

## 🗂 7. バックアップ・互換性・切り戻し対応

* 移行前に `messages` → `messages_backup_YYYYMMDD` のバックアップテーブル作成
* v2版が安定稼働するまでは旧構造と切り替え可能に（`.env`や`adminsetting.json`で指定）

---

## 📌 8. 今後の拡張に備えて

* 添付ファイル対応 → `attachment_url` 列追加予定
* 予約送信 → `scheduled_at` 列追加と予約管理モジュール
* 通知状態の把握 → `read_at`, `delivered_at` などのタイムスタンプ導入

---

## ✅ 次アクション案（優先順）

| ステップ | 作業内容                                           |
| ---- | ---------------------------------------------- |
| ①    | `messages_v2` テーブル作成・現行データ移行                   |
| ②    | `fetchAllMessages()` 等の内部関数を `v2`に切り替え         |
| ③    | UI側で `sender_type` / `message_type` に応じた描画条件導入 |
| ④    | API経由での新規送信 → `messages_v2` 書き込みに統一            |
| ⑤    | テスト完了後、旧テーブル削除 or 保守モード移行                      |

---






以下に、**リアルタイム表示に関する改修指示書セクション**を追加します。既存の `message_console.js` および `message_Transmission.php` に準拠しつつ、MySQLベースでロングポーリングによるリアルタイム更新を実現する仕様です。

---

## 🛰️ 9. リアルタイム表示対応（ロングポーリング）

### 🎯 目的

* 宿泊者からの新着メッセージを、**ページをリロードせず即時表示**
* 各部屋カードに **新着通知・最終メッセージ表示** を反映
* モーダル（スレッド画面）を開いている場合は **その部屋の新着を即時反映**

---

### 🧱 全体構成概要

| 要素   | 内容                                      |
| ---- | --------------------------------------- |
| JS   | 一定間隔で新着チェックを非同期実行 (`setTimeout`)        |
| PHP  | `action=poll` を追加し、`messages_v2` から差分取得 |
| DB   | `messages_v2.created_at` を参照して新着を判定     |
| ロジック | 最終取得時間をクライアントで保持し、差分のみ取得                |

---

### 🧩 `message_Transmission.php` の追加ルーティング

#### ルーティング：

```php
case 'poll':
    handlePoll();
    break;
```

#### ハンドラ実装：

```php
function handlePoll(): void
{
    mtLog('handlePoll invoked');
    $since = $_GET['since'] ?? null;
    if (!$since) jsonResponse(['success' => false, 'message' => 'since param required'], 400);

    $db = Database::getInstance();
    $rows = $db->select(
        "SELECT * FROM messages_v2 WHERE created_at > :since ORDER BY created_at ASC",
        ['since' => $since]
    );

    $grouped = [];
    foreach ($rows as $row) {
        $room = $row['room_number'];
        if (!isset($grouped[$room])) $grouped[$room] = [];
        $grouped[$room][] = [
            'id'           => $row['id'],
            'user_id'      => $row['user_id'],
            'sender_type'  => $row['sender_type'],
            'platform'     => $row['platform'],
            'message_type' => $row['message_type'],
            'message'      => $row['message'],
            'status'       => $row['status'],
            'created_at'   => $row['created_at']
        ];
    }

    jsonResponse([
        'success'   => true,
        'updated_at' => date('Y-m-d H:i:s'),
        'new_messages' => $grouped
    ]);
}
```

---

### 💡 JSクライアント側 (`message_console.js`) 実装例

#### 変数定義：

```js
let lastUpdate = new Date().toISOString();
```

#### ポーリング関数：

```js
function pollNewMessages() {
    fetch(`${window.LUMOS_CONSOLE_CONFIG.apiEndpoint}?action=poll&since=${encodeURIComponent(lastUpdate)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            lastUpdate = data.updated_at;

            const newMessages = data.new_messages || {};
            for (const room in newMessages) {
                // 1. roomCard にバッジやハイライト
                highlightRoomCard(room);

                // 2. modal が開いていて該当roomなら append
                if (isModalOpenForRoom(room)) {
                    newMessages[room].forEach(msg => appendMessageToModal(msg));
                }

                // 3. 最新メッセージをカードに更新
                updateRoomCardLatestMessage(room, newMessages[room].slice(-1)[0]);
            }
        })
        .finally(() => {
            setTimeout(pollNewMessages, window.LUMOS_CONSOLE_CONFIG.pollInterval || 5000);
        });
}
```

#### 起動：

```js
document.addEventListener('DOMContentLoaded', () => {
    pollNewMessages();
});
```

---

### ✅ 表示演出のおすすめ実装

| 状態             | UI効果            | 実装方法                                             |
| -------------- | --------------- | ------------------------------------------------ |
| 新着あり           | `card` を黄色く点滅   | `element.classList.add('new-message')` など        |
| 新着あり（modal表示中） | モーダル内に即時メッセージ表示 | `appendMessageToModal()`                         |
| 新着既読時          | ハイライト解除         | `classList.remove('new-message')` on modal close |

---

### 📌 オプション設計項目

| 機能           | 実装案                       |
| ------------ | ------------------------- |
| 既読トラッキング     | `read_at` 列の追加とモーダル表示時に更新 |
| 長時間ポーリング間隔調整 | 初回: 3秒 → 安定後: 10秒などに動的調整  |
| 管理者のみ通知      | JSでアクセスレベル判定しポーリング起動制御    |

---

## ✅ まとめ：リアルタイム改修指示の要点

* 新ルーティング `action=poll` によって差分取得型のポーリングを実装
* クライアント側では `lastUpdate` を元に差分取得し、UIに反映
* サーバー・DB負荷は最小限で済み、最大5人運用想定なら**非常に安定して動作**

---

ご希望であれば、`poll` に対応するPHP・JSモジュールをファイル分割した状態で納品形式にすることも可能です。必要であればお知らせください。




```
// 📁 api/poll_messages.php
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/lib/Database.php';

header('Content-Type: application/json; charset=UTF-8');

$since = $_GET['since'] ?? null;
if (!$since) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'since parameter is required']);
    exit;
}

try {
    $db = Database::getInstance();
    $rows = $db->select(
        "SELECT * FROM messages_v2 WHERE created_at > :since ORDER BY created_at ASC",
        ['since' => $since]
    );

    $grouped = [];
    foreach ($rows as $row) {
        $room = $row['room_number'];
        if (!isset($grouped[$room])) $grouped[$room] = [];
        $grouped[$room][] = [
            'id'           => $row['id'],
            'user_id'      => $row['user_id'],
            'sender_type'  => $row['sender_type'],
            'platform'     => $row['platform'],
            'message_type' => $row['message_type'],
            'message'      => $row['message'],
            'status'       => $row['status'],
            'created_at'   => $row['created_at']
        ];
    }

    echo json_encode([
        'success'      => true,
        'updated_at'   => date('Y-m-d H:i:s'),
        'new_messages' => $grouped
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}
```
