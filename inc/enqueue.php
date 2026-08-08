<?php
/**
 * 资源加载：样式与脚本。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 前端样式。
 */
function aurora_star_enqueue_styles() {
	// Font Awesome 7（自托管）。
	wp_enqueue_style(
		'aurora-star-fa',
		AURORA_STAR_URI . '/assets/icons/fontawesome/fontawesome.min.css',
		array(),
		AURORA_STAR_VERSION
	);

	// 主题主样式。
	wp_enqueue_style(
		'aurora-star-main',
		get_stylesheet_uri(),
		array( 'aurora-star-fa' ),
		AURORA_STAR_VERSION
	);

	wp_enqueue_style(
		'aurora-star-components',
		AURORA_STAR_URI . '/assets/css/main.css',
		array( 'aurora-star-main' ),
		AURORA_STAR_VERSION
	);

	// 暗黑样式始终加载（JS 按需切换）。
	wp_enqueue_style(
		'aurora-star-dark',
		AURORA_STAR_URI . '/assets/css/dark.css',
		array( 'aurora-star-components' ),
		AURORA_STAR_VERSION
	);

	// 评论样式。
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'aurora_star_enqueue_styles' );

/**
 * 前端脚本。
 */
function aurora_star_enqueue_scripts() {
	// 主脚本（暗黑切换 + 灯箱 + 通用交互）。
	wp_enqueue_script(
		'aurora-star-dark-mode',
		AURORA_STAR_URI . '/assets/js/dark-mode.js',
		array(),
		AURORA_STAR_VERSION,
		true
	);

	wp_enqueue_script(
		'aurora-star-main',
		AURORA_STAR_URI . '/assets/js/main.js',
		array( 'aurora-star-dark-mode' ),
		AURORA_STAR_VERSION,
		true
	);

	wp_localize_script(
		'aurora-star-main',
		'auroraData',
		array(
			'darkDefault'   => get_theme_mod( 'aurora_star_dark_default', 'system' ),
			'darkByTime'    => (bool) get_theme_mod( 'aurora_star_dark_default_for_late', true ),
		)
	);

	// 单篇文章：目录 + 灯箱 + 代码高亮。
	if ( is_singular() ) {
		if ( aurora_star_should_show_toc() ) {
			wp_enqueue_style(
				'aurora-star-toc',
				AURORA_STAR_URI . '/assets/css/toc.css',
				array( 'aurora-star-components' ),
				AURORA_STAR_VERSION
			);

			wp_enqueue_script(
				'aurora-star-toc',
				AURORA_STAR_URI . '/assets/js/toc.js',
				array( 'aurora-star-main' ),
				AURORA_STAR_VERSION,
				true
			);
		}

		wp_enqueue_style(
			'aurora-star-lightbox',
			AURORA_STAR_URI . '/assets/css/lightbox.css',
			array( 'aurora-star-components' ),
			AURORA_STAR_VERSION
		);

		wp_enqueue_script(
			'aurora-star-lightbox',
			AURORA_STAR_URI . '/assets/js/lightbox.js',
			array( 'aurora-star-main' ),
			AURORA_STAR_VERSION,
			true
		);

		aurora_star_enqueue_highlight();
	}
}
add_action( 'wp_enqueue_scripts', 'aurora_star_enqueue_scripts' );

/**
 * 代码高亮资源（Prism.js 自托管）。
 */
