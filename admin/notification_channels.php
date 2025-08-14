<?php
/**
 * 通知チャネル管理画面
 * 
 * 通知設定の追加、編集、削除、テスト送信を行う管理画面。
 */

require_once '../includes/init.php';
require_once '../includes/webhook_manager.php';

// 認証チェック
check_admin_auth();

// データベース接続
$db = get_db_connection();

// WebhookManagerのインスタンス化
$webhook_manager = new WebhookManager($db);

// アクション処理
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// チャネルの追加処理
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 入力値の取得と検証
    $channel_name = trim($_POST['channel_name'] ?? '');
    $channel_type = trim($_POST['channel_type'] ?? '');
    $config = $_POST['config'] ?? '';
    $trigger_types = isset($_POST['trigger_types']) ? $_POST['trigger_types'] : ['ALL'];
    $notification_level = $_POST['notification_level'] ?? 'ALL';
    $active = isset($_POST['active']) ? 1 : 0;
    
    // バリデーション
    if (empty($channel_name)) {
        $error = 'チャネル名を入力してください。';
    } elseif (empty($channel_type)) {
        $error = 'チャネルタイプを選択してください。';
    } elseif (empty($config)) {
        $error = '設定情報を入力してください。';
    } else {
        // チャネルの作成
        $channel_data = [
            'channel_name' => $channel_name,
            'channel_type' => $channel_type,
            'config' => $config,
            'trigger_types' => json_encode($trigger_types),
            'notification_level' => $notification_level,
            'active' => $active
        ];
        
        $channel_id = $webhook_manager->createChannel($channel_data);
        
        if ($channel_id) {
            $message = 'チャネルを追加しました。（ID: ' . $channel_id . '）';
            // 一覧画面にリダイレクト
            header('Location: notification_channels.php?message=' . urlencode($message));
            exit;
        } else {
            $error = 'チャネルの追加に失敗しました。';
        }
    }
}

// チャネルの編集処理
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $channel_id = isset($_POST['channel_id']) ? (int)$_POST['channel_id'] : 0;
    
    if ($channel_id <= 0) {
        $error = '無効なチャネルIDです。';
    } else {
        // 入力値の取得と検証
        $channel_name = trim($_POST['channel_name'] ?? '');
        $channel_type = trim($_POST['channel_type'] ?? '');
        $config = $_POST['config'] ?? '';
        $trigger_types = isset($_POST['trigger_types']) ? $_POST['trigger_types'] : ['ALL'];
        $notification_level = $_POST['notification_level'] ?? 'ALL';
        $active = isset($_POST['active']) ? 1 : 0;
        
        // バリデーション
        if (empty($channel_name)) {
            $error = 'チャネル名を入力してください。';
        } elseif (empty($channel_type)) {
            $error = 'チャネルタイプを選択してください。';
        } elseif (empty($config)) {
            $error = '設定情報を入力してください。';
        } else {
            // チャネルの更新
            $channel_data = [
                'channel_name' => $channel_name,
                'channel_type' => $channel_type,
                'config' => $config,
                'trigger_types' => json_encode($trigger_types),
                'notification_level' => $notification_level,
                'active' => $active
            ];
            
            $result = $webhook_manager->updateChannel($channel_id, $channel_data);
            
            if ($result) {
                $message = 'チャネルを更新しました。（ID: ' . $channel_id . '）';
                // 一覧画面にリダイレクト
                header('Location: notification_channels.php?message=' . urlencode($message));
                exit;
            } else {
                $error = 'チャネルの更新に失敗しました。';
            }
        }
    }
}

// チャネルの削除処理
if ($action === 'delete' && isset($_GET['id'])) {
    $channel_id = (int)$_GET['id'];
    
    if ($channel_id <= 0) {
        $error = '無効なチャネルIDです。';
    } else {
        // 確認画面からの送信かチェック
        if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
            $result = $webhook_manager->deleteChannel($channel_id);
            
            if ($result) {
                $message = 'チャネルを削除しました。（ID: ' . $channel_id . '）';
                // 一覧画面にリダイレクト
                header('Location: notification_channels.php?message=' . urlencode($message));
                exit;
            } else {
                $error = 'チャネルの削除に失敗しました。';
            }
        }
    }
}

// テスト送信処理
if ($action === 'test' && isset($_GET['id'])) {
    $channel_id = (int)$_GET['id'];
    
    if ($channel_id <= 0) {
        $error = '無効なチャネルIDです。';
    } else {
        $test_message = isset($_POST['test_message']) ? trim($_POST['test_message']) : 'これはテスト通知です。送信時刻: ' . date('Y-m-d H:i:s');
        
        $result = $webhook_manager->sendTestNotification($channel_id, $test_message);
        
        if ($result['status'] === 'completed') {
            $channel_result = $result['results'][$channel_id] ?? null;
            
            if ($channel_result && $channel_result['success']) {
                $message = 'テスト通知を送信しました。';
            } else {
                $error = 'テスト通知の送信に失敗しました。: ' . ($channel_result['error'] ?? '不明なエラー');
            }
        } else {
            $error = 'テスト通知の送信に失敗しました。: ' . ($result['reason'] ?? '不明なエラー');
        }
    }
}

