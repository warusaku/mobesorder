/**
 * logscan.js - ログファイルスキャナー用フロントエンドスクリプト
 * 
 * ログファイルの一覧を取得して表示し、差分ハイライトを行います
 */

document.addEventListener('DOMContentLoaded', function() {
    // 定数定義
    const REFRESH_INTERVAL = 60000; // 1分ごとに自動更新
    const MAX_HIGHLIGHT_HISTORY = 5; // 過去5回分の更新をトラッキング
    const HIGHLIGHT_COLORS = [
        'rgba(255, 255, 0, 1.0)',    // 最新の更新: 黄色 100%
        'rgba(255, 255, 0, 0.7)',    // 1回無変更: 黄色 70%
        'rgba(255, 255, 0, 0.5)',    // 2回無変更: 黄色 50%
        'rgba(255, 255, 0, 0.3)',    // 3回無変更: 黄色 30%
        'rgba(255, 255, 0, 0.1)',    // 4回無変更: 黄色 10%
        'rgba(255, 255, 255, 1.0)'   // 5回以上無変更: 白色
    ];
    const INACTIVE_TIME = 30 * 60 * 1000; // 30分 (ミリ秒)
    const VERY_INACTIVE_TIME = 6 * 60 * 60 * 1000; // 6時間 (ミリ秒)
    const INACTIVE_BG_COLOR = '#a9a9a9'; // 長時間更新のないファイルの背景色
    const STORAGE_KEY = 'logScannerState'; // ローカルストレージのキー
    
    // ログスキャン管理用オブジェクト
    const LogScanner = {
        // ログファイル情報の履歴
        fileHistories: {},
        // 最終チェック時刻
        lastCheckTime: 0,
        
        // 初期化
        init: function() {
            // ローカルストレージから状態を復元
            this.restoreState();
            this.setupListeners();
            this.fetchLogFiles();
            this.startAutoRefresh();
            
            // ログテーブルにトランジション用のスタイルを追加
            const style = document.createElement('style');
            style.innerHTML = `
                .log-files-table {
                    position: relative;
                }
                .log-files-table tr {
                    transition: background-color 0.5s ease, opacity 0.5s ease, transform 0.5s ease;
                    position: relative;
                }
                .log-file-link {
                    transition: opacity 0.3s ease;
                }
                .log-file-inactive {
                    opacity: 0.5;
                }
                tr.log-file-inactive td {
                    opacity: 0.5;
                }
                .log-file-very-inactive {
                    background-color: ${INACTIVE_BG_COLOR} !important;
                }
                @keyframes highlight-pulse {
                    0% { background-color: rgba(255, 255, 0, 1.0); }
                    50% { background-color: rgba(255, 220, 0, 0.6); }
                    100% { background-color: rgba(255, 255, 0, 1.0); }
                }
                .log-file-updated-animation {
                    animation: highlight-pulse 1s ease-in-out;
                    z-index: 10;
                }
                @keyframes slide-in {
                    0% { transform: translateY(-30px); opacity: 0; }
                    100% { transform: translateY(0); opacity: 1; }
                }
                .slide-in {
                    animation: slide-in 0.5s ease-out forwards;
                }
                .move-up {
                    z-index: 20;
                    position: relative;
                    animation: move-up 0.8s ease-out forwards;
                    box-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
                }
                @keyframes move-up {
                    0% { transform: translateY(0); }
                    20% { transform: translateY(5px); }
                    100% { transform: translateY(0); }
                }
                .flash-highlight {
                    animation: flash-highlight 1s ease-out;
                }
                @keyframes flash-highlight {
                    0% { box-shadow: 0 0 0 rgba(255, 255, 0, 0); }
                    25% { box-shadow: 0 0 15px rgba(255, 215, 0, 0.8); }
                    100% { box-shadow: 0 0 0 rgba(255, 255, 0, 0); }
                }
            `;
            document.head.appendChild(style);
        },
        
        // 状態をローカルストレージに保存
        saveState: function() {
            const state = {
                fileHistories: this.fileHistories,
                lastCheckTime: this.lastCheckTime
            };
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            } catch (e) {
                console.error('ローカルストレージへの保存に失敗しました:', e);
            }
        },
        
        // ローカルストレージから状態を復元
        restoreState: function() {
            try {
                const savedState = localStorage.getItem(STORAGE_KEY);
                if (savedState) {
                    const state = JSON.parse(savedState);
                    this.fileHistories = state.fileHistories || {};
                    this.lastCheckTime = state.lastCheckTime || 0;
                }
            } catch (e) {
                console.error('ローカルストレージからの復元に失敗しました:', e);
                // エラーの場合は新規状態で開始
                this.fileHistories = {};
                this.lastCheckTime = 0;
            }
        },
        
        // イベントリスナーのセットアップ
        setupListeners: function() {
            // ログリストのクリックイベント
            document.addEventListener('click', function(e) {
                // ログファイルリンクのクリック
                if (e.target.closest('.log-file-link')) {
                    e.preventDefault();
                    const fileName = e.target.closest('.log-file-link').dataset.fileName;
                    if (fileName) {
                        LogScanner.showLogContent(fileName);
                    }
                }
                
                // モーダルの閉じるボタン
                if (e.target.closest('.log-modal-close') || e.target.classList.contains('log-modal-overlay')) {
                    LogScanner.closeModal();
                }
            });
            
            // 手動更新ボタン
            const refreshBtn = document.getElementById('log-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    LogScanner.fetchLogFiles();
                });
            }
            
            // ページ離脱時に状態を保存
            window.addEventListener('beforeunload', () => {
                this.saveState();
            });
        },
        
        // ログファイル一覧の取得
        fetchLogFiles: function() {
            fetch('logscan.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const currentTime = new Date().getTime();
                        // このバッチでチェックされたすべてのファイル名
                        const checkedFiles = data.files.map(file => file.name);
                        // 一覧更新
                        this.updateFileList(data.files, currentTime, checkedFiles);
                        // 最終チェック時刻を更新
                        this.lastCheckTime = currentTime;
                        // 状態を保存
                        this.saveState();
                    } else {
                        this.showError(data.error || 'データの取得に失敗しました');
                    }
                })
                .catch(error => {
                    this.showError('エラー: ' + error.message);
                });
        },
        
        // ログファイル一覧の更新
        updateFileList: function(files, currentTime, checkedFiles) {
            const logTableBody = document.getElementById('log-files-tbody');
            if (!logTableBody) return;
            
            // 現在の時刻（表示用）
            const now = new Date();
            const updateTime = document.getElementById('log-last-updated');
            if (updateTime) {
                updateTime.textContent = now.toLocaleString();
            }
            
            // ファイルの位置を記録
            const currentPositions = new Map();
            const currentRows = Array.from(logTableBody.querySelectorAll('tr'));
            currentRows.forEach((row, index) => {
                const link = row.querySelector('.log-file-link');
                if (link && link.dataset.fileName) {
                    currentPositions.set(link.dataset.fileName, {
                        row: row,
                        index: index,
                        rect: row.getBoundingClientRect()
                    });
                }
            });
            
            // 現在の情報と以前の情報を比較して更新されたファイルを特定
            const updatedFiles = [];
            
            // 既知のすべてのファイルの未変更カウンターを更新
            for (const fileName in this.fileHistories) {
                // この更新バッチで見つかったファイルのみ処理
                if (checkedFiles.includes(fileName)) {
                    const fileHistory = this.fileHistories[fileName];
                    // このファイルは更新チェックでスキャンされた
                    fileHistory.lastScanned = currentTime;
                }
            }
            
            // ファイル情報を処理し、更新を検出
            files.forEach(file => {
                // ファイル履歴の初期化（存在しない場合）
                if (!this.fileHistories[file.name]) {
                    this.fileHistories[file.name] = {
                        lastModified: file.mtime, // 初期値は現在のmtimeを設定
                        lastScanned: currentTime,
                        unchanged: MAX_HIGHLIGHT_HISTORY, // 初期値は最大値を設定（白背景）
                        isHighlighted: false // 初期値は非ハイライト
                    };
                }
                
                // ファイルが更新されたかチェック
                const fileHistory = this.fileHistories[file.name];
                
                if (file.mtime > fileHistory.lastModified) {
                    // ファイルが更新された
                    updatedFiles.push(file.name);
                    fileHistory.lastModified = file.mtime;
                    fileHistory.unchanged = 0; // カウンターリセット
                    fileHistory.isHighlighted = true;
                } else if (fileHistory.isHighlighted) {
                    // 更新はないがハイライト中
                    fileHistory.unchanged++; // 未変更カウンター増加
                    
                    // MAX_HIGHLIGHT_HISTORY回以上変更がなければハイライト解除
                    if (fileHistory.unchanged >= MAX_HIGHLIGHT_HISTORY) {
                        fileHistory.isHighlighted = false;
                    }
                }
            });
            
            // 現在のテーブルの状態を保存
            const currentFileElements = {};
            const currentRowsMap = new Map();
            currentRows.forEach(row => {
                const link = row.querySelector('.log-file-link');
                if (link && link.dataset.fileName) {
                    currentFileElements[link.dataset.fileName] = row;
                    currentRowsMap.set(link.dataset.fileName, row);
                }
            });
            
            // ファイルを最終更新日時の降順でソート
            files.sort((a, b) => b.mtime - a.mtime);
            
            // 新しいレイアウトを作成するが、まだDOMには反映しない
            const fragment = document.createDocumentFragment();
            const newRows = [];
            
            if (files.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="3" class="text-center">ログファイルが見つかりません</td>';
                fragment.appendChild(tr);
            } else {
                files.forEach((file, index) => {
                    let tr;
                    const isNewFile = !currentFileElements[file.name];
                    const wasUpdated = updatedFiles.includes(file.name);
                    
                    if (currentFileElements[file.name]) {
                        // 既存の行を再利用
                        tr = currentFileElements[file.name].cloneNode(true);
                        delete currentFileElements[file.name]; // 処理済みとしてマーク
                    } else {
                        // 新しい行を作成
                        tr = document.createElement('tr');
                    }
                    
                    // 行をクリアして再構築
                    tr.innerHTML = '';
                    tr.className = ''; // クラスをリセット
                    tr.style.backgroundColor = ''; // 背景色をリセット
                    
                    // 元の位置情報を保存
                    if (currentPositions.has(file.name)) {
                        const oldPos = currentPositions.get(file.name);
                        tr.dataset.oldIndex = oldPos.index;
                        tr.dataset.newIndex = index;
                        tr.dataset.fileName = file.name;
                    }
                    
                    // ファイル情報を更新
                    const fileHistory = this.fileHistories[file.name];
                    const fileTime = file.mtime * 1000; // Unix timestamp (秒) → ミリ秒に変換
                    const timeSinceLastUpdate = now.getTime() - fileTime;
                    
                    // リンク要素を作成
                    const link = document.createElement('a');
                    link.href = '#';
                    link.className = 'log-file-link';
                    link.dataset.fileName = file.name;
                    link.textContent = file.name;
                    
                    // アクティブ状態に基づくスタイリング
                    if (timeSinceLastUpdate >= VERY_INACTIVE_TIME) {
                        // 6時間以上更新なし - 非常に非アクティブ
                        tr.classList.add('log-file-very-inactive');
                        tr.classList.add('log-file-inactive');
                        link.classList.add('log-file-inactive');
                    } else if (timeSinceLastUpdate >= INACTIVE_TIME) {
                        // 30分以上更新なし - 非アクティブ
                        tr.classList.add('log-file-inactive');
                        link.classList.add('log-file-inactive');
                    }
                    
                    // ハイライト処理（更新状態による背景色）
                    if (wasUpdated) {
                        // 新しく更新されたファイル
                        tr.classList.remove('log-file-very-inactive');
                        tr.classList.remove('log-file-inactive');
                        link.classList.remove('log-file-inactive');
                        tr.classList.add('log-file-updated');
                        
                        // 位置が変わる場合は特別なアニメーション
                        if (currentPositions.has(file.name)) {
                            const oldPos = currentPositions.get(file.name);
                            if (oldPos.index > 0) { // 元の位置が最上位でなければ
                                tr.classList.add('move-up');
                                tr.classList.add('flash-highlight');
                            }
                        }
                        
                        tr.classList.add('log-file-updated-animation'); // アニメーション追加
                        tr.style.backgroundColor = HIGHLIGHT_COLORS[0]; // 最新の更新色
                    } else if (fileHistory.isHighlighted && timeSinceLastUpdate < VERY_INACTIVE_TIME) {
                        // 過去に更新され、まだハイライト中のファイル
                        const colorIndex = Math.min(fileHistory.unchanged, HIGHLIGHT_COLORS.length - 1);
                        tr.style.backgroundColor = HIGHLIGHT_COLORS[colorIndex];
                    }
                    
                    // 新しいファイルのアニメーション
                    if (isNewFile) {
                        tr.classList.add('slide-in');
                        // アニメーション遅延を追加（順番に表示）
                        tr.style.animationDelay = (index * 0.05) + 's';
                    }
                    
                    // セルを作成
                    const tdName = document.createElement('td');
                    tdName.appendChild(link);
                    
                    const tdSize = document.createElement('td');
                    tdSize.textContent = file.size_formatted;
                    
                    const tdTime = document.createElement('td');
                    tdTime.textContent = file.mtime_formatted;
                    
                    // 行に追加
                    tr.appendChild(tdName);
                    tr.appendChild(tdSize);
                    tr.appendChild(tdTime);
                    
                    // 要素を保持
                    newRows.push(tr);
                    fragment.appendChild(tr);
                });
            }
            
            // テーブルを更新（既存の内容を置き換え）
            logTableBody.innerHTML = '';
            logTableBody.appendChild(fragment);
            
            // 更新と位置変更のアニメーション処理
            newRows.forEach(row => {
                // 更新されたファイルで位置が変わったものを強調
                if (row.classList.contains('move-up')) {
                    const fileName = row.dataset.fileName;
                    const oldIndex = parseInt(row.dataset.oldIndex);
                    const newIndex = parseInt(row.dataset.newIndex);
                    
                    // 位置が上に移動した場合のみアニメーション
                    if (oldIndex > newIndex) {
                        // リフローをトリガーして確実にアニメーションが適用されるようにする
                        void row.offsetWidth;
                    }
                }
            });
            
            // アニメーション終了後に更新エフェクトを削除
            setTimeout(() => {
                newRows.forEach(row => {
                    if (row.classList.contains('log-file-updated-animation')) {
                        row.classList.remove('log-file-updated-animation');
                    }
                    if (row.classList.contains('move-up')) {
                        row.classList.remove('move-up');
                    }
                    if (row.classList.contains('flash-highlight')) {
                        row.classList.remove('flash-highlight');
                    }
                });
            }, 1500);
            
            // 状態を保存
            this.saveState();
        },
        
        // ログファイルの内容を表示
        showLogContent: function(fileName) {
            // モーダルが既に存在すれば削除
            this.closeModal();
            
            // ローディング表示
            const loadingModal = document.createElement('div');
            loadingModal.className = 'log-modal-overlay';
            loadingModal.innerHTML = `
                <div class="log-modal log-modal-loading">
                    <div class="log-loading">
                        <div class="spinner"></div>
                        <p>ログファイルを読み込んでいます...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(loadingModal);
            
            // ファイル内容の取得
            fetch(`logscan.php?file=${encodeURIComponent(fileName)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // ローディングモーダルを削除
                    document.body.removeChild(loadingModal);
                    
                    if (data.success && data.file_content) {
                        this.showLogModal(fileName, data.file_content);
                    } else {
                        this.showError(data.error || 'ファイル内容の取得に失敗しました');
                    }
                })
                .catch(error => {
                    // ローディングモーダルを削除
                    if (document.body.contains(loadingModal)) {
                        document.body.removeChild(loadingModal);
                    }
                    this.showError('エラー: ' + error.message);
                });
        },
        
        // モーダルを閉じる
        closeModal: function() {
            const modals = document.querySelectorAll('.log-modal-overlay');
            modals.forEach(modal => {
                document.body.removeChild(modal);
            });
            
            // グローバルフラグをリセット - モーダルが閉じられたことをダッシュボードに通知
            window.logModalOpen = false;
        },
        
        // ログ内容表示用モーダルの生成
        showLogModal: function(fileName, content) {
            // モーダルのHTMLを作成
            const modal = document.createElement('div');
            modal.className = 'log-modal-overlay';
            
            // ログ内容を整形（タイムスタンプを強調表示）
            const formattedContent = this.formatLogContent(content);
            
            // モーダル内容のHTML
            modal.innerHTML = `
                <div class="log-modal">
                    <div class="log-modal-header">
                        <h3>${fileName}</h3>
                        <button class="log-modal-close">&times;</button>
                    </div>
                    <div class="log-modal-body">
                        <pre class="log-content">${formattedContent}</pre>
                    </div>
                </div>
            `;
            
            // モーダルをDOMに追加
            document.body.appendChild(modal);
            
            // グローバルフラグを設定 - モーダルが開いていることをダッシュボードに通知
            window.logModalOpen = true;
            
            // モーダルがスクロール可能になるようにする
            // 最下部（最新のログ）を表示するため、スクロール位置を最下部に設定
            const logBody = modal.querySelector('.log-modal-body');
            if (logBody) {
                // 即時実行すると高さがまだ計算されていない可能性がある
                setTimeout(() => {
                    logBody.scrollTop = logBody.scrollHeight;
                }, 10); // より短い時間で実行
            }
        },
        
        // エラーメッセージ表示
        showError: function(message) {
            const errorDiv = document.getElementById('log-error-message');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                
                setTimeout(() => {
                    errorDiv.style.opacity = '0';
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                        errorDiv.style.opacity = '1';
                    }, 500);
                }, 5000);
            }
        },
        
        // ログコンテンツのフォーマット（タイムスタンプなどを強調表示）
        formatLogContent: function(content) {
            // XSSを防止
            content = this.escapeHtml(content);
            
            // タイムスタンプを強調表示
            content = content.replace(/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)/g, 
                '<span class="log-timestamp">$1</span>');
            
            // エラーメッセージを強調表示
            content = content.replace(/\b(ERROR|CRITICAL|EXCEPTION|FATAL|FAILED)\b/gi, 
                '<span class="log-error">$1</span>');
            
            // 警告メッセージを強調表示
            content = content.replace(/\b(WARNING|WARN|ALERT)\b/gi, 
                '<span class="log-warning">$1</span>');
            
            // 情報メッセージを強調表示
            content = content.replace(/\b(INFO|NOTICE|DEBUG)\b/gi, 
                '<span class="log-info">$1</span>');
            
            return content;
        },
        
        // HTMLエスケープ
        escapeHtml: function(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },
        
        // 自動更新の開始
        startAutoRefresh: function() {
            setInterval(() => {
                this.fetchLogFiles();
            }, REFRESH_INTERVAL);
        }
    };
    
    // LogScannerの初期化
    if (document.getElementById('log-files-section')) {
        LogScanner.init();
    }
}); 