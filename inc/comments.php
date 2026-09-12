<?php
/**
 * 评论增强：Gravatar 头像、IP 归属地（本地 GeoLite2）、操作系统与浏览器。
 *
 * 归属地查询全部在本地完成，不会把访客 IP 发送给任何第三方。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 一、MaxMind GeoLite2 本地查询
 * ---------------------------------------------------------------------- */

/**
 * 自动探测 GeoLite2 数据库路径。
 *
 * 查找顺序：
 *   1. 通过后台「Aurora Star 主题 → IP 数据库」上传的文件（wp-content/uploads/aurora-star-geoip/）
 *   2. 主题目录 assets/geoip/ 下手动放置的文件
 *
 * @return string
 */
function aurora_star_geoip_auto_path() {
	$upload_dir = function_exists( 'aurora_star_geoip_upload_dir' ) ? aurora_star_geoip_upload_dir() : '';

	if ( '' !== $upload_dir ) {
		// 城市库优先于国家库。
		foreach ( array( 'GeoLite2-City.mmdb', 'GeoLite2-Country.mmdb', 'GeoLite2.mmdb' ) as $name ) {
			$candidate = $upload_dir . '/' . $name;
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}
	}

	return AURORA_STAR_DIR . '/assets/geoip/GeoLite2-City.mmdb';
}

/**
 * GeoLite2 数据库文件路径。
 *
 * 默认按 aurora_star_geoip_auto_path() 的顺序自动查找。
 * 建议用下面的过滤器把库指到主题目录之外（例如 wp-content/uploads/geoip/），
 * 这样升级主题时不会被覆盖。
 *
 * @return string
 */
function aurora_star_geoip_db_path() {
	/**
	 * 过滤 GeoLite2 数据库文件路径。
	 *
	 * @param string $path 自动探测到的数据库绝对路径。
	 */
	return (string) apply_filters( 'aurora_star_geoip_db_path', aurora_star_geoip_auto_path() );
}

/**
 * 按需载入 maxmind-db/reader（Apache-2.0）。
 *
 * 若插件或 maxminddb 扩展已经提供了同名类则直接复用，避免重复声明。
 *
 * @return void
 */
function aurora_star_load_geoip_reader() {
	if ( class_exists( '\MaxMind\Db\Reader' ) ) {
		return;
	}

	$base = AURORA_STAR_DIR . '/assets/vendor/maxmind-db-reader/src/MaxMind/Db/';

	require_once $base . 'Reader/InvalidDatabaseException.php';
	require_once $base . 'Reader/Decoder.php';
	require_once $base . 'Reader/Metadata.php';
	require_once $base . 'Reader/Util.php';
	require_once $base . 'Reader.php';
}

/**
 * 当前请求内共享的 Reader 实例（Reader 使用文件句柄读取，不会把整个库载入内存）。
 *
 * @return \MaxMind\Db\Reader|null 数据库不可用时返回 null。
 */
function aurora_star_geoip_reader() {
	static $reader   = null;
	static $resolved = false;

	if ( $resolved ) {
		return $reader;
	}
	$resolved = true;

	if ( ! get_theme_mod( 'aurora_star_comment_geo', true ) ) {
		return null;
	}

	$path = aurora_star_geoip_db_path();
	if ( '' === $path || ! is_readable( $path ) ) {
		return null;
	}

	aurora_star_load_geoip_reader();
	if ( ! class_exists( '\MaxMind\Db\Reader' ) ) {
		return null;
	}

	try {
		$reader = new \MaxMind\Db\Reader( $path );
	} catch ( \Throwable $e ) {
		$reader = null;
	}

	return $reader;
}

/**
 * 按路径从 GeoLite2 记录取值（兼容 array 与 object 两种解码结果）。
 *
 * @param mixed $data 记录。
 * @param array $path 键路径。
 * @return mixed 不存在时返回 null。
 */
function aurora_star_geo_pick( $data, $path ) {
	$node = $data;

	foreach ( $path as $key ) {
		if ( is_array( $node ) && isset( $node[ $key ] ) ) {
			$node = $node[ $key ];
		} elseif ( is_object( $node ) && isset( $node->$key ) ) {
			$node = $node->$key;
		} else {
			return null;
		}
	}

	return $node;
}

