/**
 * モバイルフレンドリーなドロップダウン選択コンポーネント
 * @version 1.1.0 - ドラムロール強化版
 */

class Picker {
    /**
     * Pickerコンポーネントを初期化
     * @param {Object} options - 設定オプション
     * @param {Array} options.data - 選択肢データ。各項目は {text: string, value: any} 形式
     * @param {number} [options.selectedIndex=0] - 初期選択インデックス
     * @param {string} [options.title='選択してください'] - ピッカーのタイトル
     * @param {Function} [options.onConfirm] - 確定時のコールバック関数
     * @param {Function} [options.onChange] - 選択変更時のコールバック関数
     * @param {Function} [options.onCancel] - キャンセル時のコールバック関数
     */
    constructor(options) {
        this.options = Object.assign({
            data: [],
            selectedIndex: 0,
            title: '選択してください',
            onConfirm: null,
            onChange: null,
            onCancel: null
        }, options);
        
        this.selectedIndex = this.options.selectedIndex;
        this.data = this.options.data;
        
        // スクロール関連の状態
        this.isScrolling = false;
        this.startY = 0;
        this.currentY = 0;
        this.scrollVelocity = 0;
        this.lastScrollTime = 0;
        this.itemHeight = 60; // アイテムの高さを80から60に削減
        
        // デバイス判定
        this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        this.isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
        
        // パフォーマンス設定
        this.animationDuration = this.isMobile ? 200 : 150; // モバイルは200ms、PCは150ms
        
        this._createDOM();
        this._bindEvents();
    }
    
    /**
     * DOM要素を作成
     * @private
     */
    _createDOM() {
        // ピッカーのコンテナ
        this.pickerContainer = document.createElement('div');
        this.pickerContainer.className = 'picker-container';
        
        // ピッカーのオーバーレイ
        this.overlay = document.createElement('div');
        this.overlay.className = 'picker-overlay';
        
        // ピッカーのコンテンツ
        this.pickerContent = document.createElement('div');
        this.pickerContent.className = 'picker-content';
        
        // ヘッダー
        const header = document.createElement('div');
        header.className = 'picker-header';
        
        const title = document.createElement('h3');
        title.textContent = this.options.title;
        
        header.appendChild(title);
        
        // アクションボタン
        const actions = document.createElement('div');
        actions.className = 'picker-actions';
        
        this.cancelButton = document.createElement('button');
        this.cancelButton.type = 'button';
        this.cancelButton.textContent = 'キャンセル';
        this.cancelButton.className = 'picker-cancel';
        
        this.confirmButton = document.createElement('button');
        this.confirmButton.type = 'button';
        this.confirmButton.textContent = '選択';
        this.confirmButton.className = 'picker-confirm';
        
        actions.appendChild(this.cancelButton);
        actions.appendChild(this.confirmButton);
        
        // 選択肢リスト
        this.listContainer = document.createElement('div');
        this.listContainer.className = 'picker-list-container';
        
        // GPU加速のためのスタイル設定
        this.listContainer.style.willChange = 'scroll-position';
        
        // ハイライト領域 (選択中アイテムを示す)
        const highlight = document.createElement('div');
        highlight.className = 'picker-highlight';
        this.listContainer.appendChild(highlight);
        
        this.list = document.createElement('ul');
        this.list.className = 'picker-list';
        
        // 選択肢を追加
        this._renderItems();
        
        this.listContainer.appendChild(this.list);
        
        // 要素を組み立て
        this.pickerContent.appendChild(header);
        this.pickerContent.appendChild(this.listContainer);
        
        // グラデーションマスク不要のため生成しない
        
        this.pickerContent.appendChild(actions);
        
        this.pickerContainer.appendChild(this.overlay);
        this.pickerContainer.appendChild(this.pickerContent);
    }
    
    /**
     * 選択肢項目をレンダリング
     * @private
     */
    _renderItems() {
        this.list.innerHTML = '';
        
        // データが空の場合は「データなし」を表示
        if (this.data.length === 0) {
            const emptyItem = document.createElement('li');
            emptyItem.className = 'picker-item';
            emptyItem.textContent = 'データがありません';
            emptyItem.dataset.index = -1;
            this.list.appendChild(emptyItem);
            return;
        }
        
        // 実際のアイテム
        this.data.forEach((item, index) => {
            const listItem = document.createElement('li');
            listItem.className = 'picker-item';
            // 部屋番号は最大5文字まで表示（それ以上はエラー）
            if (item.text.length > 5) {
                console.warn(`部屋番号 "${item.text}" は5文字を超えています。先頭5文字のみ表示します。`);
                listItem.textContent = item.text.substring(0, 5);
            } else {
                listItem.textContent = item.text;
            }
            listItem.dataset.index = index;
            listItem.dataset.value = item.value;
            
            if (index === this.selectedIndex) {
                listItem.classList.add('selected');
            }
            
            this.list.appendChild(listItem);
        });
    }
    
