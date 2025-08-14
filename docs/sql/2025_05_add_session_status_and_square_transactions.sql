-- 2025-05-XX
-- order_sessions.session_status 追加 & square_transactions 新設

-- 1) order_sessions
ALTER TABLE `order_sessions`
  ADD COLUMN `session_status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active|Completed|Force_closed' AFTER `is_active`;

-- 2) square_transactions
CREATE TABLE `square_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `square_transaction_id` VARCHAR(64) NOT NULL,
  `square_order_id`      VARCHAR(64) NOT NULL,
  `location_id`          VARCHAR(32) NOT NULL,
  `amount`               BIGINT NOT NULL,
  `currency`             CHAR(3) NOT NULL DEFAULT 'JPY',
  `order_session_id`     VARCHAR(32) NULL,
  `room_number`          VARCHAR(16) NULL,
  `payload`              JSON NOT NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session` (`order_session_id`),
  KEY `idx_square_tx` (`square_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 