/**
 * 从 GeoLite2 的 names 多语言表中取当前站点语言的名称。
 *
 * @param mixed $names 名称表。
 * @return string
 */
function aurora_star_geo_name( $names ) {
	if ( ! is_array( $names ) && ! is_object( $names ) ) {
		return '';
	}

	$names  = (array) $names;
	$locale = str_replace( '_', '-', (string) get_locale() );

	$candidates = array( $locale );
	if ( 0 === strpos( $locale, 'zh' ) ) {
		$candidates[] = 'zh-CN';
	}
	$candidates[] = 'en';

	foreach ( $candidates as $key ) {
		if ( ! empty( $names[ $key ] ) ) {
			return (string) $names[ $key ];
		}
	}

	return '';
}

/**
 * 由 IP 解析归属地。
 *
 * @param string $ip IP 地址。
 * @return array 空数组表示无法解析（IP 非法 / 库缺失 / 查不到）。
 */
function aurora_star_resolve_geo( $ip ) {
	static $memo = array();

	$reader = aurora_star_geoip_reader();
	if ( ! $reader ) {
		return array();
	}

	if ( ! is_string( $ip ) || '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return array();
	}

	// 同一请求内相同 IP 只查一次。
	if ( array_key_exists( $ip, $memo ) ) {
		return $memo[ $ip ];
	}

	try {
		$record = $reader->get( $ip );
	} catch ( \Throwable $e ) {
		$record = null;
	}

	if ( ! is_array( $record ) && ! is_object( $record ) ) {
		return $memo[ $ip ] = array();
	}

	$cc      = strtoupper( (string) aurora_star_geo_pick( $record, array( 'country', 'iso_code' ) ) );
	$country = aurora_star_geo_name( aurora_star_geo_pick( $record, array( 'country', 'names' ) ) );
	$region  = aurora_star_geo_name( aurora_star_geo_pick( $record, array( 'subdivisions', 0, 'names' ) ) );
	$city    = aurora_star_geo_name( aurora_star_geo_pick( $record, array( 'city', 'names' ) ) );

	// 只保留展示所需字段，避免把整条记录写进数据库。
	$geo = array(
		// 仅接受标准两位国家代码，'A1' / 'A2' 之类的保留值会被丢弃。
		'cc'      => preg_match( '/^[A-Z]{2}$/', $cc ) ? $cc : '',
		'country' => $country,
		'region'  => $region,
		'city'    => $city,
	);

	// 全是空值时按「查不到」处理。
	$has_value = ( '' !== $geo['cc'] || '' !== $country || '' !== $region || '' !== $city );

	return $memo[ $ip ] = ( $has_value ? $geo : array() );
}

/* -------------------------------------------------------------------------
 * 二、User-Agent 解析（纯本地，无任何外呼）
 * ---------------------------------------------------------------------- */

/**
 * 解析评论者的操作系统与浏览器。
 *
 * @param string $ua User-Agent。
 * @return array{os:string,os_icon:string,browser:string,browser_icon:string}
 */
function aurora_star_parse_user_agent( $ua ) {
	static $memo = array();

	$ua = (string) $ua;

	if ( isset( $memo[ $ua ] ) ) {
		return $memo[ $ua ];
	}

	$result = array(
		'os'           => '',
		'os_icon'      => '',
		'browser'      => '',
		'browser_icon' => '',
	);

	if ( '' === $ua ) {
		return $memo[ $ua ] = $result;
	}

	$os      = aurora_star_ua_os( $ua );
	$browser = aurora_star_ua_browser( $ua );

	$result['os']           = $os[0];
	$result['os_icon']      = $os[1];
	$result['browser']      = $browser[0];
	$result['browser_icon'] = $browser[1];

	return $memo[ $ua ] = $result;
}

/**
 * 操作系统与对应图标。
 *
 * @param string $ua User-Agent。
 * @return array{0:string,1:string} OS 名称与 Font Awesome 类名。
 */
