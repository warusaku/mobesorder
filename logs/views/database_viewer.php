<?php
// データベース設定の読み込み
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/lib/Database.php';

// データベース接続
// $db = new Database(); // コンストラクタがプライベートなので使用不可
$db = Database::getInstance(); // 代わりに静的メソッドを使う
$conn = $db->getConnection();

// テーブル一覧を取得
$tablesResult = $db->select("SHOW TABLES");
$tables = [];
foreach ($tablesResult as $row) {
    $tables[] = reset($row);
}

// テーブル構造情報を取得
$tableStructures = [];
foreach ($tables as $table) {
    $columnsResult = $db->select("DESCRIBE `$table`");
    $tableStructures[$table] = $columnsResult;
}

// テスト実行ステータスフラグ
$testStatus = [
    'total' => 0,
    'passed' => 0,
    'steps' => []
];

// データベーステスト実行
function runDatabaseTests() {
    global $db, $testStatus;
    
    // ステップ1: データベース接続テスト
    $startTime = microtime(true);
    try {
        $isConnected = $db->getConnection() ? true : false;
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000);
        
        $testStatus['steps'][] = [
            'name' => 'データベース接続テスト',
            'status' => $isConnected,
            'duration' => $duration,
            'description' => 'データベースへの接続確認',
            'expected' => 'MySQLデータベースへの接続成功',
            'actual' => $isConnected ? 'データベース接続成功' : 'データベース接続失敗',
            'criteria' => 'データベース接続が正常に確立されること'
        ];
        
        if ($isConnected) {
            $testStatus['passed']++;
        }
        $testStatus['total']++;
        
        if (!$isConnected) {
            return $testStatus; // 接続失敗時は以降のテストをスキップ
        }
    } catch (Exception $e) {
        $testStatus['steps'][] = [
            'name' => 'データベース接続テスト',
            'status' => false,
            'duration' => 0,
            'description' => 'データベースへの接続確認',
            'expected' => 'MySQLデータベースへの接続成功',
            'actual' => '接続エラー: ' . $e->getMessage(),
            'criteria' => 'データベース接続が正常に確立されること',
            'error' => $e->getMessage()
        ];
        $testStatus['total']++;
        return $testStatus;
    }
    
    // ステップ2: テーブル存在確認テスト
    $startTime = microtime(true);
    try {
        $requiredTables = ['orders', 'products', 'room_tokens'];
        $tablesQuery = $db->select("SHOW TABLES");
        $existingTables = [];
        
        foreach ($tablesQuery as $table) {
            $existingTables[] = reset($table);
        }
        
        $missingTables = [];
        foreach ($requiredTables as $requiredTable) {
            if (!in_array($requiredTable, $existingTables)) {
                $missingTables[] = $requiredTable;
            }
        }
        
        $allTablesExist = count($missingTables) === 0;
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000);
        
        $testStatus['steps'][] = [
            'name' => 'テーブル存在確認',
            'status' => $allTablesExist,
            'duration' => $duration,
            'description' => '必要なテーブルの存在確認',
            'expected' => '必要なテーブルが存在: ' . implode(', ', $requiredTables),
            'actual' => $allTablesExist ? 
                '全てのテーブルが存在しています' : 
                '不足しているテーブル: ' . implode(', ', $missingTables),
            'criteria' => '必要なテーブルがすべて存在すること',
            'details' => "検出されたテーブル: " . implode(", ", $existingTables)
        ];
        
        if ($allTablesExist) {
            $testStatus['passed']++;
        }
        $testStatus['total']++;
    } catch (Exception $e) {
        $testStatus['steps'][] = [
            'name' => 'テーブル存在確認',
            'status' => false,
            'duration' => 0,
            'description' => '必要なテーブルの存在確認',
            'expected' => '必要なテーブルが存在すること',
            'actual' => 'テーブル確認エラー: ' . $e->getMessage(),
            'criteria' => '必要なテーブルがすべて存在すること',
            'error' => $e->getMessage()
        ];
        $testStatus['total']++;
    }
    
    // ステップ3: データベース構造テスト
    $startTime = microtime(true);
    try {
        $structureIssues = [];
        
        // 重要なテーブルの構造を確認（例：orders）
        if (in_array('orders', $existingTables)) {
            $orderColumns = $db->select("DESCRIBE orders");
            $requiredColumns = ['id', 'room_number', 'guest_name', 'total_amount', 'order_status'];
            
            $existingColumns = [];
            foreach ($orderColumns as $column) {
                $existingColumns[] = $column['Field'];
            }
            
            foreach ($requiredColumns as $requiredColumn) {
                if (!in_array($requiredColumn, $existingColumns)) {
                    $structureIssues[] = "orders テーブルに必要なカラム '$requiredColumn' がありません";
                }
            }
        }
        
        $structureValid = count($structureIssues) === 0;
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000);
        
        $testStatus['steps'][] = [
            'name' => 'データベース構造テスト',
            'status' => $structureValid,
            'duration' => $duration,
            'description' => 'テーブル構造の検証',
            'expected' => '主要テーブルが正しい構造を持つこと',
            'actual' => $structureValid ? 
                'データベース構造は正常です' : 
                '構造の問題: ' . implode('; ', $structureIssues),
            'criteria' => '必要なテーブルが正しいカラムと構造を持つこと',
            'details' => "検証されたテーブル構造: orders"
        ];
        
        if ($structureValid) {
            $testStatus['passed']++;
        }
        $testStatus['total']++;
    } catch (Exception $e) {
        $testStatus['steps'][] = [
            'name' => 'データベース構造テスト',
            'status' => false,
            'duration' => 0,
            'description' => 'テーブル構造の検証',
            'expected' => '主要テーブルが正しい構造を持つこと',
            'actual' => '構造検証エラー: ' . $e->getMessage(),
            'criteria' => '必要なテーブルが正しいカラムと構造を持つこと',
            'error' => $e->getMessage()
        ];
        $testStatus['total']++;
    }
    
    return $testStatus;
}

