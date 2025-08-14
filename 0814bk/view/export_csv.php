<?php
// File: export_csv.php
// Description: device_readings テーブルのCSVダウンロード出力

// ログファイル設定
$logFile = __DIR__ . '/../logs/php.log';
function log_message($msg) {
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " [EXPORT] " . $msg . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// DB接続
require_once '../dbconfig.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// 接続エラー処理
if ($mysqli->connect_error) {
    log_message("DB接続エラー: " . $mysqli->connect_error);
    die("データベース接続エラー");
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

// エクスポート対象件数の上限（オプションで上書き可能）
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;
if ($limit <= 0 || $limit > 10000) { // 最大1万件までに制限
    $limit = 1000;
}

// ファイル名設定
$filename = "rtsp_reader_export_" . date("Ymd_His") . ".csv";
if (!empty($device_filter)) {
    $filename = "rtsp_reader_{$device_filter}_" . date("Ymd_His") . ".csv";
}

// ヘッダー設定 (CSVダウンロード)
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 出力バッファを開く (php://output にCSVを書き込む)
$output = fopen('php://output', 'w');

// BOMを出力（Excel用）
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSVヘッダー行
fputcsv($output, ['日時', 'LacisID', '表示ID', '値', '変換値', 'ID']);

try {
    // データ取得クエリ
    $query = "SELECT timestamp, lacis_id, display_id, value, converted_value, id FROM device_readings" . 
             $filterSql . " ORDER BY timestamp DESC LIMIT ?";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("SQLエラー: " . $mysqli->error);
    }
    
    // パラメータバインド
    if (!empty($filterParams)) {
        $filterParams[] = $limit;
        $paramTypes .= 'i';
        $stmt->bind_param($paramTypes, ...$filterParams);
    } else {
        $stmt->bind_param('i', $limit);
    }
    
    // クエリ実行
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 結果の各行をCSVに書き込む
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['timestamp'],
            $row['lacis_id'],
            $row['display_id'],
            $row['value'],
            $row['converted_value'] ?? $row['value'],
            $row['id']
        ]);
        $count++;
    }
    
    // ログ記録
    $filter_str = empty($filterSql) ? "全件" : trim(str_replace('WHERE', '', $filterSql));
    log_message("CSVエクスポート完了: {$count}件 [{$filter_str}]");
    
} catch (Exception $e) {
    log_message("エクスポートエラー: " . $e->getMessage());
    fputcsv($output, ['エラーが発生しました: ' . $e->getMessage()]);
} finally {
    // リソース解放
    if (isset($stmt)) {
        $stmt->close();
    }
    $mysqli->close();
    fclose($output);
}
exit;
?> 