function aurora_star_ua_os( $ua ) {
	if ( preg_match( '/Windows (?:NT|Phone)/i', $ua ) ) {
		if ( preg_match( '/Windows Phone/i', $ua ) ) {
			return array( 'Windows Phone', 'fa-brands fa-windows' );
		}

		if ( preg_match( '/Windows NT ([\d.]+)/i', $ua, $m ) ) {
			$map = array(
				'10.0' => 'Windows 10/11', // UA 无法区分 10 与 11，需 Client Hints 才能细分。
				'6.3'  => 'Windows 8.1',
				'6.2'  => 'Windows 8',
				'6.1'  => 'Windows 7',
				'6.0'  => 'Windows Vista',
				'5.1'  => 'Windows XP',
				'5.0'  => 'Windows 2000',
			);

			return array( isset( $map[ $m[1] ] ) ? $map[ $m[1] ] : 'Windows NT ' . $m[1], 'fa-brands fa-windows' );
		}
	}

	if ( preg_match( '/(?:HarmonyOS|OpenHarmony)[\/ ]?([\d.]*)/i', $ua, $m ) ) {
		return array( trim( 'HarmonyOS ' . $m[1] ), 'fa-solid fa-mobile-screen' );
	}

	// 先于 macOS 判断：iOS/iPadOS 的 UA 里同样含有 “like Mac OS X”。
	// iPad 的 UA 是 “CPU OS 16_6”，iPhone 是 “CPU iPhone OS 17_1”，因此用 [^)]* 跨过分号。
	if ( preg_match( '/(?:iPhone|iPad|iPod)[^)]*?OS (\d+)[._](\d+)/i', $ua, $m ) ) {
		$prefix = ( false !== stripos( $ua, 'iPad' ) ) ? 'iPadOS ' : 'iOS ';

		return array( $prefix . $m[1] . '.' . $m[2], 'fa-brands fa-apple' );
	}

	if ( preg_match( '/Mac OS X (\d+)[._](\d+)/i', $ua, $m ) ) {
		return array( 'macOS ' . $m[1] . '.' . $m[2], 'fa-brands fa-apple' );
	}

	if ( preg_match( '/CrOS/i', $ua ) ) {
		return array( 'Chrome OS', 'fa-brands fa-chrome' );
	}

	if ( preg_match( '/Android[ \/]([\d.]+)/i', $ua, $m ) ) {
		return array( 'Android ' . $m[1], 'fa-brands fa-android' );
	}

	if ( preg_match( '/Ubuntu/i', $ua ) ) {
		return array( 'Ubuntu', 'fa-brands fa-linux' );
	}

	if ( preg_match( '/FreeBSD|OpenBSD|NetBSD/i', $ua, $m ) ) {
		return array( $m[0], 'fa-brands fa-linux' );
	}

	if ( preg_match( '/Linux/i', $ua ) ) {
		return array( 'Linux', 'fa-brands fa-linux' );
	}

	return array( '', '' );
}

/**
 * 浏览器与对应图标。
 *
 * 顺序即优先级：内嵌 Chromium/WebKit 内核的国产浏览器必须排在 Chrome / Safari 之前。
 *
 * @param string $ua User-Agent。
 * @return array{0:string,1:string} 浏览器名称与 Font Awesome 类名。
 */
