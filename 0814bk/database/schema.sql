-- LacisMobileOrder データベーススキーマ

-- 商品テーブル
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    square_item_id VARCHAR(255) UNIQUE NOT NULL COMMENT 'Squareの商品ID',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL COMMENT '税抜価格 - 円単位',
    image_url VARCHAR(1024) COMMENT 'Square画像ID or キャッシュURL',
    stock_quantity INT DEFAULT 0 COMMENT 'Squareから同期した在庫数',
    local_stock_quantity INT DEFAULT 0 COMMENT '未使用 or 予約在庫等に利用可',
    category VARCHAR(255) COMMENT 'SquareカテゴリID or カテゴリ名',
    is_active BOOLEAN DEFAULT TRUE COMMENT '表示フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (square_item_id),
    INDEX (category),
    INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 注文テーブル
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    square_order_id VARCHAR(255) UNIQUE COMMENT 'Square注文ID',
    room_number VARCHAR(20) NOT NULL,
    guest_name VARCHAR(255),
    order_status ENUM('OPEN', 'COMPLETED', 'CANCELED') DEFAULT 'OPEN' NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0 COMMENT '税抜合計金額',
    note TEXT COMMENT 'ゲストからの備考',
    order_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    checkout_datetime DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (square_order_id),
    INDEX (room_number),
    INDEX (order_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 注文詳細テーブル
CREATE TABLE IF NOT EXISTS order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    square_item_id VARCHAR(255) NOT NULL,
    product_name VARCHAR(255) COMMENT '注文時点の商品名',
    unit_price DECIMAL(10,2) COMMENT '注文時点の単価',
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) COMMENT '単価 * 数量',
    note TEXT COMMENT '商品ごとの備考',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (order_id),
    INDEX (square_item_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 部屋トークンテーブル
CREATE TABLE IF NOT EXISTS room_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    access_token VARCHAR(64) UNIQUE NOT NULL COMMENT '認証用トークン',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'トークン有効フラグ',
    guest_name VARCHAR(255),
    check_in_date DATE,
    check_out_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (access_token),
    INDEX (room_number),
    INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- システム設定テーブル
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(255) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- LINE連携テーブル
CREATE TABLE IF NOT EXISTS line_room_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id VARCHAR(255) UNIQUE NOT NULL,
    room_number VARCHAR(20),
    access_token VARCHAR(64),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (line_user_id),
    INDEX (room_number),
    INDEX (access_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ログテーブル
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR') NOT NULL,
    log_source VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    context TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (log_level),
    INDEX (log_source),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 