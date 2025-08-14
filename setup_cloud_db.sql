-- RTSP_Reader Lolipop側データベース設定

-- デバイスの測定値を保存するテーブル (クラウド側)
CREATE TABLE IF NOT EXISTS device_readings_cloud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    display_id VARCHAR(32) NOT NULL,
    value TEXT,
    converted_value TEXT,
    timestamp DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(16) DEFAULT 'edge',
    INDEX (lacis_id),
    INDEX (timestamp),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 月別アーカイブテーブル
CREATE TABLE IF NOT EXISTS device_readings_archive_cloud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    display_id VARCHAR(32) NOT NULL,
    value TEXT,
    converted_value TEXT,
    timestamp DATETIME,
    created_at TIMESTAMP,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archive_month VARCHAR(7),
    INDEX (lacis_id),
    INDEX (timestamp),
    INDEX (archive_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- アラート履歴を保存するテーブル
CREATE TABLE IF NOT EXISTS alert_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    display_id VARCHAR(32) NOT NULL,
    value TEXT,
    detected_at DATETIME,
    alert_sent TINYINT(1) DEFAULT 0,
    webhook_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (lacis_id),
    INDEX (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- アラート設定テーブル（UI設定用）
CREATE TABLE IF NOT EXISTS alert_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    target_string VARCHAR(64) NOT NULL,
    match_type ENUM('exact', 'contains') NOT NULL DEFAULT 'contains',
    alert_category ENUM('INFO', 'WARN', 'ERROR') NOT NULL DEFAULT 'WARN',
    notification_text TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (lacis_id),
    INDEX (target_string),
    INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- デバイス設定の保存テーブル
CREATE TABLE IF NOT EXISTS device_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    config_json MEDIUMTEXT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (lacis_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 設定の同期履歴テーブル
CREATE TABLE IF NOT EXISTS config_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    source VARCHAR(16) NOT NULL COMMENT 'local or remote',
    action VARCHAR(16) NOT NULL COMMENT 'update, create, merge',
    config_json MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (lacis_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 同期ステータステーブル
CREATE TABLE IF NOT EXISTS sync_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    last_heartbeat DATETIME,
    last_sync DATETIME,
    online_status TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45),
    fail_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (lacis_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 設定変更通知フラグテーブル (Pub/Sub)
CREATE TABLE IF NOT EXISTS config_change_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lacis_id VARCHAR(32) NOT NULL,
    change_type ENUM('device_config', 'alert_settings', 'other') NOT NULL,
    is_delivered TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    INDEX (lacis_id),
    INDEX (is_delivered),
    INDEX (change_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RTSPカメラスキャン進捗テーブル
CREATE TABLE IF NOT EXISTS `rtspcam_scan_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scan_id` int(11) NOT NULL,
  `total_ips` int(11) NOT NULL DEFAULT 0,
  `scanned_ips` int(11) NOT NULL DEFAULT 0,
  `detected_cameras` int(11) NOT NULL DEFAULT 0, 
  `elapsed_seconds` float NOT NULL DEFAULT 0,
  `estimated_remaining_time` varchar(20) NOT NULL DEFAULT '',
  `scan_speed` float NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scan_id` (`scan_id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 