// テスト実行（サブミット時）
$testExecuted = false;
if (isset($_POST['run_db_tests'])) {
    $testExecuted = true;
    $testStatus = runDatabaseTests();
}
?>

<div class="card">
    <h2>データベース閲覧</h2>
    <p>データベースのテーブル情報を閲覧・検索できます。</p>
    
    <div class="tabs" style="margin-bottom: 20px;">
        <button id="tab-tables" class="tab-button active" onclick="showTab('tables')">テーブル一覧</button>
        <button id="tab-query" class="tab-button" onclick="showTab('query')">SQLクエリ</button>
        <button id="tab-structure" class="tab-button" onclick="showTab('structure')">DB構造</button>
        <button id="tab-test" class="tab-button" onclick="showTab('test')">DB診断</button>
    </div>
    
    <div id="tab-content-tables" class="tab-content" style="display: block;">
        <div style="display: flex; margin-bottom: 15px;">
            <select id="table-select" style="flex: 1; padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-right: 10px;">
                <option value="">-- テーブルを選択 --</option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="loadTableData()" class="button">表示</button>
        </div>
        
        <div id="table-filter" style="margin-bottom: 15px; display: none;">
            <input type="text" id="table-search" placeholder="検索..." style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd;" 
                  onkeyup="filterTableData()">
        </div>
        
        <div id="table-data" style="overflow-x: auto;">
            <p style="text-align: center; color: #666;">テーブルを選択してください</p>
        </div>
        
        <div id="pagination" style="margin-top: 15px; text-align: center; display: none;">
            <button onclick="changePage(-1)" class="button secondary">&laquo; 前へ</button>
            <span id="page-info" style="margin: 0 15px;">1 / 1</span>
            <button onclick="changePage(1)" class="button secondary">次へ &raquo;</button>
        </div>
    </div>
    
    <div id="tab-content-query" class="tab-content" style="display: none;">
        <div style="margin-bottom: 15px;">
            <textarea id="sql-query" placeholder="SQLクエリを入力..." style="width: 100%; height: 120px; padding: 10px; border-radius: 4px; border: 1px solid #ddd; font-family: monospace;"></textarea>
        </div>
        
        <div style="margin-bottom: 15px; display: flex; justify-content: space-between;">
            <button onclick="executeQuery()" class="button">実行</button>
            <div>
                <button onclick="loadQuery('SELECT')" class="button secondary">SELECT例</button>
                <button onclick="loadQuery('JOIN')" class="button secondary">JOIN例</button>
                <button onclick="loadQuery('COUNT')" class="button secondary">集計例</button>
            </div>
        </div>
        
        <div id="query-result" style="overflow-x: auto;">
            <p style="text-align: center; color: #666;">クエリを実行すると結果がここに表示されます</p>
        </div>
    </div>
    
    <div id="tab-content-structure" class="tab-content" style="display: none;">
        <div style="margin-bottom: 20px;">
            <h3>テーブル情報</h3>
            <div id="table-structure">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php foreach ($tableStructures as $tableName => $columns): ?>
                    <div class="table-info" style="border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
                        <h4 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php echo htmlspecialchars($tableName); ?></h4>
                        <table style="width: 100%; font-size: 0.9em;">
                            <tr>
                                <th style="text-align: left;">カラム</th>
                                <th style="text-align: left;">型</th>
                                <th style="text-align: left;">属性</th>
                            </tr>
                            <?php foreach ($columns as $column): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($column['Field']); ?></td>
                                <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                <td>
                    <?php
                                    $attributes = [];
                                    if ($column['Key'] === 'PRI') $attributes[] = 'PRIMARY KEY';
                                    if ($column['Null'] === 'NO') $attributes[] = 'NOT NULL';
                                    if (!empty($column['Default'])) $attributes[] = 'DEFAULT ' . $column['Default'];
                                    if ($column['Extra'] === 'auto_increment') $attributes[] = 'AUTO_INCREMENT';
                                    echo htmlspecialchars(implode(', ', $attributes));
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div id="tab-content-test" class="tab-content" style="display: none;">
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <div style="margin-bottom: 15px;">
                <h3>データベース診断テスト</h3>
                <p>データベース接続やテーブル構造の診断を実行します。</p>
                <button type="submit" name="run_db_tests" class="button">テスト実行</button>
            </div>
        </form>
        
        <?php if ($testExecuted): ?>
        <div style="margin-top: 20px;">
            <h3>テスト結果</h3>
            
            <?php foreach ($testStatus['steps'] as $step): ?>
            <div style="margin-bottom: 15px; border-left: 4px solid <?php echo $step['status'] ? '#4CAF50' : '#f44336'; ?>; padding: 10px; background-color: #f9f9f9;">
                <div style="display: flex; justify-content: space-between;">
                    <strong><?php echo htmlspecialchars($step['name']); ?></strong>
                    <span style="<?php echo $step['status'] ? 'color: #4CAF50;' : 'color: #f44336;'; ?>">
                        <?php echo $step['status'] ? '成功' : '失敗'; ?>
                    </span>
                </div>
                
                <?php if (isset($step['description'])): ?>
                <div style="font-size: 0.95rem; margin-top: 5px;">テスト内容: <?php echo htmlspecialchars($step['description']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($step['expected']) && isset($step['actual'])): ?>
                <div style="margin: 5px 0; font-size: 0.95em;">
                    期待値: <code style="background-color: #f5f5f5; padding: 2px 4px; border-radius: 3px;"><?php echo htmlspecialchars($step['expected']); ?></code><br>
                    実際の値: <code style="background-color: #f5f5f5; padding: 2px 4px; border-radius: 3px;"><?php echo htmlspecialchars($step['actual']); ?></code>
                </div>
                <?php endif; ?>
                
                <?php if (isset($step['criteria'])): ?>
                <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">判定基準: <?php echo htmlspecialchars($step['criteria']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($step['duration'])): ?>
                <div style="font-size: 0.85rem; color: #666; margin-top: 5px;">実行時間: <?php echo $step['duration']; ?>ms</div>
                <?php endif; ?>
                
                <?php if (!$step['status'] && isset($step['error'])): ?>
                <div style="margin-top: 10px; background-color: #fff; padding: 8px; border-radius: 4px; border: 1px solid #eee;">
                    <div style="color: #a94442; font-weight: bold;">エラー:</div>
                    <pre style="margin: 5px 0 0; font-size: 0.9em; white-space: pre-wrap; overflow-x: auto; border-left: 3px solid #a94442; padding-left: 10px;"><?php echo htmlspecialchars($step['error']); ?></pre>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <div style="padding: 15px; border-radius: 4px; <?php echo ($testStatus['passed'] === $testStatus['total']) ? 'background-color: #dff0d8; color: #3c763d;' : 'background-color: #fcf8e3; color: #8a6d3b;'; ?>">
                合計: <?php echo $testStatus['total']; ?> ステップ, 成功: <?php echo $testStatus['passed']; ?>, 失敗: <?php echo $testStatus['total'] - $testStatus['passed']; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// タブ切り替え機能
