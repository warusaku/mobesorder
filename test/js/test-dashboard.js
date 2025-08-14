/**
 * RTSP_Reader Test Framework - Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // テスト実行ボタンの処理
    const runTestButtons = document.querySelectorAll('.run-test-btn');
    if (runTestButtons) {
        runTestButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const moduleName = this.dataset.module;
                const testName = this.dataset.test;
                
                if (!moduleName || !testName) {
                    alert('モジュール名またはテスト名が指定されていません');
                    return;
                }
                
                // ボタンの状態を更新
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 実行中...';
                
                // テスト実行リクエスト
                fetch('api/run_test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        module: moduleName,
                        test: testName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // ボタンを元に戻す
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i> 実行';
                    
                    // 結果の処理
                    if (data.status === 'success') {
                        // 結果の表示
                        const resultContainer = document.getElementById(`test-result-${moduleName}-${testName}`);
                        if (resultContainer) {
                            resultContainer.innerHTML = formatTestResult(data.result);
                            resultContainer.classList.remove('d-none');
                        }
                    } else {
                        alert('エラー: ' + data.message);
                    }
                })
                .catch(error => {
                    // ボタンを元に戻す
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i> 実行';
                    
                    alert('テスト実行中にエラーが発生しました: ' + error);
                });
            });
        });
    }
    
    // モジュールテスト実行ボタンの処理
    const runModuleButtons = document.querySelectorAll('.run-module-btn');
    if (runModuleButtons) {
        runModuleButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const moduleName = this.dataset.module;
                
                if (!moduleName) {
                    alert('モジュール名が指定されていません');
                    return;
                }
                
                // ボタンの状態を更新
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 実行中...';
                
                // テスト実行リクエスト
                fetch('api/run_module_tests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        module: moduleName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // ボタンを元に戻す
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i> すべて実行';
                    
                    // 結果の処理
                    if (data.status === 'success') {
                        // 結果の表示
                        const resultContainer = document.getElementById(`module-result-${moduleName}`);
                        if (resultContainer) {
                            resultContainer.innerHTML = formatModuleResult(data.result);
                            resultContainer.classList.remove('d-none');
                        }
                    } else {
                        alert('エラー: ' + data.message);
                    }
                })
                .catch(error => {
                    // ボタンを元に戻す
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i> すべて実行';
                    
                    alert('テスト実行中にエラーが発生しました: ' + error);
                });
            });
        });
    }
    
    // 全テスト実行ボタンの処理
    const runAllButton = document.getElementById('run-all-tests-btn');
    if (runAllButton) {
        runAllButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // ボタンの状態を更新
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 実行中...';
            
            // テスト実行リクエスト
            fetch('api/run_all_tests.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                // ボタンを元に戻す
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-play"></i> すべてのテストを実行';
                
                // 結果の処理
                if (data.status === 'success') {
                    // 結果の表示
                    const resultContainer = document.getElementById('all-tests-result');
                    if (resultContainer) {
                        resultContainer.innerHTML = formatAllTestsResult(data.result);
                        resultContainer.classList.remove('d-none');
                    }
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                // ボタンを元に戻す
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-play"></i> すべてのテストを実行';
                
                alert('テスト実行中にエラーが発生しました: ' + error);
            });
        });
    }
    
    // SQLクエリ実行ボタンの処理
    const runSqlButton = document.getElementById('run-sql-btn');
    if (runSqlButton) {
        runSqlButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const database = document.getElementById('sql-database').value;
            const query = document.getElementById('sql-query').value;
            
            if (!database || !query) {
                alert('データベースまたはクエリが指定されていません');
                return;
            }
            
            // ボタンの状態を更新
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 実行中...';
            
            // クエリ実行リクエスト
            fetch('api/execute_query.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    database: database,
                    query: query
                })
            })
            .then(response => response.json())
            .then(data => {
                // ボタンを元に戻す
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-play"></i> 実行';
                
                // 結果の処理
                const resultContainer = document.getElementById('sql-result');
                if (resultContainer) {
                    resultContainer.innerHTML = formatSqlResult(data);
                    resultContainer.classList.remove('d-none');
                }
            })
            .catch(error => {
                // ボタンを元に戻す
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-play"></i> 実行';
                
                alert('クエリ実行中にエラーが発生しました: ' + error);
            });
        });
    }
});

/**
 * テスト結果のフォーマット
 * 
 * @param {Object} result テスト結果
 * @return {string} フォーマット済みHTML
 */
