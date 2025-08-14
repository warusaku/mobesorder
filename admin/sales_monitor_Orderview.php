<?php
/**
 * 注文表示クラス
 * バージョン: 1.0.1
 * ファイル説明: sales_monitor.phpの注文表示部分を分離したクラス
 * 更新履歴: 2025-05-30 メモ欄に背景色ハイライトを追加
 */

class SalesMonitorOrderView {
    
    /**
     * 統計カードを表示
     */
    public static function renderStatistics($salesData) {
        ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value" id="order-count"><?php echo $salesData['order_count']; ?></div>
                    <div class="stat-label">有効注文数</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value" id="total-amount"><?php echo self::formatJPY($salesData['total_amount']); ?></div>
                    <div class="stat-label">売上合計</div>
                    <small class="text-muted">アクティブなセッションの合計</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value" id="active-rooms"><?php echo $salesData['active_rooms']; ?></div>
                    <div class="stat-label">アクティブな部屋数</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * アクティブな部屋情報を表示
     */
    public static function renderActiveRooms($salesData) {
        ?>
        <h2 class="section-title mb-4">アクティブな部屋情報</h2>
        <div class="card mb-4">
            <div class="card-body">
                <?php if (empty($salesData['room_tickets'])): ?>
                <div class="text-center">
                    <p class="mb-0">アクティブな部屋データを取得できませんでした</p>
                    <small class="text-muted">部屋情報取得はスキップされました。後ほど再度お試しください。</small>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm room-info-table">
                        <thead>
                            <tr>
                                <th>部屋番号</th>
                                <th>部屋名</th>
                                <th>利用者名</th>
                                <th>LINE ID</th>
                                <th>チェックイン</th>
                                <th>チェックアウト</th>
                                <th>アクション</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $roomTickets = $salesData['room_tickets'] ?? []; ?>
                            <?php if (empty($roomTickets)): ?>
                            <tr>
                                <td colspan="7" class="text-center">アクティブな部屋がありません</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($roomTickets as $ticket): ?>
                                <?php
                                    $roomId      = $ticket['id'] ?? '';
                                    $roomNumber  = $ticket['room_number'] ?? '不明';
                                    $description = $ticket['description'] ?? '不明';
                                    $userName    = $ticket['user_name'] ?? '不明';
                                    $lineUserId  = $ticket['line_user_id'] ?? '';
                                    $checkInDate = $ticket['check_in_date'] ?? '';
                                    $checkOutDate= $ticket['check_out_date'] ?? '';
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($roomNumber); ?></td>
                                    <td><?php echo htmlspecialchars($description); ?></td>
                                    <td><?php echo htmlspecialchars($userName); ?></td>
                                    <td title="<?php echo htmlspecialchars($lineUserId); ?>">
                                        <?php echo !empty($lineUserId) ? substr(htmlspecialchars($lineUserId), 0, 10) . '...' : '未連携'; ?>
                                    </td>
                                    <td><?php echo self::formatDateTime($checkInDate); ?></td>
                                    <td><?php echo self::formatDateTime($checkOutDate); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger deactivate-user" 
                                                data-id="<?php echo htmlspecialchars($roomId); ?>" 
                                                data-room="<?php echo htmlspecialchars($roomNumber); ?>"
                                                data-user="<?php echo htmlspecialchars($userName); ?>">
                                            <i class="bi bi-person-x"></i> 強制削除
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 部屋ごとの注文を表示
     */
    public static function renderRoomOrders($salesData) {
        ?>
        <h2 class="section-title mb-4">部屋ごとの注文情報</h2>

        <?php if (empty($salesData['room_orders'])): ?>
        <div class="card">
            <div class="card-body text-center">注文データがありません</div>
        </div>
        <?php else: ?>
            <?php $roomOrders = $salesData['room_orders'] ?? []; $roomTotals = $salesData['room_totals'] ?? []; ?>
            <?php foreach ($roomOrders as $roomNumber => $roomOrderList): ?>
                <?php if (empty($roomOrderList)) continue; ?>
                <?php
                    $roomTotal   = $roomTotals[$roomNumber] ?? 0;
                    $sessionId   = $salesData['room_session_ids'][$roomNumber] ?? '';
                    $orderCount  = count($roomOrderList);
                    $firstOrder  = end($roomOrderList);
                    $openTime    = isset($firstOrder['order_datetime']) ? date('H:i', strtotime($firstOrder['order_datetime'])) : '--:--';
                ?>
                <div class="card mb-4 room-card">
                    <div class="card-header d-flex justify-content-between align-items-center room-header">
                        <h3 class="m-0 room-title">部屋番号: <?php echo htmlspecialchars($roomNumber); ?></h3>
                        <span class="badge bg-info text-dark ms-2 session-id-badge">
                            <?php echo $sessionId ? htmlspecialchars($sessionId) : 'NO-SESSION'; ?>
                        </span>
                        <div class="ms-auto d-flex align-items-center">
                            <button class="btn btn-sm btn-success me-2 add-order-btn" 
                                data-room="<?php echo htmlspecialchars($roomNumber); ?>"
                                data-session-id="<?php echo htmlspecialchars($sessionId); ?>"
                                title="オーダーを手動追加">
                                <i class="fa-solid fa-plus"></i> オーダーを手動追加
                            </button>
                            <?php if ($sessionId): ?>
                            <button class="btn btn-sm btn-danger ms-2 close-session-btn"
                                data-session-id="<?php echo htmlspecialchars($sessionId); ?>"
                                data-room="<?php echo htmlspecialchars($roomNumber); ?>"
                                data-open-time="<?php echo htmlspecialchars($openTime); ?>"
                                data-order-count="<?php echo $orderCount; ?>"
                                data-room-total="<?php echo $roomTotal; ?>">
                                注文をクローズする
                            </button>
                            <?php endif; ?>
                            <span class="badge bg-light text-dark ms-2">オープン <?php echo htmlspecialchars($openTime); ?></span>
                            <span class="badge bg-secondary ms-1">注文 <?php echo $orderCount; ?> 件</span>
                            <span class="fw-bold room-total ms-2"><?php echo self::formatJPY($roomTotal); ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php self::renderOrderTable($roomNumber, $roomOrderList, $salesData); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
    }
    
    /**
     * 注文テーブルを表示
     */
    private static function renderOrderTable($roomNumber, $roomOrderList, $salesData) {
        ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0 order-table">
                <thead>
                    <tr class="table-secondary">
                        <th style="width: 80px">注文ID</th>
                        <th style="width: 120px">ゲスト名</th>
                        <th style="width: 100px">金額</th>
                        <th style="width: 150px">注文日時</th>
                        <th>メモ</th>
                        <th class="text-center">編集</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roomOrderList as $order): ?>
                        <?php
                            $orderId      = $order['id'] ?? '0';
                            // 部屋リンク情報から user_name を取得（fallback で guest_name）
                            $guestName = '匿名';
                            if (isset($salesData['room_tickets'])) {
                                foreach ($salesData['room_tickets'] as $rt) {
                                    if (($rt['room_number'] ?? '') === $roomNumber) {
                                        $guestName = $rt['user_name'] ?? '匿名';
                                        break;
                                    }
                                }
                            }
                            if ($guestName === '匿名') {
                                $guestName = $order['guest_name'] ?? '匿名';
                            }
                            $orderAmount  = $order['total_amount'] ?? 0;
                            $orderDatetime= $order['order_datetime'] ?? '';
                            $orderNote    = $order['memo'] ?? '';
                            $memoStyle    = !empty($orderNote) ? ' style="background-color:#FFE500;"' : '';
                            $hasDetails   = isset($salesData['order_details'][$orderId]) && !empty($salesData['order_details'][$orderId]);
                            // 直近5分以内の注文をハイライト
                            $isRecent     = false;
                            if (!empty($orderDatetime)) {
                                $isRecent = (time() - strtotime($orderDatetime)) <= 300; // 5分=300秒
                            }
                        ?>
                        <tr class="order-row <?php echo $isRecent ? 'new-order' : ''; ?>" data-order-id="<?php echo htmlspecialchars($orderId); ?>">
                            <td class="fw-bold"><?php echo htmlspecialchars($orderId); ?></td>
                            <td><?php echo htmlspecialchars($guestName); ?></td>
                            <td class="text-end fw-bold"><?php echo self::formatJPY($orderAmount); ?></td>
                            <td><?php echo self::formatDateTime($orderDatetime); ?></td>
                            <td<?php echo $memoStyle; ?>><?php echo !empty($orderNote) ? htmlspecialchars(substr($orderNote, 0, 30)) . (strlen($orderNote) > 30 ? '...' : '') : ''; ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-order-btn" title="編集" data-order-id="<?php echo htmlspecialchars($orderId); ?>">
                                    <i class="fa-solid fa-pen fa-sm"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-success save-order-btn d-none" title="保存" data-order-id="<?php echo htmlspecialchars($orderId); ?>">
                                    <i class="fa-solid fa-floppy-disk fa-sm"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary cancel-edit-btn d-none ms-1" title="キャンセル" data-order-id="<?php echo htmlspecialchars($orderId); ?>">
                                    <i class="fa-solid fa-xmark fa-sm"></i>
                                </button>
                            </td>
                        </tr>
                        <?php if ($hasDetails): ?>
                        <tr class="order-details-row" id="details-<?php echo htmlspecialchars($orderId); ?>">
                            <td colspan="6" class="p-0 border-0">
                                <?php self::renderOrderDetails($orderId, $salesData['order_details'][$orderId], $orderAmount); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * 注文詳細を表示
     */
    private static function renderOrderDetails($orderId, $details, $orderAmount) {
        ?>
        <div class="order-details-content">
            <div class="details-table-wrapper">
                <table class="table table-sm table-striped mb-0 details-table">
                    <thead class="table-light">
                        <tr>
                            <th>商品ID</th>
                            <th>商品名</th>
                            <th class="text-center">数量</th>
                            <th class="text-end">単価</th>
                            <th class="text-end">小計</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $detail): ?>
                        <tr data-detail-id="<?php echo htmlspecialchars($detail['id'] ?? 0); ?>" class="order-detail-row">
                            <td class="small text-muted"><?php echo htmlspecialchars($detail['square_item_id'] ?? '不明'); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($detail['product_name'] ?? '不明な商品'); ?></td>
                            <td class="text-center qty-cell">
                                <button type="button" class="btn btn-sm btn-outline-secondary qty-minus d-none"><i class="fa-solid fa-minus fa-sm"></i></button>
                                <span class="qty-value mx-1"><?php echo htmlspecialchars($detail['quantity'] ?? 0); ?></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary qty-plus d-none"><i class="fa-solid fa-plus fa-sm"></i></button>
                            </td>
                            <td class="text-end"><?php echo self::formatJPY($detail['unit_price'] ?? 0); ?></td>
                            <td class="text-end fw-bold subtotal-cell"><?php echo self::formatJPY($detail['subtotal'] ?? ($detail['unit_price'] * $detail['quantity'])); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger delete-detail-btn d-none" title="削除"><i class="fa-solid fa-trash fa-sm"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-secondary">
                            <td colspan="3" class="text-muted small">商品数: <?php echo count($details); ?></td>
                            <td colspan="2" class="text-end fw-bold">合計:</td>
                            <td class="text-end fw-bold"><?php echo self::formatJPY($orderAmount); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * システム情報を表示
     */
    public static function renderSystemInfo() {
        ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">システム情報</div>
                    <div class="card-body">
                        <div class="mb-3 text-end">
                            <a href="#" id="testSessionBtn" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-beaker"></i> テストセッション実行
                            </a>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>最終データ更新:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>PHPバージョン:</strong> <?php echo phpversion(); ?></p>
                                <p><strong>サーバー時間:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="refresh-settings">
                                    <label for="refreshInterval" class="form-label"><strong>自動更新間隔:</strong></label>
                                    <select id="refreshInterval" class="form-select form-select-sm d-inline-block w-auto">
                                        <option value="30000">30秒</option>
                                        <option value="60000" selected>1分</option>
                                        <option value="180000">3分</option>
                                        <option value="300000">5分</option>
                                        <option value="0">無効</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * モーダルを表示
     */
    public static function renderModals() {
        ?>
        <!-- 通知エリア -->
        <div id="notification" class="notification" style="display: none;">
            <span id="notification-message"></span>
        </div>

        <!-- 手動注文追加モーダル -->
        <div class="modal fade" id="manualOrderModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">オーダーを手動追加</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>部屋番号:</strong> <span id="manualOrderRoom">--</span>
                            <input type="hidden" id="manualOrderRoomInput" value="">
                            <input type="hidden" id="manualOrderSessionId" value="">
                        </div>
                        
                        <!-- 商品追加セクション -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">商品追加</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">カテゴリ</label>
                                        <select id="categorySelect" class="form-select">
                                            <option value="">カテゴリを選択</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">商品</label>
                                        <select id="productSelect" class="form-select" disabled>
                                            <option value="">商品を選択</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">数量</label>
                                        <input type="number" id="productQuantity" class="form-control" value="1" min="1">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-primary" id="addProductBtn" disabled>
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 金額修正商品セクション -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">金額修正商品</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">商品名（自由入力）</label>
                                        <input type="text" id="customProductName" class="form-control" placeholder="例: 大盛り追加、割引">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">金額（マイナス値可）</label>
                                        <input type="number" id="customProductPrice" class="form-control" placeholder="例: 300, -500">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-warning" id="addCustomProductBtn">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 追加予定の商品リスト -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">追加する商品</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>商品名</th>
                                                <th class="text-center">数量</th>
                                                <th class="text-end">単価</th>
                                                <th class="text-end">小計</th>
                                                <th class="text-center">削除</th>
                                            </tr>
                                        </thead>
                                        <tbody id="manualOrderItems">
                                            <tr class="no-items">
                                                <td colspan="5" class="text-center text-muted">商品が追加されていません</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">合計:</td>
                                                <td class="text-end fw-bold" id="manualOrderTotal">¥0</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">メモ（任意）</label>
                            <textarea id="manualOrderMemo" class="form-control" rows="2" placeholder="注文に関するメモ"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" id="submitManualOrderBtn">
                            <i class="fa-solid fa-check"></i> 注文を追加
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- セッションクローズ確認モーダル -->
        <div class="modal fade" id="closeSessionModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">セッションクローズ確認</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>以下のセッションをクローズしますか？</p>
                        <dl class="row">
                            <dt class="col-sm-4">部屋番号:</dt>
                            <dd class="col-sm-8" id="csRoom">--</dd>
                            <dt class="col-sm-4">セッションID:</dt>
                            <dd class="col-sm-8"><code id="csSessionId">--</code></dd>
                            <dt class="col-sm-4">オープン時刻:</dt>
                            <dd class="col-sm-8" id="csOpenTime">--</dd>
                            <dt class="col-sm-4">注文数:</dt>
                            <dd class="col-sm-8" id="csOrderCount">--</dd>
                            <dt class="col-sm-4">合計金額:</dt>
                            <dd class="col-sm-8 fw-bold" id="csTotal">--</dd>
                        </dl>
                        
                        <!-- 注文明細一覧 -->
                        <hr>
                        <h6>注文明細（レシート）</h6>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>商品名</th>
                                        <th class="text-center">数量</th>
                                        <th class="text-end">単価</th>
                                        <th class="text-end">小計</th>
                                    </tr>
                                </thead>
                                <tbody id="csOrderDetails">
                                    <!-- 動的に挿入 -->
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">合計:</td>
                                        <td class="text-end fw-bold" id="csDetailTotal">--</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle"></i> この操作は取り消すことができません。
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-warning" id="pendingCloseBtn">
                            <i class="bi bi-cash-stack"></i> 未会計クローズ
                        </button>
                        <button type="button" class="btn btn-danger" id="forceCloseBtn">
                            <i class="bi bi-x-circle"></i> 強制クローズ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- テストセッションモーダル -->
        <div class="modal fade" id="testSessionModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">テストセッション実行</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> テスト用の注文セッションを作成し、自動的にクローズまで実行します。
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="forceCloseChk">
                            <label class="form-check-label" for="forceCloseChk">
                                既存のアクティブセッションを強制クローズする
                            </label>
                        </div>
                        <div id="testSessionLoading" class="text-center py-3">
                            <span class="spinner-border spinner-border-sm me-2"></span>
                            実行中...
                        </div>
                        <pre id="testSessionResult" class="d-none"></pre>
                        <h6 class="mt-3">実行ステップ:</h6>
                        <div class="table-responsive" id="testSessionSteps">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Step</th>
                                        <th>Module</th>
                                        <th>Message</th>
                                        <th>Args</th>
                                        <th>Return</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <h6 class="mt-3">実行履歴:</h6>
                        <ul id="testSessionHistory" class="list-group"></ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // ヘルパーメソッド
    private static function formatJPY($amount) {
        return '¥' . number_format((float)$amount);
    }
    
    private static function formatDateTime($dateTime) {
        if (empty($dateTime)) {
            return '';
        }
        try {
            $dt = new DateTime($dateTime);
            return $dt->format('Y-m-d H:i');
        } catch (Exception $e) {
            return $dateTime;
        }
    }
}