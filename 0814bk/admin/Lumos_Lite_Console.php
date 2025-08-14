<?php
// Lumos_Lite_Console.php
// ------------------------------------------------------------
// LINE / LUMOS メッセージング管理コンソール
// 部屋単位でカード表示し、モーダルでスレッド表示・送信を行う UI。
// Version: v20240526
// Author : FG Dev Team
// ------------------------------------------------------------

// --------------- ログ送信関数定義 ---------------
function lumosLiteConsoleLog(string $message, string $level = 'INFO'): void
{
    $scriptName = basename(__FILE__, '.php');
    $logDir     = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . "/{$scriptName}.log";

    if (file_exists($logFile) && filesize($logFile) > 307200) { // 300KB
        $content = file_get_contents($logFile);
        $retainSize = (int)(307200 * 0.2);
        $content = substr($content, -$retainSize);
        file_put_contents($logFile, $content, LOCK_EX);
    }

    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date][$level] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// アクセスログ
lumosLiteConsoleLog('ページアクセス:' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

// ページタイトル
$pageTitle = 'Lumos Lite Console';

// 共通ヘッダ読み込み
require_once __DIR__ . '/inc/admin_header.php';

// adminsetting.json から設定取得
if (!defined('ADMIN_SETTING_INTERNAL_CALL')) {
    define('ADMIN_SETTING_INTERNAL_CALL', true);
}
require_once __DIR__ . '/adminsetting_registrer.php';
$settings        = loadSettings();
$consoleSettings = $settings['lumos_console'] ?? [];
$pollSec         = (int)($consoleSettings['poll_interval_sec'] ?? 5);
$apiEndpoint     = '/admin/message_Transmission.php';
?>

<?php if ($isLoggedIn): ?>
<link rel="stylesheet" href="css/apple_like.css?v=20240531">
<link rel="stylesheet" href="css/line_like.css?v=20240531">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="lumos_console.css">
<script src="js/message_console.js?v=20240530" defer></script>
<style>
.order-total-row:hover {
    background-color: #e9ecef !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
.order-total-row:active {
    transform: translateY(0);
}
</style>

<script>
window.LUMOS_CONSOLE_CONFIG = {
    pollInterval: <?php echo $pollSec * 1000; ?>,
    apiEndpoint : '<?php echo $apiEndpoint; ?>'
};
</script>

<div class="d-flex justify-content-end align-items-center mb-2">
    <button id="broadcastAllBtn" class="btn btn-success btn-sm"><i class="fa-solid fa-bullhorn"></i> 全ユーザーへ送信</button>
    <button id="createTemplateBtn" class="btn btn-primary btn-sm ms-2"><i class="fa-solid fa-pen-to-square"></i> テンプレートを作成</button>
    <button id="templateListBtn" class="btn btn-info btn-sm ms-2"><i class="fa-solid fa-list"></i> テンプレートリスト</button>
</div>
<div id="roomCards" class="cards-grid"></div>

<!-- Messenger Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content line-modal">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalRoomTitle">Room</h5>
          <div id="modalRoomInfo" class="small text-muted"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column">
        <div id="messageContainer" class="message-container d-flex flex-column gap-2"></div>
      </div>
      <div class="modal-footer">
        <div class="d-flex flex-column w-100 gap-2">
          <div class="d-flex gap-2">
            <select id="messageTypeSelect" class="form-select form-select-sm" style="max-width:120px">
              <option value="text">テキスト</option>
              <option value="rich">リッチ</option>
              <option value="template">定型文</option>
            </select>
            <select id="richSelect" class="form-select form-select-sm d-none" style="max-width:200px"></select>
            <select id="templateSelect" class="form-select form-select-sm d-none" style="max-width:200px"></select>
            <button id="editTemplateBtn" class="btn btn-outline-secondary btn-sm d-none"><i class="fa-solid fa-pen"></i></button>
            <button id="timerBtn" class="btn btn-outline-primary btn-sm"><i class="fa-regular fa-clock"></i></button>
          </div>
          <div class="input-group">
            <select id="userSelect" class="form-select form-select-sm" style="max-width:150px"></select>
            <textarea id="messageInput" class="form-control" rows="1" placeholder="メッセージを入力" style="resize:none"></textarea>
            <button class="btn btn-primary" id="sendBtn"><i class="fa-solid fa-paper-plane"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Template Edit Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">定型文を編集</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul id="templateList" class="list-group small mb-3"></ul>
        <div class="input-group">
          <input id="newTemplateInput" type="text" class="form-control form-control-sm" placeholder="新しい定型文">
          <button id="addTemplateBtn" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-plus"></i></button>
        </div>
        <small class="text-muted">※ UI 確認用に localStorage へ保存します。本番時は API 実装が必要です。</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
</div>

<!-- Timer Modal -->
<div class="modal fade" id="timerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">予約送信</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body d-flex flex-column gap-2">
        <input id="timerDatetime" type="datetime-local" class="form-control form-control-sm">
        <select id="timerTemplate" class="form-select form-select-sm"></select>
      </div>
      <div class="modal-footer"><button id="timerSaveBtn" class="btn btn-primary btn-sm">保存</button></div>
    </div>
  </div>
</div>

<!-- send.php用モーダル -->
<div class="modal fade" id="sendModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">全ユーザーへ送信</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="height:80vh;">
        <iframe id="sendIframe" src="about:blank" style="width:100%;height:100%;border:none;"></iframe>
      </div>
    </div>
  </div>
</div>
<!-- テンプレート作成用モーダル -->
<div class="modal fade" id="createTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">テンプレートを作成</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="height:80vh;">
        <iframe id="createTemplateIframe" src="about:blank" style="width:100%;height:100%;border:none;"></iframe>
      </div>
    </div>
  </div>
</div>
<!-- テンプレートリスト用モーダル -->
<div class="modal fade" id="templateListModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">テンプレートリスト</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="height:80vh;">
        <iframe id="templateListIframe" src="about:blank" style="width:100%;height:100%;border:none;"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- 注文詳細モーダル -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderDetailsTitle">注文詳細</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="orderDetailsContent">
          <div class="text-center">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">読み込み中...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('broadcastAllBtn').addEventListener('click', function() {
    var sendModal = new bootstrap.Modal(document.getElementById('sendModal'));
    document.getElementById('sendIframe').src = '/lumos/admin/send.php';
    sendModal.show();
});
// テンプレート作成ボタンの処理
document.getElementById('createTemplateBtn').addEventListener('click', function() {
    var templateModal = new bootstrap.Modal(document.getElementById('createTemplateModal'));
    document.getElementById('createTemplateIframe').src = '/lumos/admin/create_message.php';
    templateModal.show();
});

// テンプレートリストボタンの処理
document.getElementById('templateListBtn').addEventListener('click', function() {
    var templateListModal = new bootstrap.Modal(document.getElementById('templateListModal'));
    document.getElementById('templateListIframe').src = '/lumos/admin/template_list.php';
    templateListModal.show();
});
</script>

<?php else: ?>
<!-- 未ログインの場合は admin_header.php 内のフォームが表示される -->
<?php endif; ?>

<?php require_once __DIR__ . '/inc/admin_footer.php'; ?> 