    /**
     * イベントをバインド
     * @private
     */
    _bindEvents() {
        // タッチイベント
        this.listContainer.addEventListener('touchstart', this._onTouchStart.bind(this));
        this.listContainer.addEventListener('touchmove', this._onTouchMove.bind(this));
        this.listContainer.addEventListener('touchend', this._onTouchEnd.bind(this));
        
        // 選択肢クリック
        this.list.addEventListener('click', (e) => {
            // スクロール中のクリックは無視
            if (this.isScrolling) return;
            
            const item = e.target.closest('.picker-item');
            if (!item) return;
            
            const index = parseInt(item.dataset.index, 10);
            if (index >= 0) {
                this._scrollToIndex(index, true);
            }
        });
        
        // キャンセルボタン
        this.cancelButton.addEventListener('click', () => {
            this.hide();
            if (typeof this.options.onCancel === 'function') {
                this.options.onCancel();
            }
        });
        
        // 確定ボタン
        this.confirmButton.addEventListener('click', () => {
            // スクロールが止まってから選択
            if (this.isScrolling) {
                this._stopScrollingWithInertia();
            }
            
            const selectedItem = this.data[this.selectedIndex];
            this.hide();
            
            if (selectedItem && typeof this.options.onConfirm === 'function') {
                this.options.onConfirm(selectedItem.value, selectedItem.text);
            }
        });
        
        // オーバーレイクリックでキャンセル
        this.overlay.addEventListener('click', () => {
            this.hide();
            if (typeof this.options.onCancel === 'function') {
                this.options.onCancel();
            }
        });
        
        // スクロールイベント（マウスホイールでの選択）
        this.listContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            
            // スクロール方向を判定
            const delta = e.deltaY || -e.wheelDelta || e.detail;
            const direction = delta > 0 ? 1 : -1;
            
            // 次のインデックス
            const nextIndex = Math.max(0, Math.min(this.data.length - 1, this.selectedIndex + direction));
            
            if (nextIndex !== this.selectedIndex) {
                this._scrollToIndex(nextIndex, true);
            }
        });
    }
    
    /**
     * タッチ開始時の処理
     * @param {TouchEvent} e - タッチイベント 
     */
    _onTouchStart(e) {
        if (!this.data.length) return;
        
        this.startY = e.touches[0].clientY;
        this.currentY = this.startY;
        this.isScrolling = true;
        this.scrollVelocity = 0;
        this.lastScrollTime = Date.now();
        
        // スクロールアニメーションを停止
        if (this.scrollAnimation) {
            cancelAnimationFrame(this.scrollAnimation);
            this.scrollAnimation = null;
        }
    }
    
    /**
     * タッチ移動時の処理
     * @param {TouchEvent} e - タッチイベント 
     */
    _onTouchMove(e) {
        if (!this.isScrolling || !this.data.length) return;
        
        e.preventDefault(); // スクロールのデフォルト動作を防ぐ
        
        const lastY = this.currentY;
        this.currentY = e.touches[0].clientY;
        
        // スクロール速度を計算
        const now = Date.now();
        const deltaTime = now - this.lastScrollTime;
        if (deltaTime > 0) {
            this.scrollVelocity = (this.currentY - lastY) / deltaTime;
        }
        this.lastScrollTime = now;
        
        // 移動距離から次の選択インデックスを計算
        const deltaY = this.currentY - this.startY;
        const itemsToMove = Math.round(deltaY / this.itemHeight);
        
        // 新しいインデックス（境界チェック）
        const newIndex = Math.max(0, Math.min(this.data.length - 1, this.selectedIndex - itemsToMove));
        
        if (newIndex !== this.selectedIndex) {
            // requestAnimationFrameを使用して描画を最適化
            if (!this.moveFrame) {
                this.moveFrame = requestAnimationFrame(() => {
                    this._selectItem(newIndex, false);
                    this.startY = this.currentY;
                    this.moveFrame = null;
                });
            }
        }
    }
    
    /**
     * タッチ終了時の処理
     */
    _onTouchEnd() {
        if (!this.isScrolling || !this.data.length) return;
        
        // 慣性スクロールを開始
        this._startInertiaScroll();
    }
    
    /**
     * 慣性スクロールを開始
     * @private
     */
    _startInertiaScroll() {
        // デバイスに応じて慣性の感度を調整
        const inertiaMultiplier = this.isIOS ? 10 : 5; // iOSは10、AndroidとPCは5
        
        // スクロール速度に基づいて、慣性で何アイテム分移動するかを計算
        const inertiaItems = Math.round(this.scrollVelocity * inertiaMultiplier);
        
        // 目標インデックス（境界チェック）
        const targetIndex = Math.max(0, Math.min(
            this.data.length - 1, 
            this.selectedIndex - inertiaItems
        ));
        
        // アニメーションでスクロール
        this._scrollToIndex(targetIndex, true);
    }
    
    /**
     * 慣性スクロールを停止
     * @private
     */
    _stopScrollingWithInertia() {
        if (this.scrollAnimation) {
            cancelAnimationFrame(this.scrollAnimation);
            this.scrollAnimation = null;
        }
        
        // 最も近いアイテム位置にスナップ
        const currentScrollTop = this.listContainer.scrollTop;
        const closestIndex = Math.round(currentScrollTop / this.itemHeight);
        const targetIndex = Math.max(0, Math.min(this.data.length - 1, closestIndex));
        
        this._scrollToIndex(targetIndex, true);
    }
    
    /**
     * 指定インデックスにスクロール
     * @param {number} index - スクロール先のインデックス
     * @param {boolean} animated - アニメーションするかどうか
     * @private
     */
    _scrollToIndex(index, animated) {
        if (index < 0 || index >= this.data.length) return;
        
        // スクロール先の位置
        const targetScrollTop = index * this.itemHeight;
        
        if (animated) {
            this._animateScroll(targetScrollTop, () => {
                this._selectItem(index, true);
                this.isScrolling = false;
            });
        } else {
            this.listContainer.scrollTop = targetScrollTop;
            this._selectItem(index, false);
        }
    }
    
    /**
     * スクロールアニメーション
     * @param {number} targetScrollTop - スクロール先のスクロール位置
     * @param {Function} callback - アニメーション完了後のコールバック
     * @private
     */
    _animateScroll(targetScrollTop, callback) {
        const startPosition = this.listContainer.scrollTop;
        const distance = targetScrollTop - startPosition;
        const duration = this.animationDuration;
        const startTime = Date.now();
        
        if (this.scrollAnimation) {
            cancelAnimationFrame(this.scrollAnimation);
        }
        
        const animateFrame = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easing = this._easeOutCubic(progress);
            
            this.listContainer.scrollTop = startPosition + distance * easing;
            
            if (progress < 1) {
                this.scrollAnimation = requestAnimationFrame(animateFrame);
            } else {
                this.scrollAnimation = null;
                if (callback) callback();
            }
        };
        
        this.scrollAnimation = requestAnimationFrame(animateFrame);
    }
    
    /**
     * イージング関数 (easeOutCubic)
     * @param {number} t - 進行度 (0-1)
     * @returns {number} 補間値
     * @private
     */
    _easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }
    
    /**
     * 項目を選択
     * @param {number} index - 選択するインデックス
     * @param {boolean} updateScroll - スクロール位置も更新するか
     * @private
     */
    _selectItem(index, updateScroll) {
        if (index < 0 || index >= this.data.length) return;
        
        // 現在の選択をクリア
        const currentSelected = this.list.querySelector('.selected');
        if (currentSelected) {
            currentSelected.classList.remove('selected');
        }
        
        // 新しい選択を設定
        this.selectedIndex = index;
        const newSelected = this.list.querySelector(`[data-index="${index}"]`);
        if (newSelected) {
            newSelected.classList.add('selected');
            
            // スクロール位置も更新する場合
            if (updateScroll) {
                this.listContainer.scrollTop = index * this.itemHeight;
            }
        }
        
        // コールバック呼び出し
        if (typeof this.options.onChange === 'function') {
            const selectedItem = this.data[this.selectedIndex];
            this.options.onChange(selectedItem.value, selectedItem.text);
        }
    }
    
    /**
     * ピッカーを表示
     */
    show() {
        // ドキュメントに追加
        document.body.appendChild(this.pickerContainer);
        
        // アニメーション開始
        setTimeout(() => {
            this.pickerContainer.classList.add('visible');
            this.pickerContent.classList.add('visible');
            
            // 選択アイテムにスクロール
            if (this.data.length > 0) {
                this._scrollToIndex(this.selectedIndex, false);
            }
        }, 10);
    }
    
    /**
     * ピッカーを非表示
     */
    hide() {
        this.pickerContainer.classList.remove('visible');
        this.pickerContent.classList.remove('visible');
        
        // アニメーション完了後に削除
        setTimeout(() => {
            if (this.pickerContainer.parentNode) {
                this.pickerContainer.parentNode.removeChild(this.pickerContainer);
            }
        }, 300);
    }
    
    /**
     * 選択肢データを更新
     * @param {Array} data - 新しい選択肢データ
     */
    updateData(data) {
        this.data = data;
        this.selectedIndex = 0;
        this._renderItems();
    }
    
    /**
     * 選択インデックスを設定
     * @param {number} index - 設定するインデックス
     */
    setSelectedIndex(index) {
        this._selectItem(index, true);
    }
} 