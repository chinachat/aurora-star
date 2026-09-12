<?php
/**
 * Aurora Star 主题主入口。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AURORA_STAR_VERSION', '2.0.1' );
define( 'AURORA_STAR_DIR', get_template_directory() );
define( 'AURORA_STAR_URI', get_template_directory_uri() );

// 阅读数：meta key 沿用历史键名，避免升级后既有统计丢失。
define( 'AURORA_STAR_VIEW_META', 'aurora_star_views' );
define( 'AURORA_STAR_VIEW_COOKIE', 'aurora_star_views' );
// 去重 Cookie 中最多记住多少篇文章：体积恒定（约 300 字节），远低于 4096 字节上限。
define( 'AURORA_STAR_VIEW_COOKIE_MAX', 50 );

require_once AURORA_STAR_DIR . '/inc/setup.php';
require_once AURORA_STAR_DIR . '/inc/enqueue.php';
require_once AURORA_STAR_DIR . '/inc/customizer.php';
require_once AURORA_STAR_DIR . '/inc/shortcodes.php';
require_once AURORA_STAR_DIR . '/inc/toc.php';
require_once AURORA_STAR_DIR . '/inc/menu-walker.php';
require_once AURORA_STAR_DIR . '/inc/comments.php';
require_once AURORA_STAR_DIR . '/inc/geoip-admin.php';
require_once AURORA_STAR_DIR . '/inc/admin-menu.php';

// Markdown 发布模块（由 wp-markdown-publisher 插件集成而来）。
// 内部自带守卫：检测到独立插件仍在启用时整个模块让路。
require_once AURORA_STAR_DIR . '/inc/markdown/bootstrap.php';
