# OrderService_Catalog.php 依存関係検証レポート

## 旧実装（0813bk）の完全分析

### 🔍 旧実装の処理フロー

#### セッション作成時（353-356行目）
```php
// 旧実装：部屋番号のみで全レコード更新
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ?",
    [$sessionId, $roomNumber]
);
```

#### 実際のデータパターン（line_room_links実例）
```sql
-- fg#12部屋の例
id=4:  user_id='U33f5f9aa...', room='fg#12', order_session_id=NULL,                  is_active=0
id=35: user_id='U4d777614...', room='fg#12', order_session_id='250516193833098604749', is_active=1

-- fg#01部屋の例  
id=30: user_id='U657b240f...', room='fg#01', order_session_id='250516193833098604749', is_active=0
```

### 📊 依存処理の完全マップ

#### 1. Square Webhook（注文完了時）
**ファイル**: `api/webhook/square.php:256`
```sql
UPDATE line_room_links SET is_active = 0 WHERE order_session_id = ?
```
**影響**: セッション完了時に**同じorder_session_idを持つ全ユーザー**を非アクティブ化

#### 2. 管理画面（セッション強制終了）
**ファイル**: `admin/close_order_session.php:118`
```sql
UPDATE line_room_links SET is_active=0 WHERE (order_session_id = :sid OR room_number = :room) AND is_active = 1
```
**重要**: `OR room_number`の**フォールバック機能**が存在

#### 3. 注文処理（orders テーブル）
**複数箇所**:
```sql
UPDATE orders SET order_status='COMPLETED' WHERE order_session_id = ? AND order_status='OPEN'
SELECT id, total_amount FROM orders WHERE order_session_id = :sid AND order_status='OPEN'
```
**影響**: 注文履歴の検索・更新がorder_session_idベース

#### 4. AI機能（mobes_ai）
**ファイル**: `order/mobes_ai/api/add_to_cart.php:45`
```sql
SELECT id, total_amount FROM orders WHERE order_session_id = :sid AND order_status='OPEN' FOR UPDATE
```
**影響**: AI推奨機能がorder_session_idで注文を特定

#### 5. 管理画面での分析機能
**ファイル**: `admin/Square_DB_Console.php`
```sql
WHERE od.order_session_id = ?
WHERE lrl.order_session_id = ?
```
**影響**: セッション分析、レポート機能

## 🚨 現在の修正（0814変更箇所）の問題

### 現在の処理
```php
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);
```

### 問題のシナリオ詳細

#### シナリオ1: 同一部屋での順次利用
```
1. ユーザーA（fg#12）: セッションSESS001で注文
2. ユーザーAチェックアウト: is_active=0, order_session_id='SESS001'
3. ユーザーB（fg#12）チェックイン: is_active=1, order_session_id=NULL
4. ユーザーBが新規注文: 新セッションSESS002作成
```

**旧実装の結果**:
```
user_A: room='fg#12', order_session_id='SESS002', is_active=0  // 上書きされる
user_B: room='fg#12', order_session_id='SESS002', is_active=1
```

**現在の修正の結果**:
```
user_A: room='fg#12', order_session_id='SESS001', is_active=0  // 保持される
user_B: room='fg#12', order_session_id='SESS002', is_active=1
```

**依存処理への影響**:
- Webhook: `WHERE order_session_id = 'SESS002'` → ユーザーAは対象外
- 管理画面: `WHERE order_session_id = 'SESS002' OR room_number = 'fg#12'` → ユーザーAも対象（フォールバック有効）

#### シナリオ2: 同時利用（複数ユーザー）
```
部屋fg#12に同時在室:
- ユーザーA: is_active=1, order_session_id=NULL
- ユーザーB: is_active=1, order_session_id=NULL
```

**新規セッション作成時**:
```
旧実装: 両方とも同じorder_session_idを取得
現修正: 両方とも同じorder_session_idを取得（問題なし）
```

### 🎯 真の問題の特定

**問題の本質**: データ汚染問題は解決されたが、**Webhook処理での非対称性**が発生

1. **Webhook依存**: Square WebhookはFOR文がない単純な`WHERE order_session_id = ?`
2. **管理画面は安全**: `OR room_number`フォールバックで全ユーザーをカバー
3. **AI機能等**: order_session_idベースの検索で一部ユーザーが除外される可能性

## 🔧 修正指示書の検証結果

### 現在の修正指示書の評価

#### ✅ 正しく特定できている点
1. 問題の根本原因（order_session_idが設定されない）
2. 段階的アプローチの採用
3. フォールバック機能の重要性

#### ⚠️ 不完全な点
1. **Webhook処理の特殊性**が考慮されていない
2. **管理画面のフォールバック機能**への言及不足
3. **AI機能等への影響**が分析されていない

### 📋 完全な解決策

#### 推奨修正（改良版）
```php
// line_room_linksも更新（データ汚染防止＋依存処理対応）
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND (is_active = 1 OR (is_active = 0 AND order_session_id IS NULL))",
    [$sessionId, $roomNumber]
);
```

**条件の詳細**:
- `is_active = 1`: アクティブユーザー（従来通り）
- `is_active = 0 AND order_session_id IS NULL`: 非アクティブだがセッションID未設定のユーザー

#### より安全な段階的修正
```php
// Phase 1: 既存処理保持
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);

// Phase 2: セッションID未設定の非アクティブユーザーも対象に追加
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 0 AND order_session_id IS NULL",
    [$sessionId, $roomNumber]
);
```

### 🧪 検証すべきテストケース

#### テストケース1: Webhook動作確認
```sql
-- セッション完了時の処理が全ユーザーに適用されるか
UPDATE line_room_links SET is_active = 0 WHERE order_session_id = 'TEST_SESSION';
```

#### テストケース2: 管理画面フォールバック確認
```sql
-- 管理画面でのセッション終了が全ユーザーに適用されるか  
UPDATE line_room_links SET is_active=0 WHERE (order_session_id = 'TEST_SESSION' OR room_number = 'TEST_ROOM') AND is_active = 1;
```

#### テストケース3: AI機能への影響確認
```sql
-- AI機能での注文検索が正常に動作するか
SELECT id, total_amount FROM orders WHERE order_session_id = 'TEST_SESSION' AND order_status='OPEN';
```

## 結論

現在の修正指示書は**部分的に正しい**が、**Webhook処理の特殊性**と**管理画面のフォールバック機能**を考慮した完全な解決策が必要です。推奨する改良版の修正により、データ汚染防止と依存処理の一貫性の両方を実現できます。 