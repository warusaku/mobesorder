/**
 * FG Square 管理画面用 JavaScript
 */

// DOM読み込み完了時に実行
document.addEventListener('DOMContentLoaded', function() {
    // フォーム送信前の検証
    setupFormValidation();
    
    // フラッシュメッセージ自動非表示
    setupFlashMessages();
    
    // 表のソート機能
    setupTableSorting();
    
    // カテゴリ表示順の自動ナンバリング
    setupCategoryOrderButtons();
});

/**
 * フォーム検証を設定
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const requiredInputs = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    
                    // エラーメッセージ要素がなければ作成
                    let errorMsg = input.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        errorMsg.style.color = '#d73a49';
                        errorMsg.style.fontSize = '12px';
                        errorMsg.style.marginTop = '4px';
                        errorMsg.textContent = '必須項目です';
                        input.parentNode.insertBefore(errorMsg, input.nextSibling);
                    }
                } else {
                    input.classList.remove('is-invalid');
                    const errorMsg = input.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                event.preventDefault();
            }
        });
        
        // 入力時にエラー表示をクリア
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                    const errorMsg = this.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
        });
    });
}

/**
 * フラッシュメッセージの自動非表示を設定
 */
function setupFlashMessages() {
    const alerts = document.querySelectorAll('.alert-success, .alert-info');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            fadeOut(alert);
        }, 5000);
    });
}

/**
 * 要素をフェードアウト
 */
function fadeOut(element) {
    let opacity = 1;
    const timer = setInterval(() => {
        if (opacity <= 0.1) {
            clearInterval(timer);
            element.style.display = 'none';
        }
        element.style.opacity = opacity;
        opacity -= 0.1;
    }, 50);
}

/**
 * テーブルのソート機能を設定
 */
function setupTableSorting() {
    const tables = document.querySelectorAll('table.sortable');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            
            // ソートアイコンを追加
            const sortIcon = document.createElement('span');
            sortIcon.className = 'sort-icon';
            sortIcon.innerHTML = ' ↕️';
            sortIcon.style.fontSize = '12px';
            header.appendChild(sortIcon);
            
            header.addEventListener('click', function() {
                const sortBy = this.getAttribute('data-sort');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                
                // 現在のソート方向を決定
                const currentDir = this.getAttribute('data-sort-direction') || 'asc';
                const newDir = currentDir === 'asc' ? 'desc' : 'asc';
                
                // 他のヘッダーのソート方向をリセット
                headers.forEach(h => {
                    h.setAttribute('data-sort-direction', '');
                    h.querySelector('.sort-icon').innerHTML = ' ↕️';
                });
                
                // 現在のヘッダーのソート方向を設定
                this.setAttribute('data-sort-direction', newDir);
                this.querySelector('.sort-icon').innerHTML = newDir === 'asc' ? ' ↑' : ' ↓';
                
                // 行をソート
                rows.sort((a, b) => {
                    let valueA = a.querySelector(`td[data-${sortBy}]`)?.getAttribute(`data-${sortBy}`) || 
                                 a.cells[Array.from(headers).indexOf(header)].textContent.trim();
                                 
                    let valueB = b.querySelector(`td[data-${sortBy}]`)?.getAttribute(`data-${sortBy}`) || 
                                 b.cells[Array.from(headers).indexOf(header)].textContent.trim();
                    
                    // 数値なら数値としてソート
                    if (!isNaN(valueA) && !isNaN(valueB)) {
                        valueA = parseFloat(valueA);
                        valueB = parseFloat(valueB);
                    }
                    
                    if (valueA < valueB) return newDir === 'asc' ? -1 : 1;
                    if (valueA > valueB) return newDir === 'asc' ? 1 : -1;
                    return 0;
                });
                
                // ソートした行をDOMに再挿入
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    });
}

/**
 * カテゴリ表示順の自動ナンバリングボタンを設定
 */
function setupCategoryOrderButtons() {
    const autoNumberButton = document.getElementById('auto-number-categories');
    if (!autoNumberButton) return;
    
    autoNumberButton.addEventListener('click', function(event) {
        event.preventDefault();
        
        const inputs = document.querySelectorAll('input[name="display_order[]"]');
        let startNumber = 10;
        const increment = 10;
        
        inputs.forEach(input => {
            input.value = startNumber;
            startNumber += increment;
        });
        
        // 保存は自動的には行わず、ユーザーが確認してから保存できるようにする
        alert('カテゴリに表示順を振りました。変更を保存するには「変更を保存」ボタンをクリックしてください。');
    });
}

/**
 * 確認ダイアログを表示
 * @param {string} message 確認メッセージ
 * @returns {boolean} 確認結果
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * URLパラメータを取得
 * @param {string} name パラメータ名
 * @returns {string|null} パラメータ値
 */
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

/**
 * 数値の先頭にゼロを埋める
 * @param {number} num 数値
 * @param {number} places 桁数
 * @returns {string} ゼロ埋めされた文字列
 */
function padZero(num, places) {
    return String(num).padStart(places, '0');
}

/**
 * 日付をフォーマット
 * @param {Date} date 日付
 * @returns {string} フォーマットされた日付文字列
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = padZero(date.getMonth() + 1, 2);
    const day = padZero(date.getDate(), 2);
    const hours = padZero(date.getHours(), 2);
    const minutes = padZero(date.getMinutes(), 2);
    
    return `${year}-${month}-${day} ${hours}:${minutes}`;
} 