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
	// 暗黑模式决策脚本：必须放在 <head> 中同步执行，否则暗色用户首屏会白闪。
	wp_enqueue_script(
		'aurora-star-dark-mode',
		AURORA_STAR_URI . '/assets/js/dark-mode.js',
		array(),
		AURORA_STAR_VERSION,
		false
	);

	// 主脚本（移动端导航 + 标签页 + 手风琴 + 浮动导航 + 搜索面板）。
	wp_enqueue_script(
		'aurora-star-main',
		AURORA_STAR_URI . '/assets/js/main.js',
		array( 'aurora-star-dark-mode' ),
		AURORA_STAR_VERSION,
		true
	);

	// 暗黑模式配置必须挂在 dark-mode.js（<head>）上，不能挂在页脚的 main.js 上。
	wp_localize_script(
		'aurora-star-dark-mode',
		'auroraData',
		array(
			'darkDefault' => get_theme_mod( 'aurora_star_dark_default', 'system' ),
			'darkByTime'  => (bool) get_theme_mod( 'aurora_star_dark_default_for_late', true ),
		)
	);

	wp_localize_script(
		'aurora-star-main',
		'auroraL10n',
		array(
			'copyFailed'  => __( '复制失败，请手动复制地址', 'aurora-star' ),
			'wechatHint'  => __( '微信内请使用右上角 ··· 分享', 'aurora-star' ),
			'menuToggle'  => __( '展开子菜单', 'aurora-star' ),
			'expandAll'   => __( '展开全部', 'aurora-star' ),
			'collapseAll' => __( '收起全部', 'aurora-star' ),
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

		wp_localize_script(
			'aurora-star-lightbox',
			'auroraStarLightboxL10n',
			array(
				'close'      => __( '关闭', 'aurora-star' ),
				'zoomIn'     => __( '放大', 'aurora-star' ),
				'zoomOut'    => __( '缩小', 'aurora-star' ),
				'reset'      => __( '重置', 'aurora-star' ),
				'rotate'     => __( '旋转', 'aurora-star' ),
				'prev'       => __( '上一张', 'aurora-star' ),
				'next'       => __( '下一张', 'aurora-star' ),
				'hint'       => __( '滚动或按钮缩放 · 拖动平移 · ESC 关闭', 'aurora-star' ),
				'openImage'  => __( '放大图片', 'aurora-star' ),
			)
		);

		aurora_star_enqueue_highlight();
	}
}
add_action( 'wp_enqueue_scripts', 'aurora_star_enqueue_scripts' );

/**
 * 将语言别名归一化为 Prism 组件名。
 *
 * sanitize_html_class() 会剥掉 “+”“#” 等字符（c++ → c），因此必须先做别名映射。
 *
 * @param string $lang 原始语言标识。
 * @return string 归一化后的组件名。
 */
function aurora_star_normalize_prism_language( $lang ) {
	$lang = strtolower( trim( (string) $lang ) );
	if ( '' === $lang ) {
		return '';
	}

	$aliases = array(
		'c++'         => 'cpp',
		'cplusplus'   => 'cpp',
		'c#'          => 'csharp',
		'cs'          => 'csharp',
		'f#'          => 'fsharp',
		'objective-c' => 'objectivec',
		'objc'        => 'objectivec',
		'js'          => 'javascript',
		'ts'          => 'typescript',
		'py'          => 'python',
		'rb'          => 'ruby',
		'sh'          => 'bash',
		'shell'       => 'bash',
		'zsh'         => 'bash',
		'yml'         => 'yaml',
		'md'          => 'markdown',
		'html'        => 'markup',
		'xml'         => 'markup',
		'svg'         => 'markup',
		'ps1'         => 'powershell',
		'dockerfile'  => 'docker',
		'make'        => 'makefile',
	);

	if ( isset( $aliases[ $lang ] ) ) {
		$lang = $aliases[ $lang ];
	}

	return sanitize_html_class( $lang );
}

/**
 * highlight.js 自动识别可能返回的语言集合。
 *
 * 必须与 assets/js/highlight.js 中的 detectLanguage() 返回值保持一致。
 *
 * @return string[]
 */
function aurora_star_prism_autodetect_languages() {
	return array( 'markup', 'clike', 'javascript', 'php', 'python', 'java', 'c', 'cpp', 'go', 'bash', 'sql', 'json', 'yaml' );
}

/**
 * 依正文用到的语言计算需要入队的 Prism 组件（含依赖闭包）。
 *
 * @param string[] $languages 正文中显式声明的语言。
 * @param array    $langs     组件映射表（组件名 => 依赖 handle 列表）。
 * @return array 组件名查找表（键为组件名）。
 */
function aurora_star_prism_resolve_languages( $languages, $langs ) {
	$wanted = aurora_star_prism_autodetect_languages();

	foreach ( (array) $languages as $lang ) {
		$lang = aurora_star_normalize_prism_language( $lang );
		if ( '' !== $lang ) {
			$wanted[] = $lang;
		}
	}

	$needed = array();
	$queue  = array_values( array_unique( $wanted ) );

	while ( $queue ) {
		$lang = array_pop( $queue );
		if ( isset( $needed[ $lang ] ) || ! isset( $langs[ $lang ] ) ) {
			continue;
		}
		$needed[ $lang ] = true;

		foreach ( $langs[ $lang ] as $dep_handle ) {
			if ( 0 !== strpos( $dep_handle, 'aurora-star-prism-' ) ) {
				continue;
			}
			$dep = substr( $dep_handle, strlen( 'aurora-star-prism-' ) );
			if ( ! isset( $needed[ $dep ] ) ) {
				$queue[] = $dep;
			}
		}
	}

	/**
	 * 过滤最终入队的 Prism 语言组件。
	 *
	 * @param array    $needed    组件名查找表。
	 * @param string[] $languages 正文声明的语言。
	 */
	return apply_filters( 'aurora_star_prism_languages', $needed, $languages );
}

