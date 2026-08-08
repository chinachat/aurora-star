<?php
/**
 * 服务端目录（TOC）生成。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 根据内容解析标题结构生成目录树。
 *
 * @param string $content 文章内容（已带 id 锚点）。
 * @return array 目录树。
 */
function aurora_star_toc_extract( $content ) {
	$depth = (int) get_theme_mod( 'aurora_star_toc_depth', 3 );
	$depth = max( 1, min( 6, $depth ) );

	$tags = array();
	for ( $i = 2; $i <= $depth; $i++ ) {
		$tags[] = "h{$i}";
	}

	if ( empty( $tags ) ) {
		return array();
	}

	$pattern = '/<(' . implode( '|', $tags ) . ')([^>]*)>(.*?)<\/\1>/is';
	preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

	if ( empty( $matches ) ) {
		return array();
	}

	// 拍平标题列表。
	$flat = array();
	foreach ( $matches as $match ) {
		$tag = (int) substr( $match[1], 1 );

		$id = '';
		if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $match[2], $id_m ) ) {
			$id = $id_m[1];
		}

		$text = trim( wp_strip_all_tags( $match[3] ) );
		if ( '' === $id || '' === $text ) {
			continue;
		}

		$flat[] = array(
			'id'    => $id,
			'text'  => $text,
			'depth' => $tag,
		);
	}

	if ( empty( $flat ) ) {
		return array();
	}

	// 依据标题级别构建树。
	$tree  = array();
	$stack = array(); // 存放父节点的引用。

	foreach ( $flat as $item ) {
		while ( ! empty( $stack ) && $stack[ count( $stack ) - 1 ]['depth'] >= $item['depth'] ) {
			array_pop( $stack );
		}

		$node = array(
			'id'       => $item['id'],
			'text'     => $item['text'],
			'depth'    => $item['depth'],
			'children' => array(),
		);

		if ( empty( $stack ) ) {
			$tree[] = $node;
			$stack[] = &$tree[ count( $tree ) - 1 ];
		} else {
			$idx = count( $stack ) - 1;
			$stack[ $idx ]['children'][] = $node;
			$stack[] = &$stack[ $idx ]['children'][ count( $stack[ $idx ]['children'] ) - 1 ];
		}
	}

	return $tree;
}

/**
 * 递归渲染目录树 HTML。
 *
 * @param array $tree 目录树。
 * @return string
 */
function aurora_star_toc_render( $tree ) {
	if ( empty( $tree ) ) {
		return '';
	}

	$html = '<ul class="aurora-star-toc-list">';
	foreach ( $tree as $node ) {
		$has_children = ! empty( $node['children'] );
		$html .= '<li class="aurora-star-toc-item' . ( $has_children ? ' has-children' : '' ) . '" data-depth="' . $node['depth'] . '">';
		if ( $has_children ) {
			$html .= '<button type="button" class="aurora-star-toc-caret" aria-label="' . esc_attr__( '展开/折叠', 'aurora-star' ) . '"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>';
		}
		$html .= '<a class="aurora-star-toc-link" href="#' . esc_attr( $node['id'] ) . '">' . esc_html( $node['text'] ) . '</a>';
		if ( $has_children ) {
			$html .= aurora_star_toc_render( $node['children'] );
		}
		$html .= '</li>';
	}
	$html .= '</ul>';

	return $html;
}

/**
 * 获取目录 HTML（供模板调用）。
 *
 * @param int $post_id 文章 ID。
 * @return string
 */
function aurora_star_toc( $post_id = 0 ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$content = get_post_field( 'post_content', $post_id );

	// 字数过滤（支持中文）。
	$min_words = (int) get_theme_mod( 'aurora_star_toc_word_min', 300 );
	$plain     = wp_strip_all_tags( $content );
	$plain     = preg_replace( '/\s+/u', '', $plain );
	$word_count = function_exists( 'mb_strlen' ) ? mb_strlen( $plain ) : strlen( $plain );
	if ( $min_words > 0 && $word_count < $min_words ) {
		return '';
	}

	// 复用标题 id 处理函数为标题补 id（与正文输出保持一致）。
	if ( function_exists( 'aurora_star_ensure_heading_ids' ) ) {
		$content = aurora_star_ensure_heading_ids( $content );
	}
	$content = str_replace( ']]>', ']]&gt;', $content );

	$tree = aurora_star_toc_extract( $content );
	if ( empty( $tree ) ) {
		return '';
	}

	$html  = '<aside class="aurora-star-toc" data-toc>';
	$html .= '<div class="aurora-star-toc-head">';
	$html .= '<span class="aurora-star-toc-title"><i class="fa-solid fa-list" aria-hidden="true"></i> ' . __( '文章目录', 'aurora-star' ) . '</span>';
	$html .= '<button type="button" class="aurora-star-toc-toggle" aria-expanded="false"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>';
	$html .= '</div>';
	$html .= '<nav class="aurora-star-toc-body" aria-label="' . esc_attr__( '文章目录', 'aurora-star' ) . '">';
	$html .= '<div class="aurora-star-toc-progress" aria-hidden="true"><span class="aurora-star-toc-progress-bar"></span></div>';
	$html .= aurora_star_toc_render( $tree );
	$html .= '</nav></aside>';

	return $html;
}

/**
 * 文章元框：禁用目录（经典编辑器）。
 */
function aurora_star_toc_meta_box() {
	add_meta_box(
		'aurora_star_toc_meta',
		__( 'Aurora Star 文章选项', 'aurora-star' ),
		'aurora_star_toc_meta_box_cb',
		array( 'post', 'page' ),
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'aurora_star_toc_meta_box' );

/**
 * 元框内容。
 *
 * @param WP_Post $post 文章对象。
 */
function aurora_star_toc_meta_box_cb( $post ) {
	wp_nonce_field( 'aurora_star_toc_meta', 'aurora_star_toc_meta_nonce' );
	$disabled = get_post_meta( $post->ID, '_aurora_star_disable_toc', true );
	?>
	<p>
		<label>
			<input type="checkbox" name="aurora_star_disable_toc" value="1" <?php checked( $disabled, '1' ); ?> />
			<?php esc_html_e( '隐藏本文浮动目录', 'aurora-star' ); ?>
		</label>
	</p>
	<?php
}

/**
 * 保存元框数据。
 *
 * @param int $post_id 文章 ID。
 */
function aurora_star_toc_meta_save( $post_id ) {
	if ( ! isset( $_POST['aurora_star_toc_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aurora_star_toc_meta_nonce'] ) ), 'aurora_star_toc_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['aurora_star_disable_toc'] ) ) {
		update_post_meta( $post_id, '_aurora_star_disable_toc', '1' );
	} else {
		delete_post_meta( $post_id, '_aurora_star_disable_toc' );
	}
}
add_action( 'save_post', 'aurora_star_toc_meta_save' );
