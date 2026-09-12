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
 * 清理短码属性值：还原 HTML 实体并去掉包裹的引号。
 *
 * 从 Markdown / 富文本粘贴进 WordPress 的正文里，引号常被转义为 &quot;。
 * 直接把这些值交给 sanitize_html_class() 会得到 quotprimaryquot 这种无意义结果。
 *
 * @param string $value 属性值。
 * @return string
 */
function aurora_star_clean_attr( $value ) {
	$value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return trim( $value, " \t\n\r\0\x0B\"'" );
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
	if ( aurora_star_clean_attr( $atts['icon'] ) ) {
		$icon = '<i class="aurora-star-sc-btn-icon ' . esc_attr( aurora_star_clean_attr( $atts['icon'] ) ) . '" aria-hidden="true"></i>';
	}

	$target = aurora_star_clean_attr( $atts['target'] );
	$target = $target ? ' target="' . esc_attr( $target ) . '"' : '';

	$rel = aurora_star_clean_attr( $atts['rel'] );
	$rel = $rel ? ' rel="' . esc_attr( $rel ) . '"' : '';

	$extra_class = aurora_star_clean_attr( $atts['class'] );

	$class = 'aurora-star-btn aurora-star-btn-' . sanitize_html_class( aurora_star_clean_attr( $atts['color'] ) )
		. ' aurora-star-btn-' . sanitize_html_class( aurora_star_clean_attr( $atts['size'] ) )
		. ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' );

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
 * 清理内容管线在 [code] 正文里注入的段落包裹。
 *
 * 有些内容处理器（Markdown 插件、区块编辑器转换等）会先把正文渲染成 HTML 段落，
 * 再交给 do_shortcode。含空行的短码会被拆成两个 <p>：
 *
 *     <p>[code lang="php"]
 *     a = 1</p>
 *     <p>b = 2
 *     [/code]</p>
 *
 * 于是 [code] 拿到的正文变成 "…a = 1</p>\n<p>b = 2…"。这两个标签是段落标记而非代码，
 * 却会被下面的 htmlspecialchars() 转义成字面量，代码块里因此出现 &lt;/p&gt; 和 &lt;p&gt;。
 *
 * 只处理"孤立"的段落标签：</p> 所在行没有配对的 <p>，且下一行行首的 <p> 所在行没有
 * 配对的 </p>。像 HTML 代码示例那样成对出现在同一行的 <p>foo</p> 不受影响。
 *
 * @param string $code 代码原文。
 * @return string
 */
function aurora_star_repair_code_paragraphs( $code ) {
	if ( false === stripos( $code, '</p>' ) || false === stripos( $code, '<p>' ) ) {
		return $code;
	}

	$repaired = preg_replace_callback(
		'#^(?<before>[^\n]*?)</p>[ \t]*\n(?:[ \t]*\n)*[ \t]*<p>(?<after>[^\n]*)$#im',
		function ( $matches ) {
			// 同一行还有另一个 <p> 或 </p>，说明是真实成对的段落元素，保持原样。
			if ( preg_match( '#<p\b#i', $matches['before'] ) || preg_match( '#</p>#i', $matches['after'] ) ) {
				return $matches[0];
			}

			// 这一对孤立标签本来就是"空行"被段落化的结果，还原成空行。
			return $matches['before'] . "\n\n" . $matches['after'];
		},
		$code
	);

	return ( null === $repaired ) ? $code : $repaired;
}

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
	$code = aurora_star_repair_code_paragraphs( $code );
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
 * 这里在优先级 8（早于同挂在 9 的 do_blocks 与标题锚点过滤器）先做替换：
 * 内容变成单行、不含换行，wpautop 无从下手，其它基于正则改 HTML 的过滤器
 * （例如给正文标题补目录锚点的 aurora_star_heading_ids）也不会误伤代码示例。
 *
 * 正则同时吃掉短码开/闭标签外层的 <p> 段落包裹。块级用法下（短码标签独占一行）
 * Markdown 插件等渲染器会产出 `<p>[code …]</p>` 与 `<p>[/code]</p>`：
 * 只替换短码本身，这对 <p> 就会残缺一个——开标签前的 <p> 失去收尾，
 * wpautop 随后会替它补一个 </p>，于是正文里出现 `<p><pre>…</pre></p>` 这种非法嵌套；
 * 而那个 </p> 还会被卷进 base64 正文，变成代码里的字面量。
 * 因此整对包裹一起吃掉，并且只在开标签确实带 <p> 时才吃闭标签后的 </p>，
 * 避免误伤 `<p>文字 [code]…[/code]</p>` 这种行内用法。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_protect_code_shortcode( $content ) {
	if ( false === strpos( $content, '[code' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'#(?:(<p>)\s*)?\[code(\s[^\]]*)?\](?:</p>\s*(?!\[/code\]))?(.*?)\[/code\](?(1)(?:\s*</p>)?)#is',
		function ( $matches ) {
			$atts = isset( $matches[2] ) ? $matches[2] : '';

			// 用 base64 承载原文，避免被 wpautop 或其它过滤器改动。
			return '[aurora_star_code' . $atts . ']' . base64_encode( $matches[3] ) . '[/aurora_star_code]';
		},
		$content
	);
}
/**
 * 把 <pre> 与行内 <code> 里的短码语法转义，避免被 do_shortcode 执行。
 *
 * WordPress 核心并不会保护代码元素里的短码。当正文里出现
 * `<pre><code>[button href="…"]…[/button]</code></pre>`（Markdown 插件、Gutenberg
 * 代码块、或直接粘贴的文档）时，短码会被真的执行，代码示例因此变成渲染后的 UI。
 * 若属性里的引号已被转义（&quot;），还会解析出 `quotprimaryquot` 这种垃圾类名。
 *
 * **行内 <code> 同样必须处理。** Markdown 里写 `` `[code]` `` 会渲染成
 * `<code>[code]</code>`；它不在 <pre> 内，于是会被 aurora_star_protect_code_shortcode()
 * 当成真短码，一路吞到文档里下一个 `[/code]` 为止——整段正文被 base64 化并变成
 * 一个代码块，标题等结构全部被转义成文本。同理 `` `[markdown]` `` 会被真的执行成空块。
 *
 * 代码元素里的方括号只应作为文字显示，因此这里把 [ ] 转成实体；
 * 浏览器仍显示为 [ ]，但 do_shortcode 不再匹配。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_escape_pre_content( $content ) {
	if ( false === stripos( $content, '<pre' ) && false === stripos( $content, '<code' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'#<(pre|code)\b[^>]*>.*?</\1>#is',
		function ( $matches ) {
			return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $matches[0] );
		},
		$content
	);
}

/**
 * 在 wpautop / do_shortcode 之前统一保护正文里的代码内容。
 *
 * 顺序很重要：先转义代码元素（<pre> 与行内 <code>）内的方括号，再处理 [code] 短码。
 * 反过来的话，写在 <pre> 里的 [code] 会先被换成占位短码，随后仍会被执行。
 *
 * @param string $content 文章内容。
 * @return string
 */
function aurora_star_protect_content( $content ) {
	$content = aurora_star_escape_pre_content( $content );
	$content = aurora_star_protect_code_shortcode( $content );

	return $content;
}
add_filter( 'the_content', 'aurora_star_protect_content', 8 );

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
	$size  = aurora_star_clean_attr( $atts['size'] );
	$size  = in_array( $size, $sizes, true ) ? ' fa-' . $size : '';

	$color_value = aurora_star_clean_attr( $atts['color'] );
	$color       = $color_value ? ' style="color:' . esc_attr( $color_value ) . '"' : '';

	return '<i class="' . esc_attr( aurora_star_clean_attr( $atts['name'] ) ) . $size . '" aria-hidden="true"' . $color . '></i>';
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
