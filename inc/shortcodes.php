<?php
/**
 * 短码系统。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 提取短码内部 HTML（处理嵌套）。
 *
 * @param array  $attrs    短码属性。
 * @param string $content  内容。
 * @param string $inner_tag 内部标签。
 * @return string
 */
function aurora_star_shortcode_content( $content, $inner_tag ) {
	if ( false !== strpos( $content, '[' ) ) {
		$content = do_shortcode( $content );
	}
	$inner = trim( $content );
	if ( ! empty( $inner ) ) {
		return '<' . $inner_tag . '>' . $inner . '</' . $inner_tag . '>';
	}
	return '';
}

/**
 * [button] 按钮。
 * 用法：[button href="https://example.com" color="primary" size="md" target="_blank" rel="nofollow"]文字[/button]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_button( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'href'   => '#',
			'color'  => 'primary',
			'size'   => 'md',
			'target' => '',
			'rel'    => '',
			'icon'   => '',
			'class'  => '',
		),
		$atts,
		'button'
	);

	$content = do_shortcode( trim( $content ) );
	if ( empty( $content ) ) {
		$content = __( '按钮', 'aurora-star' );
	}

	$icon = '';
	if ( $atts['icon'] ) {
		$icon = '<i class="aurora-star-sc-btn-icon ' . esc_attr( $atts['icon'] ) . '" aria-hidden="true"></i>';
	}

	$target = $atts['target'] ? ' target="' . esc_attr( $atts['target'] ) . '"' : '';
	$rel    = $atts['rel'] ? ' rel="' . esc_attr( $atts['rel'] ) . '"' : '';

	$class = 'aurora-star-btn aurora-star-btn-' . sanitize_html_class( $atts['color'] ) . ' aurora-star-btn-' . sanitize_html_class( $atts['size'] ) . ( $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '' );

	return '<a class="' . $class . '" href="' . esc_url( $atts['href'] ) . '"' . $target . $rel . '>' . $icon . '<span>' . $content . '</span></a>';
}
add_shortcode( 'button', 'aurora_star_sc_button' );
add_shortcode( 'btn', 'aurora_star_sc_button' );

/**
 * [alert] 提示框。
 * 用法：[alert type="info|success|warning|error" title="标题"]内容[/alert]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_alert( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'type'  => 'info',
			'title' => '',
		),
		$atts,
		'alert'
	);

	$type  = in_array( $atts['type'], array( 'info', 'success', 'warning', 'error' ), true ) ? $atts['type'] : 'info';
	$icons = array(
		'info'    => 'fa-solid fa-circle-info',
		'success' => 'fa-solid fa-circle-check',
		'warning' => 'fa-solid fa-triangle-exclamation',
		'error'   => 'fa-solid fa-circle-xmark',
	);

	$title = '';
	if ( $atts['title'] ) {
		$title = '<div class="aurora-star-alert-title"><i class="' . $icons[ $type ] . '"></i> ' . esc_html( $atts['title'] ) . '</div>';
	}

	return '<div class="aurora-star-alert aurora-star-alert-' . $type . '">' . $title . '<div class="aurora-star-alert-body">' . do_shortcode( $content ) . '</div></div>';
}
add_shortcode( 'alert', 'aurora_star_sc_alert' );
add_shortcode( 'tip', 'aurora_star_sc_alert' );

/**
 * [note] 注释块。
 * 用法：[note title="标题"]内容[/note]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_note( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'title' => '',
		),
		$atts,
		'note'
	);

	$title = $atts['title'] ? '<div class="aurora-star-note-title">' . esc_html( $atts['title'] ) . '</div>' : '';

	return '<div class="aurora-star-note">' . $title . '<div class="aurora-star-note-body">' . do_shortcode( $content ) . '</div></div>';
}
add_shortcode( 'note', 'aurora_star_sc_note' );

/**
 * [tabs] 标签页。
 * 用法：
 * [tabs]
 *   [tab title="标签一"]内容一[/tab]
 *   [tab title="标签二"]内容二[/tab]
 * [/tabs]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_tabs( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'active' => 1,
		),
		$atts,
		'tabs'
	);

	$pattern = get_shortcode_regex( array( 'tab' ) );
	preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER );

	if ( empty( $matches ) ) {
		return do_shortcode( $content );
	}

	$active = max( 1, (int) $atts['active'] );

	$nav  = '<div class="aurora-star-tabs" role="tablist">';
	$nav .= '<div class="aurora-star-tabs-nav" role="tablist">';

	$panes = '';
	$i     = 0;
	foreach ( $matches as $match ) {
		$i++;

		$item_atts = shortcode_parse_atts( $match[3] );
		$title     = isset( $item_atts['title'] ) ? trim( $item_atts['title'] ) : '';
		if ( empty( $title ) ) {
			$title = __( '标签', 'aurora-star' ) . ' ' . $i;
		}
		$title = do_shortcode( $title );

		$id      = 'aurora-star-tab-' . wp_generate_password( 6, false, false );
		$is_act  = ( $i === $active ) ? ' is-active' : '';

		$nav .= '<button type="button" class="aurora-star-tabs-tab' . $is_act . '" role="tab" aria-selected="' . ( $is_act ? 'true' : 'false' ) . '" aria-controls="' . $id . '">' . $title . '</button>';
		$panes .= '<div class="aurora-star-tabs-pane' . $is_act . '" id="' . $id . '" role="tabpanel">' . do_shortcode( $match[5] ) . '</div>';
	}

	$nav  .= '</div>';
	$panes = '<div class="aurora-star-tabs-content">' . $panes . '</div>';

	return $nav . $panes . '</div>';
}
add_shortcode( 'tabs', 'aurora_star_sc_tabs' );

/**
 * [tab] 标签（配合 [tabs]）。
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_tab( $atts, $content = '' ) {
	// 不处理，交由外层 [tabs] 解析。
	return '';
}
add_shortcode( 'tab', 'aurora_star_sc_tab' );

/**
 * [accordion] 手风琴。
 * 用法：
 * [accordion]
 *   [accordion-item title="标题"]内容[/accordion-item]
 * [/accordion]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_accordion( $atts, $content = '' ) {
	$pattern = get_shortcode_regex( array( 'accordion-item' ) );
	preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER );

	if ( empty( $matches ) ) {
		return do_shortcode( $content );
	}

	$html = '<div class="aurora-star-accordion">';
	foreach ( $matches as $match ) {
		$item_atts = shortcode_parse_atts( $match[3] );
		$title     = isset( $item_atts['title'] ) ? $item_atts['title'] : __( '标题', 'aurora-star' );
		$open      = isset( $item_atts['open'] ) && 'true' === $item_atts['open'];
		$id        = 'aurora-star-acc-' . wp_generate_password( 6, false, false );

		$html .= '<div class="aurora-star-accordion-item' . ( $open ? ' is-open' : '' ) . '">';
		$html .= '<button type="button" class="aurora-star-accordion-head" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . $id . '">';
		$html .= '<span class="aurora-star-accordion-title">' . esc_html( $title ) . '</span>';
		$html .= '<i class="fa-solid fa-chevron-down aurora-star-accordion-icon" aria-hidden="true"></i>';
		$html .= '</button>';
		$html .= '<div class="aurora-star-accordion-body" id="' . $id . '">';
		$html .= do_shortcode( $match[5] );
		$html .= '</div></div>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'accordion', 'aurora_star_sc_accordion' );

/**
 * [accordion-item] 手风琴项（配合 [accordion]）。
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_accordion_item( $atts, $content = '' ) {
	return '';
}
add_shortcode( 'accordion-item', 'aurora_star_sc_accordion_item' );

/**
 * [code] 代码高亮。
 * 用法：[code lang="php" line="true"]代码[/code]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_code( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'lang' => '',
			'line' => 'false',
		),
		$atts,
		'code'
	);

	// 移除 pre/code 包装并解码实体。
	$code = preg_replace( '/^<pre[^>]*>/i', '', trim( $content ) );
	$code = preg_replace( '/<\/pre>$/i', '', $code );
	$code = preg_replace( '/^<code[^>]*>/i', '', $code );
	$code = preg_replace( '/<\/code>$/i', '', $code );
	$code = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

	$lang = strtolower( sanitize_html_class( $atts['lang'] ) );
	$cls  = 'language-' . ( $lang ? $lang : 'markup' );
	$extra = '';

	if ( 'true' === $atts['line'] ) {
		$extra .= ' line-numbers';
	}

	$code = trim( $code );
	$code = htmlspecialchars( $code, ENT_NOQUOTES, get_bloginfo( 'charset' ) );

	// 保持换行与缩进。
	$code = str_replace( "\t", '    ', $code );

	return '<pre class="' . esc_attr( $cls . $extra ) . '"><code class="' . esc_attr( $cls ) . '">' . $code . '</code></pre>';
}
add_shortcode( 'code', 'aurora_star_sc_code' );

/**
 * [youtube] 视频。
 * 用法：[youtube id="xxxxx" width="800"]
 *
 * @param array $atts 属性。
 * @return string
 */