// GETパラメータからメッセージを取得
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// チャネル一覧の取得
$channels = [];
if ($action === 'list' || $action === 'delete' || $action === 'test') {
    // データベースから全チャネルを取得
    $stmt = $db->prepare("
        SELECT * FROM notification_channels
        ORDER BY channel_id DESC
    ");
    $stmt->execute();
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 編集対象のチャネルを取得
$edit_channel = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $channel_id = (int)$_GET['id'];
    
    if ($channel_id > 0) {
        $stmt = $db->prepare("
            SELECT * FROM notification_channels
            WHERE channel_id = ?
        ");
        $stmt->execute([$channel_id]);
        $edit_channel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_channel) {
            // JSON形式の設定をデコード
            $edit_channel['trigger_types'] = json_decode($edit_channel['trigger_types'], true);
        }
    }
}

// ページタイトルの設定
$page_title = '通知チャネル管理';
if ($action === 'add') {
    $page_title .= ' - 新規追加';
} elseif ($action === 'edit') {
    $page_title .= ' - 編集';
} elseif ($action === 'delete') {
    $page_title .= ' - 削除確認';
} elseif ($action === 'test') {
    $page_title .= ' - テスト送信';
}

// HTML出力開始
include_once '../includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><?php echo $page_title; ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">ホーム</a></li>
                    <?php if ($action !== 'list'): ?>
                    <li class="breadcrumb-item"><a href="notification_channels.php">通知チャネル管理</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active"><?php echo $action === 'list' ? '通知チャネル管理' : ($action === 'add' ? '新規追加' : ($action === 'edit' ? '編集' : ($action === 'delete' ? '削除確認' : 'テスト送信'))); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h5><i class="icon fas fa-check"></i> 成功</h5>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h5><i class="icon fas fa-ban"></i> エラー</h5>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
        <!-- チャネル一覧 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">通知チャネル一覧</h3>
                <div class="card-tools">
                    <a href="notification_channels.php?action=add" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> 新規チャネル追加
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width: 60px">ID</th>
                            <th>チャネル名</th>
                            <th>タイプ</th>
                            <th>通知レベル</th>
                            <th style="width: 80px">状態</th>
                            <th style="width: 220px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($channels)): ?>
                        <tr>
                            <td colspan="6" class="text-center">通知チャネルがありません。</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($channels as $channel): ?>
                        <tr>
                            <td><?php echo $channel['channel_id']; ?></td>
                            <td><?php echo htmlspecialchars($channel['channel_name']); ?></td>
                            <td>
                                <?php
                                $type_labels = [
                                    'slack' => 'Slack',
                                    'line' => 'LINE',
                                    'discord' => 'Discord',
                                    'webhook' => 'Webhook'
                                ];
                                echo $type_labels[$channel['channel_type']] ?? $channel['channel_type'];
                                ?>
                            </td>
                            <td>
                                <?php
                                $level_labels = [
                                    'ALL' => 'すべて',
                                    'ALERTS_ONLY' => 'アラートのみ',
                                    'CRITICAL_ONLY' => '重要アラートのみ'
                                ];
                                echo $level_labels[$channel['notification_level']] ?? $channel['notification_level'];
                                ?>
                            </td>
                            <td>
                                <?php if ($channel['active']): ?>
                                <span class="badge badge-success">有効</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">無効</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="notification_channels.php?action=test&id=<?php echo $channel['channel_id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-paper-plane"></i> テスト
                                    </a>
                                    <a href="notification_channels.php?action=edit&id=<?php echo $channel['channel_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> 編集
                                    </a>
                                    <a href="notification_channels.php?action=delete&id=<?php echo $channel['channel_id']; ?>" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> 削除
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add' || ($action === 'edit' && $edit_channel)): ?>
        <!-- チャネル追加・編集フォーム -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo $action === 'add' ? 'チャネル追加' : 'チャネル編集'; ?></h3>
            </div>
            <form method="post" action="notification_channels.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $edit_channel['channel_id'] : ''; ?>">
                <div class="card-body">
                    <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="channel_id" value="<?php echo $edit_channel['channel_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="channel_name">チャネル名</label>
                        <input type="text" class="form-control" id="channel_name" name="channel_name" required
                            value="<?php echo $action === 'edit' ? htmlspecialchars($edit_channel['channel_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="channel_type">チャネルタイプ</label>
                        <select class="form-control" id="channel_type" name="channel_type" required onchange="updateConfigTemplate()">
                            <option value="">選択してください</option>
                            <option value="slack" <?php echo $action === 'edit' && $edit_channel['channel_type'] === 'slack' ? 'selected' : ''; ?>>Slack</option>
                            <option value="line" <?php echo $action === 'edit' && $edit_channel['channel_type'] === 'line' ? 'selected' : ''; ?>>LINE</option>
                            <option value="discord" <?php echo $action === 'edit' && $edit_channel['channel_type'] === 'discord' ? 'selected' : ''; ?>>Discord</option>
                            <option value="webhook" <?php echo $action === 'edit' && $edit_channel['channel_type'] === 'webhook' ? 'selected' : ''; ?>>Webhook</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notification_level">通知レベル</label>
                        <select class="form-control" id="notification_level" name="notification_level" required>
                            <option value="ALL" <?php echo $action === 'edit' && $edit_channel['notification_level'] === 'ALL' ? 'selected' : ''; ?>>すべて</option>
                            <option value="ALERTS_ONLY" <?php echo $action === 'edit' && $edit_channel['notification_level'] === 'ALERTS_ONLY' ? 'selected' : ''; ?>>アラートのみ</option>
                            <option value="CRITICAL_ONLY" <?php echo $action === 'edit' && $edit_channel['notification_level'] === 'CRITICAL_ONLY' ? 'selected' : ''; ?>>重要アラートのみ</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>通知トリガー</label>
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" id="trigger_all" name="trigger_types[]" value="ALL"
                                <?php echo $action === 'edit' && in_array('ALL', $edit_channel['trigger_types']) ? 'checked' : ''; ?>>
                            <label for="trigger_all" class="custom-control-label">すべて</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" id="trigger_error" name="trigger_types[]" value="ERROR"
                                <?php echo $action === 'edit' && in_array('ERROR', $edit_channel['trigger_types']) ? 'checked' : ''; ?>>
                            <label for="trigger_error" class="custom-control-label">エラー</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" id="trigger_alert" name="trigger_types[]" value="ALERT"
                                <?php echo $action === 'edit' && in_array('ALERT', $edit_channel['trigger_types']) ? 'checked' : ''; ?>>
                            <label for="trigger_alert" class="custom-control-label">アラート</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" id="trigger_warning" name="trigger_types[]" value="WARNING"
                                <?php echo $action === 'edit' && in_array('WARNING', $edit_channel['trigger_types']) ? 'checked' : ''; ?>>
                            <label for="trigger_warning" class="custom-control-label">警告</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" id="trigger_info" name="trigger_types[]" value="INFO"
                                <?php echo $action === 'edit' && in_array('INFO', $edit_channel['trigger_types']) ? 'checked' : ''; ?>>
                            <label for="trigger_info" class="custom-control-label">情報</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="config">設定情報 (JSON形式)</label>
                        <textarea class="form-control" id="config" name="config" rows="10" required><?php echo $action === 'edit' ? htmlspecialchars($edit_channel['config']) : ''; ?></textarea>
                        <small class="form-text text-muted">チャネルタイプに応じたJSON形式の設定情報を入力してください。</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input" type="checkbox" id="active" name="active" value="1"
                                <?php echo $action === 'edit' ? ($edit_channel['active'] ? 'checked' : '') : 'checked'; ?>>
                            <label for="active" class="custom-control-label">有効にする</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>設定テンプレート</label>
                        <div id="config_templates">
                            <div id="slack_template" class="config-template" style="display: none;">
<pre>{
  "webhook_url": "https://hooks.slack.com/services/XXXXX/XXXXX/XXXXX",
  "channel": "#alerts",
  "username": "RTSP-OCR-Bot",
  "icon_emoji": ":camera:"
}</pre>
                            </div>
                            <div id="line_template" class="config-template" style="display: none;">
<pre>{
  "access_token": "Channel Access Token",
  "to": "User ID or Group ID"
}</pre>
                            </div>
                            <div id="discord_template" class="config-template" style="display: none;">
<pre>{
  "webhook_url": "https://discord.com/api/webhooks/XXXXX/XXXXX",
  "username": "RTSP-OCR監視",
  "avatar_url": "http://test-mijeos.but.jp/RTSP_reader/assets/images/logo.png"
}</pre>
                            </div>
                            <div id="webhook_template" class="config-template" style="display: none;">
<pre>{
  "endpoint_url": "https://example.com/api/alerts",
  "method": "POST",
  "payload_format": "json",
  "auth_type": "api_key",
  "api_key_name": "X-API-KEY",
  "api_key_value": "your-api-key",
  "api_key_in": "header",
  "timeout": 30,
  "custom_fields": {
    "system_id": "RTSP-OCR-001",
    "department": "生産管理"
  }
}</pre>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="notification_channels.php" class="btn btn-default">キャンセル</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'delete' && isset($_GET['id'])): ?>
        <!-- 削除確認 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">削除確認</h3>
            </div>
            <div class="card-body">
                <p>以下の通知チャネルを削除してもよろしいですか？</p>
                
                <?php
                $channel_id = (int)$_GET['id'];
                $channel = null;
                
                foreach ($channels as $ch) {
                    if ($ch['channel_id'] === $channel_id) {
                        $channel = $ch;
                        break;
                    }
                }
                
                if ($channel):
                ?>
                <div class="callout callout-danger">
                    <h5><?php echo htmlspecialchars($channel['channel_name']); ?> (ID: <?php echo $channel['channel_id']; ?>)</h5>
                    <p>
                        タイプ: <?php echo $type_labels[$channel['channel_type']] ?? $channel['channel_type']; ?><br>
                        通知レベル: <?php echo $level_labels[$channel['notification_level']] ?? $channel['notification_level']; ?><br>
                        状態: <?php echo $channel['active'] ? '有効' : '無効'; ?>
                    </p>
                </div>
                
                <div class="alert alert-warning">
                    <i class="icon fas fa-exclamation-triangle"></i> この操作は元に戻せません。
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="icon fas fa-ban"></i> 指定されたチャネルが見つかりません。
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <?php if ($channel): ?>
                <a href="notification_channels.php?action=delete&id=<?php echo $channel_id; ?>&confirm=yes" class="btn btn-danger">削除する</a>
                <?php endif; ?>
                <a href="notification_channels.php" class="btn btn-default">キャンセル</a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'test' && isset($_GET['id'])): ?>
        <!-- テスト送信 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">テスト通知送信</h3>
            </div>
            <div class="card-body">
                <?php
                $channel_id = (int)$_GET['id'];
                $channel = null;
                
                foreach ($channels as $ch) {
                    if ($ch['channel_id'] === $channel_id) {
                        $channel = $ch;
                        break;
                    }
                }
                
                if ($channel):
                ?>
                <div class="callout callout-info">
                    <h5><?php echo htmlspecialchars($channel['channel_name']); ?> (ID: <?php echo $channel['channel_id']; ?>)</h5>
                    <p>
                        タイプ: <?php echo $type_labels[$channel['channel_type']] ?? $channel['channel_type']; ?><br>
                        通知レベル: <?php echo $level_labels[$channel['notification_level']] ?? $channel['notification_level']; ?><br>
                        状態: <?php echo $channel['active'] ? '有効' : '無効'; ?>
                    </p>
                </div>
                
                <form method="post" action="notification_channels.php?action=test&id=<?php echo $channel_id; ?>">
                    <div class="form-group">
                        <label for="test_message">テストメッセージ</label>
                        <input type="text" class="form-control" id="test_message" name="test_message" 
                               value="これはテスト通知です。送信時刻: <?php echo date('Y-m-d H:i:s'); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">テスト送信</button>
                </form>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="icon fas fa-ban"></i> 指定されたチャネルが見つかりません。
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="notification_channels.php" class="btn btn-default">チャネル一覧に戻る</a>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</section>

