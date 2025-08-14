# OrderService_Catalog.php 現在の問題分析

## 現在の修正コード
```php
// line_room_linksも更新（アクティブユーザーのみ）
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);
```

## 🚨 現在の修正が機能しない理由

### 問題1: 新規セッション作成のタイミング
```
シナリオ:
1. 部屋fg#12にユーザーA（is_active=1, order_session_id=NULL）がチェックイン
2. ユーザーAが注文 → 新セッション'SESS001'作成
3. ユーザーBも同じ部屋fg#12にチェックイン（is_active=1, order_session_id=NULL）
4. ユーザーBが注文 → 既存セッション'SESS001'を取得すべき
```

**現在の処理**:
```sql
UPDATE line_room_links SET order_session_id = 'SESS001' WHERE room_number = 'fg#12' AND is_active = 1
```

**結果**:
- ユーザーA: order_session_id='SESS001' ✅
- ユーザーB: order_session_id='SESS001' ✅

**問題なし？** → **実際の問題はここではない**

### 問題2: 部屋のセッション継続性
```
より複雑なシナリオ:
1. ユーザーA（fg#12）: 注文中（is_active=1, order_session_id='SESS001'）
2. ユーザーB（fg#12）: チェックアウト（is_active=0, order_session_id='SESS001'）
3. ユーザーC（fg#12）: 新規チェックイン（is_active=1, order_session_id=NULL）
4. ユーザーCが注文 → 新セッション'SESS002'を作成
```

**現在の処理**:
```sql
UPDATE line_room_links SET order_session_id = 'SESS002' WHERE room_number = 'fg#12' AND is_active = 1
```

**結果**:
- ユーザーA: order_session_id='SESS002' ✅（上書きされる）
- ユーザーB: order_session_id='SESS001' ✅（保護される）
- ユーザーC: order_session_id='SESS002' ✅

**問題**: ユーザーAの進行中の注文セッション'SESS001'が'SESS002'に変更される

### 問題3: 注文セッションの不整合
```
具体的な機能不全:
1. ユーザーAが'SESS001'で注文を進行中
2. ユーザーCの新規注文で'SESS002'が作成
3. ユーザーAのorder_session_idが'SESS002'に変更
4. ユーザーAの進行中の注文が'SESS001'に残る
5. Square Webhook等で'SESS002'完了時にユーザーAが影響を受ける
6. しかしユーザーAの実際の注文は'SESS001'に存在 → 不整合
```

## 🎯 真の問題の特定

### 根本的設計の問題
**部屋ベースのセッション管理の限界**

1. **同時注文**: 同じ部屋で複数ユーザーが異なるタイミングで注文
2. **セッション上書き**: 新規セッション作成時にアクティブユーザー全員が影響
3. **進行中注文の混乱**: 既存の注文セッションと新規セッションの競合

### 機能しない具体例
```
時系列:
10:00 - ユーザーA: 注文開始（SESS001）
10:30 - ユーザーB: チェックアウト
10:45 - ユーザーC: チェックイン、注文開始（SESS002作成）
10:45 - ユーザーA: order_session_id = SESS002に変更される
11:00 - ユーザーA: 注文完了 → SESS001のordersテーブルに記録
11:00 - Square Webhook: SESS002完了として処理 → ユーザーAが対象外
```

## 必要な解決策

### 選択肢A: セッション管理の見直し
```php
// 既存のアクティブなセッションがある場合は新規作成しない
$existingSession = $this->db->selectOne(
    "SELECT id FROM order_sessions WHERE room_number = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1",
    [$roomNumber]
);

if ($existingSession) {
    // 既存セッションを使用、新規ユーザーにも同じセッションIDを設定
    $sessionId = $existingSession['id'];
} else {
    // 新規セッション作成
}
```

### 選択肢B: ユーザー個別セッション管理
```php
// アクティブユーザーのうち、セッションIDを持たないユーザーのみ更新
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND order_session_id IS NULL",
    [$sessionId, $roomNumber]
);
```

### 選択肢C: 部屋セッションの強制統一（旧実装）
```php
// 部屋内全ユーザーを統一（データ汚染を容認）
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ?",
    [$sessionId, $roomNumber]
);
```

## 結論

現在の修正`AND is_active = 1`は理論的には正しいが、**進行中の注文セッションを上書きする**という致命的な問題があります。真の解決には、セッション管理ロジックの根本的な見直しが必要です。 