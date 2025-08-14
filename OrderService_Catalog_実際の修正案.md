# OrderService_Catalog.php 実際の修正案

## 🚨 現在の修正が機能しない真の理由

### 問題の核心
```php
// 336-340行目: セッション検索
$session = $this->db->selectOne(
    "SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 1",
    [$roomNumber]
);

// 342-356行目: セッション作成処理
if (!$session) {
    $sessionId = $this->generateSessionId();
    // ... 新規セッション作成 ...
    
    // 353-356行目: 問題の箇所
    $this->db->execute(
        "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
        [$sessionId, $roomNumber]
    );
}
```

### 🎯 実際の問題シナリオ

#### シナリオ: 既存セッションがあるのに新規セッションが作成される
```
時系列:
10:00 - ユーザーA: 注文開始 → SESS001作成、ユーザーAにSESS001設定
10:30 - ユーザーB: チェックアウト（is_active=0）
10:45 - ユーザーC: チェックイン
10:50 - ユーザーC: 注文開始
```

**現在のロジック**:
1. `order_sessions`テーブルでSESS001は`is_active=1`で存在
2. **既存セッションが見つかる** → 新規作成されない
3. ユーザーCには既存のSESS001が返される
4. **しかし、ユーザーCのline_room_linksは更新されない**

**結果**:
- ユーザーA: order_session_id='SESS001' ✅
- ユーザーC: order_session_id=NULL ❌（致命的）

## 🎯 正しい修正案

### 修正案A: セッション取得時もline_room_links更新
```php
private function getOrCreateSession($roomNumber) {
    $session = $this->db->selectOne(
        "SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 1",
        [$roomNumber]
    );
    
    if (!$session) {
        // 新規セッション作成
        $sessionId = $this->generateSessionId();
        $this->db->insert("order_sessions", [
            'id' => $sessionId,
            'room_number' => $roomNumber,
            'is_active' => 1,
            'session_status' => 'active',
            'opened_at' => date('Y-m-d H:i:s')
        ]);
        
        // 新規セッション時：アクティブユーザーのみに設定
        $this->db->execute(
            "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
            [$sessionId, $roomNumber]
        );
        
        return ['id' => $sessionId, 'square_item_id' => null];
    } else {
        // 既存セッション取得時：セッションIDを持たないアクティブユーザーに設定
        $this->db->execute(
            "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND order_session_id IS NULL",
            [$session['id'], $roomNumber]
        );
        
        return $session;
    }
}
```

### 修正案B: セッション参加者の個別管理
```php
private function getOrCreateSession($roomNumber) {
    $session = $this->db->selectOne(
        "SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 1",
        [$roomNumber]
    );
    
    if (!$session) {
        // 新規セッション作成
        $sessionId = $this->generateSessionId();
        $this->db->insert("order_sessions", [
            'id' => $sessionId,
            'room_number' => $roomNumber,
            'is_active' => 1,
            'session_status' => 'active',
            'opened_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $sessionId = $session['id'];
    }
    
    // 共通処理：セッションIDを持たないアクティブユーザーに設定
    $this->db->execute(
        "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND order_session_id IS NULL",
        [$sessionId, $roomNumber]
    );
    
    return ['id' => $sessionId, 'square_item_id' => $session['square_item_id'] ?? null];
}
```

### 修正案C: 部屋セッション完全統一（最もシンプル）
```php
private function getOrCreateSession($roomNumber) {
    $session = $this->db->selectOne(
        "SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 1",
        [$roomNumber]
    );
    
    if (!$session) {
        // 新規セッション作成
        $sessionId = $this->generateSessionId();
        $this->db->insert("order_sessions", [
            'id' => $sessionId,
            'room_number' => $roomNumber,
            'is_active' => 1,
            'session_status' => 'active',
            'opened_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $sessionId = $session['id'];
    }
    
    // 部屋内全アクティブユーザーに最新セッションIDを設定
    $this->db->execute(
        "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
        [$sessionId, $roomNumber]
    );
    
    return ['id' => $sessionId, 'square_item_id' => $session['square_item_id'] ?? null];
}
```

## 推奨修正案

### 🎯 推奨：修正案A（最も安全）

**理由**:
1. **新規セッション時**: アクティブユーザーのみに設定（データ汚染防止）
2. **既存セッション時**: セッションIDを持たないユーザーのみ追加（進行中注文を保護）
3. **データ整合性**: 過去のセッションデータを保護
4. **機能完全性**: 全てのアクティブユーザーが適切なセッションIDを持つ

### 具体的修正手順

#### 📝 336-360行目を以下に置換：
```php
    private function getOrCreateSession($roomNumber) {
        $session = $this->db->selectOne(
            "SELECT * FROM order_sessions WHERE room_number = ? AND is_active = 1 LIMIT 1",
            [$roomNumber]
        );
        
        if (!$session) {
            // 新規セッション作成
            $sessionId = $this->generateSessionId();
            $this->db->insert("order_sessions", [
                'id' => $sessionId,
                'room_number' => $roomNumber,
                'is_active' => 1,
                'session_status' => 'active',
                'opened_at' => date('Y-m-d H:i:s')
            ]);
            
            // 新規セッション時：アクティブユーザーのみに設定
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
                [$sessionId, $roomNumber]
            );
            
            return ['id' => $sessionId, 'square_item_id' => null];
        } else {
            // 既存セッション取得時：セッションIDを持たないアクティブユーザーに設定
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND order_session_id IS NULL",
                [$session['id'], $roomNumber]
            );
            
            return $session;
        }
    }
```

## 効果検証

### ✅ 解決される問題
1. **新規ユーザーのorder_session_id未設定**: 既存セッション時も適切に設定
2. **進行中注文の保護**: 既にセッションIDを持つユーザーは変更されない
3. **データ汚染防止**: 非アクティブユーザーは影響を受けない
4. **Webhook処理の完全性**: 全アクティブユーザーが適切に処理される

この修正により、**現在の修正が機能しない根本的問題**が完全に解決されます。 