function aurora_star_ua_browser( $ua ) {
	$generic = 'fa-solid fa-compass';

	if ( preg_match( '/MicroMessenger[\/ ]([\d.]+)/i', $ua, $m ) ) {
		return array( '微信 ' . $m[1], 'fa-brands fa-weixin' );
	}

	if ( preg_match( '/(?:MQQBrowser|QQBrowser)[\/ ]([\d.]+)/i', $ua, $m ) ) {
		return array( 'QQ 浏览器 ' . $m[1], 'fa-brands fa-qq' );
	}

	// 注意：BIDUBrowser 里含有子串 UBrowser，必须排在 UC 之前。
	if ( preg_match( '/(?:BIDUBrowser|baidubrowser)[\/ ]([\d.]+)/i', $ua, $m ) ) {
		return array( '百度浏览器 ' . $m[1], $generic );
	}

	// (?<![A-Za-z]) 防止把 BIDUBrowser 之类误判为 UC。
	if ( preg_match( '/(?<![A-Za-z])(?:UCBrowser|UBrowser)[\/ ]([\d.]+)/i', $ua, $m ) ) {
		return array( 'UC 浏览器 ' . $m[1], $generic );
	}

	if ( preg_match( '/(?:QihooBrowser|360SE|360EE)[\/ ]?([\d.]+)?/i', $ua, $m ) ) {
		return array( trim( '360 浏览器 ' . ( isset( $m[1] ) ? $m[1] : '' ) ), $generic );
	}

	// 搜狗 UA 里的 “MetaSr 1.0” 是内核标识而非浏览器版本，因此不取版本号。
	if ( preg_match( '/SogouMobileBrowser[\/ ]([\d.]+)/i', $ua, $m ) ) {
		return array( '搜狗浏览器 ' . $m[1], $generic );
	}

	if ( preg_match( '/MetaSr/i', $ua ) ) {
		return array( '搜狗浏览器', $generic );
	}

	if ( preg_match( '/Weibo[\/ ]([\d.]+)/i', $ua, $m ) ) {
		return array( '微博 ' . $m[1], 'fa-brands fa-weibo' );
	}

	if ( preg_match( '/Edg(?:e|A|iOS)?[\/ ]([\d.]+)/', $ua, $m ) ) {
		return array( 'Edge ' . $m[1], 'fa-brands fa-edge' );
	}

	if ( preg_match( '/(?:OPR|Opera)[\/ ]([\d.]+)/', $ua, $m ) ) {
		return array( 'Opera ' . $m[1], 'fa-brands fa-opera' );
	}

	if ( preg_match( '/SamsungBrowser[\/ ]([\d.]+)/', $ua, $m ) ) {
		return array( 'Samsung Internet ' . $m[1], $generic );
	}

	if ( preg_match( '/(?:Firefox|FxiOS)[\/ ]([\d.]+)/', $ua, $m ) ) {
		return array( 'Firefox ' . $m[1], 'fa-brands fa-firefox-browser' );
	}

	if ( preg_match( '/(?:Chrome|CriOS)[\/ ]([\d.]+)/', $ua, $m ) ) {
		return array( 'Chrome ' . $m[1], 'fa-brands fa-chrome' );
	}

	if ( preg_match( '/Version[\/ ]([\d.]+)[^)]*Safari/', $ua, $m ) ) {
		return array( 'Safari ' . $m[1], 'fa-brands fa-safari' );
	}

	if ( preg_match( '/MSIE ([\d.]+)/', $ua, $m ) ) {
		return array( 'Internet Explorer ' . $m[1], 'fa-brands fa-internet-explorer' );
	}

	if ( preg_match( '/Trident\/.*?rv:([\d.]+)/', $ua, $m ) ) {
		return array( 'Internet Explorer ' . $m[1], 'fa-brands fa-internet-explorer' );
	}

	return array( '', '' );
}

/* -------------------------------------------------------------------------
 * 三、评论归属地读写
 * ---------------------------------------------------------------------- */

/**
 * 读取评论归属地，首次访问时解析并缓存到评论 meta。
 *
 * 归属地一旦解析成功就写入 comment meta，后续渲染不再访问 mmdb，
 * 因此不会在每次页面浏览时重复查询。
 *
 * @param WP_Comment|int $comment 评论对象或 ID。
 * @return array 空数组表示无归属地信息。
 */