/**
 * 扫描当前文章正文：是否含代码块、用到哪些语言、是否强制行号。
 *
 * 结果可通过 aurora_star_code_scan 过滤器覆盖（例如短码或小工具在渲染期生成代码块时）。
 *
 * @return array{has_code:bool,languages:string[],line_numbers:bool}
 */
function aurora_star_scan_code_languages() {
	$result = array(
		'has_code'     => false,
		'languages'    => array(),
		'line_numbers' => false,
	);

	$post_id = get_the_ID();
	$content = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';

	if ( '' !== $content ) {
		$result['has_code'] = (
			false !== strpos( $content, '<pre' ) ||
			false !== strpos( $content, '<code' ) ||
			false !== strpos( $content, 'wp:code' ) ||
			false !== strpos( $content, '[code' )
		);

		$languages = array();
		if ( preg_match_all( '/language-([a-z0-9#+._-]+)/i', $content, $matches ) ) {
			$languages = array_merge( $languages, $matches[1] );
		}
		if ( preg_match_all( '/\[code\b[^\]]*\blang\s*=\s*["\']?([a-z0-9#+._-]+)/i', $content, $matches ) ) {
			$languages = array_merge( $languages, $matches[1] );
		}
		$result['languages'] = array_values( array_unique( array_map( 'strtolower', $languages ) ) );

		$result['line_numbers'] = (bool) preg_match( '/\[code\b[^\]]*\bline\s*=\s*["\']?true/i', $content );
	}

	/**
	 * 过滤代码块扫描结果。
	 *
	 * @param array $result  扫描结果。
	 * @param int   $post_id 文章 ID。
	 */
	return apply_filters( 'aurora_star_code_scan', $result, $post_id );
}

/**
 * 代码高亮资源（Prism.js 自托管）。
 */
function aurora_star_enqueue_highlight() {
	if ( ! get_theme_mod( 'aurora_star_highlight', true ) ) {
		return;
	}

	$prism = AURORA_STAR_URI . '/assets/vendor/prism';

	// 仅当正文确实包含代码块时才加载 Prism，避免在每个详情页输出 40+ 个脚本。
	$scan = aurora_star_scan_code_languages();
	if ( empty( $scan['has_code'] ) ) {
		return;
	}

	// 语言组件及其依赖（Prism 官方依赖关系）。数组顺序即依赖顺序：依赖项必须排在使用者之前。
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

	// 计算需要加载的语言：正文显式声明的 + 自动识别可能返回的，并补齐依赖闭包。
	$needed = aurora_star_prism_resolve_languages( $scan['languages'], $langs );

	// 核心 → 按依赖顺序加载。
	$deps = array();
	wp_enqueue_script( 'aurora-star-prism-core', $prism . '/prism-core.js', array(), '1.30.0', true );
	// data-manual 属性：禁止 Prism 自动高亮，由 highlight.js 统一控制时机（避免行号竞态丢失）。
	wp_script_add_data( 'aurora-star-prism-core', 'data-manual', true );
	$deps[] = 'aurora-star-prism-core';

	foreach ( $langs as $lang => $lang_deps ) {
		if ( ! isset( $needed[ $lang ] ) ) {
			continue;
		}
		$handle = 'aurora-star-prism-' . $lang;
		wp_enqueue_script( $handle, $prism . '/components/prism-' . $lang . '.min.js', array_merge( array( 'aurora-star-prism-core' ), $lang_deps ), '1.30.0', true );
		$deps[] = $handle;
	}

	// 插件：行号、工具栏、复制、语言标签、空白处理。
	$line_numbers = (bool) get_theme_mod( 'aurora_star_highlight_line_numbers', true );

	// 行号资源是否入队，取决于「全局开关」或「正文中存在 [code line="true"]」。
	// 否则短码仍会给 pre 加上 line-numbers 类，但插件与样式未加载，行号会静默失效。
	$line_numbers_assets = $line_numbers || ! empty( $scan['line_numbers'] );

	// 顺序即依赖顺序（show-language / copy-to-clipboard 依赖 toolbar，line-numbers 需在 toolbar 之前）。
	$plugin_chain = array( 'normalize-whitespace' );
	if ( $line_numbers_assets ) {
		$plugin_chain[] = 'line-numbers';
	}
	$plugin_chain[] = 'toolbar';
	$plugin_chain[] = 'show-language';
	$plugin_chain[] = 'copy-to-clipboard';

	$plugin_deps    = $deps;
	$plugin_handles = array();
	foreach ( $plugin_chain as $plugin ) {
		$handle           = 'aurora-star-prism-' . $plugin;
		wp_enqueue_script( $handle, $prism . '/plugins/prism-' . $plugin . '.min.js', $plugin_deps, '1.30.0', true );
		$plugin_deps      = array( $handle );
		$plugin_handles[] = $handle;
	}

	// 高亮触发脚本。
	$highlight_deps = array_merge( $deps, $plugin_handles );
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

	if ( $line_numbers_assets ) {
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