<script>
// 設定テンプレートの表示を更新する関数
function updateConfigTemplate() {
    const channelType = document.getElementById('channel_type').value;
    const templates = document.querySelectorAll('.config-template');
    
    // すべてのテンプレートを非表示
    templates.forEach(template => {
        template.style.display = 'none';
    });
    
    // 選択されたタイプのテンプレートを表示
    if (channelType) {
        const templateId = channelType + '_template';
        const template = document.getElementById(templateId);
        if (template) {
            template.style.display = 'block';
        }
    }
}

// 初期表示時に実行
document.addEventListener('DOMContentLoaded', function() {
    updateConfigTemplate();
    
    // トリガータイプの「すべて」がチェックされたら他を無効化
    const triggerAll = document.getElementById('trigger_all');
    if (triggerAll) {
        triggerAll.addEventListener('change', function() {
            const otherTriggers = document.querySelectorAll('input[name="trigger_types[]"]:not(#trigger_all)');
            otherTriggers.forEach(trigger => {
                trigger.disabled = this.checked;
                if (this.checked) {
                    trigger.checked = false;
                }
            });
        });
        
        // 初期状態を反映
        if (triggerAll.checked) {
            const otherTriggers = document.querySelectorAll('input[name="trigger_types[]"]:not(#trigger_all)');
            otherTriggers.forEach(trigger => {
                trigger.disabled = true;
            });
        }
    }
});
</script>

<?php
include_once '../includes/admin_footer.php';
?> 
 
 
 
 