function showTab(tabName) {
    // すべてのタブコンテンツを非表示
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // すべてのタブボタンから active クラスを削除
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // 選択されたタブを表示
    document.getElementById('tab-content-' + tabName).style.display = 'block';
    
    // 選択されたタブボタンに active クラスを追加
    document.getElementById('tab-' + tabName).classList.add('active');
}

// 現在のページとページサイズ
let currentPage = 1;
const pageSize = 10;
let currentData = [];

// テーブルデータ読み込み
function loadTableData() {
    const tableName = document.getElementById('table-select').value;
    if (!tableName) return;
    
    document.getElementById('table-data').innerHTML = '<p style="text-align: center;">データを読み込み中...</p>';
    
    // AJAXでテーブルデータを取得
    fetch(`/fgsquare/api/database_viewer_api.php?action=get_table_data&table=${encodeURIComponent(tableName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('table-data').innerHTML = `<p style="color: #f44336;">エラー: ${data.error}</p>`;
                return;
            }
            
            currentData = data;
    currentPage = 1;
    
    document.getElementById('table-filter').style.display = 'block';
    document.getElementById('pagination').style.display = 'flex';
    document.getElementById('pagination').style.justifyContent = 'center';
    
    displayTableData(currentData);
        })
        .catch(error => {
            document.getElementById('table-data').innerHTML = `<p style="color: #f44336;">データ取得エラー: ${error.message}</p>`;
        });
}

// テーブルデータ表示
function displayTableData(data) {
    const tableElement = document.getElementById('table-data');
    
    if (data.length === 0) {
        tableElement.innerHTML = '<p style="text-align: center; color: #666;">データがありません</p>';
        document.getElementById('pagination').style.display = 'none';
        return;
    }
    
    // ページネーション更新
    const totalPages = Math.ceil(data.length / pageSize);
    document.getElementById('page-info').textContent = `${currentPage} / ${totalPages}`;
    
    // 現在のページのデータを取得
    const startIndex = (currentPage - 1) * pageSize;
    const pageData = data.slice(startIndex, startIndex + pageSize);
    
    // テーブルヘッダー作成
    let tableHTML = '<table style="width: 100%; border-collapse: collapse;">';
    tableHTML += '<thead><tr>';
    
    for (const key in pageData[0]) {
        tableHTML += `<th style="padding: 8px; text-align: left; border-bottom: 2px solid #ddd;">${key}</th>`;
    }
    
    tableHTML += '</tr></thead><tbody>';
    
    // テーブルデータ行の作成
    pageData.forEach((row, rowIndex) => {
        tableHTML += `<tr style="background-color: ${rowIndex % 2 === 0 ? '#f9f9f9' : 'white'};">`;
        
        for (const key in row) {
            const cellValue = row[key] === null ? '<em style="color: #999;">NULL</em>' : row[key];
            tableHTML += `<td style="padding: 8px; border-bottom: 1px solid #ddd;">${cellValue}</td>`;
        }
        
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody></table>';
    tableElement.innerHTML = tableHTML;
}

// ページ切り替え
function changePage(direction) {
    const totalPages = Math.ceil(currentData.length / pageSize);
    
    currentPage += direction;
    
    if (currentPage < 1) currentPage = 1;
    if (currentPage > totalPages) currentPage = totalPages;
    
    displayTableData(currentData);
}

// テーブルデータ検索フィルター
function filterTableData() {
    const searchText = document.getElementById('table-search').value.toLowerCase();
    const allData = [...currentData]; // 元のデータのコピー
    
    if (searchText.trim() === '') {
        displayTableData(allData);
    } else {
        const filteredData = allData.filter(row => {
            return Object.values(row).some(value => {
                return value !== null && value.toString().toLowerCase().includes(searchText);
            });
        });
    
    currentPage = 1;
        displayTableData(filteredData);
    }
}

// SQLクエリ実行
function executeQuery() {
    const query = document.getElementById('sql-query').value.trim();
    
    if (!query) {
        alert("クエリを入力してください");
        return;
    }
    
    document.getElementById('query-result').innerHTML = '<p style="text-align: center;">クエリを実行中...</p>';
    
    // AJAXでクエリを実行
    fetch('/fgsquare/api/database_viewer_api.php?action=execute_query', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `query=${encodeURIComponent(query)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            document.getElementById('query-result').innerHTML = `<p style="color: #f44336;">エラー: ${data.error}</p>`;
            return;
        }
        
        if (data.length === 0) {
            document.getElementById('query-result').innerHTML = '<p style="text-align: center; color: #666;">結果はありません</p>';
            return;
        }
        
        displayQueryResult(data);
    })
    .catch(error => {
        document.getElementById('query-result').innerHTML = `<p style="color: #f44336;">クエリ実行エラー: ${error.message}</p>`;
    });
}

