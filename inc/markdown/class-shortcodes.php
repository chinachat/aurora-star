<?php
/**
 * 短代码：[markdown] 与 [mdp_toc]。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Shortcodes', false ) ) {
	return;
}

/**
 * 短代码模块。
 */
class Mdp_Shortcodes {

	/**
	 * 插件实例。
	 *
	 * @var Mdp_Plugin
	 */
	protected $plugin;

	/**
	 * 构造。
	 *
	 * @param Mdp_Plugin $plugin 插件实例。
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_shortcode( 'markdown', array( $this, 'shortcode_markdown' ) );
		add_shortcode( 'md', array( $this, 'shortcode_markdown' ) );
		add_shortcode( 'mdp_toc', array( $this, 'shortcode_toc' ) );

		// wpautop 会破坏短代码里的 Markdown，因此在它之前先渲染好。
		// 优先级 7：早于主题自己的 aurora_star_protect_content(8)。两者不再同档，
		// 执行顺序不再依赖「谁先注册」这种加载顺序上的巧合。
		add_filter( 'the_content', array( $this, 'pre_render_markdown_blocks' ), 7 );
	}

	/**
	 * [markdown]短代码。
	 *
	 * 由 do_shortcode 调用（the_content 优先级 11），此时正文可能已经被
	 * wpautop 处理过，因此需要还原段落标记。
	 *
	 * @param array  $atts    属性。
	 * @param string $content 内容。
	 * @return string
	 */
	public function shortcode_markdown( $atts, $content = '' ) {
		return $this->render_block( $atts, $content, true );
	}

	/**
	 * 渲染一个 [markdown] 区块。
	 *
	 * @param array  $atts          属性。
	 * @param string $content       内容。
	 * @param bool   $undo_wpautop  是否还原 wpautop 留下的段落标记。
	 * @return string
	 */
	protected function render_block( $atts, $content = '', $undo_wpautop = true ) {
		$atts = shortcode_atts(
			array(
				'toc'     => '0',
				'html'    => '',
				'tables'  => '',
				'heading' => '',
			),
			$atts,
			'markdown'
		);

		$content = (string) $content;
		if ( $undo_wpautop ) {
			$content = $this->normalize_shortcode_content( $content );
		}

		if ( '' === trim( $content ) ) {
			return '';
		}

		$args = array();
		if ( '0' === (string) $atts['toc'] ) {
			$args['toc_min'] = 99;
			$args['toc_max'] = 99;
		}
		if ( '' !== $atts['html'] ) {
			$args['allow_html'] = (bool) intval( $atts['html'] );
		}
		if ( '' !== $atts['tables'] ) {
			$args['tables'] = (bool) intval( $atts['tables'] );
		}
		if ( '' !== $atts['heading'] ) {
			$args['heading_ids'] = (bool) intval( $atts['heading'] );
		}

		$result = $this->plugin->render( $content, $args );

		return '<div class="mdp-content mdp-content--shortcode">' . $result['html'] . '</div>';
	}

