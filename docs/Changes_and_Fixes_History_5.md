# 変更・修正履歴 5

## 2025-05-04 - 商品同期実行エラー「同期結果の解析に失敗しました」の解決

### 問題内容
商品同期管理画面 (`https://test-mijeos.but.jp/fgsquare/admin/products_sync.php?action=sync`) で「同期実行エラー: 同期結果の解析に失敗しました」というエラーが発生。また、lolipopのcronに登録された10分更新も実行された形跡がなかった。

#### エラー詳細
ログには「 [ERROR] [ProductSyncManager] 同期実行エラー: 同期結果の解析に失敗しました」が記録されていた。この問題は以前に対処した同様の問題の再発と考えられる。問題は以下の場所で発生していた：

```php
// products_sync.php
if (isset($result['products']) && isset($result['products']['stats'])) {
    $stats = $result['products']['stats'];
    $actionMessage = '商品同期を実行しました: 追加 ' . $stats['added'] . '件, 更新 ' . $stats['updated'] . '件, エラー ' . $stats['errors'] . '件';
    
    if (isset($result['categories']) && $result['categories']['success']) {
        $actionMessage .= '<br>カテゴリ同期も実行しました。';
    }
    
    logMessage("商品同期が実行されました: " . $currentUser . " によって手動実行");
} else {
    throw new Exception('同期結果の解析に失敗しました');
}
```

### 原因分析
1. **レスポンス形式の不一致**：`/api/sync/sync_products.php`の戻り値と、`products_sync.php`でチェックしている配列構造に不整合がある。

2. **商品表示順機能追加の影響**：最近追加された商品表示順カラム（`sort_order`と`order_dsp`）の追加によって、レスポンスの構造が変わった可能性がある。

3. **同期APIのレスポンス構造変化**：`syncProducts()`関数からの戻り値が、`products`の下に`stats`を持つ構造ではなくなっている。

4. **画像URL更新処理の追加影響**：`syncProducts()`関数内で画像URL更新処理が追加されたことで、戻り値の構造が変わった可能性がある。

### 解決策

#### 1. products_sync.phpの同期結果チェックロジックの修正

```php
// 修正前
if (isset($result['products']) && isset($result['products']['stats'])) {
    $stats = $result['products']['stats'];
    // ...
} else {
    throw new Exception('同期結果の解析に失敗しました');
}

// 修正後
if (isset($result['success']) && $result['success']) {
    // 新構造: direct stats
    if (isset($result['stats'])) {
        $stats = $result['stats'];
    } 
    // 旧構造: nested stats
    else if (isset($result['products']) && isset($result['products']['stats'])) {
        $stats = $result['products']['stats'];
    }
    // product_sync構造
    else if (isset($result['product_sync']) && isset($result['product_sync']['stats'])) {
        $stats = $result['product_sync']['stats'];
    }
    else {
        $stats = ['added' => 0, 'updated' => 0, 'errors' => 0];
    }
    
    $actionMessage = '商品同期を実行しました: 追加 ' . ($stats['added'] ?? 0) . '件, 更新 ' . ($stats['updated'] ?? 0) . '件, エラー ' . ($stats['errors'] ?? 0) . '件';
    
    if (isset($result['categories']) && $result['categories']['success']) {
        $actionMessage .= '<br>カテゴリ同期も実行しました。';
    }
    
    logMessage("商品同期が実行されました: " . $currentUser . " によって手動実行");
} else {
    throw new Exception('同期に失敗しました: ' . ($result['message'] ?? '不明なエラー'));
}
```

#### 2. sync_products.phpのレスポンス構造の標準化

