<?php
/**
 * 受信メッセージ一覧表示
 * 
 * @package Lumos
 * @subpackage Admin
 */

require_once __DIR__ . '/../config/config.php';

// データベース接続
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('データベース接続エラー: ' . $e->getMessage());
}

// メッセージ一覧の取得
$sql = "SELECT 
            m.id,
            m.room_number,
            m.user_id,
            m.sender_type,
            m.platform,
            m.message_type,
            m.message,
            m.status,
            m.created_at,
            l.user_name
        FROM messages m
        LEFT JOIN line_room_links l
          ON m.user_id COLLATE utf8mb4_0900_ai_ci = l.line_user_id COLLATE utf8mb4_0900_ai_ci
        ORDER BY m.created_at DESC
        LIMIT 100";

$stmt = $pdo->query($sql);
$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メッセージ一覧 - Lumos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .message-guest { background-color: #f8f9fa; }
        .message-staff { background-color: #e3f2fd; }
        .message-system { background-color: #fff3e0; }
        .status-sent { color: #6c757d; }
        .status-delivered { color: #0d6efd; }
        .status-read { color: #198754; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>メッセージ一覧</h1>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>部屋番号</th>
                        <th>ユーザー名</th>
                        <th>送信者種別</th>
                        <th>プラットフォーム</th>
                        <th>メッセージ種別</th>
                        <th>メッセージ</th>
                        <th>ステータス</th>
                        <th>受信日時</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                    <tr class="message-<?= htmlspecialchars($message['sender_type']) ?>">
                        <td><?= htmlspecialchars($message['id']) ?></td>
                        <td><?= htmlspecialchars($message['room_number']) ?></td>
                        <td><?= htmlspecialchars($message['user_name'] ?? '不明') ?></td>
                        <td><?= htmlspecialchars($message['sender_type']) ?></td>
                        <td><?= htmlspecialchars($message['platform']) ?></td>
                        <td><?= htmlspecialchars($message['message_type']) ?></td>
                        <td><?= htmlspecialchars($message['message']) ?></td>
                        <td class="status-<?= htmlspecialchars($message['status']) ?>">
                            <?= htmlspecialchars($message['status']) ?>
                        </td>
                        <td><?= htmlspecialchars($message['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 