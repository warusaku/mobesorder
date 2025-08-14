<?php
// manual_order_viewer.php
// Square APIから最新の注文を取得し、詳細を表示
require_once __DIR__ . '/config/config.php';

$accessToken = SQUARE_ACCESS_TOKEN;
$locationId = SQUARE_LOCATION_ID;

$url = "https://connect.squareup.com/v2/orders/search";
$headers = [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
];
$body = json_encode([
    "location_ids" => [$locationId],
    "limit" => 10 // 最新10件を取得
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
if ($response === false) {
    echo "APIリクエストに失敗しました: " . curl_error($ch);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);
$orders = $data['orders'] ?? [];

// HTMLで表示
?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Square注文履歴</title>
    <style>table{border-collapse:collapse;}td,th{border:1px solid #ccc;padding:4px;}</style>
</head>
<body>
<h1>Square注文履歴（最新10件）</h1>
<?php if (empty($orders)): ?>
<p>注文データがありません。</p>
<?php else: ?>
<?php foreach ($orders as $order): ?>
    <h2>注文ID: <?= htmlspecialchars($order['id'] ?? '-') ?></h2>
    <pre style="background:#f8f8f8;padding:8px;border:1px solid #ccc;overflow-x:auto;">
<?= htmlspecialchars(json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
    </pre>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html> 