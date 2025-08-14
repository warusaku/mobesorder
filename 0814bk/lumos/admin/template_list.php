<?php
session_start();
require_once __DIR__ . '/../config/lumos_config.php';

// DB接続
try {
    $pdo = new PDO(
        'mysql:host=' . LUMOS_DB_HOST . ';dbname=' . LUMOS_DB_NAME . ';charset=utf8mb4',
        LUMOS_DB_USER,
        LUMOS_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die('データベース接続エラー: ' . $e->getMessage());
}

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $template_id = intval($_POST['template_id'] ?? 0);
    
    if ($template_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM message_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $_SESSION['result_message'] = '<div class="result success">テンプレートを削除しました。</div>';
        } catch (Exception $e) {
            $_SESSION['result_message'] = '<div class="result error">削除に失敗しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// テンプレート一覧取得
$templates = [];
try {
    $stmt = $pdo->query("SELECT id, title, message_type, content, created_at, updated_at FROM message_templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templates = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メッセージテンプレート一覧</title>
    <link rel="stylesheet" href="send.css">
    <style>
        body {
            background: transparent;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: none;
            padding: 0;
            background: transparent;
        }
        .template-list {
            margin: 0;
        }
        .template-item {
            border: none;
            border-bottom: 1px solid #e0e0e0;
            border-radius: 0;
            margin-bottom: 0;
            padding: 1em 0;
            background: transparent;
            box-shadow: none;
        }
        .template-item:last-child {
            border-bottom: none;
        }
        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        .template-title-section {
            display: flex;
            align-items: center;
            gap: 1em;
            flex: 1;
            min-width: 0; /* flexアイテムの縮小を許可 */
        }
        .template-actions-section {
            display: flex;
            align-items: center;
            gap: 0.8em; /* 作成日時と展開ボタンの間に空白を追加 */
            flex-shrink: 0; /* ボタン部分は縮小しない */
        }
        .template-meta-inline {
            font-size: 0.9em;
            color: #666;
            white-space: nowrap; /* 改行を防ぐ */
            flex-shrink: 0; /* 日時部分は縮小しない */
        }
        .template-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            min-width: 0;
        }
        .template-type {
            padding: 0.3em 0.8em;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
            flex-shrink: 0; /* バッジは縮小しない */
            text-align: center; /* 中央揃え */
        }
        .template-type.text {
            background: #e3f2fd;
            color: #1976d2;
        }
        .template-type.rich {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .template-thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #ddd;
            flex-shrink: 0;
        }
        .template-content {
            margin: 0;
        }
        .template-preview {
            max-width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .template-text {
            background: #f5f5f5;
            padding: 1em;
            border-radius: 4px;
            white-space: pre-wrap;
            font-size: 0.9em;
        }
        .template-meta {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 1em;
        }
        .template-actions {
            text-align: right;
        }
        .btn-delete {
            background: #f44336;
            color: white;
            border: none;
            padding: 0.4em;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            min-width: 2.2em;
            height: 2.2em;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-delete:hover {
            background: #d32f2f;
        }
        .btn-delete svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
        .result {
            padding: 1em;
            margin: 1em 0;
            border-radius: 4px;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .empty-state {
            text-align: center;
            padding: 3em;
            color: #666;
        }
        .rich-content {
            font-size: 0.9em;
            color: #666;
        }
        .content-toggle {
            background: #f5f5f5;
            color: #666;
            border: 1px solid #ddd;
            padding: 0.4em 0.6em;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            min-width: 2em;
            text-align: center;
        }
        .content-toggle:hover {
            background: #e0e0e0;
        }
        .template-content {
            margin: 0;
        }
        .template-content-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            margin-top: 0;
            padding-left: 1em;
        }
        .template-content-body.show {
            max-height: 500px; /* 十分な高さを設定 */
            margin-top: 1em;
            transition: max-height 0.3s ease-in;
        }
    </style>
</head>
<body>
<div class="container">
    <?php
    if (isset($_SESSION['result_message'])) {
        echo $_SESSION['result_message'];
        unset($_SESSION['result_message']);
    }
    ?>
    
    <div class="template-list">
        <?php if (empty($templates)): ?>
            <div class="empty-state">
                <h3>テンプレートがまだありません</h3>
                <p>「新しいテンプレートを作成」ボタンから最初のテンプレートを作成してください。</p>
            </div>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="template-item">
                    <div class="template-header">
                        <div class="template-title-section">
                            <?php if ($template['message_type'] === 'text'): ?>
                                <div class="template-type <?php echo $template['message_type']; ?>">
                                    テキスト
                                </div>
                            <?php else: ?>
                                <?php 
                                $content = json_decode($template['content'], true);
                                if ($content && isset($content['baseUrl'])):
                                ?>
                                    <img src="<?php echo htmlspecialchars($content['baseUrl'] . '/240'); ?>" 
                                         alt="<?php echo htmlspecialchars($content['altText'] ?? 'リッチメッセージ'); ?>" 
                                         class="template-thumbnail">
                                <?php else: ?>
                                    <div class="template-type rich">
                                        リッチ
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="template-title"><?php echo htmlspecialchars($template['title']); ?></div>
                            <div class="template-meta-inline">
                                作成：<?php echo date('Y/m/d H:i', strtotime($template['created_at'])); ?>
                                <?php if ($template['created_at'] !== $template['updated_at']): ?>
                                    | 更新：<?php echo date('Y/m/d H:i', strtotime($template['updated_at'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="template-actions-section">
                            <button class="content-toggle" onclick="toggleContent(<?php echo $template['id']; ?>)" id="toggle-btn-<?php echo $template['id']; ?>">
                                ▼
                            </button>
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['title'], ENT_QUOTES); ?>')">
                                <svg viewBox="0 0 24 24">
                                    <path d="M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8,9H10V19H8V9M14,9H16V19H14V9M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="template-content">
                        <div class="template-content-body" id="content-<?php echo $template['id']; ?>">
                            <?php if ($template['message_type'] === 'text'): ?>
                                <div class="template-text"><?php echo htmlspecialchars($template['content']); ?></div>
                            <?php else: ?>
                                <?php 
                                $content = json_decode($template['content'], true);
                                if ($content && isset($content['baseUrl'])):
                                ?>
                                    <div>
                                        <img src="<?php echo htmlspecialchars($content['baseUrl'] . '/300'); ?>" 
                                             alt="<?php echo htmlspecialchars($content['altText'] ?? 'リッチメッセージ'); ?>" 
                                             class="template-preview" 
                                             style="max-width: 300px;">
                                    </div>
                                    <div class="rich-content">
                                        <strong>タイトル:</strong> <?php echo htmlspecialchars($content['altText'] ?? ''); ?><br>
                                        <strong>リンク:</strong><br>
                                        <?php if (!empty($content['actions'])): ?>
                                            <?php foreach ($content['actions'] as $index => $action): ?>
                                                <?php if (!empty($action['linkUri'])): ?>
                                                    <div style="margin: 0.3em 0; padding-left: 1em;">
                                                        <strong>エリア<?php echo $index + 1; ?>:</strong> 
                                                        <a href="<?php echo htmlspecialchars($action['linkUri']); ?>" target="_blank" style="color: #0066cc; text-decoration: none; word-break: break-all;">
                                                            <?php echo htmlspecialchars($action['linkUri']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="padding-left: 1em; color: #999;">リンクが設定されていません</div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="rich-content">リッチメッセージ（プレビュー不可）</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(templateId, templateTitle) {
    if (confirm('テンプレート「' + templateTitle + '」を削除してもよろしいですか？\n\nこの操作は取り消せません。')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'template_id';
        idInput.value = templateId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleContent(templateId) {
    const contentDiv = document.getElementById('content-' + templateId);
    const toggleBtn = document.getElementById('toggle-btn-' + templateId);
    
    // showクラスの有無で状態を判定
    const isExpanded = contentDiv.classList.contains('show');
    
    if (isExpanded) {
        // 折りたたみ
        contentDiv.classList.remove('show');
        toggleBtn.textContent = '▼';
    } else {
        // 展開
        contentDiv.classList.add('show');
        toggleBtn.textContent = '▲';
    }
}
</script>
</body>
</html> 