<?php
/**
 * LUMOS専用 LINE APIの設定ファイル
 * 
 * @package Lumos
 * @subpackage Config
 */

define('LUMOS_LINE_CHANNEL_ACCESS_TOKEN', 'Rs8igxvBvCUaI303qymNWbE4OQ+wHfctCwfT7EMzrY/mKSlwDqjMvqBSBXnWyWdyCwguFEtWdhKEWpn2i200pFGD3VqNf/oLkl1PMybtKY0+X+yfUTtxlb1Oicx0RKaMEcYaeMwQmMcnvEHzykeJXQdB04t89/1O/w1cDnyilFU=');
define('LUMOS_LINE_CHANNEL_SECRET', '8e238742291b39328af58c8546e89d94');

define('LUMOS_DB_HOST', 'mysql320.phy.lolipop.lan');
define('LUMOS_DB_NAME', 'LAA1207717-fgsquare');
define('LUMOS_DB_USER', 'LAA1207717');
define('LUMOS_DB_PASS', 'fg12345');

define('LUMOS_LOG_DIR', __DIR__ . '/../logs');
define('LUMOS_LINE_LOG_DIR', LUMOS_LOG_DIR . '/line');

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Tokyo'); 