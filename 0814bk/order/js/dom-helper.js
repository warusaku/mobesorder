/**
 * DOM操作の安全性を向上させるヘルパーライブラリ
 * 要素が存在しない場合でもエラーを発生させずに操作を可能にする
 */

// このスクリプト自体の読み込みをログ
if (typeof window.logScriptLoad === 'function') {
    window.logScriptLoad('dom-helper.js');
}

// ログ設定 - DOM_ヘルパー専用 (二重宣言防止)
if (typeof window.DOM_HELPER_LOG_LEVEL === 'undefined') {
    window.DOM_HELPER_LOG_LEVEL = typeof window.DEBUG_LEVEL !== 'undefined' ? window.DEBUG_LEVEL : 3;
}

/**
 * ログ出力関数
 * @param {string} message ログメッセージ
 * @param {string} level ログレベル
 * @param {Object} data 追加データ
 */
function log(message, level = 'INFO', data = null) {
    const timestamp = new Date().toISOString();
    const prefix = `[DOM-HELPER][${timestamp}][${level}]`;
    
    switch(level) {
        case 'ERROR':
            if (window.DOM_HELPER_LOG_LEVEL >= 1) console.error(`${prefix} ${message}`, data || '');
            break;
        case 'WARN':
            if (window.DOM_HELPER_LOG_LEVEL >= 2) console.warn(`${prefix} ${message}`, data || '');
            break;
        case 'INFO':
            if (window.DOM_HELPER_LOG_LEVEL >= 3) console.log(`${prefix} ${message}`, data || '');
            break;
        case 'DEBUG':
            if (window.DOM_HELPER_LOG_LEVEL >= 3) console.debug(`${prefix} ${message}`, data || '');
            break;
    }
}