function aurora_star_sc_youtube( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'    => '',
			'width' => '100%',
		),
		$atts,
		'youtube'
	);

	if ( empty( $atts['id'] ) ) {
		return '';
	}

	$id = sanitize_title( $atts['id'] );

	return '<div class="aurora-star-video"><iframe src="https://www.youtube-nocookie.com/embed/' . $id . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:' . esc_attr( $atts['width'] ) . '"></iframe></div>';
}
add_shortcode( 'youtube', 'aurora_star_sc_youtube' );

/**
 * [icon] 图标。
 * 用法：[icon name="fa-solid fa-heart" size="2x" color="#f00"]
 *
 * @param array $atts 属性。
 * @return string
 */
function aurora_star_sc_icon( $atts ) {
	$atts = shortcode_atts(
		array(
			'name'  => 'fa-solid fa-star',
			'size'  => '1x',
			'color' => '',
		),
		$atts,
		'icon'
	);

	$sizes = array( 'xs', 'sm', 'lg', 'xl', '2xl', '1x', '2x', '3x', '4x', '5x' );
	$size  = in_array( $atts['size'], $sizes, true ) ? ' fa-' . $atts['size'] : '';

	$color = $atts['color'] ? ' style="color:' . esc_attr( $atts['color'] ) . '"' : '';

	return '<i class="' . esc_attr( $atts['name'] ) . $size . '" aria-hidden="true"' . $color . '></i>';
}
add_shortcode( 'icon', 'aurora_star_sc_icon' );

/**
 * [notice] 免责声明（底部小字）。
 * 用法：[notice]这是声明内容[/notice]
 *
 * @param array  $atts    属性。
 * @param string $content 内容。
 * @return string
 */
function aurora_star_sc_notice( $atts, $content = '' ) {
	return '<div class="aurora-star-notice">' . do_shortcode( $content ) . '</div>';
}
add_shortcode( 'notice', 'aurora_star_sc_notice' );

/**
 * 文章页禁用目录的快捷方式（需要后台编辑插入 meta，此函数供模板判断）。
 *
 * @return void
 */
function aurora_star_shortcodes_admin_notice() {
	// 保留占位，说明短码均在自定义器有说明。
}