function aurora_star_get_comment_geo( $comment ) {
	static $memo = array();

	$comment_id = is_object( $comment ) ? (int) $comment->comment_ID : (int) $comment;
	if ( ! $comment_id ) {
		return array();
	}

	if ( isset( $memo[ $comment_id ] ) ) {
		return $memo[ $comment_id ];
	}

	$stored = get_comment_meta( $comment_id, '_aurora_star_geo', true );

	// 未解析过时 get_comment_meta 返回 ''；已解析但无结果时会返回空数组。
	if ( is_array( $stored ) ) {
		return $memo[ $comment_id ] = $stored;
	}

	$ip = is_object( $comment )
		? (string) $comment->comment_author_IP
		: (string) get_comment_author_ip( $comment_id );

	$geo = aurora_star_resolve_geo( $ip );

	/**
	 * 是否把解析结果写入评论 meta。
	 *
	 * @param bool $persist    默认 true。
	 * @param int  $comment_id 评论 ID。
	 */
	if ( apply_filters( 'aurora_star_persist_comment_geo', true, $comment_id ) ) {
		// 无条件写入（含空数组），作为「已解析」标记，避免每次浏览都重查。
		update_comment_meta( $comment_id, '_aurora_star_geo', $geo );
	}

	return $memo[ $comment_id ] = $geo;
}

/**
 * 新评论提交后立即解析归属地，使首屏渲染无需查库。
 *
 * @param int $comment_id 评论 ID。
 * @return void
 */
function aurora_star_comment_geo_on_insert( $comment_id ) {
	if ( ! get_theme_mod( 'aurora_star_comment_geo', true ) ) {
		return;
	}

	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return;
	}

	$ip = (string) $comment->comment_author_IP;
	if ( '' === $ip ) {
		return;
	}

	$geo = aurora_star_resolve_geo( $ip );
	update_comment_meta( $comment_id, '_aurora_star_geo', $geo );
}
add_action( 'wp_insert_comment', 'aurora_star_comment_geo_on_insert' );

/* -------------------------------------------------------------------------
 * 四、展示
 * ---------------------------------------------------------------------- */

/**
 * 国旗图片（自托管 SVG，按 ISO 3166-1 alpha-2 取文件）。
 *
 * @param string $cc 两位国家代码。
 * @return string
 */
function aurora_star_flag_html( $cc ) {
	static $memo = array();

	$cc = strtolower( (string) $cc );

	// 只允许 a-z 两位，杜绝路径穿越。
	if ( ! preg_match( '/^[a-z]{2}$/', $cc ) ) {
		return '';
	}

	if ( isset( $memo[ $cc ] ) ) {
		return $memo[ $cc ];
	}

	$file = AURORA_STAR_DIR . '/assets/vendor/flag-icons/flags/4x3/' . $cc . '.svg';
	if ( ! is_readable( $file ) ) {
		return $memo[ $cc ] = '';
	}

	$url = AURORA_STAR_URI . '/assets/vendor/flag-icons/flags/4x3/' . $cc . '.svg';

	// 国家名就在相邻文本里，图片 alt 留空避免读屏重复。
	return $memo[ $cc ] = '<img class="aurora-comment-flag" src="' . esc_url( $url ) . '" alt="" width="16" height="12" loading="lazy" decoding="async" />';
}

/**
 * 归属地文本，例如「中国 · 广东 · 深圳」。
 *
 * @param array $geo 归属地数据。
 * @return string
 */
function aurora_star_geo_label( $geo ) {
	if ( ! is_array( $geo ) ) {
		return '';
	}

	$parts = array();

	foreach ( array( 'country', 'region', 'city' ) as $key ) {
		$value = isset( $geo[ $key ] ) ? trim( (string) $geo[ $key ] ) : '';
		if ( '' === $value ) {
			continue;
		}
		// 去重：GeoLite2 里台湾等地区会出现 country 与 region 同名。
		if ( in_array( $value, $parts, true ) ) {
			continue;
		}
		$parts[] = $value;
	}

	return implode( ' · ', $parts );
}

/**
 * 评论元信息徽章（归属地 + 操作系统 + 浏览器）。
 *
 * @param WP_Comment $comment 评论对象。
 * @return string
 */
