-- 通知チャネル管理テーブル
CREATE TABLE IF NOT EXISTS `notification_channels` (
  `channel_id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_name` varchar(100) NOT NULL COMMENT 'チャネル名',
  `channel_type` varchar(20) NOT NULL COMMENT 'チャネルタイプ（slack, line, discord, webhook）',
  `config` text NOT NULL COMMENT 'チャネル設定（JSON形式）',
  `trigger_types` text DEFAULT NULL COMMENT '通知トリガータイプ（JSON配列）',
  `notification_level` enum('ALL','ALERTS_ONLY','CRITICAL_ONLY') NOT NULL DEFAULT 'ALL' COMMENT '通知レベル',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'アクティブフラグ',
  `created_at` datetime NOT NULL COMMENT '作成日時',
  `updated_at` datetime NOT NULL COMMENT '更新日時',
  PRIMARY KEY (`channel_id`),
  KEY `idx_channel_type` (`channel_type`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知チャネル設定';

-- 通知ログテーブル
CREATE TABLE IF NOT EXISTS `notification_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL COMMENT '送信されたメッセージ',
  `determination_result_id` varchar(50) DEFAULT NULL COMMENT '関連する判定結果ID',
  `options` text DEFAULT NULL COMMENT '送信オプション（JSON形式）',
  `results` text DEFAULT NULL COMMENT '送信結果（JSON形式）',
  `created_at` datetime NOT NULL COMMENT '送信日時',
  PRIMARY KEY (`log_id`),
  KEY `idx_determination_result_id` (`determination_result_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知ログ';

-- テスト用サンプルデータ (Slackチャネル)
INSERT INTO `notification_channels` 
(`channel_name`, `channel_type`, `config`, `trigger_types`, `notification_level`, `active`, `created_at`, `updated_at`)
VALUES
('Slack通知', 'slack', 
 '{"webhook_url":"https://hooks.slack.com/services/XXXXX/XXXXX/XXXXX", "channel":"#alerts", "username":"RTSP-OCR-Bot", "icon_emoji":":camera:"}',
 '["ALL"]', 'ALL', 1, NOW(), NOW());

-- テスト用サンプルデータ (LINE通知)
INSERT INTO `notification_channels` 
(`channel_name`, `channel_type`, `config`, `trigger_types`, `notification_level`, `active`, `created_at`, `updated_at`)
VALUES
('LINE通知', 'line', 
 '{"access_token":"XXXXXXXXX", "to":"UXXXXXXX"}',
 '["ALERT", "ERROR"]', 'ALERTS_ONLY', 1, NOW(), NOW());

-- テスト用サンプルデータ (Discord通知)
INSERT INTO `notification_channels` 
(`channel_name`, `channel_type`, `config`, `trigger_types`, `notification_level`, `active`, `created_at`, `updated_at`)
VALUES
('Discord通知', 'discord', 
 '{"webhook_url":"https://discord.com/api/webhooks/XXXXX/XXXXX", "username":"RTSP-OCR監視", "avatar_url":"http://test-mijeos.but.jp/RTSP_reader/assets/images/logo.png"}',
 '["ERROR", "CRITICAL"]', 'CRITICAL_ONLY', 1, NOW(), NOW());

-- テスト用サンプルデータ (汎用Webhook)
INSERT INTO `notification_channels` 
(`channel_name`, `channel_type`, `config`, `trigger_types`, `notification_level`, `active`, `created_at`, `updated_at`)
VALUES
('社内システム連携', 'webhook', 
 '{"endpoint_url":"https://example.com/api/alerts", "method":"POST", "payload_format":"json", "auth_type":"api_key", "api_key_name":"X-API-KEY", "api_key_value":"abcdef123456", "api_key_in":"header", "timeout":30, "custom_fields":{"system_id":"RTSP-OCR-001", "department":"生産管理"}}',
 '["ALL"]', 'ALL', 1, NOW(), NOW()); 
 
 
 
 