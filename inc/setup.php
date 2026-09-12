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

	// 区块编辑器：add_editor_style() 必须配合 editor-styles 才会在 Gutenberg 中生效。
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );

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

	// 注册导航菜单。
	register_nav_menus(
		array(
			'primary' => __( '主导航', 'aurora-star' ),
			'footer'  => __( '页脚导航', 'aurora-star' ),
			'friends' => __( '友情链接', 'aurora-star' ),
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
 * 允许上传 WebP（WordPress 5.8+ 已默认允许，此处仅作显式声明）。
 *
 * SVG 默认不再开放：SVG 可携带脚本，直接访问附件地址即形成存储型 XSS。
 * 确有需要请在子主题中自行开放，并务必将上传权限限制在可信用户：
 *
 *     add_filter( 'aurora_star_allow_svg_upload', '__return_true' );
 *
 * @param array $mimes 已允许的 MIME 类型。
 * @return array
 */
function aurora_star_mime_types( $mimes ) {
	if ( apply_filters( 'aurora_star_allow_svg_upload', false ) && current_user_can( 'unfiltered_html' ) ) {
		$mimes['svg'] = 'image/svg+xml';
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

	// is_main_query() 在次级循环中依然为真（全局 $wp_query 仍是主查询对象），
	// 因此还要确认当前循环的文章就是主查询对象，否则会生成重复的标题 id。
	if ( get_queried_object_id() !== get_the_ID() ) {
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
		// 属性部分允许引号包裹的任意内容（含 ">"），并单独捕获自闭合斜杠，避免生成
		// `<img ... / data-lightbox>` 这类畸形标记，或把属性注入到其它属性值内部。
		'/<img\b((?:"[^"]*"|\'[^\']*\'|[^>"\'])*?)(\s*\/?)>/i',
		function ( $matches ) {
			$attrs = $matches[1];
			$close = $matches[2];

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

			return '<img' . $attrs . $extra . $close . '>';
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
 * 读取去重 Cookie 中已浏览过的文章 ID。
 *
 * @return int[]
 */
function aurora_star_get_viewed_posts() {
	if ( empty( $_COOKIE[ AURORA_STAR_VIEW_COOKIE ] ) ) {
		return array();
	}

	$raw = sanitize_text_field( wp_unslash( $_COOKIE[ AURORA_STAR_VIEW_COOKIE ] ) );

	$ids = array();
	foreach ( explode( '.', $raw ) as $part ) {
		$id = (int) $part;
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	return array_slice( array_values( array_unique( $ids ) ), 0, AURORA_STAR_VIEW_COOKIE_MAX );
}

/**
 * 是否疑似爬虫 / 监控探针 / 脚本请求。
 *
 * @return bool
 */
function aurora_star_is_bot() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
		? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
		: '';

	// 空 UA 基本可判定为脚本或探针。
	if ( '' === $ua ) {
		return true;
	}

	$needles = array(
		'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'httpclient', 'python-requests',
		'python-urllib', 'java/', 'go-http-client', 'okhttp', 'libwww', 'monitor', 'lighthouse',
		'headless', 'pingdom', 'uptime', 'statuscake', 'semrush', 'ahrefs', 'mj12', 'dotbot',
		'facebookexternalhit', 'telegrambot', 'whatsapp', 'applebot', 'yandex', 'baiduspider',
		'sogou', '360spider', 'bytespider', 'petalbot', 'gptbot', 'claudebot', 'ccbot',
		'perplexitybot', 'anthropic',
	);

	foreach ( $needles as $needle ) {
		if ( false !== strpos( $ua, $needle ) ) {
			return true;
		}
	}

	return false;
}

/**
 * 阅读数 +1。
 *
 * 用单条 UPDATE 原子自增，避免并发下「读-改-写」丢失计数；同时省掉一次 SELECT。
 *
 * @param int $post_id 文章 ID。
 * @return void
 */
function aurora_star_increment_views( $post_id ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
			$post_id,
			AURORA_STAR_VIEW_META
		)
	);

	// 首访时还没有 meta 行；add_post_meta(..., true) 保证并发下只插入一次。
	if ( ! $updated ) {
		add_post_meta( $post_id, AURORA_STAR_VIEW_META, 1, true );
	}

	wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * 获取文章阅读数。
 *
 * @param int $post_id 文章 ID，0 表示当前文章。
 * @return int
 */
function aurora_star_get_views( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( ! $post_id ) {
		return 0;
	}

	return (int) get_post_meta( $post_id, AURORA_STAR_VIEW_META, true );
}

/**
 * 记录文章阅读数。
 *
 * 挂在 template_redirect 上（而非 wp_head），因为 setcookie() 必须在任何输出之前调用，
 * 否则在关闭 PHP 输出缓冲的环境下会触发 “headers already sent”。
 */
function aurora_star_track_views() {
	// 仅统计单篇文章的正常 GET 请求。
	if ( ! is_singular( 'post' ) || is_preview() || is_customize_preview() || is_feed() || is_robots() || is_trackback() ) {
		return;
	}

	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
		return;
	}

	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	/**
	 * 是否统计当前请求。
	 *
	 * @param bool $track 默认 true。
	 */
	if ( ! apply_filters( 'aurora_star_should_track_view', true ) ) {
		return;
	}

	// 登录用户默认不计数，避免作者自刷。
	if ( is_user_logged_in() && ! apply_filters( 'aurora_star_count_logged_in_views', false ) ) {
		return;
	}

	// 爬虫与探针不计入。
	if ( aurora_star_is_bot() ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$viewed = aurora_star_get_viewed_posts();
	if ( in_array( $post_id, $viewed, true ) ) {
		return;
	}

	aurora_star_increment_views( $post_id );

	// 只保留最近 N 篇：Cookie 体积恒定，不会随浏览过的文章数无限增长。
	array_unshift( $viewed, $post_id );
	$viewed = array_slice( array_values( array_unique( $viewed ) ), 0, AURORA_STAR_VIEW_COOKIE_MAX );
	$value  = implode( '.', $viewed );

	setcookie(
		AURORA_STAR_VIEW_COOKIE,
		$value,
		array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);

	// 同一请求内如需再次读取，保持一致。
	$_COOKIE[ AURORA_STAR_VIEW_COOKIE ] = $value;
}
add_action( 'template_redirect', 'aurora_star_track_views' );