function aurora_star_comment_badges_html( $comment ) {
	$badges = array();

	// —— IP 归属地 ——
	if ( get_theme_mod( 'aurora_star_comment_geo', true ) ) {
		$geo   = aurora_star_get_comment_geo( $comment );
		$label = aurora_star_geo_label( $geo );

		if ( '' !== $label ) {
			$flag   = isset( $geo['cc'] ) ? aurora_star_flag_html( $geo['cc'] ) : '';
			$marker = ( '' !== $flag )
				? $flag
				: '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>';

			$badges[] = '<span class="aurora-comment-badge aurora-comment-badge--geo" title="'
				. esc_attr__( 'IP 归属地', 'aurora-star' ) . '">'
				. $marker . '<span>' . esc_html( $label ) . '</span></span>';
		}
	}

	// —— 操作系统 / 浏览器 ——
	if ( get_theme_mod( 'aurora_star_comment_ua', true ) ) {
		$ua = aurora_star_parse_user_agent( isset( $comment->comment_agent ) ? $comment->comment_agent : '' );

		if ( '' !== $ua['os'] ) {
			$badges[] = '<span class="aurora-comment-badge aurora-comment-badge--os" title="'
				. esc_attr__( '操作系统', 'aurora-star' ) . '">'
				. aurora_star_badge_icon( $ua['os_icon'] )
				. '<span>' . esc_html( $ua['os'] ) . '</span></span>';
		}

		if ( '' !== $ua['browser'] ) {
			$badges[] = '<span class="aurora-comment-badge aurora-comment-badge--browser" title="'
				. esc_attr__( '浏览器', 'aurora-star' ) . '">'
				. aurora_star_badge_icon( $ua['browser_icon'] )
				. '<span>' . esc_html( $ua['browser'] ) . '</span></span>';
		}
	}

	if ( ! $badges ) {
		return '';
	}

	// 徽章本身是纯装饰性元信息，读屏时按组跳过，避免每个评论重复朗读。
	return '<span class="aurora-comment-badges" aria-label="' . esc_attr__( '评论者信息', 'aurora-star' ) . '">'
		. implode( '', $badges ) . '</span>';
}

/**
 * 徽章图标。
 *
 * @param string $icon Font Awesome 类名。
 * @return string
 */
function aurora_star_badge_icon( $icon ) {
	$icon = trim( (string) $icon );
	if ( '' === $icon ) {
		return '';
	}

	// 只允许 Font Awesome 的类名片段，避免把任意字符串拼进 class 属性。
	if ( ! preg_match( '/^(?:fa-brands|fa-solid|fa-regular)(?: fa-[a-z0-9-]+)+$/', $icon ) ) {
		$icon = 'fa-solid fa-globe';
	}

	return '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
}

/**
 * 评论头像。
 *
 * 默认走 Gravatar（get_avatar 的默认服务）。若站点关闭了头像功能，
 * 仍会按评论邮箱拼出 Gravatar 地址，保证评论区始终有头像。
 *
 * @param WP_Comment $comment 评论对象。
 * @param int        $size    尺寸。
 * @return string
 */
