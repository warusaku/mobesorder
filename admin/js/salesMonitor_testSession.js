/**
 * テストセッション機能
 * バージョン: 1.0.0
 * ファイル説明: sales_monitor.phpのテストセッション機能を分離したモジュール
 */

(function() {
    'use strict';
    
    // 初期化処理
    function initTestSession() {
        const btn = document.getElementById('testSessionBtn');
        if (!btn || typeof bootstrap === 'undefined') return;
        
        const modalEl = document.getElementById('testSessionModal');
        const bsModal = new bootstrap.Modal(modalEl);
        const loading = document.getElementById('testSessionLoading');
        const resultPre = document.getElementById('testSessionResult');
        const histUl = document.getElementById('testSessionHistory');
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleTestSessionClick(bsModal, loading, resultPre, histUl);
        });
    }
    
    // テストセッションクリック処理
    function handleTestSessionClick(bsModal, loading, resultPre, histUl) {
        // UIリセット
        loading.classList.remove('d-none');
        loading.textContent = '実行中…';
        resultPre.classList.add('d-none');
        resultPre.textContent = '';
        histUl.innerHTML = '';
        bsModal.show();
        
        // 強制クローズパラメータ
        const forceParam = document.getElementById('forceCloseChk')?.checked ? '&force=1' : '';
        
        // テストセッション実行
        fetch('test_session_tool.php?ajax=1' + forceParam)
            .then(response => response.json())
            .then(data => {
                displayTestResults(data, loading, resultPre);
            })
            .catch(err => {
                loading.textContent = 'エラー: ' + err.message;
            });
    }
    
    // テスト結果表示
    function displayTestResults(data, loading, resultPre) {
        loading.classList.add('d-none');
        resultPre.classList.remove('d-none');
        
        // 結果表示
        resultPre.textContent = JSON.stringify({
            success: data.success,
            order_id: data.order_id,
            session_id: data.session_id,
            duration_ms: data.duration_ms
        }, null, 2);
        
        // ステップテーブル更新
        const tbody = document.querySelector('#testSessionSteps tbody');
        if (tbody) {
            tbody.innerHTML = '';
            
            if (Array.isArray(data.steps)) {
                data.steps.forEach(step => {
                    const tr = createStepRow(step);
                    tbody.appendChild(tr);
                });
            }
        }
    }
    
    // ステップ行作成
    function createStepRow(step) {
        const tr = document.createElement('tr');
        const msg = step.message || '';
        
        tr.innerHTML = `
            <td>${escapeHtml(step.step)}</td>
            <td>${escapeHtml(step.module)}</td>
            <td>${escapeHtml(msg)}</td>
            <td><code>${escapeHtml(JSON.stringify(step.args))}</code></td>
            <td><code>${escapeHtml(JSON.stringify(step.return))}</code></td>
            <td>${escapeHtml(step.status)}</td>
        `;
        
        return tr;
    }
    
    // HTMLエスケープ
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
    
    // エクスポート（グローバルスコープに公開）
    window.SalesMonitorTestSession = {
        init: initTestSession
    };
    
    // DOMContentLoaded で初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTestSession);
    } else {
        initTestSession();
    }
})(); 