<?php
require_once __DIR__ . '/../config/lumos_config.php';
$token = LUMOS_LINE_CHANNEL_ACCESS_TOKEN;
$url = 'https://api.line.me/v2/bot/info';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// 必要に応じてSSL検証を無効化（本番では推奨しません）
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $http_code\n";
echo $response; 