# OrderService_Catalog.php 依存関係詳細分析

## 概要
- **分析対象**: OrderService_Catalog.php のgetOrCreateSessionメソッド（353行目）
- **問題**: order_session_idが正常に設定されない
- **分析日時**: 2025年8月14日

## 関連テーブル構造

### line_room_links テーブル
```sql
CREATE TABLE `line_room_links` (
  `id` int NOT NULL,
  `line_user_id` varchar(255) NOT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `order_session_id` char(21) DEFAULT NULL,  -- 重要：21桁のセッションID
  `user_name` varchar(255) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `access_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',       -- 重要：アクティブフラグ
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### order_sessions テーブル
```sql
-- セッション管理テーブル（推定）
order_sessions (
  id CHAR(21),           -- 21桁セッションID
  room_number VARCHAR(20),
  is_active BOOLEAN,
  session_status VARCHAR(20),
  opened_at TIMESTAMP
)
```

## 依存関係マップ

### 1. セッション作成フロー

```
OrderService_Catalog::createOrder()
├── getOrCreateSession($roomNumber)  -- 353行目の問題箇所
│   ├── SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1
│   ├── [セッション未存在の場合]
│   │   ├── generateSessionId() → 21桁ID生成
│   │   ├── INSERT INTO order_sessions (id, room_number, is_active, ...)
│   │   └── UPDATE line_room_links SET order_session_id = ? WHERE room_number = ?  ← 問題の箇所
│   └── return sessionData
└── 後続処理（Square商品作成等）
```

### 2. order_session_id 設定の影響範囲

**現在の処理（変更前）**:
```sql
UPDATE line_room_links SET order_session_id = ? WHERE room_number = ?
```
- 対象：指定された部屋番号のすべてのレコード
- 結果：アクティブ・非アクティブ問わず全てに同じorder_session_idが設定される

**変更後の処理**:
```sql
UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1
```
- 対象：指定された部屋番号のアクティブレコードのみ
- 結果：非アクティブユーザーのorder_session_idは更新されない

### 3. order_session_id依存処理

#### A) Square Webhook（注文完了時）
**ファイル**: `api/webhook/square.php:256`
```sql
UPDATE line_room_links SET is_active = 0 WHERE order_session_id = ?
```
**目的**: 注文完了時に該当セッションのユーザーを全て非アクティブ化

#### B) 管理画面（セッション強制終了）
**ファイル**: `admin/close_order_session.php:117`
```sql
UPDATE line_room_links SET is_active=0 WHERE (order_session_id = :sid
```
**目的**: 管理者によるセッション強制終了時の処理

#### C) セッション状態管理
- order_session_idにより部屋単位でのセッション一括管理
- 注文履歴の紐付け
- ユーザーグループの識別

## 問題の詳細分析

### 1. データ一貫性の問題

**シナリオ**: 部屋101にユーザーA（非アクティブ）とユーザーB（アクティブ）が存在

**変更前**:
```
line_room_links
├── id:1, user_id:A, room:101, order_session_id:session123, is_active:0
└── id:2, user_id:B, room:101, order_session_id:session123, is_active:1
```

**変更後**:
```
line_room_links
├── id:1, user_id:A, room:101, order_session_id:NULL, is_active:0
└── id:2, user_id:B, room:101, order_session_id:session123, is_active:1
```

**結果**: Webhook処理でセッション完了時、ユーザーAがorder_session_idを持たないため処理対象外となる

### 2. 業務ロジックへの影響

#### A) 注文完了時の不整合
```sql
-- この処理でユーザーAは対象外になる
UPDATE line_room_links SET is_active = 0 WHERE order_session_id = 'session123'
```

#### B) 履歴管理の複雑化
- 部屋単位でのセッション追跡が困難
- ユーザーの注文履歴の関連付けが不完全

#### C) 管理画面での問題
- セッション一覧でユーザー数の不一致
- 強制終了時の処理対象漏れ

### 3. 元の問題（データ汚染）の詳細

**問題のシナリオ**:
1. ユーザーAが部屋101で注文セッションAを作成（order_session_id: "sessionA"）
2. ユーザーAがチェックアウト（is_active = 0）
3. ユーザーBが同じ部屋101にチェックイン（is_active = 1）
4. ユーザーBが新しい注文セッションBを作成（order_session_id: "sessionB"）
5. **問題**: ユーザーAのorder_session_idも"sessionB"に上書きされる
6. **結果**: ユーザーAの過去の注文がセッションBに混入

## 推奨解決策

### 1. 即座の対応（短期）

**選択肢A**: 日付ベース制限
```sql
UPDATE line_room_links SET order_session_id = ? 
WHERE room_number = ? AND is_active = 1 AND DATE(created_at) = CURDATE()
```

**選択肢B**: 二段階更新
```sql
-- 1. 古いレコードのセッションIDをクリア
UPDATE line_room_links SET order_session_id = NULL 
WHERE room_number = ? AND is_active = 0 AND order_session_id IS NOT NULL;

-- 2. アクティブユーザーにセッションID設定
UPDATE line_room_links SET order_session_id = ? 
WHERE room_number = ? AND is_active = 1;
```

### 2. 根本解決（長期）

#### A) テーブル構造の改善
```sql
-- セッション参加者テーブルの新設
CREATE TABLE session_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_session_id CHAR(21) NOT NULL,
  line_user_id VARCHAR(255) NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  left_at TIMESTAMP NULL,
  is_active BOOLEAN DEFAULT TRUE,
  INDEX (order_session_id),
  INDEX (line_user_id)
);
```

#### B) セッション管理ロジックの分離
```php
class SessionManager {
  public function addUserToSession($sessionId, $userId) {
    // session_participantsテーブルで管理
  }
  
  public function removeUserFromSession($sessionId, $userId) {
    // 履歴は保持、is_activeのみ更新
  }
}
```

### 3. 移行戦略

1. **Phase 1**: 現行システムの短期修正（選択肢A実装）
2. **Phase 2**: 新テーブル設計とマイグレーション
3. **Phase 3**: ロジック分離とリファクタリング

## 結論

現在の問題は「バグフィックスの副作用」であり、元の問題（データ汚染）と新しい問題（セッション設定不全）の両方を解決する必要があります。短期的には日付ベース制限で対応し、長期的にはセッション管理の根本的な設計改善を推奨します。 