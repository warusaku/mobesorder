<?php
// イメージマップメッセージ送信用API
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/lumos_config.php';

$channel_access_token = LUMOS_LINE_CHANNEL_ACCESS_TOKEN;
$push_api_url = 'https://api.line.me/v2/bot/message/push';
$broadcast_api_url = 'https://api.line.me/v2/bot/message/broadcast';

function sendImagemapMessage($to, $image_url, $link_url, $alt_text = '画像メッセージ') {
    global $channel_access_token, $push_api_url, $broadcast_api_url;
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channel_access_token
    ];
    // baseUrlは拡張子を除いたURL
    $base_url = preg_replace('/\.(jpg|jpeg|png)$/i', '', $image_url);
    $imagemap_message = [
        'type' => 'imagemap',
        'baseUrl' => $base_url,
        'altText' => $alt_text,
        'baseSize' => [ 'width' => 1040, 'height' => 1040 ],
        'actions' => [
            [
                'type' => 'uri',
                'linkUri' => $link_url,
                'area' => [ 'x' => 0, 'y' => 0, 'width' => 1040, 'height' => 1040 ]
            ]
        ]
    ];
    if ($to) {
        $post_data = [
            'to' => $to,
            'messages' => [ $imagemap_message ]
        ];
        $url = $push_api_url;
    } else {
        $post_data = [
            'messages' => [ $imagemap_message ]
        ];
        $url = $broadcast_api_url;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'http_code' => $http_code,
        'response' => $response
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $to = $input['to'] ?? null;
    
    // messages配列からデータを取得
    $message = $input['messages'][0] ?? null;
    if (!$message || $message['type'] !== 'imagemap') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid message format']);
        exit;
    }

    $base_url = $message['baseUrl'] ?? '';
    $link_url = $message['actions'][0]['linkUri'] ?? '';
    $alt_text = $message['altText'] ?? '画像メッセージ';

    // バリデーション
    if (!$base_url || !$link_url) {
        http_response_code(400);
        echo json_encode(['error' => 'baseUrl and linkUri are required']);
        exit;
    }

    // メッセージのバリデーション
    $validate_url = 'https://api.line.me/v2/bot/message/validate/push';
        $imagemap_message = [
            'type' => 'imagemap',
            'baseUrl' => $base_url,
            'altText' => $alt_text,
        'baseSize' => $message['baseSize'] ?? [ 'width' => 1040, 'height' => 1040 ],
        'actions' => $message['actions'] ?? [
                [
                    'type' => 'uri',
                    'linkUri' => $link_url,
                    'area' => [ 'x' => 0, 'y' => 0, 'width' => 1040, 'height' => 1040 ]
                ]
            ]
        ];

    $validate_data = [
        'to' => $to,
        'messages' => [ $imagemap_message ]
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LUMOS_LINE_CHANNEL_ACCESS_TOKEN
    ];

    // バリデーション実行
    $ch = curl_init($validate_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validate_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $validate_response = curl_exec($ch);
    $validate_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($validate_http_code !== 200) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Message validation failed',
            'validation_response' => $validate_response
        ]);
        exit;
    }

    // 実際のメッセージ送信
        if ($to) {
            $post_data = [
                'to' => $to,
                'messages' => [ $imagemap_message ]
            ];
            $url = $push_api_url;
        } else {
            $post_data = [
                'messages' => [ $imagemap_message ]
            ];
            $url = $broadcast_api_url;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        header('Content-Type: application/json');
        echo json_encode([
            'http_code' => $http_code,
        'response' => $response,
        'sent_message' => $imagemap_message  // デバッグ用に送信したメッセージも返す
        ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 