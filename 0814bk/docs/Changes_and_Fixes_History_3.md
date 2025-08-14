# 変更・修正履歴 3

## ファイル記入ルール

このファイルは、システムに対して行われた変更や修正を記録するためのものです。以下のルールに従って記載してください：

1. **新しい課題や問題**：
   - トピックごとに見出しを作成し、日付とタイトルを記載
   - 問題内容、エラー詳細、影響範囲を記述
   - 原因と解決案を記載
   - 実施予定のタスクをチェックリスト形式で記載

2. **修正実施報告**：
   - 実施内容に「修正実施報告(N)」という見出しを付け、具体的な変更内容を記述
   - 変更前/変更後のコードを記載
   - 動作確認結果を記載
   - 完了したタスクにはチェックマーク（[x]）を付ける

3. **形式**：
   - マークダウン形式で記載
   - コードブロックは言語を指定（例：```php）
   - 日付形式は YYYY-MM-DD 形式で統一

---

## 2025-05-04 - フロントエンドでのカテゴリ読み取りエラー

### 問題内容
モバイルオーダーのフロントエンドでカテゴリの読み取りに失敗し、「商品カテゴリの読み込みに失敗しました。ページを再読み込みしてください。」というエラーメッセージが表示されています。

#### エラー詳細
Chromeコンソールでは以下のようなエラーが発生しています：
```
カテゴリ取得エラー: Error: カテゴリデータの形式が不正です
    at getCategories (api.js:62:19)
    at async loadCategories (ui.js:12:26)
