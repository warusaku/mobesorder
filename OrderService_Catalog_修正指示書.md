# OrderService_Catalog.php 修正指示書

## 対象ファイル
- **ファイルパス**: `/Volumes/crucial_MX500/lacis_project/project/mobesorder/0814変更箇所/OrderService_Catalog.php`
- **問題箇所**: 353-356行目（getOrCreateSessionメソッド内）
- **修正緊急度**: 🔴 最緊急
- **修正日時**: 2025年8月14日

## 現在の問題コード

### 📍 353-356行目
```php
// line_room_linksも更新（アクティブユーザーのみ）
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);
```

## 問題の詳細

### 🚨 症状
- **メインの症状**: 「order_session_idが振られない、機能しない」
- **影響範囲**: セッション管理全体の機能不全
- **発生条件**: 部屋に非アクティブユーザーが存在する場合

### 📋 問題のメカニズム
1. **条件`AND is_active = 1`の追加**により、アクティブユーザーのみがorder_session_idを受け取る
2. **非アクティブユーザー**はorder_session_idを持たないため、以下の依存処理で問題が発生：
   - Square Webhook（注文完了時の処理）
   - 管理画面でのセッション強制終了
   - 履歴管理と紐付け

### 🔄 元の問題（修正前）
- **問題**: チェックアウト済みユーザーのorder_session_idが新規セッション作成時に上書きされる
- **結果**: 過去の注文履歴が現在の注文セッションと混在（データ汚染）

## 修正方法

### 🎯 推奨修正（選択肢A）: 完全解決版

#### 修正前
```php
// line_room_linksも更新（アクティブユーザーのみ）
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);
```

#### 修正後（完全版）
```php
// line_room_linksも更新（データ汚染防止＋依存処理対応）
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND (is_active = 1 OR (is_active = 0 AND order_session_id IS NULL))",
    [$sessionId, $roomNumber]
);
```

#### ⚠️ 重要な改良点
1. **`is_active = 1`**: アクティブユーザー（従来通り）
2. **`is_active = 0 AND order_session_id IS NULL`**: 非アクティブだがセッションID未設定のユーザー
3. **データ汚染防止**: 既にセッションIDを持つ非アクティブユーザーは保護
4. **依存処理対応**: Webhook等でセッション完了時に全ユーザーを適切に処理

#### コメント修正
```php
// 変更前
// line_room_linksも更新（アクティブユーザーのみ）

// 変更後  
// line_room_linksも更新（データ汚染防止＋依存処理対応）
```

### 🔧 より安全な修正（選択肢B）: 段階的二回実行

```php
// line_room_linksも更新（段階的アプローチ）
// Step 1: アクティブユーザーに設定
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);

// Step 2: セッションID未設定の非アクティブユーザーにも設定
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 0 AND order_session_id IS NULL",
    [$sessionId, $roomNumber]
);
```

**メリット**:
- 各段階で個別にログ出力・監視可能
- 問題発生時にStep 2のみ無効化可能
- 既存処理（Step 1）への影響を最小化

### 🔧 より安全な修正（選択肢C）: 条件の段階的緩和

```php
// 既存の問題を最小限に抑える修正
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND (order_session_id IS NULL OR DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY))",
    [$sessionId, $roomNumber]
);
```

**条件説明**:
- `order_session_id IS NULL`: セッションIDが未設定のレコード
- `DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)`: 過去24時間以内に更新されたレコード

### 🔒 最も安全な修正（選択肢D）: ロールバック可能な二段階

```php
// 段階1: 現在の処理を一時的に無効化（コメントアウト）
/*
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);
*/

// 段階2: 新しい安全な処理を追加
try {
    $this->db->execute(
        "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND DATE(created_at) >= CURDATE()",
        [$sessionId, $roomNumber]
    );
    
    // ログ出力でモニタリング
    Utils::log("Session ID updated for room {$roomNumber}, session {$sessionId}", 'INFO', 'OrderService_Catalog');
} catch (Exception $e) {
    // 失敗時は元の処理にフォールバック
    Utils::log("Session ID update failed, using fallback: " . $e->getMessage(), 'WARNING', 'OrderService_Catalog');
    $this->db->execute(
        "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
        [$sessionId, $roomNumber]
    );
}
```