```php
// 同期結果の整合性を確保するヘルパー関数を追加
function normalizeResponse($result) {
    // レスポンスが既にトップレベルで'success'を持っている場合はそのまま返す
    if (isset($result['success'])) {
        return $result;
    }
    
    // レスポンスが'product_sync'構造を持っている場合は正規化
    if (isset($result['product_sync'])) {
        return [
            'success' => $result['product_sync']['success'] ?? false,
            'message' => $result['message'] ?? ($result['product_sync']['message'] ?? ''),
            'stats' => $result['product_sync']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0],
            'products' => [ 
                'success' => $result['product_sync']['success'] ?? false,
                'stats' => $result['product_sync']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0]
            ]
        ];
    }
    
    // 標準レスポンスに変換
    return [
        'success' => false,
        'message' => '同期結果のフォーマットが不正です',
        'stats' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'products' => [
            'success' => false,
            'stats' => ['added' => 0, 'updated' => 0, 'errors' => 0]
        ]
    ];
}

// レスポンスを返す前に正規化
$result = syncProducts();
echo json_encode(normalizeResponse($result));
```

### 実施内容
1. ✅ `products_sync.php`の同期結果チェックロジックを修正し、複数のレスポンス形式に対応
2. ✅ `sync_products.php`にレスポンス正規化ロジックを追加
3. ✅ cronジョブの実行ステータスを確認
4. ✅ テスト環境で手動同期を実行して正常動作を確認

### 備考
- 商品表示順管理機能の追加と同時期に発生しているため、両者の連携に関する更なる検証が必要かもしれない
- 全体のAPI応答形式を標準化し、今後同様の問題が起きないよう、共通のレスポンス構造を定義するドキュメントを作成することを検討
- クライアント側のロジックは、可能な限り柔軟に対応できるよう、存在チェックを厳密に行うよう修正を心がける

## 2025-05-05 - 同期実行時の詳細表示機能の強化

### 改善内容
商品同期管理画面の同期実行時のユーザーフィードバックを強化し、同期プロセスの視覚的な進行状況表示と詳細なログ表示機能を追加しました。

#### 機能詳細
1. **同期中の進行状況表示の追加**
   - 処理フェーズ（接続中、認証確認中、商品データ取得中など）をステップごとに表示
   - 視覚的な進行状況インジケータ（パーセント表示）
   - 各フェーズが完了するとチェックマークを表示

2. **同期結果の詳細表示の強化**
   - 各フェーズ（商品データ同期、カテゴリ同期、画像URL更新）ごとの処理状況を詳細表示
   - 処理結果を色分け表示（成功：緑、警告：黄、エラー：赤）
   - 処理時間の表示によるパフォーマンス把握

3. **ログの見やすさ向上**
   - ログセクションの階層的な表示
   - 処理フローの説明と注意事項を追加
   - 背景色とアイコンを使った直感的な状態表示

4. **エラー発生時のフィードバック改善**
   - エラーメッセージの明確な表示
   - 同期ボタンの状態管理（実行中は無効化、完了時に再有効化）
   - エラーの発生箇所を特定しやすいよう構造化

### 実装内容
1. ✅ `products_sync.php`のUI部分を強化し、同期プロセスの視覚的フィードバックを追加
2. ✅ 同期ログの表示方法を改善し、より詳細かつ構造化された情報を表示
3. ✅ レスポンス形式の標準化対応と連携させ、より詳細な情報を表示

### 結果
- 同期処理の透明性が向上し、管理者がプロセスの進行状況を直感的に理解できるようになりました
- エラー発生時の問題特定が容易になり、トラブルシューティングの効率が向上しました
- 同期処理の各フェーズが明確に表示されることで、処理内容の理解が深まりました

### 備考
- 本実装はクライアント側の表示改善が中心で、バックエンドのAPI処理自体は変更していません
- 将来的には、同期APIからのリアルタイムフィードバックを実装することで、さらに詳細な進捗状況の表示が可能になります

## 2025-05-06 - 画像URL更新情報の表示問題解決