	/**
	 * [mdp_toc] 短代码。
	 *
	 * @param array $atts 属性。
	 * @return string
	 */
	public function shortcode_toc( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
				'min'   => '',
				'max'   => '',
			),
			$atts,
			'mdp_toc'
		);

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$html = (string) get_post_meta( $post_id, MDP_META_TOC, true );

		if ( '' === $html ) {
			$html = $this->toc_from_html( (string) get_post_field( 'post_content', $post_id ), $atts );
		}

		if ( '' === $html ) {
			return '';
		}

		return '<div class="mdp-toc-wrap">' . $html . '</div>';
	}

	/**
	 * 从已有 HTML 生成目录（用于非 Markdown 文章）。
	 *
	 * @param string $html HTML。
	 * @param array  $atts 属性。
	 * @return string
	 */
	protected function toc_from_html( $html, $atts ) {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$min = ( '' !== $atts['min'] ) ? (int) $atts['min'] : (int) $this->plugin->option( 'toc_min', 1 );
		$max = ( '' !== $atts['max'] ) ? (int) $atts['max'] : (int) $this->plugin->option( 'toc_max', 4 );
		$min = max( 1, min( 6, $min ) );
		$max = max( $min, min( 6, $max ) );

		if ( ! preg_match_all( '#<h([1-6])([^>]*)>(.*?)</h\1>#is', $html, $matches, PREG_SET_ORDER ) ) {
			return '';
		}

		$items = array();
		$index = 0;
		foreach ( $matches as $match ) {
			$level = (int) $match[1];
			if ( $level < $min || $level > $max ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( $match[3] ) );
			if ( '' === $text ) {
				continue;
			}
			$id = '';
			if ( preg_match( '/\sid=["\']([^"\']+)["\']/', $match[2], $idMatch ) ) {
				$id = $idMatch[1];
			} else {
				$index++;
				$id = 'mdp-h-' . $index;
			}
			$items[] = array(
				'level' => $level,
				'id'    => $id,
				'text'  => $text,
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		$title = ( '' !== $atts['title'] ) ? $atts['title'] : (string) $this->plugin->option( 'toc_title', '目录' );

		$out  = '<nav class="mdp-toc" aria-label="' . esc_attr( $title ) . '">' . "\n";
		$out .= '<div class="mdp-toc-title">' . esc_html( $title ) . "</div>\n";
		$out .= '<ol class="mdp-toc-list mdp-toc-depth-1">' . "\n";

		$current = 0;
		foreach ( $items as $item ) {
			if ( 0 === $current ) {
				$current = $item['level'];
			} elseif ( $item['level'] > $current ) {
				$out    .= "\n<ol class=\"mdp-toc-list mdp-toc-depth-2\">\n";
				$current = $item['level'];
			} elseif ( $item['level'] < $current ) {
				$out    .= "</li>\n</ol>\n</li>\n";
				$current = $item['level'];
			} else {
				$out .= "</li>\n";
			}
			$out .= '<li class="mdp-toc-item mdp-toc-h' . (int) $item['level'] . '"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
		}
		$out .= "</li>\n</ol>\n</nav>\n";

		return $out;
	}

	/**
	 * 在 wpautop 之前把 [markdown] 区块渲染成 HTML。
	 *
	 * 本过滤器挂在 the_content 的优先级 8，早于 wpautop(10)，因此正文此时
	 * 还是用户书写的 Markdown 原文，**没有**被 wpautop 加过段落标记，
	 * 不能调用 normalize_shortcode_content()：那会把原文里合法的 </p><p>
	 * （例如 HTML 教学示例）误当成 wpautop 的产物拆掉。
	 *
	 * @param string $content 内容。
	 * @return string
	 */
	public function pre_render_markdown_blocks( $content ) {
		if ( false === strpos( $content, '[markdown' ) && false === strpos( $content, '[md]' ) ) {
			return $content;
		}

		$pattern = '/\[(markdown|md)\b([^\]]*)\](.*?)\[\/\1\]/is';

		$result = preg_replace_callback(
			$pattern,
			function ( $m ) {
				return $this->render_block( shortcode_parse_atts( $m[2] ), $m[3], false );
			},
			$content
		);

		return ( null === $result ) ? $content : $result;
	}

	/**
	 * 还原短代码内容里的自动转义。
	 *
	 * 仅在正文确实被 wpautop 处理过时才有意义：wpautop 会把每一段都包成
	 * <p>…</p>，段落之间留下 </p>\n<p>。因此先确认首尾确实被 <p> 包裹，
	 * 否则原样返回，避免改动用户手写的 HTML。
	 *
	 * @param string $content 内容。
	 * @return string
	 */
	protected function normalize_shortcode_content( $content ) {
		$probe = trim( (string) $content );
		if ( 0 !== stripos( $probe, '<p>' ) && 0 !== stripos( $probe, '<p ' ) ) {
			return $content;
		}
		if ( ! preg_match( '#</p>\s*$#i', $probe ) ) {
			return $content;
		}

		$content = str_replace( array( '<br />', '<br/>', '<br>' ), "\n", $content );
		$content = preg_replace( '#</p>\s*<p>#i', "\n\n", $content );
		$content = preg_replace( '#^\s*<p>#i', '', $content );
		$content = preg_replace( '#</p>\s*$#i', '', $content );
		return $content;
	}
}
