<?php
/**
 * Aurora Star 主题主入口。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AURORA_STAR_VERSION', '1.1.1' );
define( 'AURORA_STAR_DIR', get_template_directory() );
define( 'AURORA_STAR_URI', get_template_directory_uri() );

require_once AURORA_STAR_DIR . '/inc/setup.php';
require_once AURORA_STAR_DIR . '/inc/enqueue.php';
require_once AURORA_STAR_DIR . '/inc/customizer.php';
require_once AURORA_STAR_DIR . '/inc/shortcodes.php';
require_once AURORA_STAR_DIR . '/inc/toc.php';
require_once AURORA_STAR_DIR . '/inc/menu-walker.php';
require_once AURORA_STAR_DIR . '/inc/admin-menu.php';