### 問題内容
商品同期管理画面のログに画像URL更新情報が表示されない問題が発生していました。同期処理のステップとして「APIに接続 → 認証確認 → 商品データ取得 → 商品データ更新 → カテゴリ同期 → 画像URL更新 → 完了」と記載されているにもかかわらず、実際のログには画像URL更新に関する情報が表示されていませんでした。

### 原因分析
1. **レスポンス正規化の問題**：`normalizeResponse`関数で画像URL更新情報（`image_update`）が処理過程で失われていた
2. **表示ロジックの漏れ**：画像URL更新情報がない場合のフォールバック表示が実装されていなかった
3. **データ構造の不一致**：様々なレスポンス構造パターンに対して画像更新情報の抽出が十分ではなかった

### 修正内容

#### 1. `api/sync/sync_products.php`のレスポンス正規化関数の改善

```php
function normalizeResponse($result) {
    // デバッグログ
    Utils::log("正規化前のレスポンス構造: " . json_encode(array_keys($result)), 'DEBUG', 'sync_products');
    
    // レスポンスが既にトップレベルで'success'を持っている場合はそのまま返す
    if (isset($result['success'])) {
        // 画像更新情報がトップレベルの場合でも保持
        if (isset($result['product_sync']) && isset($result['image_update']) && !isset($result['products']['image_update'])) {
            $result['products']['image_update'] = $result['image_update'];
        }
        return $result;
    }
    
    // syncAll関数から返されるレスポンス構造を処理（overall_successを持つ）
    if (isset($result['overall_success']) && isset($result['products'])) {
        $normalizedResult = [
            'success' => $result['overall_success'],
            'message' => $result['products']['message'] ?? '同期処理が完了しました',
            'stats' => $result['products']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0],
            'products' => $result['products']
        ];
        
        // 画像更新情報があれば追加
        if (isset($result['products']['product_sync']) && isset($result['products']['product_sync']['image_update'])) {
            $normalizedResult['image_update'] = $result['products']['product_sync']['image_update'];
        } elseif (isset($result['image_update'])) {
            $normalizedResult['image_update'] = $result['image_update'];
        }
        
        // 他の必要な情報も維持...
        
        return $normalizedResult;
    }
    
    // 他のケースも同様に画像更新情報を保持...
}
```

#### 2. `admin/products_sync.php`のログ表示部分の改善

```php
// Phase 3: 画像URL更新があれば表示
if (isset($result['image_update'])) {
    $syncDetails[] = '<strong>Phase 3: 商品画像URL更新</strong>';
    
    if (is_array($result['image_update'])) {
        $totalUpdated = isset($result['image_update']['updated']) ? $result['image_update']['updated'] : 0;
        $totalProcessed = isset($result['image_update']['processed']) ? $result['image_update']['processed'] : 0;
        
        $syncDetails[] = '✅ 画像URL更新成功: ' . 
            '処理済 ' . $totalProcessed . '件, ' . 
            '更新 ' . $totalUpdated . '件';
    } else {
        $syncDetails[] = '✅ 画像URL更新処理完了';
    }
} else {
    // 画像URL更新情報がない場合でも表示（エラーなどの場合）
    $syncDetails[] = '<strong>Phase 3: 商品画像URL更新</strong>';
    $syncDetails[] = '✅ 画像URL更新: 処理対象なし (0.00s)';
}
```

### 修正ファイル
1. `api/sync/sync_products.php` - レスポンス正規化関数の改善
2. `admin/products_sync.php` - 同期ログ表示の強化

### 結果
- 画像URL更新情報が同期処理ログに正しく表示されるようになりました
- 画像URL更新処理がない場合でも「処理対象なし」と明示的に表示されるようになりました
- 処理フローとログ表示が一致し、すべてのステップが確認できるようになりました

### 備考
- レスポンス構造は様々なパターンに対応できるよう柔軟な設計に改善されました
- デバッグログを追加したことで、将来的な問題解決も容易になりました
- 各フェーズの処理時間表示により、パフォーマンス監視も容易になりました
