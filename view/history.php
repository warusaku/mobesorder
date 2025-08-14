<?php
// File: history.php
// Description: デバイスログの一覧表示（device_readings テーブル）

// DB接続設定
require_once '../dbconfig.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// 接続エラー処理
if ($mysqli->connect_error) {
    die('データベース接続エラー: ' . $mysqli->connect_error);
}

// フィルタリング処理
$filters = [];
$filterSql = '';
$filterParams = [];
$paramTypes = '';

// デバイスIDフィルタ
$device_filter = isset($_GET['device']) ? $_GET['device'] : '';
if (!empty($device_filter)) {
    $filters[] = "lacis_id = ?";
    $filterParams[] = $device_filter;
    $paramTypes .= 's';
}

// 表示IDフィルタ
$display_filter = isset($_GET['display']) ? $_GET['display'] : '';
if (!empty($display_filter)) {
    $filters[] = "display_id = ?";
    $filterParams[] = $display_filter;
    $paramTypes .= 's';
}

// 日付範囲フィルタ
$date_from = isset($_GET['from']) ? $_GET['from'] : '';
$date_to = isset($_GET['to']) ? $_GET['to'] : '';

if (!empty($date_from)) {
    $filters[] = "timestamp >= ?";
    $filterParams[] = $date_from . ' 00:00:00';
    $paramTypes .= 's';
}

if (!empty($date_to)) {
    $filters[] = "timestamp <= ?";
    $filterParams[] = $date_to . ' 23:59:59';
    $paramTypes .= 's';
}

// 値フィルタ
$value_filter = isset($_GET['value']) ? $_GET['value'] : '';
if (!empty($value_filter)) {
    $filters[] = "value LIKE ?";
    $filterParams[] = "%{$value_filter}%";
    $paramTypes .= 's';
}

// フィルタSQL生成
if (!empty($filters)) {
    $filterSql = ' WHERE ' . implode(' AND ', $filters);
}

// ページネーション
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// 総レコード数取得
$countQuery = "SELECT COUNT(*) as total FROM device_readings" . $filterSql;
$stmt = $mysqli->prepare($countQuery);

if (!empty($filterParams)) {
    $stmt->bind_param($paramTypes, ...$filterParams);
}

$stmt->execute();
$countResult = $stmt->get_result();
$totalRow = $countResult->fetch_assoc();
$total = $totalRow['total'];
$total_pages = ceil($total / $per_page);

// データ取得クエリ
$query = "SELECT * FROM device_readings" . $filterSql . 
         " ORDER BY timestamp DESC LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);

// パラメータバインド
if (!empty($filterParams)) {
    $paramTypes .= 'ii';
    $filterParams[] = $per_page;
    $filterParams[] = $offset;
    $stmt->bind_param($paramTypes, ...$filterParams);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTSP_Reader - 履歴一覧</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f8f8;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .filters {
            background: #eee;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .pagination {
            margin: 20px 0;
            text-align: center;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        .pagination .current {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        .export-btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }
        .alert {
            color: #721c24;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .ok {
            color: #155724;
            background-color: #d4edda;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📋 RTSP_Reader 履歴表示</h2>
        
        <!-- フィルターフォーム -->
        <div class="filters">
            <form method="get" action="">
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label for="device">LacisID:</label>
                        <input type="text" id="device" name="device" value="<?= htmlspecialchars($device_filter) ?>">
                    </div>
                    <div>
                        <label for="display">表示ID:</label>
                        <input type="text" id="display" name="display" value="<?= htmlspecialchars($display_filter) ?>">
                    </div>
                    <div>
                        <label for="value">値:</label>
                        <input type="text" id="value" name="value" value="<?= htmlspecialchars($value_filter) ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div>
                        <label for="from">期間:</label>
                        <input type="date" id="from" name="from" value="<?= htmlspecialchars($date_from) ?>">
                        〜
                        <input type="date" id="to" name="to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div>
                        <button type="submit" style="padding: 5px 10px;">フィルター適用</button>
                        <a href="?" style="padding: 5px 10px; margin-left: 5px;">リセット</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- エクスポートボタン -->
        <a href="export_csv.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="export-btn">CSVエクスポート</a>
        
        <!-- 結果表示 -->
        <p>全<?= $total ?>件中 <?= $offset + 1 ?>〜<?= min($offset + $per_page, $total) ?>件表示</p>
        
        <!-- テーブル表示 -->
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>LacisID</th>
                    <th>表示ID</th>
                    <th>値</th>
                    <th>変換後</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="5">データがありません</td>
                </tr>
                <?php else: ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['timestamp']) ?></td>
                    <td>
                        <a href="?device=<?= htmlspecialchars($row['lacis_id']) ?>">
                            <?= htmlspecialchars($row['lacis_id']) ?>
                        </a>
                    </td>
                    <td>
                        <a href="?device=<?= htmlspecialchars($row['lacis_id']) ?>&display=<?= htmlspecialchars($row['display_id']) ?>">
                            <?= htmlspecialchars($row['display_id']) ?>
                        </a>
                    </td>
                    <td <?= (stripos($row['value'], 'ERR') !== false || stripos($row['value'], '999') !== false || stripos($row['value'], 'FAIL') !== false) ? 'class="alert"' : (stripos($row['value'], 'OK') !== false ? 'class="alert ok"' : '') ?>>
                        <?= htmlspecialchars($row['value']) ?>
                    </td>
                    <td>
                        <?= isset($row['converted_value']) ? htmlspecialchars($row['converted_value']) : htmlspecialchars($row['value']) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- ページネーション -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : '' ?>">前へ</a>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): 
                if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : '' ?>"><?= $i ?></a>
                <?php endif;
            endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : '' ?>">次へ</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
// DB接続クローズ
$mysqli->close();
?> 