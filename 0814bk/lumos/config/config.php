<?php
/**
 * LINE APIの設定ファイル
 * 
 * @package Lumos
 * @subpackage Config
 */

// LINE Messaging APIの設定
define('LINE_CHANNEL_ACCESS_TOKEN', 'Rs8igxvBvCUaI303qymNWbE4OQ+wHfctCwfT7EMzrY/mKSlwDqjMvqBSBXnWyWdyCwguFEtWdhKEWpn2i200pFGD3VqNf/oLkl1PMybtKY0+X+yfUTtxlb1Oicx0RKaMEcYaeMwQmMcnvEHzykeJXQdB04t89/1O/w1cDnyilFU=');
define('LINE_CHANNEL_SECRET', '08e238742291b39328af58c8546e89d94');

// データベース設定
define('DB_HOST', 'mysql320.phy.lolipop.lan');
define('DB_NAME', 'LAA1207717-fgsquare');
define('DB_USER', 'LAA1207717');
define('DB_PASS', 'fg12345');

// ログ設定
define('LOG_DIR', __DIR__ . '/../logs');
define('LINE_LOG_DIR', LOG_DIR . '/line');

// エラー設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo'); 