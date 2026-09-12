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
 * 去掉块级短码内容首尾由 wpautop 插入的换行标记。
 *
 * wpautop 会把短码内容里的换行转成 <br />。对于块级短码（tabs 面板、手风琴内容、
 * 提示框正文），这些 <br /> 只会在区块上下顶出多余空白，因此需要在首尾剔除；
 * 中间的 <br /> 属于正文换行，必须保留。
 *
 * @param string $html 内容 HTML。
 * @return string
 */
function aurora_star_trim_block_breaks( $html ) {
	$html = trim( (string) $html );

	// 去掉首尾连续的 <br> / <br/> / <br />（含其间的空白）。
	$html = preg_replace( '#^(?:\s*<br\s*/?>\s*)+#i', '', $html );
	$html = preg_replace( '#(?:\s*<br\s*/?>\s*)+$#i', '', $html );

	return $html;
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

	return '<div class="aurora-star-alert aurora-star-alert-' . $type . '">' . $title . '<div class="aurora-star-alert-body">' . do_shortcode( aurora_star_trim_block_breaks( $content ) ) . '</div></div>';
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

	return '<div class="aurora-star-note">' . $title . '<div class="aurora-star-note-body">' . do_shortcode( aurora_star_trim_block_breaks( $content ) ) . '</div></div>';
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
	$group  = wp_unique_id( 'aurora-star-tabs-' );

	// 注意：role="tablist" 只能出现在直接包含 role="tab" 的元素上（此处为 .aurora-star-tabs-nav）。
	$nav  = '<div class="aurora-star-tabs" data-aurora-tabs>';
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
		// 标题来自短码属性（正文），可能未经 kses 过滤（导入/REST/插件写入），必须过滤后再输出。
		$title = wp_kses_post( do_shortcode( $title ) );

		$tab_id   = $group . 'tab-' . $i;
		$pane_id  = $group . 'pane-' . $i;
		$is_act   = ( $i === $active );
		$class    = 'aurora-star-tabs-tab' . ( $is_act ? ' is-active' : '' );

		$nav  .= '<button type="button" id="' . esc_attr( $tab_id ) . '" class="' . esc_attr( $class ) . '"'
			. ' role="tab" aria-selected="' . ( $is_act ? 'true' : 'false' ) . '"'
			. ' aria-controls="' . esc_attr( $pane_id ) . '" tabindex="' . ( $is_act ? '0' : '-1' ) . '">'
			. $title . '</button>';

		$panes .= '<div class="aurora-star-tabs-pane' . ( $is_act ? ' is-active' : '' ) . '"'
			. ' id="' . esc_attr( $pane_id ) . '" role="tabpanel"'
			. ' aria-labelledby="' . esc_attr( $tab_id ) . '" tabindex="0">'
			. do_shortcode( aurora_star_trim_block_breaks( $match[5] ) ) . '</div>';
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

	$html  = '<div class="aurora-star-accordion" data-aurora-accordion>';
	$group = wp_unique_id( 'aurora-star-acc-' );
	$i     = 0;
	foreach ( $matches as $match ) {
		$i++;
		$item_atts = shortcode_parse_atts( $match[3] );
		$title     = isset( $item_atts['title'] ) ? $item_atts['title'] : __( '标题', 'aurora-star' );
		$open      = isset( $item_atts['open'] ) && 'true' === $item_atts['open'];
		$head_id   = $group . 'head-' . $i;
		$body_id   = $group . 'body-' . $i;

		$html .= '<div class="aurora-star-accordion-item' . ( $open ? ' is-open' : '' ) . '" data-aurora-accordion-item>';
		$html .= '<button type="button" id="' . esc_attr( $head_id ) . '" class="aurora-star-accordion-head"'
			. ' aria-expanded="' . ( $open ? 'true' : 'false' ) . '"'
			. ' aria-controls="' . esc_attr( $body_id ) . '">';
		$html .= '<span class="aurora-star-accordion-title">' . esc_html( $title ) . '</span>';
		$html .= '<i class="fa-solid fa-chevron-down aurora-star-accordion-icon" aria-hidden="true"></i>';
		$html .= '</button>';
		$html .= '<div class="aurora-star-accordion-body" id="' . esc_attr( $body_id ) . '"'
			. ' role="region" aria-labelledby="' . esc_attr( $head_id ) . '">';
		// 内层容器提供内边距（见 main.css 的 .aurora-star-accordion-body-inner）。
		$html .= '<div class="aurora-star-accordion-body-inner">' . do_shortcode( aurora_star_trim_block_breaks( $match[5] ) ) . '</div>';
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
			'line' => 'auto',
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

	// 别名归一化（c++ → cpp 等）；sanitize_html_class 会剥掉 + / #，不能直接用。
	$lang  = aurora_star_normalize_prism_language( $atts['lang'] );
	$cls   = 'language-' . ( $lang ? $lang : 'markup' );
	$extra = '';

	// line 属性：true=强制显示行号，false=强制隐藏，auto/未指定=跟随全局设置。
	if ( 'true' === $atts['line'] ) {
		$extra .= ' line-numbers';
	} elseif ( 'false' === $atts['line'] ) {
		$extra .= ' no-line-numbers';
	}

	$code = trim( $code );
	$code = htmlspecialchars( $code, ENT_NOQUOTES, get_bloginfo( 'charset' ) );

	// 保持换行与缩进。
	$code = str_replace( "\t", '    ', $code );

	return '<pre class="' . esc_attr( $cls . $extra ) . '"><code class="' . esc_attr( $cls ) . '">' . $code . '</code></pre>';
}
add_shortcode( 'code', 'aurora_star_sc_code' );

/**
 * wpautop 之前把 [code] 的内容换成不含换行的 base64 占位短码。
 *
 * 背景：the_content 上的优先级是 wptexturize(10) → wpautop(10) → shortcode_unautop(10)
 * → do_shortcode(11)，也就是 wpautop 在短码展开**之前**就跑了。它会把代码里的
 * 换行变成 <br />、空行变成 </p><p>，等 [code] 执行时这些标记已被 htmlspecialchars
 * 转义，于是代码块里会混入字面量的 <br /> 和 </p>。
 *
 * 这里在优先级 9（早于 wpautop）先把内容 base64 化，内容变成单行、不含换行，
 * wpautop 便无从下手；短码执行时再解码还原。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_protect_code_shortcode( $content ) {
	if ( false === strpos( $content, '[code' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'/\[code(\s[^\]]*)?\](.*?)\[\/code\]/is',
		function ( $matches ) {
			$atts = isset( $matches[1] ) ? $matches[1] : '';

			// 用 base64 承载原文，避免 wpautop 改动内容。
			return '[aurora_star_code' . $atts . ']' . base64_encode( $matches[2] ) . '[/aurora_star_code]';
		},
		$content
	);
}
add_filter( 'the_content', 'aurora_star_protect_code_shortcode', 9 );

/**
 * 内部占位短码：解码后交给 [code] 的处理函数。
 *
 * @param array  $atts    短码属性。
 * @param string $content base64 内容。
 * @return string
 */
function aurora_star_sc_code_encoded( $atts, $content = '' ) {
	$decoded = base64_decode( (string) $content, true );

	return aurora_star_sc_code( $atts, false === $decoded ? '' : $decoded );
}
add_shortcode( 'aurora_star_code', 'aurora_star_sc_code_encoded' );

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

	// YouTube 视频 ID 大小写敏感，不能用 sanitize_title()（它内部会强制 strtolower）。
	$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $atts['id'] );
	if ( '' === $id ) {
		return '';
	}

	// width 仅接受纯数字或数字 + px/%，避免任意 CSS 值进入 style 属性。
	$width = trim( (string) $atts['width'] );
	if ( ! preg_match( '/^\d+(?:\.\d+)?(?:px|%)?$/', $width ) ) {
		$width = '100%';
	}

	return '<div class="aurora-star-video"><iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $id ) . '" title="' . esc_attr__( 'YouTube 视频', 'aurora-star' ) . '" loading="lazy" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:' . esc_attr( $width ) . '"></iframe></div>';
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
	return '<div class="aurora-star-notice">' . do_shortcode( aurora_star_trim_block_breaks( $content ) ) . '</div>';
}
add_shortcode( 'notice', 'aurora_star_sc_notice' );