function aurora_star_enqueue_highlight() {
	if ( ! get_theme_mod( 'aurora_star_highlight', true ) ) {
		return;
	}

	$prism = AURORA_STAR_URI . '/assets/vendor/prism';

	// 语言组件及其依赖（Prism 官方依赖关系）。
	$langs = array(
		'markup'             => array(),
		'markup-templating'  => array( 'aurora-star-prism-markup' ),
		'css'                => array(),
		'css-extras'         => array( 'aurora-star-prism-css' ),
		'clike'              => array(),
		'javascript'         => array( 'aurora-star-prism-clike' ),
		'typescript'         => array( 'aurora-star-prism-javascript' ),
		'jsx'                => array( 'aurora-star-prism-markup', 'aurora-star-prism-javascript' ),
		'tsx'                => array( 'aurora-star-prism-jsx', 'aurora-star-prism-typescript' ),
		'php'                => array( 'aurora-star-prism-markup-templating' ),
		'python'             => array(),
		'java'               => array( 'aurora-star-prism-clike' ),
		'bash'               => array(),
		'json'               => array(),
		'sql'                => array(),
		'markdown'           => array( 'aurora-star-prism-markup' ),
		'yaml'               => array(),
		'go'                 => array( 'aurora-star-prism-clike' ),
		'rust'               => array(),
		'c'                  => array( 'aurora-star-prism-clike' ),
		'cpp'                => array( 'aurora-star-prism-c' ),
		'csharp'             => array( 'aurora-star-prism-clike' ),
		'ruby'               => array( 'aurora-star-prism-clike' ),
		'swift'              => array(),
		'kotlin'             => array( 'aurora-star-prism-clike' ),
		'docker'             => array(),
		'git'                => array(),
		'http'               => array(),
		'nginx'              => array(),
		'powershell'         => array(),
		'scss'               => array( 'aurora-star-prism-css' ),
		'diff'               => array(),
		'ini'                => array(),
		'properties'         => array(),
		'toml'               => array(),
		'vim'                => array(),
		'makefile'           => array(),
	);

	// 核心 → 按依赖顺序加载。
	$deps = array();
	wp_enqueue_script( 'aurora-star-prism-core', $prism . '/prism-core.js', array(), '1.30.0', true );
	$deps[] = 'aurora-star-prism-core';

	foreach ( $langs as $lang => $lang_deps ) {
		$handle = 'aurora-star-prism-' . $lang;
		wp_enqueue_script( $handle, $prism . '/components/prism-' . $lang . '.min.js', array_merge( array( 'aurora-star-prism-core' ), $lang_deps ), '1.30.0', true );
		$deps[] = $handle;
	}

	// 插件：行号、工具栏、复制、语言标签、空白处理。
	$line_numbers = (bool) get_theme_mod( 'aurora_star_highlight_line_numbers', true );

	$plugin_files = array(
		'normalize-whitespace' => array(),
		'toolbar'              => array(),
		'show-language'        => array(),
		'copy-to-clipboard'    => array(),
	);

	if ( $line_numbers ) {
		// 行号插件需在 toolbar 之前加载（依赖链）。
		$plugin_files = array(
			'normalize-whitespace' => array(),
			'line-numbers'         => array( 'aurora-star-prism-line-numbers' ),
			'toolbar'              => array(),
			'show-language'        => array(),
			'copy-to-clipboard'    => array(),
		);
	}

	$plugin_deps = $deps;
	foreach ( $plugin_files as $plugin => $css ) {
		$handle = 'aurora-star-prism-' . $plugin;
		wp_enqueue_script( $handle, $prism . '/plugins/prism-' . $plugin . '.min.js', $plugin_deps, '1.30.0', true );
		$plugin_deps = array( $handle );
	}

	// 高亮触发脚本。
	$highlight_deps = array_merge( $deps, array( 'aurora-star-prism-normalize-whitespace', 'aurora-star-prism-toolbar', 'aurora-star-prism-copy-to-clipboard', 'aurora-star-prism-show-language' ) );
	if ( $line_numbers ) {
		$highlight_deps[] = 'aurora-star-prism-line-numbers';
	}
	wp_enqueue_script(
		'aurora-star-highlight',
		AURORA_STAR_URI . '/assets/js/highlight.js',
		$highlight_deps,
		AURORA_STAR_VERSION,
		true
	);

	wp_localize_script(
		'aurora-star-highlight',
		'auroraStarHighlight',
		array(
			'lineNumbers' => $line_numbers,
			'wrap'        => (bool) get_theme_mod( 'aurora_star_highlight_wrap', false ),
		)
	);

	// 主题样式。
	$prism_theme = get_theme_mod( 'aurora_star_highlight_theme', 'okaidia' );
	wp_enqueue_style(
		'aurora-star-prism-theme',
		$prism . '/themes/prism-' . $prism_theme . '.min.css',
		array( 'aurora-star-components' ),
		'1.30.0'
	);

	wp_enqueue_style(
		'aurora-star-prism-toolbar',
		$prism . '/plugins/prism-toolbar.min.css',
		array( 'aurora-star-prism-theme' ),
		'1.30.0'
	);

	if ( $line_numbers ) {
		wp_enqueue_style(
			'aurora-star-prism-line-numbers',
			$prism . '/plugins/prism-line-numbers.min.css',
			array( 'aurora-star-prism-theme' ),
			'1.30.0'
		);
	}

	// 高亮自定义样式。
	wp_enqueue_style(
		'aurora-star-highlight',
		AURORA_STAR_URI . '/assets/css/highlight.css',
		array( 'aurora-star-prism-theme' ),
		AURORA_STAR_VERSION
	);
}

/**
 * 是否显示目录。
 *
 * @return bool
 */
function aurora_star_should_show_toc() {
	if ( ! get_theme_mod( 'aurora_star_toc_enable', true ) ) {
		return false;
	}

	$post_id = get_the_ID();
	if ( $post_id && get_post_meta( $post_id, '_aurora_star_disable_toc', true ) ) {
		return false;
	}

	return true;
}
