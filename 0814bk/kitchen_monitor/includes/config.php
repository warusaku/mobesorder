<?php
/**
 * Kitchen Monitor Configuration
 * 
 * Configuration file for the Mobes Kitchen Monitor system
 */

// Include main application config
require_once __DIR__ . '/../../api/config/config.php';

// Kitchen Monitor specific configuration
return [
    'environment' => 'production',
    'auto_refresh_interval' => 30000, // 30 seconds in milliseconds
    'audio_enabled' => true,
    'lumos_integration' => true,
    'kiosk_mode' => true,
    'allowed_ips' => [
        '192.168.1.100', // Kitchen tablet 1
        '192.168.1.101', // Kitchen tablet 2
        '127.0.0.1',     // Local development
        '::1'            // IPv6 localhost
    ],
    'database' => [
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'pass' => DB_PASS
    ],
    'kitchen_auth' => [
        'session_timeout' => 3600, // 1 hour
        'require_login' => false,   // Set to true if authentication is needed
        'admin_password' => 'kitchen2025' // Simple password for kitchen access
    ],
    'status_colors' => [
        'ordered' => '#3498db',     // Blue
        'ready' => '#f39c12',       // Orange
        'delivered' => '#27ae60',   // Green
        'cancelled' => '#e74c3c'    // Red
    ],
    'priority_colors' => [
        'normal' => '#3498db',      // Blue
        'warning' => '#f1c40f',     // Yellow
        'urgent' => '#e67e22'       // Dark orange
    ],
    'notification' => [
        'chime_volume' => 0.7,
        'show_notifications' => true,
        'sound_enabled' => true
    ],
    'ui' => [
        'cards_per_page' => 20,
        'auto_scroll' => true,
        'show_completed_toggle' => true,
        'tablet_mode' => true
    ],
    'discord' => [
        'webhook_enabled' => true,
        'webhook_url' => 'https://discord.com/api/webhooks/1403666576435183729/enkU5aE0QCKEmmVhxkPWEUm2tk5NDshxZHOFAQDqcLok3J-mgiRD3e31CJoLX43uvio5', // Replace with your actual webhook URL
        'send_on_status' => ['ready', 'delivered', 'cancelled'], // Which status changes to send
        'timeout' => 5 // Webhook request timeout in seconds
    ]
];