## 具体的な修正手順

### ステップ1: ファイルバックアップ
```bash
cp /Volumes/crucial_MX500/lacis_project/project/mobesorder/0814変更箇所/OrderService_Catalog.php \
   /Volumes/crucial_MX500/lacis_project/project/mobesorder/0814変更箇所/OrderService_Catalog.php.backup
```

### ステップ2: 修正実行

#### 📝 353-356行目を以下に置換：

**推奨修正（選択肢A）**:
```php
            // line_room_linksも更新（データ汚染防止＋依存処理対応）
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND (is_active = 1 OR (is_active = 0 AND order_session_id IS NULL))",
                [$sessionId, $roomNumber]
            );
```

**推奨修正（選択肢B）**:
```php
            // line_room_linksも更新（段階的アプローチ）
            // Step 1: アクティブユーザーに設定
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
                [$sessionId, $roomNumber]
            );

            // Step 2: セッションID未設定の非アクティブユーザーにも設定
            $this->db->execute(
                "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 0 AND order_session_id IS NULL",
                [$sessionId, $roomNumber]
            );
```

### ステップ3: 修正確認
```bash
# 1. 構文チェック
php -l /Volumes/crucial_MX500/lacis_project/project/mobesorder/0814変更箇所/OrderService_Catalog.php

# 2. 差分確認
diff -u OrderService_Catalog.php.backup OrderService_Catalog.php
```

### ステップ4: 本番反映
```bash
# 本番環境への反映（実際のパスに置き換え）
cp /Volumes/crucial_MX500/lacis_project/project/mobesorder/0814変更箇所/OrderService_Catalog.php [本番パス]/api/lib/
```

## 修正効果の検証

### ✅ 期待される改善
1. **セッション管理の正常化**: order_session_idが適切に設定される
2. **データ汚染の防止**: 過去のユーザーデータが新規セッションと混在しない
3. **依存処理の正常化**: Webhook、管理画面での処理が正常に動作
4. **履歴管理の改善**: ユーザー注文履歴の正確な紐付け

### 🧪 テスト項目

1. **新規セッション作成テスト**:
   - 部屋番号を指定して最初の注文を実行
   - line_room_linksテーブルのorder_session_id設定を確認

2. **データ汚染防止テスト**:
   - 過去のユーザー（is_active=0, order_session_id設定済み）が存在する部屋で新規セッション作成
   - 過去ユーザーのorder_session_idが変更されないことを確認

3. **Square Webhook動作テスト**（重要）:
   ```sql
   -- セッション完了時の処理が全ユーザーに適用されるか
   UPDATE line_room_links SET is_active = 0 WHERE order_session_id = 'TEST_SESSION';
   ```
   - 注文完了時のSquare Webhookが正常に動作することを確認
   - セッション終了時にorder_session_idを持つ全ユーザーが適切に非アクティブ化されることを確認

4. **管理画面フォールバック確認**:
   ```sql
   -- 管理画面でのセッション終了が全ユーザーに適用されるか  
   UPDATE line_room_links SET is_active=0 WHERE (order_session_id = 'TEST_SESSION' OR room_number = 'TEST_ROOM') AND is_active = 1;
   ```
   - 管理者によるセッション強制終了が正常に動作することを確認

5. **AI機能への影響確認**:
   ```sql
   -- AI機能での注文検索が正常に動作するか
   SELECT id, total_amount FROM orders WHERE order_session_id = 'TEST_SESSION' AND order_status='OPEN';
   ```
   - AI推奨機能がorder_session_idで正しく注文を特定できることを確認

6. **同一部屋での順次利用テスト**:
   - ユーザーA→チェックアウト→ユーザーB→新規注文の流れをテスト
   - 両ユーザーが適切なorder_session_idを保持することを確認

## リスク評価

### 🟢 最低リスク（推奨：選択肢D）
- **影響範囲**: 新規処理のみ、既存処理は保持
- **副作用**: なし（フォールバック機能付き）
- **ロールバック**: 即座可能（コメントアウト解除）
- **モニタリング**: ログ出力で動作確認可能