function aurora_star_comment_avatar( $comment, $size = 48 ) {
	$size = (int) $size;
	if ( $size <= 0 ) {
		$size = 48;
	}

	$defaults = array( 'mystery', 'mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'robohash', 'blank' );
	$default  = (string) get_theme_mod( 'aurora_star_avatar_default', 'mystery' );
	if ( ! in_array( $default, $defaults, true ) ) {
		$default = 'mystery';
	}

	$alt   = isset( $comment->comment_author ) ? (string) $comment->comment_author : '';
	$attrs = array(
		'class'      => 'aurora-comment-avatar-img',
		'extra_attr' => 'loading="lazy" decoding="async"',
	);

	$avatar = get_avatar( $comment, $size, $default, $alt, $attrs );

	// get_avatar() 在站点关闭头像（show_avatars=off）时返回空串。
	if ( ! $avatar && ! empty( $comment->comment_author_email ) ) {
		$hash = md5( strtolower( trim( (string) $comment->comment_author_email ) ) );
		$url  = 'https://secure.gravatar.com/avatar/' . $hash
			. '?s=' . $size . '&d=' . rawurlencode( $default ) . '&r=g';

		$avatar = '<img class="aurora-comment-avatar-img" src="' . esc_url( $url )
			. '" alt="' . esc_attr( $alt ) . '" width="' . $size . '" height="' . $size
			. '" loading="lazy" decoding="async" />';
	}

	// 无邮箱（如由后台代发的评论）时退化为本地占位图，避免布局塌陷。
	if ( ! $avatar ) {
		$avatar = '<img class="aurora-comment-avatar-img aurora-comment-avatar-img--fallback" src="'
			. esc_url( AURORA_STAR_URI . '/assets/img/avatar-fallback.svg' )
			. '" alt="' . esc_attr( $alt ) . '" width="' . $size . '" height="' . $size
			. '" loading="lazy" decoding="async" />';
	}

	/**
	 * 过滤评论头像 HTML。
	 *
	 * @param string     $avatar  头像 HTML。
	 * @param WP_Comment $comment 评论对象。
	 * @param int        $size    尺寸。
	 */
	return apply_filters( 'aurora_star_comment_avatar_html', $avatar, $comment, $size );
}

/* -------------------------------------------------------------------------
 * 五、评论列表 Walker
 * ---------------------------------------------------------------------- */

/**
 * 自定义评论 Walker。
 *
 * 只覆盖 html5_comment()，评论树的遍历、pingback 与 end_el 仍复用 WP 核心实现。
 */
class Aurora_Comment_Walker extends Walker_Comment {

	/**
	 * 输出单条评论。
	 *
	 * @param WP_Comment $comment 评论对象。
	 * @param int        $depth   层级。
	 * @param array      $args    参数。
	 * @return void
	 */
	protected function html5_comment( $comment, $depth, $args ) {
		$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';

		$commenter          = wp_get_current_commenter();
		$show_pending_links = ! empty( $commenter['comment_author'] );

		if ( ! empty( $commenter['comment_author_email'] ) ) {
			$moderation_note = __( '你的评论正在等待审核。', 'aurora-star' );
		} else {
			$moderation_note = __( '你的评论正在等待审核。这是预览效果，审核通过后才会公开显示。', 'aurora-star' );
		}
		?>
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 固定值。 ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $this->has_children ? 'parent' : '', $comment ); ?>>
			<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">

				<div class="comment-avatar">
					<?php
					echo aurora_star_comment_avatar( $comment, $args['avatar_size'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 已转义。
					?>
				</div>

				<div class="comment-main">
					<footer class="comment-meta">
						<div class="comment-author vcard">
							<?php
							$comment_author = get_comment_author_link( $comment );

							if ( '0' === $comment->comment_approved && ! $show_pending_links ) {
								$comment_author = get_comment_author( $comment );
							}

							echo '<b class="fn">' . $comment_author . '</b>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_comment_author_link 已转义。
							echo aurora_star_comment_badges_html( $comment ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部已转义。
							?>
						</div><!-- .comment-author -->

						<div class="comment-metadata">
							<?php
							printf(
								'<a href="%s"><time datetime="%s">%s</time></a>',
								esc_url( get_comment_link( $comment, $args ) ),
								esc_attr( get_comment_time( 'c' ) ),
								esc_html( get_comment_date( '', $comment ) . ' ' . get_comment_time() )
							);

							edit_comment_link( __( '编辑', 'aurora-star' ), ' <span class="edit-link">', '</span>' );
							?>
						</div><!-- .comment-metadata -->

						<?php if ( '0' === $comment->comment_approved ) : ?>
							<em class="comment-awaiting-moderation"><?php echo esc_html( $moderation_note ); ?></em>
						<?php endif; ?>
					</footer><!-- .comment-meta -->

					<div class="comment-content">
						<?php comment_text(); ?>
					</div><!-- .comment-content -->

					<?php
					if ( '1' === $comment->comment_approved || $show_pending_links ) {
						comment_reply_link(
							array_merge(
								$args,
								array(
									'add_below' => 'div-comment',
									'depth'     => $depth,
									'max_depth' => $args['max_depth'],
									'before'    => '<div class="reply">',
									'after'     => '</div>',
								)
							)
						);
					}
					?>
				</div><!-- .comment-main -->
			</article><!-- .comment-body -->
		<?php
	}
}