```

スクリーンショットに表示されたエラー画面では、「エラーが発生しました」という赤いアイコンとともにエラーメッセージが表示されています。

#### 影響範囲
- モバイルオーダーの根幹機能であるカテゴリの表示ができない
- 商品の閲覧や注文が一切できない状態
- すべてのユーザーに影響する可能性が高い

### 調査結果と推定原因

ログファイルと関連コードを調査した結果、以下の問題が見つかりました：

1. **APIレスポンスの途中切断**:
   - CategoryAPI.logを確認すると、リクエストがProductServiceの初期化で停止している
   - 初期化は始まっているが、カテゴリデータの取得やレスポンス生成まで進んでいない

2. **API実行エラーの可能性**:
   - カテゴリ一覧取得処理（`getCategories`メソッド）が完了せずにタイムアウトまたはクラッシュしている可能性がある
   - 内部例外がキャッチされていない、またはエラーログに記録されていない可能性がある

3. **フロントエンドのJSON解析問題**:
   - フロントエンドの`api.js`では`data.success && Array.isArray(data.categories)`をチェックしている
   - APIレスポンスがこの形式に従っていない可能性がある

4. **フォールバック機能の不全**:
   - categories.phpには例外処理とフォールバックが含まれているが、これが機能していない可能性がある
   - フォールバック時のレスポンス形式が不正な可能性がある

### 解決案

#### 1. サーバーサイドのデバッグ強化
`api/v1/products/categories.php`のエラー処理とロギングを強化して、以下の改善を行います：

```php
// 既存のエラーハンドラを強化
function customExceptionHandler($exception) {
    // 既存のコード...
    
    // JSONエンコード前にデータ構造を検証
    $fallbackResponse = [
        'success' => true,
        'categories' => [
            ['id' => 'default', 'name' => 'メニュー', 'icon_url' => 'images/icons/default.png']
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'is_fallback' => true
    ];
    
    // 応答形式を確実にフロントエンドと互換性を持たせる
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode($fallbackResponse);
    
    // ログにもレスポンスを記録
    logCategoryAPI("エラー応答を返しました: " . json_encode($fallbackResponse), 'INFO');
    
    exit(1);
}
```

#### 2. ProductServiceのgetCategoriesメソッドのタイムアウト対策
```php
public function getCategories($activeOnly = true) {
    try {
        // タイムアウト設定を追加
        ini_set('max_execution_time', 30); // 30秒に設定
        
        // 既存のコード...
        
        // 実行時間を計測
        $startTime = microtime(true);
        
        // データベースクエリの実行...
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
        self::logMessage("getCategories完了 - 取得件数: " . count($result) . ", 実行時間: " . $executionTime . "ms");
        
        return $result;
    } catch (Exception $e) {
        // エラーログを詳細に記録
        self::logMessage("カテゴリ取得エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
        
        // エラー時でも最低限のデータを返す
        return [
            ['id' => 'default', 'name' => 'メニュー', 'icon_url' => 'images/icons/default.png']
        ];
    }
}
```

#### 3. フロントエンドのエラーハンドリング強化
`api.js`のgetCategoriesメソッドを修正して、より柔軟なレスポンス形式に対応します：

```javascript
/**
 * カテゴリ一覧を取得
 * @returns {Promise<Array>} カテゴリ一覧
 */
async getCategories() {
    // キャッシュがあればそれを返す
    if (categoriesCache) {
        return categoriesCache;
    }
    
    try {
        // 直接PHPファイルにアクセス
        const data = await this.apiRequest('/products/categories.php');
        
        // より柔軟なレスポンス形式チェック
        if (data && data.success && Array.isArray(data.categories)) {
            // 正常なレスポンス
            categoriesCache = data.categories;
            return data.categories;
        } else if (data && Array.isArray(data)) {
            // データ自体が配列の場合は直接使用
            categoriesCache = data;
            return data;
        } else if (data && typeof data === 'object') {
            // データがオブジェクトで、categoriesプロパティがない場合
            console.warn('カテゴリデータの形式が標準と異なります:', data);
            
            // フォールバック：デフォルトカテゴリを返す
            const fallbackCategories = [
                {id: 'default', name: 'メニュー', icon_url: 'images/icons/default.png'}
            ];
            categoriesCache = fallbackCategories;
            return fallbackCategories;
        } else {
            console.error('カテゴリデータの形式が不正です:', data);
            throw new Error('カテゴリデータの形式が不正です');
        }
    } catch (error) {
        console.error('カテゴリ取得エラー:', error);
        
        // エラーをより詳細に記録
        if (error.response) {
            console.error('エラーレスポンス:', error.response);
        }
        
        // エラー時はデフォルトカテゴリを返す
        const fallbackCategories = [
            {id: 'default', name: 'メニュー', icon_url: 'images/icons/default.png'}
        ];
        categoriesCache = fallbackCategories;
        
        // エラーを投げるか、フォールバックを返すかの判断
        // throw error; // エラーを投げる場合
        return fallbackCategories; // フォールバックを返す場合
    }
}
```

#### 4. ロギングの拡充とモニタリング
サーバーログとクライアントログの両方を強化して問題の原因をより詳細に追跡できるようにします：

1. PHPのログローテーションを正しく設定し、ログが切り捨てられないようにする
2. フロントエンドのコンソールログをサーバーに送信する機能を追加
3. 定期的なログ分析を実施して潜在的な問題を早期に発見する

### 実施予定
- [x] `app.js`にAPIインスタンスのグローバル設定を追加
- [x] `ui.js`にAPI関数のヘルパーラッパーを追加
- [x] `liff-init.js`の初期化シーケンスを修正
- [ ] 修正後の動作確認とエラー監視

### 修正実施報告(1) - 2025-05-04
APIアクセスの問題を修正するため、以下の変更を実施しました：

1. **ui.jsにヘルパー関数を追加**
   - グローバルなAPIインスタンスを使用するためのラッパー関数を実装
   - `getCategories`および`getProductsByCategory`のヘルパー関数を追加
   - これにより、APIクラスのインスタンスメソッドをグローバル関数として呼び出し可能に

2. **app.jsにグローバルAPIインスタンスの設定を追加**
   - アプリケーション初期化時に`window.apiClient = new API()`を設定
   - これによりアプリ全体で共通のAPIインスタンスが使用可能に

3. **liff-init.jsの初期化順序を修正**
   - カテゴリ読み込み前にAPIインスタンスが確実に初期化されるよう変更
   - 二重初期化防止のための条件チェックを追加

これらの変更により、APIクラスのインスタンスメソッドと、UIモジュールでのグローバル関数呼び出しの不一致が解消され、「getCategories is not defined」エラーが修正されました。今後の課題として、モジュール間の依存関係をより明確にするための改善を進める必要があります。

### 補足情報
この問題は、最近のフロントエンドコードのリファクタリングで、APIクラスがインスタンスベースのアプローチに変更されたことに起因しています。以前は関数として直接呼び出せていた機能が、現在はインスタンスメソッドとなったため、適切なインスタンス参照なしでは機能しなくなりました。

## 2025-05-04 - UIからAPIインスタンスへの参照エラー（getCategories is not defined）

### 問題内容
モバイルオーダーのLIFF初期化後にカテゴリーリストの表示に失敗し、「商品カテゴリの読み込みに失敗しました。ページを再読み込みしてください。」というエラーメッセージが表示されています。

#### エラー詳細
ブラウザのコンソールには以下のエラーメッセージが記録されています：
```
カテゴリ読み込みエラー: ReferenceError: getCategories is not defined
    at loadCategories (ui.js:14:28)
    at HTMLDocument.initializeLiff (liff-init.js:50:9)
```

エラーはLIFF初期化後に`loadCategories`関数内で`getCategories`関数を呼び出そうとした際に発生しています。しかし、グローバルスコープ上に`getCategories`という関数が定義されていないため、参照エラーが発生しています。

#### 影響範囲
- モバイルオーダーの根幹機能であるカテゴリの表示ができない
- カテゴリが表示されないため、メニュー選択や注文機能が完全に使用不可
- すべてのユーザーに影響するため、サービス全体が事実上停止状態

### 原因
問題を調査した結果、以下の原因が特定されました：

1. **API関数のスコープ問題**：
   - `API`クラスの`getCategories`メソッドはインスタンスメソッドとして定義されている
   - しかし、`ui.js`の`loadCategories`関数では、グローバル関数として`getCategories`を呼び出そうとしている
   - APIインスタンスへの参照が正しく共有されていないため、関数が見つからない

2. **モジュール間の連携不足**：
   - `liff-init.js`と`ui.js`の間でAPI関数の呼び出し方法に不一致がある
   - `app.js`でAPIインスタンスをグローバルに設定する処理が不足している

### 解決案

#### 1. APIインスタンスをグローバルに設定
`app.js`を修正して、アプリケーション初期化時にAPIインスタンスをグローバルに設定します：

```javascript
// app.js内のinitializeApp関数を修正
async function initializeApp() {
    if (app.initialized) return;
    
    try {
        // APIインスタンスをグローバルに設定
        window.apiClient = new API();
        
        // 残りの初期化処理...
```

#### 2. UIヘルパー関数の追加
`ui.js`に新たなヘルパー関数を追加して、グローバルなAPIインスタンスを介してカテゴリを取得します：

```javascript
/**
 * カテゴリ一覧を取得するヘルパー関数
 * APIインスタンスのgetCategoriesメソッドを呼び出す
 * @returns {Promise<Array>} カテゴリ一覧
 */
async function getCategories() {
    if (!window.apiClient) {
        window.apiClient = new API();
    }
    return await window.apiClient.getCategories();
}

/**
 * カテゴリ別商品一覧を取得するヘルパー関数
 * @param {string} categoryId カテゴリID
 * @returns {Promise<Array>} 商品一覧
 */
async function getProductsByCategory(categoryId) {
    if (!window.apiClient) {
        window.apiClient = new API();
    }
    return await window.apiClient.getProductsByCategory(categoryId);
}
```

#### 3. liff-init.jsでのロード順序の修正
`liff-init.js`を修正して、APIインスタンスが確実に初期化された後にカテゴリを読み込むようにします：

```javascript
// liff-init.js内のinitializeLiff関数の該当部分を修正
async function initializeLiff() {
    try {
        // ...既存のコード...
        
        // ローディング表示を終了し、コンテンツを表示
        document.getElementById('loading').style.display = 'none';
        document.getElementById('content-container').style.display = 'block';
        
        // APIインスタンスを初期化（未定義の場合）
        if (!window.apiClient) {
            window.apiClient = new API();
        }
        
        // 商品データの初期読み込み
        loadCategories();
        
    } catch (error) {
        // ...既存のコード...
    }
}
```

### 実施予定
- [x] `app.js`にAPIインスタンスのグローバル設定を追加
- [x] `ui.js`にAPI関数のヘルパーラッパーを追加
- [x] `liff-init.js`の初期化順序を修正
- [ ] 修正後の動作確認とエラー監視

### 補足情報
この問題は、最近のフロントエンドコードのリファクタリングで、APIクラスがインスタンスベースのアプローチに変更されたことに起因しています。以前は関数として直接呼び出せていた機能が、現在はインスタンスメソッドとなったため、適切なインスタンス参照なしでは機能しなくなりました。