function formatTestResult(result) {
    let statusClass = '';
    let statusIcon = '';
    
    switch (result.status) {
        case 'success':
            statusClass = 'success';
            statusIcon = 'check-circle';
            break;
        case 'failed':
        case 'failure':
        case 'fail':
            statusClass = 'warning';
            statusIcon = 'exclamation-circle';
            break;
        case 'error':
            statusClass = 'danger';
            statusIcon = 'exclamation-triangle';
            break;
        default:
            statusClass = 'secondary';
            statusIcon = 'question-circle';
    }
    
    let html = `
        <div class="alert alert-${statusClass}">
            <i class="fas fa-${statusIcon} me-2"></i>
            <strong>ステータス:</strong> ${result.status}
            <span class="float-end"><strong>実行時間:</strong> ${result.execution_time}ms</span>
        </div>
    `;
    
    if (result.message) {
        html += `
            <div class="mb-3">
                <strong>メッセージ:</strong> ${escapeHtml(result.message)}
            </div>
        `;
    }
    
    if (result.details && Object.keys(result.details).length > 0) {
        html += `
            <div class="mb-3">
                <strong>詳細:</strong>
                <pre class="mt-2 p-2 bg-light">${escapeHtml(JSON.stringify(result.details, null, 2))}</pre>
            </div>
        `;
    }
    
    return html;
}

/**
 * モジュールテスト結果のフォーマット
 * 
 * @param {Object} result モジュールテスト結果
 * @return {string} フォーマット済みHTML
 */
