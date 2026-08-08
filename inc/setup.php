<?php
/**
 * 主题初始化：主题支持、菜单、翻译、内容宽度。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 主题初始化。
 */
function aurora_star_setup() {
	// 翻译文件。
	load_theme_textdomain( 'aurora-star', AURORA_STAR_DIR . '/languages' );

	// 文档标题。
	add_theme_support( 'title-tag' );

	// 特色图。
	add_theme_support( 'post-thumbnails' );
	add_image_size( 'aurora-star-card', 640, 360, true );
	add_image_size( 'aurora-star-hero', 1600, 800, true );

	// 自动 feed 链接。
	add_theme_support( 'automatic-feed-links' );

	// HTML5 标记。
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// 自定义 Logo。
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// 自定义背景色。
	add_theme_support(
		'custom-background',
		array(
			'default-color' => 'f6f7f9',
		)
	);

	// 选择性刷新。
	add_theme_support( 'customize-selective-refresh-widgets' );

	// 内容宽度（无编辑器时）。
	$GLOBALS['content_width'] = 800;

	// 注册导航菜单。
	register_nav_menus(
		array(
			'primary' => __( '主导航', 'aurora-star' ),
			'footer'  => __( '页脚导航', 'aurora-star' ),
		)
	);
}
add_action( 'after_setup_theme', 'aurora_star_setup' );

/**
 * 设置内容宽度。
 */
function aurora_star_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'aurora_star_content_width', 800 );
}
add_action( 'after_setup_theme', 'aurora_star_content_width', 0 );

/**
 * 为经典编辑器添加自定义样式。
 */
function aurora_star_editor_style() {
	add_editor_style( array( 'assets/css/editor.css', 'assets/icons/fontawesome/fontawesome.min.css' ) );
}
add_action( 'after_setup_theme', 'aurora_star_editor_style' );

/**
 * 修改 Excerpt 长度与结尾。
 */
function aurora_star_excerpt_length( $length ) {
	return 60;
}
add_filter( 'excerpt_length', 'aurora_star_excerpt_length' );

function aurora_star_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'aurora_star_excerpt_more' );

/**
 * 评论回复链接默认样式类。
 */
function aurora_star_comment_form_defaults( $defaults ) {
	$defaults['class_submit'] = 'aurora-star-btn aurora-star-btn-primary';
	return $defaults;
}
add_filter( 'comment_form_defaults', 'aurora_star_comment_form_defaults' );

/**
 * 允许 svg 上传（用于图标库）。
 *
 * @param array $mimes 已允许的 MIME 类型。
 * @return array
 */
function aurora_star_mime_types( $mimes ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['webp'] = 'image/webp';
	}
	return $mimes;
}
add_filter( 'upload_mimes', 'aurora_star_mime_types' );

/**
 * 为正文标题补 id 用于目录锚点。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_heading_ids( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	return aurora_star_ensure_heading_ids( $content );
}

/**
 * 为标题补 id 的纯函数（供正文过滤器与 TOC 复用）。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_ensure_heading_ids( $content ) {
	$prefix = 'aurora-star-toc-';
	$count  = 0;

	return preg_replace_callback(
		'/<h([2-4])([^>]*)>(.*?)<\/h\1>/is',
		function ( $matches ) use ( $prefix, &$count ) {
			$tag  = $matches[1];
			$attr = $matches[2];

			// 已有 id 则不重复添加。
			if ( preg_match( '/\bid\s*=\s*["\'][^"\']+["\']/i', $attr ) ) {
				return $matches[0];
			}

			$count++;
			$id  = $prefix . $count . '-' . sanitize_title( wp_strip_all_tags( $matches[3] ) );
			$id  = substr( $id, 0, 80 );
			return '<h' . $tag . $attr . ' id="' . esc_attr( $id ) . '">' . $matches[3] . '</h' . $tag . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'aurora_star_heading_ids', 9 );

/**
 * 给正文图片添加灯箱标记。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_lightbox_images( $content ) {
	if ( ! is_singular() || ! get_theme_mod( 'aurora_star_lightbox', true ) ) {
		return $content;
	}

	return preg_replace_callback(
		'/<img([^>]*)>/i',
		function ( $matches ) {
			$attrs = $matches[1];

			// 跳过 emoji 和已经标记的。
			if ( preg_match( '/class\s*=\s*["\'][^"\']*wp-smiley[^"\']*["\']/i', $attrs ) ) {
				return $matches[0];
			}
			if ( false !== strpos( $attrs, 'data-lightbox' ) ) {
				return $matches[0];
			}

			// 提取 src 与 srcset 中的高清版本。
			$src = '';
			if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $attrs, $m ) ) {
				$src = $m[1];
			}
			$full = $src;
			if ( preg_match( '/srcset\s*=\s*["\']([^"\']+)["\']/i', $attrs, $m ) ) {
				$parts = preg_split( '/\s*,\s*/', $m[1] );
				$largest = $src;
				$maxW = 0;
				foreach ( $parts as $part ) {
					if ( preg_match( '/(\S+)\s+(\d+)w/', $part, $pm ) ) {
						if ( (int) $pm[2] > $maxW ) {
							$maxW = (int) $pm[2];
							$largest = $pm[1];
						}
					}
				}
				if ( $largest ) {
					$full = $largest;
				}
			}

			$extra = ' data-lightbox';
			if ( $full && $full !== $src ) {
				$extra .= ' data-full="' . esc_attr( $full ) . '"';
			}

			return '<img' . $attrs . $extra . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'aurora_star_lightbox_images', 11 );

/**
 * 导航菜单回退：无菜单时输出页面列表。
 */
function aurora_star_menu_fallback() {
	echo '<ul id="primary-menu" class="menu">';
	wp_list_pages(
		array(
			'title_li' => '',
			'depth'    => 1,
		)
	);
	echo '</ul>';
}

/**
 * 记录文章阅读数。
 */
function aurora_star_track_views() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_the_ID();
	if ( ! $post_id || is_user_logged_in() ) {
		return;
	}

	// 基于 cookie 简单去重，防止刷新刷量。
	$cookie = isset( $_COOKIE['aurora_star_views'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['aurora_star_views'] ) ) : '';

	if ( false !== strpos( $cookie, "|{$post_id}|" ) ) {
		return;
	}

	$views = (int) get_post_meta( $post_id, 'aurora_star_views', true );
	update_post_meta( $post_id, 'aurora_star_views', $views + 1 );

	$new_cookie = $cookie . '|' . $post_id . '|';
	setcookie( 'aurora_star_views', $new_cookie, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
}
add_action( 'wp_head', 'aurora_star_track_views', 1 );
