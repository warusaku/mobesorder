<?php
/**
 * 注文編集サービス
 * バージョン: 1.0.0
 * ファイル説明: 注文詳細（order_details）および注文ヘッダ（orders）の編集・削除を担当するクラス
 */

require_once __DIR__ . '/Database.php';

class OrderService_Edit {
    private $db;
    private static $logFile = null;
    private static $maxLogSize = 307200; // 300KB

    public function __construct() {
        $this->db = Database::getInstance();
        self::initLogFile();
    }

    /* === パブリックAPI ============================= */

    /**
     * 注文詳細の数量変更 / 削除を行い、orders テーブルの合計金額を更新します。
     *
     * @param int   $orderId 対象注文ID
     * @param array $items   [ [detail_id=>int, quantity=>int, delete=>bool] ]
     * @return array [success=>bool, new_total=>float, removed=>bool, message=>string]
     */
    public function editOrder(int $orderId, array $items): array {
        self::log("editOrder called for order_id={$orderId} items=" . json_encode($items));

        if ($orderId <= 0) {
            return $this->fail("Invalid order_id");
        }
        if (empty($items)) {
            return $this->fail("items is empty");
        }

        // トランザクション開始
        if (!$this->db->beginTransaction()) {
            return $this->fail("トランザクション開始に失敗");
        }

        try {
            foreach ($items as $item) {
                $detailId  = (int)($item['detail_id'] ?? 0);
                $quantity  = isset($item['quantity']) ? (int)$item['quantity'] : null;
                $deleteFlg = isset($item['delete']) && filter_var($item['delete'], FILTER_VALIDATE_BOOLEAN);

                if ($detailId <= 0) {
                    self::log("skip invalid detail_id: " . json_encode($item), 'WARNING');
                    continue;
                }

                if ($deleteFlg) {
                    // 削除
                    $this->db->execute("DELETE FROM order_details WHERE id = ? AND order_id = ?", [$detailId, $orderId]);
                    self::log("detail {$detailId} deleted");
                } else {
                    // 数量更新
                    if ($quantity === null || $quantity < 0) {
                        self::log("skip invalid quantity for detail {$detailId}", 'WARNING');
                        continue;
                    }
                    // unit_price を取得
                    $row = $this->db->selectOne("SELECT unit_price FROM order_details WHERE id = ? AND order_id = ?", [$detailId, $orderId]);
                    if (!$row) {
                        self::log("detail not found: {$detailId}", 'WARNING');
                        continue;
                    }
                    $unitPrice = (float)$row['unit_price'];
                    $subtotal  = $unitPrice * $quantity;
                    $this->db->execute("UPDATE order_details SET quantity = ?, subtotal = ? WHERE id = ? AND order_id = ?", [$quantity, $subtotal, $detailId, $orderId]);
                    self::log("detail {$detailId} updated qty={$quantity} subtotal={$subtotal}");
                }
            }

            // 明細が残っているか確認
            $row = $this->db->selectOne("SELECT SUM(subtotal) AS total, COUNT(*) AS cnt FROM order_details WHERE order_id = ?", [$orderId]);
            $totalAmount = (float)($row['total'] ?? 0);
            $detailCnt   = (int)($row['cnt'] ?? 0);

            if ($detailCnt === 0 || $totalAmount <= 0) {
                // 先に明細を削除
                $this->db->execute("DELETE FROM order_details WHERE order_id = ?", [$orderId]);
                // 注文ヘッダを削除
                $this->db->execute("DELETE FROM orders WHERE id = ?", [$orderId]);
                self::log("order {$orderId} removed because total 0 or no details");
                $this->db->commit();
                return [ 'success' => true, 'new_total' => 0, 'removed' => true ];
            }

            // orders テーブルを更新
            $this->db->execute("UPDATE orders SET total_amount = ?, updated_at = NOW() WHERE id = ?", [$totalAmount, $orderId]);

            $this->db->commit();
            self::log("order {$orderId} updated new_total={$totalAmount}");
            return [ 'success' => true, 'new_total' => $totalAmount, 'removed' => false ];
        } catch (Exception $e) {
            $this->db->rollback();
            self::log("editOrder error: " . $e->getMessage(), 'ERROR');
            return $this->fail($e->getMessage());
        }
    }

    /* === 内部ユーティリティ ========================= */

    private function fail(string $msg): array {
        self::log($msg, 'ERROR');
        return [ 'success' => false, 'message' => $msg ];
    }

    private static function initLogFile() {
        if (self::$logFile !== null) return;
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        self::$logFile = $logDir . '/OrderService_Edit.log';
        self::checkLogRotation();
    }

    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) return;
        if (filesize(self::$logFile) > self::$maxLogSize) {
            $keep = intval(self::$maxLogSize * 0.2);
            $content = file_get_contents(self::$logFile);
            $content = substr($content, -$keep);
            file_put_contents(self::$logFile, $content);
        }
    }

    private static function log($msg, $level = 'INFO') {
        self::initLogFile();
        $ts = date('Y-m-d H:i:s');
        $line = "[$ts] [$level] $msg\n";
        file_put_contents(self::$logFile, $line, FILE_APPEND);
    }
} 