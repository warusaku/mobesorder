<?php
require_once 'api/lib/Database.php';
require_once 'api/lib/Utils.php';
require_once 'api/lib/RoomTicketService.php';

// RoomTicketServiceのインスタンスを作成
$roomTicketService = new RoomTicketService();

// 操作処理
$message = '';
$messageType = '';

// 新規保留伝票作成
if (isset($_POST['create_ticket'])) {
    $roomNumber = $_POST['room_number'] ?? '';
    $guestName = $_POST['guest_name'] ?? '';
    
    if (empty($roomNumber)) {
        $message = '部屋番号を入力してください';
        $messageType = 'error';
    } else {
        $result = $roomTicketService->createRoomTicket($roomNumber, $guestName);
        
        if ($result) {
            $message = "部屋 $roomNumber の保留伝票を作成しました";
            $messageType = 'success';
        } else {
            $message = "部屋 $roomNumber の保留伝票作成に失敗しました";
            $messageType = 'error';
        }
    }
}

// チェックアウト処理
if (isset($_POST['checkout'])) {
    $roomNumber = $_POST['checkout_room'] ?? '';
    
    if (empty($roomNumber)) {
        $message = 'チェックアウトする部屋番号を指定してください';
        $messageType = 'error';
    } else {
        $result = $roomTicketService->checkoutRoomTicket($roomNumber);
        
        if ($result) {
            $message = "部屋 $roomNumber のチェックアウト処理が完了しました";
            $messageType = 'success';
        } else {
            $message = "部屋 $roomNumber のチェックアウト処理に失敗しました";
            $messageType = 'error';
        }
    }
}

// アクティブな保留伝票を取得
$activeTickets = $roomTicketService->getAllActiveRoomTickets();
?>

<div class="card">
    <h2>保留伝票管理</h2>
    <p>各客室に対応する保留伝票（部屋タブ）の管理ができます。チェックアウト時に一括精算するための機能です。</p>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
        <div class="card">
            <h3>新規保留伝票作成</h3>
            <form method="post" action="">
                <div style="margin-bottom: 10px;">
                    <label for="room_number">部屋番号:</label>
                    <input type="text" id="room_number" name="room_number" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="margin-bottom: 10px;">
                    <label for="guest_name">ゲスト名（オプション）:</label>
                    <input type="text" id="guest_name" name="guest_name" style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <button type="submit" name="create_ticket">保留伝票を作成</button>
            </form>
        </div>
        
        <div class="card">
            <h3>チェックアウト処理</h3>
            <form method="post" action="">
                <div style="margin-bottom: 10px;">
                    <label for="checkout_room">部屋番号:</label>
                    <select id="checkout_room" name="checkout_room" required style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="">-- 部屋を選択 --</option>
                        <?php foreach ($activeTickets as $ticket): ?>
                            <option value="<?php echo htmlspecialchars($ticket['room_number']); ?>">
                                <?php echo htmlspecialchars($ticket['room_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="checkout" class="danger">チェックアウト処理</button>
            </form>
        </div>
    </div>
    
    <h3>アクティブな保留伝票一覧</h3>
    <?php if (empty($activeTickets)): ?>
        <p>アクティブな保留伝票はありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>部屋番号</th>
                    <th>Square伝票ID</th>
                    <th>ステータス</th>
                    <th>作成日時</th>
                    <th>金額</th>
                    <th>商品数</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeTickets as $ticket): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['room_number']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['square_order_id']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['created_at']); ?></td>
                        <td>
                            <?php 
                            echo isset($ticket['square_data']['total_amount']) 
                                ? number_format($ticket['square_data']['total_amount']) . '円' 
                                : '-';
                            ?>
                        </td>
                        <td>
                            <?php 
                            echo isset($ticket['square_data']['line_items']) 
                                ? count($ticket['square_data']['line_items']) 
                                : '0';
                            ?> 個
                        </td>
                        <td>
                            <form method="post" action="" style="display: inline;">
                                <input type="hidden" name="checkout_room" value="<?php echo htmlspecialchars($ticket['room_number']); ?>">
                                <button type="submit" name="checkout" class="danger" style="padding: 5px 10px; font-size: 12px;">
                                    チェックアウト
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h3>保留伝票の詳細</h3>
    <p>各保留伝票の詳細情報は以下から確認できます。拡大するには伝票をクリックしてください。</p>
    
    <div style="display: flex; flex-wrap: wrap; gap: 20px;">
        <?php foreach ($activeTickets as $ticket): ?>
            <div class="card" style="min-width: 300px; cursor: pointer;" onclick="toggleTicketDetails('ticket-<?php echo $ticket['id']; ?>')">
                <h4>部屋 <?php echo htmlspecialchars($ticket['room_number']); ?></h4>
                <p>作成日時: <?php echo htmlspecialchars($ticket['created_at']); ?></p>
                <p>
                    金額: 
                    <?php 
                    echo isset($ticket['square_data']['total_amount']) 
                        ? number_format($ticket['square_data']['total_amount']) . '円' 
                        : '-';
                    ?>
                </p>
                
                <div id="ticket-<?php echo $ticket['id']; ?>" style="display: none; margin-top: 10px;">
                    <h5>商品リスト</h5>
                    <?php if (empty($ticket['square_data']['line_items'])): ?>
                        <p>商品がありません。</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>商品名</th>
                                    <th>数量</th>
                                    <th>単価</th>
                                    <th>備考</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ticket['square_data']['line_items'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td><?php echo number_format($item['base_price_money']) . '円'; ?></td>
                                        <td><?php echo htmlspecialchars($item['note'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.alert {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 4px;
}
.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<script>
function toggleTicketDetails(id) {
    const element = document.getElementById(id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}
</script> 