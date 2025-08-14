"use strict";

/**
 * バックエンドAPIとの通信を行うモジュール
 * LIFFベースの認証を使用し、注文操作やカート管理を行う
 */

// APIのベースURL
const API_BASE_URL = 'https://test-mijeos.but.jp/fgsquare/api/v1';

// カテゴリデータをキャッシュする変数
let categoriesCache = null;

// 商品データをキャッシュする変数
let productsCache = {};
class API {
  constructor(baseUrl) {
    this.baseUrl = baseUrl || '/fgsquare/order/api';
    this.apiBaseUrl = API_BASE_URL;
    this.productCache = {};
    this.categoryCache = {};
    this.adminSettingsCache = null;
    console.log('APIクライアント初期化: baseUrl=' + this.baseUrl);
  }

  /**
   * APIリクエストを送信する基本関数
   * @param {string} endpoint - APIエンドポイント
   * @param {string} method - HTTPメソッド
   * @param {Object} data - 送信するデータ
   * @returns {Promise<Object>} レスポンス
   */
  async apiRequest(endpoint) {
    let method = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'GET';
    let data = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
    const url = `${this.baseUrl}${endpoint}`;
    console.log(`API呼び出し: ${url}`);
    const options = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    };

    // 認証情報をヘッダーに追加
    if (typeof roomInfo !== 'undefined' && roomInfo) {
      if (roomInfo.token) {
        options.headers['Authorization'] = `Bearer ${roomInfo.token}`;
        console.log('部屋情報から認証トークンをリクエストに追加しました');
      } else {
        console.warn('認証トークンが空か未定義です。認証なしでリクエストを続行します。');
        // ヘッダーに空のトークンを設定しない
      }
    } else {
      console.warn('部屋情報が利用できません。認証なしでリクエストを続行します。');
    }

