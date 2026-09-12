<?php
/**
 * Markdown 发布模块。
 *
 * 由独立插件 wp-markdown-publisher 1.0.1 集成而来，自 v2.0.0 起随主题分发。
 *
 * 目录结构：
 *   inc/markdown/class-*.php     解析器与各功能模块（命名空间 Mdp_*）
 *   inc/markdown/views/*.php     后台页面视图
 *   inc/markdown/languages/*     翻译模板
 *   inc/markdown/examples/*      示例文章
 *   assets/markdown/*            后台与前台静态资源
 *
 * 数据键名刻意沿用插件原来的写法，这样：
 *   1. 主题接管后，已有文章的 Markdown 原文、渲染状态、设置项全部无缝保留；
 *   2. 万一要回退到独立插件，两边读写的仍是同一份数据。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 独立插件仍在运行时让路。
 *
 * 插件先于主题加载，所以这里能检测到它。让路可以同时避免
 * 「类重复定义」与「同一份内容被渲染两次」两个问题。
 */
if ( defined( 'MDP_VERSION' ) || class_exists( 'Mdp_Plugin', false ) ) {
	add_action( 'admin_notices', 'aurora_star_markdown_plugin_conflict_notice' );
	return;
}

define( 'MDP_VERSION', '1.0.3' );
define( 'MDP_FILE', __FILE__ );
define( 'MDP_DIR', __DIR__ . '/' );
// 静态资源统一放在主题的 assets/markdown/ 下。
define( 'MDP_URL', AURORA_STAR_URI . '/assets/markdown/' );
// 仅用于拼 plugin_action_links_* 过滤器名；主题形态下该过滤器永远不会触发。
define( 'MDP_BASENAME', 'aurora-star/inc/markdown/bootstrap.php' );

// 数据键名：与独立插件保持一致，不可更改。
define( 'MDP_META_SOURCE', '_mdp_markdown' );
define( 'MDP_META_ENABLED', '_mdp_enabled' );
define( 'MDP_META_TOC', '_mdp_toc' );
define( 'MDP_META_RENDERED', '_mdp_rendered_at' );
define( 'MDP_META_PARSER', '_mdp_parser_version' );
define( 'MDP_OPTION', 'mdp_settings' );

require_once MDP_DIR . 'class-markdown.php';
require_once MDP_DIR . 'class-front-matter.php';
require_once MDP_DIR . 'class-plugin.php';
require_once MDP_DIR . 'class-post-editor.php';
require_once MDP_DIR . 'class-importer.php';
require_once MDP_DIR . 'class-shortcodes.php';
require_once MDP_DIR . 'class-frontend.php';
require_once MDP_DIR . 'class-rest.php';
require_once MDP_DIR . 'class-admin.php';

/*
 * 主题没有 register_activation_hook，用「切换主题」代替：
 * 首次启用本主题时补齐 mdp_settings 的默认值。
 */
add_action( 'after_switch_theme', array( 'Mdp_Plugin', 'on_activate' ) );

/*
 * 主题也没有 uninstall.php，用「切走主题」对应插件原来的卸载清理。
 * 与 uninstall.php 行为一致：只有勾选了「删除设置项」才真的删。
 */
add_action( 'switch_theme', 'aurora_star_markdown_cleanup_on_switch' );

Mdp_Plugin::instance();

/**
 * 改回插件时代的 `mdp()` 快捷函数，供已有自定义代码继续使用。
 *
 * 主题本不该定义无前缀的全局函数，这里是为了向后兼容而保留，
 * 并加了 function_exists 守卫：独立插件仍在用时以插件的定义为准。
 *
 * @return Mdp_Plugin
 */
if ( ! function_exists( 'mdp' ) ) {
	function mdp() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return Mdp_Plugin::instance();
	}
}

/**
 * 切换主题时的数据清理，对应插件原来的 uninstall.php。
 *
 * @return void
 */
function aurora_star_markdown_cleanup_on_switch() {
	$options = get_option( MDP_OPTION, array() );

	if ( is_array( $options ) && ! empty( $options['delete_data'] ) ) {
		delete_option( MDP_OPTION );
		delete_option( 'mdp_version' );
	}

	// 清理导入/渲染过程中可能留下的临时提示。
	delete_transient( 'mdp_notice_' . get_current_user_id() );
}

/**
 * 独立插件仍在运行时的后台提示。
 *
 * @return void
 */
function aurora_star_markdown_plugin_conflict_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>Aurora Star 主题</strong>：'
		. '检测到独立的 <code>Markdown 发布器</code> 插件仍在启用，主题内置的 Markdown 模块已让路，'
		. '当前由插件负责渲染。两者功能重复，建议停用插件（文章与设置不受影响）。'
		. '</p></div>';
}