// クエリ結果表示
function displayQueryResult(data) {
    const resultElement = document.getElementById('query-result');
    
    // テーブル作成
    let tableHTML = '<table style="width: 100%; border-collapse: collapse;">';
    tableHTML += '<thead><tr>';
    
    for (const key in data[0]) {
        tableHTML += `<th style="padding: 8px; text-align: left; border-bottom: 2px solid #ddd;">${key}</th>`;
    }
    
    tableHTML += '</tr></thead><tbody>';
    
    // テーブルデータ行の作成
    data.forEach((row, rowIndex) => {
        tableHTML += `<tr style="background-color: ${rowIndex % 2 === 0 ? '#f9f9f9' : 'white'};">`;
        
        for (const key in row) {
            const cellValue = row[key] === null ? '<em style="color: #999;">NULL</em>' : row[key];
            tableHTML += `<td style="padding: 8px; border-bottom: 1px solid #ddd;">${cellValue}</td>`;
        }
        
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody></table>';
    resultElement.innerHTML = tableHTML;
}

// SQLクエリ例をロード
function loadQuery(type) {
    let query = '';
    
    switch (type) {
        case 'SELECT':
            query = 'SELECT * FROM orders WHERE status = "CONFIRMED" ORDER BY created_at DESC LIMIT 10';
            break;
        case 'JOIN':
            query = 'SELECT o.id, o.room_number, o.created_at, oi.product_id, oi.quantity, p.name\n' +
                   'FROM orders o\n' +
                   'JOIN order_items oi ON o.id = oi.order_id\n' +
                   'JOIN products p ON oi.product_id = p.id\n' +
                   'WHERE o.room_number = "101"\n' +
                   'ORDER BY o.created_at DESC';
            break;
        case 'COUNT':
            query = 'SELECT room_number, COUNT(*) as order_count, SUM(total) as total_spent\n' +
                   'FROM orders\n' +
                   'WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)\n' +
                   'GROUP BY room_number\n' +
                   'ORDER BY total_spent DESC';
            break;
    }
    
    document.getElementById('sql-query').value = query;
}
</script>

<style>
.tab-button {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    border-bottom: none;
    padding: 10px 15px;
    cursor: pointer;
    border-radius: 4px 4px 0 0;
    outline: none;
}

.tab-button.active {
    background-color: white;
    font-weight: bold;
    border-bottom: 2px solid white;
    margin-bottom: -1px;
}

.tabs {
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
}

.tab-content {
    padding: 15px 0;
}
</style> 