function formatModuleResult(result) {
    const successRate = result.tests_count > 0 ? Math.round((result.success / result.tests_count) * 100) : 0;
    const statusClass = successRate >= 90 ? 'success' : (successRate >= 70 ? 'warning' : 'danger');
    
    let html = `
        <div class="card mb-3">
            <div class="card-header bg-${statusClass} text-white">
                <strong>実行結果:</strong> 成功率 ${successRate}% (${result.success}/${result.tests_count})
                <span class="float-end"><strong>実行時間:</strong> ${result.execution_time}ms</span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 text-center">
                        <div class="p-2 bg-success text-white rounded">
                            <strong>成功:</strong> ${result.success}
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-2 bg-warning text-white rounded">
                            <strong>失敗:</strong> ${result.failed}
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-2 bg-danger text-white rounded">
                            <strong>エラー:</strong> ${result.error}
                        </div>
                    </div>
                </div>
                
                <h5>テスト詳細</h5>
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>テスト名</th>
                            <th>ステータス</th>
                            <th>メッセージ</th>
                            <th>実行時間</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    // 各テスト結果の行を追加
    for (const [testName, testResult] of Object.entries(result.results)) {
        let rowClass = '';
        switch (testResult.status) {
            case 'success': rowClass = 'table-success'; break;
            case 'failed': case 'failure': case 'fail': rowClass = 'table-warning'; break;
            case 'error': rowClass = 'table-danger'; break;
            default: rowClass = 'table-secondary';
        }
        
        html += `
            <tr class="${rowClass}">
                <td>${testName}</td>
                <td>${testResult.status}</td>
                <td>${escapeHtml(testResult.message || '')}</td>
                <td>${testResult.execution_time}ms</td>
            </tr>
        `;
    }
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    return html;
}

/**
 * 全テスト結果のフォーマット
 * 
 * @param {Object} result 全テスト結果
 * @return {string} フォーマット済みHTML
 */
function formatAllTestsResult(result) {
    const successRate = result.tests_count > 0 ? Math.round((result.success / result.tests_count) * 100) : 0;
    const statusClass = successRate >= 90 ? 'success' : (successRate >= 70 ? 'warning' : 'danger');
    
    let html = `
        <div class="card mb-3">
            <div class="card-header bg-${statusClass} text-white">
                <strong>全テスト実行結果:</strong> 成功率 ${successRate}% (${result.success}/${result.tests_count})
                <span class="float-end"><strong>実行時間:</strong> ${result.execution_time}ms</span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 text-center">
                        <div class="p-2 bg-primary text-white rounded">
                            <strong>モジュール数:</strong> ${result.modules_count}
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="p-2 bg-success text-white rounded">
                            <strong>成功:</strong> ${result.success}
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="p-2 bg-warning text-white rounded">
                            <strong>失敗:</strong> ${result.failed}
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="p-2 bg-danger text-white rounded">
                            <strong>エラー:</strong> ${result.error}
                        </div>
                    </div>
                </div>
                
                <h5>モジュール別結果</h5>
                <div class="accordion" id="modulesAccordion">
    `;
    
    // 各モジュール結果のアコーディオンを追加
    let moduleIndex = 0;
    for (const [moduleName, moduleResult] of Object.entries(result.results)) {
        const moduleSuccessRate = moduleResult.tests_count > 0 ? Math.round((moduleResult.success / moduleResult.tests_count) * 100) : 0;
        const moduleStatusClass = moduleSuccessRate >= 90 ? 'success' : (moduleSuccessRate >= 70 ? 'warning' : 'danger');
        
        html += `
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading${moduleIndex}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${moduleIndex}" aria-expanded="false" aria-controls="collapse${moduleIndex}">
                        <span class="badge bg-${moduleStatusClass} me-2">${moduleSuccessRate}%</span>
                        ${moduleName} (${moduleResult.success}/${moduleResult.tests_count})
                        <span class="ms-auto">${moduleResult.execution_time}ms</span>
                    </button>
                </h2>
                <div id="collapse${moduleIndex}" class="accordion-collapse collapse" aria-labelledby="heading${moduleIndex}" data-bs-parent="#modulesAccordion">
                    <div class="accordion-body">
                        ${formatModuleResult(moduleResult)}
                    </div>
                </div>
            </div>
        `;
        
        moduleIndex++;
    }
    
    html += `
                </div>
            </div>
        </div>
    `;
    
    return html;
}

/**
 * SQLクエリ実行結果のフォーマット
 * 
 * @param {Object} result クエリ実行結果
 * @return {string} フォーマット済みHTML
 */
function formatSqlResult(result) {
    let html = '';
    
    if (result.status === 'error') {
        html = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>エラー:</strong> ${escapeHtml(result.message)}
            </div>
        `;
    } else {
        html = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>クエリタイプ:</strong> ${result.query_type}
                <span class="float-end"><strong>実行時間:</strong> ${result.execution_time}ms</span>
            </div>
        `;
        
        if (result.query_type === 'SELECT') {
            html += `
                <div class="mb-3">
                    <strong>取得行数:</strong> ${result.row_count}
                </div>
            `;
            
            if (result.data && result.data.length > 0) {
                html += `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                `;
                
                // ヘッダー行の生成
                const columns = Object.keys(result.data[0]);
                columns.forEach(column => {
                    html += `<th>${escapeHtml(column)}</th>`;
                });
                
                html += `
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                // データ行の生成
                result.data.forEach(row => {
                    html += '<tr>';
                    columns.forEach(column => {
                        const value = row[column] !== null ? row[column] : '<null>';
                        html += `<td>${escapeHtml(value)}</td>`;
                    });
                    html += '</tr>';
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                html += `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        結果セットは空です
                    </div>
                `;
            }
        } else {
            html += `
                <div class="mb-3">
                    <strong>影響を受けた行数:</strong> ${result.affected_rows}
                </div>
            `;
        }
    }
    
    return html;
}

/**
 * HTMLエスケープ
 * 
 * @param {string} str エスケープする文字列
 * @return {string} エスケープされた文字列
 */
function escapeHtml(str) {
    if (str === null || str === undefined) {
        return '';
    }
    
    if (typeof str !== 'string') {
        str = String(str);
    }
    
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
} 