api.js
/**
 * バックエンドAPIとの通信を行うモジュール
 * 
 * 【重要】商品情報・ラベル情報のハードコードは禁止
 * すべての商品情報とラベル情報はデータベースから動的に取得すること
 * 特定の商品IDに依存した処理も禁止
 * - 商品ID、商品名、ラベル情報などの値をコード内に直接記述してはならない
 * - すべての情報はAPIを通じてDBから取得すること
 * - 特定商品の例外処理・条件分岐も実装してはならない
 */

/**
 * API通信を管理するモジュール
 * プロトタイプベースのシンプルな実装
 */

// LIFF IDを取得する関数
function getLiffId() {
    console.log("取得されたLIFF ID:", window.LIFF_ID);
    return window.LIFF_ID;
}

// 初期化時にLIFF IDを確認
console.log("LIFF ID確認:", window.LIFF_ID);

// APIクラスの定義（プロトタイプベース）
function API(baseUrl) {
    this.baseUrl = baseUrl || '/fgsquare/api/v1';
    this.productCache = {};
    this.categoryCache = {};
    this.requestTimeout = 15000; // 15秒タイムアウト
    console.log('API初期化 baseUrl:', this.baseUrl);
    
    // LIFF IDの確認
    console.log("API初期化時のLIFF ID:", getLiffId());
}

// プロトタイプメソッド：製品カテゴリを取得
API.prototype.getCategories = function() {
    var self = this;
    
    // index.phpのルーティングに合わせたエンドポイント
    var url = this.baseUrl + '/products/categories';
    console.log('カテゴリ取得URL:', url);
    var timestamp = new Date().getTime();
    
    // タイムスタンプでキャッシュを防止
    url += '?_=' + timestamp;
    
    return this.sendRequest(url)
        .then(function(data) {
            if (data && data.categories) {
                self.categoryCache = data.categories.reduce(function(cache, category) {
                    cache[category.id] = category;
                    return cache;
                }, {});
                return data.categories;
            } else {
                throw new Error('カテゴリデータが不正です');
            }
        });
};

// プロトタイプメソッド：製品一覧を取得
API.prototype.getProducts = function(categoryId) {
    var self = this;
    
    // index.phpのルーティングに合わせたエンドポイント
    var url = this.baseUrl + '/products';
    var timestamp = new Date().getTime();
    
    // パラメータ設定
    url += '?_=' + timestamp;
    if (categoryId) {
        url += '&category_id=' + encodeURIComponent(categoryId);
    }
    
    return this.sendRequest(url)
        .then(function(data) {
            if (data && data.products) {
                // キャッシュに保存
                data.products.forEach(function(product) {
                    self.productCache[product.id] = product;
                });
                return data.products;
            } else {
                throw new Error('製品データが不正です');
            }
        });
};

// プロトタイプメソッド：製品詳細を取得
API.prototype.getProductDetails = function(productId) {
    var self = this;
    
    // キャッシュにあれば使用
    if (this.productCache[productId]) {
        return Promise.resolve(this.productCache[productId]);
    }
    
    // 製品詳細のエンドポイントは直接定義されていないので、製品リスト取得と同じエンドポイントを使用
    var url = this.baseUrl + '/products?id=' + encodeURIComponent(productId);
    var timestamp = new Date().getTime();
    url += '&_=' + timestamp;
    
    return this.sendRequest(url)
        .then(function(data) {
            if (data && data.products && data.products.length > 0) {
                var product = data.products[0];
                // キャッシュに保存
                self.productCache[product.id] = product;
                return product;
            } else {
                throw new Error('製品詳細データが不正です');
            }
        });
};

// プロトタイプメソッド：製品を検索
API.prototype.searchProducts = function(query) {
    var url = this.baseUrl + '/search.php?q=' + encodeURIComponent(query);
    var timestamp = new Date().getTime();
    url += '&_=' + timestamp;
    
    return this.sendRequest(url)
        .then(function(data) {
            if (data && data.products) {
                return data.products;
            } else {
                return [];
            }
        });
};

// プロトタイプメソッド：注文を送信
API.prototype.submitOrder = function(orderData) {
    var url = this.baseUrl + '/submit-order.php';
    var timestamp = new Date().getTime();
    url += '?_=' + timestamp;
    
    return this.sendRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    });
};

// プロトタイプメソッド：注文履歴を取得
API.prototype.getOrderHistory = function(userId) {
    if (!userId) {
        return Promise.reject(new Error('ユーザーIDが指定されていません'));
    }
    
    var url = this.baseUrl + '/order-history.php?user_id=' + encodeURIComponent(userId);
    var timestamp = new Date().getTime();
    url += '&_=' + timestamp;
    
    return this.sendRequest(url);
};

// プロトタイプメソッド：部屋連携状態を確認
API.prototype.checkRoomLink = function(lineUserId) {
    if (!lineUserId) {
        return Promise.reject(new Error('LINEユーザーIDが指定されていません'));
    }
    
    var url = this.baseUrl + '/check-room-link.php?line_user_id=' + encodeURIComponent(lineUserId);
    var timestamp = new Date().getTime();
    url += '&_=' + timestamp;
    
    return this.sendRequest(url);
};

// プロトタイプメソッド：部屋を登録
API.prototype.registerRoom = function(lineUserId, roomNumber) {
    if (!lineUserId) {
        return Promise.reject(new Error('LINEユーザーIDが指定されていません'));
    }
    
    if (!roomNumber) {
        return Promise.reject(new Error('部屋番号が指定されていません'));
    }
    
    var url = this.baseUrl + '/register-room.php';
    var timestamp = new Date().getTime();
    url += '?_=' + timestamp;
    
    return this.sendRequest(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            line_user_id: lineUserId,
            room_number: roomNumber
        })
    });
};

// プロトタイプメソッド：HTTP通信の基本メソッド
API.prototype.sendRequest = function(url, options) {
    var self = this;
    options = options || {};
    
    console.log('API リクエスト送信:', url);

    // デフォルトタイムアウトの設定
    var timeoutId;
    var timeoutPromise = new Promise(function(_, reject) {
        timeoutId = setTimeout(function() {
            reject(new Error('リクエストがタイムアウトしました'));
        }, self.requestTimeout);
    });
    
    // 実際のリクエスト処理
    var fetchPromise = fetch(url, options)
        .then(function(response) {
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error('APIエラー: ' + response.status);
            }
            
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                throw new Error(data.error);
            }
            console.log('API レスポンス成功:', url);
            return data;
        });
    
    // タイムアウトとfetchを競争させる
    return Promise.race([fetchPromise, timeoutPromise])
        .catch(function(error) {
            console.error('API通信エラー:', error);
            throw error;
        });
};

// グローバルインスタンスの作成
window.apiClient = new API('/fgsquare/api/v1');

console.log('API通信モジュールが正常に初期化されました'); 