    // LINEユーザーIDがあれば追加（新しい認証方式用）
    if (typeof userProfile !== 'undefined' && userProfile && userProfile.userId) {
      options.headers['X-LINE-USER-ID'] = userProfile.userId;
      console.log('LINE User IDをヘッダーに追加しました:', userProfile.userId.substring(0, 8) + '...');
    }
    if (data && (method === 'POST' || method === 'PUT')) {
      // LINE User IDをデータにも追加（新しい認証方式用）
      if (typeof userProfile !== 'undefined' && userProfile && userProfile.userId) {
        data = {
          ...data,
          line_user_id: userProfile.userId
        };
        console.log('リクエストデータにLINE User IDを追加しました');
      }
      options.body = JSON.stringify(data);
    }
    try {
      const response = await fetch(url, options);

      // レスポンスのステータスコードをチェック
      if (!response.ok) {
        // 401エラーの場合、特別な処理を追加
        if (response.status === 401) {
          console.warn('認証エラー (401)。認証なしでリクエストを再試行します...');

          // Authorizationヘッダーを削除して再試行
          const retryOptions = {
            ...options
          };
          delete retryOptions.headers['Authorization'];
          const retryResponse = await fetch(url, retryOptions);
          if (retryResponse.ok) {
            return await retryResponse.json();
          } else {
            const errorData = await retryResponse.json().catch(() => ({}));
            throw new Error(errorData.message || `APIエラー: ${retryResponse.status}`);
          }
        }
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `APIエラー: ${response.status} ${response.statusText}`);
      }
      return await response.json();
    } catch (error) {
      console.error(`API ${endpoint} エラー:`, error);
      throw error;
    }
  }

  /**
   * カテゴリ一覧を取得
   * @returns {Promise<Object>} カテゴリ一覧とメタデータ
   */
  async getCategories() {
    // キャッシュがあればそれを返す
    if (categoriesCache) {
      return categoriesCache;
    }
    try {
      // APIエンドポイントにアクセス
      const data = await this.apiRequest('/products/categories.php');
      if (data && data.success) {
        // 現在のレスポンス形式をチェック
        if (data.categories && data.categories.categories && Array.isArray(data.categories.categories)) {
          // 現在の形式：categories.categoriesが配列、categories.metaがメタデータ
          categoriesCache = {
            items: data.categories.categories,
            metadata: data.categories.meta || {}
          };
          return categoriesCache;
        }
        // 新しい応答形式をチェック
        else if (data.categories && data.categories.items && Array.isArray(data.categories.items)) {
          // 新しい形式 - オブジェクトにitemsとmetadataを持つ
          categoriesCache = data.categories;
          return data.categories;
        } else if (Array.isArray(data.categories)) {
          // 古い形式 - 単純な配列
          // 新しい形式に変換
          categoriesCache = {
            items: data.categories,
            metadata: {
              all_closed: false,
              current_time: new Date().toTimeString().substring(0, 5)
            }
          };
          return categoriesCache;
        } else {
          console.error('不明なカテゴリデータ形式:', data);
          throw new Error('カテゴリデータの形式が不正です');
        }
      } else {
        throw new Error('カテゴリ取得に失敗しました: ' + (data.message || '不明なエラー'));
      }
    } catch (error) {
      console.error('カテゴリ取得エラー:', error);

      // エラー時はデフォルト返却
      const defaultCategories = {
        items: [],
        metadata: {
          all_closed: false,
          current_time: new Date().toTimeString().substring(0, 5),
          is_error: true,
          error_message: error.message
        }
      };
      categoriesCache = defaultCategories;
      return defaultCategories;
    }
  }

  /**
   * カテゴリ別商品一覧を取得する
   * @param {string} categoryId カテゴリID
   * @returns {Promise<Array>} 商品一覧
   */
  async getProductsByCategory(categoryId) {
    if (!categoryId) {
      console.error('カテゴリIDが指定されていません');
      return [];
    }
    try {
      // ラベル情報も含めるためのパラメータを追加
      const timestamp = Date.now();
      console.log(`API呼び出し: ${this.baseUrl}/products?category_id=${categoryId}&include_labels=true&nocache=${timestamp}`);
      console.log(`APIパラメータ: categoryId=${categoryId}, include_labels=true`);

      // APIリクエストを実行
      const data = await this.apiRequest(`/products?category_id=${categoryId}&include_labels=true&nocache=${timestamp}`);
      console.log("API応答データ:", data);

      // より堅牢なチェック - 複数の応答形式に対応
      if (data && data.success === true && Array.isArray(data.products)) {
        const products = data.products;
        console.log(`取得商品数: ${products.length}件`);
        if (products.length > 0) {
          console.log(`最初の商品データサンプル:`, products[0]);
        } else {
          console.warn(`カテゴリ ${categoryId} の商品がありません`);
        }

        // 商品データをキャッシュに保存
        if (typeof this.cacheProducts === 'function') {
          this.cacheProducts(products);
        } else {
          // キャッシュ関数が存在しない場合は単純なキャッシュを実装
          productsCache[categoryId] = products;
        }
        return products;
      } else {
        console.warn('API応答に有効な商品データがありません:', data);
        return [];
      }
    } catch (error) {
      console.error('カテゴリ別商品取得APIエラー:', error);
      return [];
    }
  }

  /**
   * 商品の詳細情報を取得
   * @param {string} productId - 商品ID
   * @returns {Promise<Object>} 商品詳細
   */
  async getProductDetails(productId) {
    try {
      // APIエンドポイントを構築 - URLが重複しないように修正
      const endpoint = `/fgsquare/order/api/get-product-details.php?id=${productId}`;
      const fullUrl = `${document.location.origin}${endpoint}`;
      console.log(`API呼び出し: ${fullUrl}`);

      // APIリクエスト
      const response = await fetch(fullUrl);

      // エラーチェック
      if (!response.ok) {
        throw new Error(`APIエラー: ${response.status}`);
      }

      // JSONをパース
      const data = await response.json();

      // 結果チェック
      if (!data || !data.success) {
        console.warn(`商品詳細取得エラー: ${data && data.error ? data.error : '不明なエラー'}`);
        return null;
      }

      // キャッシュに格納
      if (data.data) {
        this.productCache[productId] = data.data;
      }
      return data.data;
    } catch (error) {
      console.error('商品詳細取得中にエラーが発生しました:', error);
      return null;
    }
  }

  /**
   * 注文を作成
   * @param {Object} orderData - 注文データ
   * @returns {Promise<Object>} 作成された注文情報
   */
  async createOrder(orderData) {
    try {
      // 部屋番号を確実に設定（グローバルのroomInfoから取得）
      if (typeof roomInfo !== 'undefined' && roomInfo && roomInfo.room_number) {
        // 元のデータを保存（デバッグ用）
        const originalRoomNumber = orderData.roomNumber || 'なし';
        console.log('注文前の元の部屋番号:', originalRoomNumber);
        console.log('認証情報の部屋番号:', roomInfo.room_number);

        // 部屋番号を上書き
        orderData.roomNumber = roomInfo.room_number;
        console.log('部屋番号を設定:', orderData.roomNumber);
      }

      // データ形式変換: product_id を square_item_id に変換
      if (orderData.items && Array.isArray(orderData.items)) {
        // 先にすべてのカテゴリの商品を取得して、正しいsquare_item_idを得るために必要
        if (!categoriesCache) {
          await this.getCategories();
        }
        const processedItems = [];
        for (let item of orderData.items) {
          // すでにJSONに変換されている場合は、パースして処理
          let parsedItem = typeof item === 'string' ? JSON.parse(item) : item;

          // 商品IDを使用してキャッシュから商品情報を取得
          const productId = parsedItem.product_id;
          let foundProduct = null;
          let squareItemId = null;
          let productName = parsedItem.name;
          let productPrice = parsedItem.price;

          // 商品情報をキャッシュから探す
          for (const categoryId in productsCache) {
            const products = productsCache[categoryId];
            const product = products.find(p => p.id === productId || p.id === Number(productId));
            if (product) {
              foundProduct = product;
              squareItemId = product.square_item_id;
              productName = product.name || productName;
              productPrice = product.price || productPrice;
              break;
            }
          }

          // キャッシュに商品がない場合、カテゴリごとに商品を取得して探す
          if (!foundProduct && categoriesCache) {
            for (const category of categoriesCache) {
              try {
                const products = await this.getProductsByCategory(category.id);
                const product = products.find(p => p.id === productId || p.id === Number(productId));
                if (product) {
                  foundProduct = product;
                  squareItemId = product.square_item_id;
                  productName = product.name || productName;
                  productPrice = product.price || productPrice;
                  break;
                }
              } catch (error) {
                console.error("カテゴリ " + category.id + " の商品取得エラー:", error);
              }
            }
          }

          // 名前と価格を確認
          if (!productName) {
            console.error("商品ID " + productId + " の名前が見つかりません。この商品はスキップされます。");
            continue;
          }
          if (!productPrice) {
            console.warn("商品ID " + productId + " の価格が見つかりません。価格は0として扱われます。");
            productPrice = 0;
          }

          // 修正後は常に名前と価格を送信し、square_item_idも追加情報として送信
          const processedItem = {
            name: productName,
            price: productPrice,
            quantity: parsedItem.quantity,
            note: parsedItem.note || ''
          };

          // 追加情報としてsquare_item_idを保持
          if (squareItemId) {
            processedItem.square_item_id = squareItemId;
          }
          processedItems.push(processedItem);
        }
        orderData.items = processedItems;
      }

      // 送信直前の詳細なログ
      console.log('注文送信前の完全なデータ:', JSON.stringify(orderData));
      const data = await this.apiRequest('/orders', 'POST', orderData);

      // APIレスポンスをデバッグ出力
      console.log('注文作成リクエスト送信データ:', JSON.stringify(orderData));
      console.log('注文作成レスポンス:', data);
      if (data && data.success && data.order) {
        return data.order;
      } else {
        throw new Error(data.message || '注文作成に失敗しました');
      }
    } catch (error) {
      console.error('注文作成エラー:', error);
      throw error;
    }
  }

  /**
   * 注文履歴を取得
   * @param {String} roomNumber - 部屋番号
   * @param {Number} limit - 取得する最大件数 (デフォルト10)
   * @returns {Promise<Array>} 注文履歴一覧
   */
  async getOrderHistory(roomNumber) {
    let limit = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 10;
    try {
      // まずバックエンドAPIを試す
      try {
        const data = await this.apiRequest('/orders/history');
        if (data && data.success && Array.isArray(data.orders)) {
          return data.orders;
        }
      } catch (error) {
        console.log('標準APIでの注文履歴取得に失敗しました、直接エンドポイントを試します:', error);
      }

      // 部屋番号を取得
      if (!roomNumber && typeof roomInfo !== 'undefined' && roomInfo) {
        roomNumber = roomInfo.room_number;
      }
      if (!roomNumber) {
        throw new Error('部屋番号が必要です');
      }

      // 直接APIエンドポイントを呼び出す
      const url = "/fgsquare/order/api/get-order-history.php?room_number=" + encodeURIComponent(roomNumber) + "&limit=" + limit;
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error("API error: " + response.status);
      }
      const data = await response.json();
      if (data && data.success && Array.isArray(data.data)) {
        return data.data;
      } else {
        throw new Error(data.error || '注文履歴データの形式が不正です');
      }
    } catch (error) {
      console.error('注文履歴取得エラー:', error);
      // エラー時は空配列を返す
      return [];
    }
  }

  /**
   * 特定の注文の詳細を取得
   * @param {string} orderId - 注文ID
   * @returns {Promise<Object>} 注文詳細
   */
  async getOrderDetails(orderId) {
    try {
      const data = await this.apiRequest("/orders?id=" + orderId);
      if (data && data.success && data.order) {
        return data.order;
      } else {
        throw new Error('注文詳細データの形式が不正です');
      }
    } catch (error) {
      console.error("注文ID " + orderId + " の詳細取得エラー:", error);
      throw error;
    }
  }

  /**
   * カートデータをサーバーに保存
   * @param {Array} cartItems - カート内商品
   * @returns {Promise<Object>} 保存結果
   */
  async saveCart(cartItems) {
    try {
      // ローカルストレージにのみ保存する（バックエンドAPIなし）
      localStorage.setItem('cartItems', JSON.stringify(cartItems));
      return {
        success: true
      };
    } catch (error) {
      console.error('カート保存エラー:', error);
      throw error;
    }
  }

  /**
   * サーバーからカートデータを取得
   * @returns {Promise<Array>} カート内商品
   */
  async getCart() {
    try {
      // ローカルストレージからのみ取得する（バックエンドAPIなし）
      const cartData = localStorage.getItem('cartItems');
      return cartData ? JSON.parse(cartData) : [];
    } catch (error) {
      console.error('カート取得エラー:', error);
      throw error;
    }
  }

  /**
   * カートをクリア
   * @returns {Promise<Object>} クリア結果
   */
  async clearCart() {
    try {
      // ローカルストレージからのみ削除する（バックエンドAPIなし）
      localStorage.removeItem('cartItems');
      return {
        success: true
      };
    } catch (error) {
      console.error('カートクリアエラー:', error);
      throw error;
    }
  }

  /**
   * 商品画像URLを取得する
   * 画像IDからSquare APIを使って実際のURLを取得
   * 
   * @param {string} imageId 画像ID
   * @returns {Promise<string>} 画像URL（取得できない場合は空文字）
   */
  async getImageUrl(imageId) {
    try {
      // 既にURLの場合はそのまま返す
      if (!imageId || imageId.startsWith('http')) {
        return imageId;
      }

      // 画像URLを取得するAPIエンドポイントを呼び出す
      const url = `${this.baseUrl}/products/image_url.php?id=${encodeURIComponent(imageId)}`;
      const response = await fetch(url);
      if (!response.ok) {
        console.error(`画像URL取得エラー: ${response.status}`);
        return '';
      }
      const data = await response.json();
      if (data.success && data.url) {
        return data.url;
      } else {
        console.log(`画像URL取得失敗: ${data.message || '不明なエラー'}`);
        return '';
      }
    } catch (error) {
      console.error('画像URL取得中に例外が発生:', error);
      return '';
    }
  }

  /**
   * キャッシュされた商品情報から特定の商品を取得
   * @param {string} productId - 商品ID
   * @returns {Object|null} 商品情報、見つからない場合はnull
   */
  getCachedProduct(productId) {
    // すべてのカテゴリの商品キャッシュを検索
    for (const categoryId in productsCache) {
      if (productsCache.hasOwnProperty(categoryId)) {
        const products = productsCache[categoryId];
        const product = products.find(p => p.id === productId);
        if (product) {
          return product;
        }
      }
    }

    // 商品が見つからない場合
    console.warn(`キャッシュに商品ID:${productId}が見つかりません`);
    return null;
  }

  /**
   * 管理設定を取得する
   * adminpagesetting.jsonの内容をAPIを通じて取得
   * @returns {Promise<Object>} 管理設定
   */
  async getAdminSettings() {
    // キャッシュがある場合はそれを返す
    if (this.adminSettingsCache) {
      console.log('管理設定: キャッシュを返します', JSON.stringify(this.adminSettingsCache).substring(0, 200) + '...');
      return this.adminSettingsCache;
    }
    try {
      console.log('管理設定API呼び出し開始: ' + this.baseUrl + '/admin/adminpagesetting_ragistrer.php');
      // 管理設定APIを呼び出す
      const response = await fetch(this.baseUrl + '/admin/adminpagesetting_ragistrer.php');
      console.log('管理設定API応答受信: status=' + response.status);
      if (!response.ok) {
        console.error('管理設定API HTTP エラー:', response.status, response.statusText);
        throw new Error(`APIエラー: ${response.status}`);
      }

      // レスポンス内容をテキストとしてログ
      const responseText = await response.text();
      console.log('管理設定API応答テキスト (最初の200文字):', responseText.substring(0, 200));
      try {
        // JSON として解析 - 構文エラーはここで捕捉
        const data = JSON.parse(responseText);
        console.log('管理設定API応答(JSON解析後):', Object.keys(data));
        if (data && data.success) {
          this.adminSettingsCache = data.data || {};
          return this.adminSettingsCache;
        } else {
          console.error('管理設定API応答にデータがありません:', data);
          throw new Error(data.message || '管理設定データが不正です');
        }
      } catch (jsonError) {
        console.error('管理設定JSON解析エラー:', jsonError);
        console.error('応答テキスト:', responseText);
        throw new Error('管理設定のJSONパースに失敗しました');
      }
    } catch (error) {
      console.error('管理設定取得エラー:', error);

      // エラー時はデフォルト値を返す
      const defaultSettings = {
        product_display_util: {
          directlink_baseURL: 'https://test-mijeos.but.jp/fgsquare/order'
        },
        open_close: {
          default_open: '10:00',
          default_close: '1:00',
          interval: {},
          "Days off": [],
          "Restrict individual": "false"
        }
      };
      return defaultSettings;
    }
  }

  /**
   * 管理設定を更新する
   * @param {Object} settings - 更新する設定オブジェクト
   * @returns {Promise<boolean>} 成功したかどうか
   */
  async updateAdminSettings(settings) {
    try {
      // TODO: 管理設定更新APIが実装された場合に対応
      console.warn('管理設定更新APIは現在実装されていません');
      return false;
    } catch (error) {
      console.error('管理設定更新エラー:', error);
      throw error;
    }
  }
}