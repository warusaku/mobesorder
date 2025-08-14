-- メッセージテーブル
CREATE TABLE IF NOT EXISTS messages (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_number   VARCHAR(20) NOT NULL,                         -- 部屋番号（表示・スレッド単位）
    user_id       VARCHAR(255) NOT NULL,                        -- 宿泊者ID（LINE ID等）
    sender_type   ENUM('guest', 'staff', 'system') NOT NULL,    -- 誰が送ったか
    platform      VARCHAR(20) DEFAULT 'LINE',                   -- LINE / WhatsApp / Messenger / etc.
    message_type  ENUM('text', 'image', 'template', 'rich') DEFAULT 'text',
    message       TEXT NOT NULL,                                -- テキスト or JSON（テンプレート等）
    status        ENUM('sent', 'delivered', 'read', 'error') DEFAULT 'sent',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_number (room_number),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 拡張メッセージテーブル（v2）
CREATE TABLE IF NOT EXISTS messages_v2 (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_number   VARCHAR(20) NOT NULL,                         -- 部屋番号（表示・スレッド単位）
    user_id       VARCHAR(255) NOT NULL,                        -- 宿泊者ID（LINE ID等）
    sender_type   ENUM('guest', 'staff', 'system') NOT NULL,    -- 誰が送ったか
    platform      VARCHAR(20) DEFAULT 'LINE',                   -- LINE / WhatsApp / Messenger / etc.
    message_type  ENUM('text', 'image', 'template', 'rich') DEFAULT 'text',
    message       TEXT NOT NULL,                                -- テキスト or JSON（テンプレート等）
    status        ENUM('sent', 'delivered', 'read', 'error') DEFAULT 'sent',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_number (room_number),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