// DOMHelperが既に定義されている場合は再定義しない
if (typeof window.$$ !== 'object' || window.$$ === null) {
    /**
     * DOMヘルパー - 各種DOM操作の安全な実行を提供
     */
    function DOMHelper() {}
    
    // 以下、静的メソッドとして定義していたものをプロトタイプに変更
    
    /**
     * IDから要素を安全に取得する
     * @param {string} id 要素のID
     * @param {boolean} warnIfMissing 要素が見つからない場合に警告するかどうか
     * @returns {HTMLElement|null} 見つかった要素またはnull
     */
    DOMHelper.getId = function(id, warnIfMissing) {
        warnIfMissing = warnIfMissing !== false; // デフォルトはtrue
        const element = document.getElementById(id);
        if (!element && warnIfMissing) {
            log('要素が見つかりません: #' + id, 'WARN');
        }
        return element;
    };
    
    /**
     * セレクタから要素を安全に取得する
     * @param {string} selector CSSセレクタ
     * @param {HTMLElement} parent 親要素（省略時はdocument）
     * @param {boolean} warnIfMissing 要素が見つからない場合に警告するかどうか
     * @returns {HTMLElement|null} 見つかった要素またはnull
     */
    DOMHelper.get = function(selector, parent, warnIfMissing) {
        parent = parent || document;
        warnIfMissing = warnIfMissing !== false; // デフォルトはtrue
        
        try {
            const element = parent.querySelector(selector);
            if (!element && warnIfMissing) {
                log('要素が見つかりません: ' + selector, 'WARN');
            }
            return element;
        } catch (error) {
            log('セレクタの実行中にエラーが発生: ' + selector, 'ERROR', error);
            return null;
        }
    };
    
    /**
     * セレクタから複数の要素を安全に取得する
     * @param {string} selector CSSセレクタ
     * @param {HTMLElement} parent 親要素（省略時はdocument）
     * @returns {Array<HTMLElement>} 見つかった要素の配列
     */
    DOMHelper.getAll = function(selector, parent) {
        parent = parent || document;
        
        try {
            return Array.from(parent.querySelectorAll(selector));
        } catch (error) {
            log('セレクタの実行中にエラーが発生: ' + selector, 'ERROR', error);
            return [];
        }
    };
    
    /**
     * 要素のテキストを安全に設定する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} text 設定するテキスト
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.setText = function(target, text) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.textContent = text;
            return true;
        } catch (error) {
            log('テキスト設定中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素のHTMLを安全に設定する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} html 設定するHTML
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.setHTML = function(target, html) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.innerHTML = html;
            return true;
        } catch (error) {
            log('HTML設定中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素の表示/非表示を切り替える
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {boolean} visible 表示するかどうか
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.setVisible = function(target, visible) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.style.display = visible ? '' : 'none';
            return true;
        } catch (error) {
            log('表示設定中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素のクラスを安全に追加する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} className 追加するクラス名
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.addClass = function(target, className) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.classList.add(className);
            return true;
        } catch (error) {
            log('クラス追加中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素のクラスを安全に削除する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} className 削除するクラス名
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.removeClass = function(target, className) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.classList.remove(className);
            return true;
        } catch (error) {
            log('クラス削除中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素の属性を安全に設定する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} attrName 属性名
     * @param {string} attrValue 属性値
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.setAttribute = function(target, attrName, attrValue) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.setAttribute(attrName, attrValue);
            return true;
        } catch (error) {
            log('属性設定中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素の属性を安全に取得する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} attrName 属性名
     * @returns {string|null} 属性値またはnull
     */
    DOMHelper.getAttribute = function(target, attrName) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return null;
        
        try {
            return element.getAttribute(attrName);
        } catch (error) {
            log('属性取得中にエラーが発生', 'ERROR', error);
            return null;
        }
    };
    
    /**
     * イベントリスナを安全に追加する
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @param {string} eventType イベントタイプ（click, inputなど）
     * @param {Function} listener リスナー関数
     * @param {Object} options イベントオプション
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.addListener = function(target, eventType, listener, options) {
        options = options || {}; // デフォルトは空オブジェクト
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            element.addEventListener(eventType, listener, options);
            return true;
        } catch (error) {
            log('イベントリスナ追加中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素を安全に作成して親要素に追加する
     * @param {string} tagName タグ名
     * @param {HTMLElement} parent 親要素
     * @param {Object} attributes 属性オブジェクト
     * @param {string} textContent テキストコンテンツ
     * @returns {HTMLElement|null} 作成された要素またはnull
     */
    DOMHelper.createElement = function(tagName, parent, attributes, textContent) {
        parent = parent || null;
        attributes = attributes || {};
        textContent = textContent || '';
        
        try {
            const element = document.createElement(tagName);
            
            // 属性を設定
            for (var key in attributes) {
                if (attributes.hasOwnProperty(key)) {
                    if (key === 'className') {
                        element.className = attributes[key];
                    } else {
                        element.setAttribute(key, attributes[key]);
                    }
                }
            }
            
            // テキストを設定
            if (textContent) {
                element.textContent = textContent;
            }
            
            // 親要素に追加
            if (parent) {
                parent.appendChild(element);
            }
            
            return element;
        } catch (error) {
            log('要素作成中にエラーが発生', 'ERROR', error);
            return null;
        }
    };
    
    /**
     * 全てのDOM要素を安全にクリアする
     * @param {string|HTMLElement} target 要素のIDまたはHTML要素
     * @returns {boolean} 成功したかどうか
     */
    DOMHelper.clear = function(target) {
        const element = typeof target === 'string' ? this.getId(target) : target;
        if (!element) return false;
        
        try {
            while (element.firstChild) {
                element.removeChild(element.firstChild);
            }
            return true;
        } catch (error) {
            log('要素クリア中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * 要素が存在するかを安全に確認する
     * @param {string} selector 確認するセレクタ
     * @returns {boolean} 要素が存在するかどうか
     */
    DOMHelper.exists = function(selector) {
        try {
            return document.querySelector(selector) !== null;
        } catch (error) {
            log('要素存在確認中にエラーが発生', 'ERROR', error);
            return false;
        }
    };
    
    /**
     * フォーム内の値を安全に取得する
     * @param {string} formSelector フォームのセレクタ
     * @returns {Object} フォームデータオブジェクト
     */
    DOMHelper.getFormData = function(formSelector) {
        const form = this.get(formSelector);
        if (!form) return {};
        
        try {
            const formData = new FormData(form);
            const data = {};
            
            for (var pair of formData.entries()) {
                data[pair[0]] = pair[1];
            }
            
            return data;
        } catch (error) {
            log('フォームデータ取得中にエラーが発生', 'ERROR', error);
            return {};
        }
    };

    // グローバルスコープにエクスポート
    window.$$ = DOMHelper;
    log('DOMヘルパーライブラリが初期化されました', 'INFO');
} else {
    log('DOMヘルパーライブラリは既に初期化されています', 'INFO');
} 