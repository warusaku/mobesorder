-- Kitchen Monitor Database Schema Modifications
-- Add status column and related tables for Mobes Kitchen Monitor

-- Add status column to order_details table
ALTER TABLE order_details ADD COLUMN status ENUM(
    'ordered',      -- 注文済み（初期値）
    'ready',        -- スタンバイ完了（調理完了・未配達）
    'delivered',    -- 配達済み（完了）
    'cancelled'     -- キャンセル
) NOT NULL DEFAULT 'ordered';

-- Add timestamp for status updates
ALTER TABLE order_details ADD COLUMN status_updated_at DATETIME NULL;
ALTER TABLE order_details ADD COLUMN status_updated_by VARCHAR(100) NULL;

-- Add indexes for performance
CREATE INDEX idx_order_details_status ON order_details(status);
CREATE INDEX idx_order_details_status_updated ON order_details(status_updated_at);
CREATE INDEX idx_order_details_status_active ON order_details(status, created_at) 
WHERE status IN ('ordered', 'ready');

-- Create order status history table
CREATE TABLE order_status_history (
    id INT NOT NULL AUTO_INCREMENT,
    order_detail_id INT NOT NULL,
    previous_status ENUM('ordered', 'ready', 'delivered', 'cancelled') NULL,
    new_status ENUM('ordered', 'ready', 'delivered', 'cancelled') NOT NULL,
    changed_by VARCHAR(100) NOT NULL DEFAULT 'kitchen_monitor',
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note TEXT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (order_detail_id) REFERENCES order_details(id) ON DELETE CASCADE,
    INDEX idx_order_detail_id (order_detail_id),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create trigger to automatically log status changes
DELIMITER $$
CREATE TRIGGER order_status_change_log 
    AFTER UPDATE ON order_details
    FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO order_status_history 
        (order_detail_id, previous_status, new_status, changed_by, note)
        VALUES 
        (NEW.id, OLD.status, NEW.status, NEW.status_updated_by, 
         CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$
DELIMITER ;

-- Create view for active orders (kitchen monitor main query)
CREATE VIEW kitchen_active_orders AS
SELECT 
    od.id as order_detail_id,
    od.order_id,
    od.order_session_id,
    od.product_name,
    od.quantity,
    od.status,
    od.created_at as order_datetime,
    od.status_updated_at,
    o.room_number,
    TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) as minutes_elapsed,
    CASE 
        WHEN TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) >= 30 THEN 'urgent'
        WHEN TIMESTAMPDIFF(MINUTE, od.created_at, NOW()) >= 20 THEN 'warning'
        ELSE 'normal'
    END as priority_level
FROM order_details od
JOIN orders o ON od.order_id = o.id
WHERE od.status IN ('ordered', 'ready')
    AND o.order_status = 'OPEN'
ORDER BY 
    FIELD(od.status, 'ordered', 'ready'),
    od.created_at ASC,
    od.id ASC;

-- Create view for daily statistics
CREATE VIEW kitchen_daily_stats AS
SELECT 
    COUNT(CASE WHEN status = 'ordered' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_orders,
    COUNT(CASE WHEN status = 'delivered' AND DATE(status_updated_at) = CURDATE() THEN 1 END) as delivered_today,
    COUNT(CASE WHEN status = 'cancelled' AND DATE(status_updated_at) = CURDATE() THEN 1 END) as cancelled_today,
    AVG(CASE 
        WHEN status = 'delivered' AND status_updated_at IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, created_at, status_updated_at) 
        END) as avg_completion_time
FROM order_details 
WHERE DATE(created_at) = CURDATE();