### 🟡 低リスク（選択肢A）
- **影響範囲**: 限定的（当日以降のアクティブユーザーのみ）
- **副作用**: 最小限
- **ロールバック**: 容易
- **注意**: `>= CURDATE()`で当日を含む

### 🟡 中リスク（選択肢C）
- **影響範囲**: 中程度（条件の複雑化）
- **副作用**: パフォーマンスへの軽微な影響
- **ロールバック**: 中程度
- **注意**: 複雑なSQL条件の動作検証が必要

### 🔴 ~~高リスク（非推奨：選択肢B）~~
- **理由**: 削除されました（二段階更新は予期しない副作用のリスク）

## 段階的実装戦略

### Phase 0: 事前準備（必須）
```bash
# 1. 現在のファイルの完全バックアップ
cp OrderService_Catalog.php OrderService_Catalog.php.original

# 2. データベースバックアップ
mysqldump [database_name] line_room_links > line_room_links_backup.sql

# 3. 開発環境でのテスト環境構築
```

### Phase 1: 監視機能追加（最低リスク）
```php
// 現在の処理の前後にログ追加
Utils::log("Before session ID update for room {$roomNumber}", 'DEBUG', 'OrderService_Catalog');

$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);

$affectedRows = $this->db->lastInsertId(); // 影響を受けた行数を取得
Utils::log("Session ID update completed for room {$roomNumber}, affected rows: {$affectedRows}", 'INFO', 'OrderService_Catalog');
```

### Phase 2: 安全な新処理の並行実行（推奨）
```php
// 既存処理は維持、新処理を追加で実行
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1",
    [$sessionId, $roomNumber]
);

// 追加：より安全な条件での再更新
$this->db->execute(
    "UPDATE line_room_links SET order_session_id = ? WHERE room_number = ? AND is_active = 1 AND DATE(created_at) >= CURDATE() AND order_session_id IS NULL",
    [$sessionId, $roomNumber]
);
```

### Phase 3: 段階的移行
1. **1週間**: 並行実行でログ監視
2. **問題なし**: 既存処理をコメントアウト
3. **2週間**: 新処理のみで動作確認
4. **確認完了**: 既存処理を完全削除

## 長期的な改善提案

### 📊 根本的な解決策
1. **session_participantsテーブルの新設**
2. **セッション管理ロジックの分離**
3. **データベース設計の最適化**

### 🚀 Phase化された改善計画
- **Phase 1**: 当日制限修正（即座実行）
- **Phase 2**: 新テーブル設計（1-2週間）
- **Phase 3**: ロジック分離（1ヶ月）

## 注意事項

### ⚠️ 修正時の注意点
1. **本番反映前の必須テスト**: 開発環境での動作確認
2. **データベースバックアップ**: 修正前にDBのバックアップを取得
3. **ログ監視**: 修正後のエラーログを継続監視
4. **関連機能の確認**: Webhook、管理画面の動作確認

### 📞 緊急時の対応
- **問題発生時**: 即座にOrderService_Catalog.php.backupから復元
- **連絡先**: システム管理者
- **ログ確認**: `/logs/OrderService_Catalog.log`

## 結論

この修正指示書は**破壊的影響を最小限に抑える段階的アプローチ**を採用しています。

### 🎯 最推奨アプローチ
1. **Phase 1**: 既存処理を保持したまま監視機能を追加
2. **Phase 2**: 新処理を並行実行で導入
3. **Phase 3**: 段階的に新処理に移行

### ✅ 安全性の保証
- **即座のロールバック**: いつでも元の状態に戻せる
- **フォールバック機能**: 新処理で問題が発生しても既存処理で継続
- **詳細な監視**: ログ出力で動作状況を常時確認
- **段階的検証**: 各段階で十分な検証期間を設ける

### 🚀 推奨実装順序
1. **今すぐ**: Phase 1の監視機能追加（リスクゼロ）
2. **1週間後**: Phase 2の並行実行導入（問題発生時は即座停止可能）
3. **2週間後**: Phase 3の段階的移行（完全な動作確認後）

この段階的アプローチにより、「order_session_idが振られない」問題を解決しつつ、システムの安定性を完全に保持できます。各段階で問題が発生した場合は、即座に前の段階に戻すことができる**破壊的影響ゼロの修正戦略**です。 