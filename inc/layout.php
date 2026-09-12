<?php
/**
 * 文章列表布局：卡片 / 列表 / 紧凑网格。
 *
 * 三种布局共用同一份标记（template-parts/content-card.php），
 * 只靠容器上的修饰类切换观感，因此模板里不需要分支。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 可选布局。
 *
 * @return array
 */
function aurora_star_layout_choices() {
	return array(
		'card'    => __( '卡片（默认，响应式网格）', 'aurora-star' ),
		'list'    => __( '列表（缩略图在左的通栏行）', 'aurora-star' ),
		'compact' => __( '紧凑网格（小卡、不显示摘要）', 'aurora-star' ),
	);
}

/**
 * 桌面端列数。
 *
 * @return array
 */
function aurora_star_column_choices() {
	return array(
		'auto' => __( '自适应（手机 1 / 平板 2 / 桌面 3）', 'aurora-star' ),
		'2'    => __( '2 栏', 'aurora-star' ),
		'3'    => __( '3 栏', 'aurora-star' ),
		'4'    => __( '4 栏', 'aurora-star' ),
	);
}

/**
 * 当前页面是否套用自定义布局。
 *
 * 三个范围开关默认全开；关掉某一个，该页就回到主题内置的卡片网格。
 *
 * @return bool
 */
function aurora_star_layout_applies() {
	if ( is_search() ) {
		return (bool) get_theme_mod( 'aurora_star_layout_on_search', true );
	}

	if ( is_archive() ) {
		return (bool) get_theme_mod( 'aurora_star_layout_on_archive', true );
	}

	if ( is_home() ) {
		return (bool) get_theme_mod( 'aurora_star_layout_on_home', true );
	}

	return false;
}

/**
 * 当前生效的布局。
 *
 * @return string card|list|compact
 */
function aurora_star_list_layout() {
	if ( ! aurora_star_layout_applies() ) {
		return 'card';
	}

	$layout = (string) get_theme_mod( 'aurora_star_list_layout', 'card' );

	return array_key_exists( $layout, aurora_star_layout_choices() ) ? $layout : 'card';
}

/**
 * 当前生效的桌面列数。
 *
 * @return string auto|2|3|4
 */
function aurora_star_list_columns() {
	$cols = (string) get_theme_mod( 'aurora_star_list_columns', 'auto' );

	return array_key_exists( $cols, aurora_star_column_choices() ) ? $cols : 'auto';
}

/**
 * 是否显示摘要。
 *
 * @return bool
 */
function aurora_star_list_show_excerpt() {
	return (bool) get_theme_mod( 'aurora_star_list_excerpt', true );
}

/**
 * 列表容器的 class。
 *
 * @return string
 */
function aurora_star_post_grid_class() {
	$classes = array( 'post-grid' );
	$layout  = aurora_star_list_layout();

	if ( 'card' !== $layout ) {
		$classes[] = 'post-grid--' . $layout;
	}

	if ( ! aurora_star_list_show_excerpt() ) {
		$classes[] = 'post-grid--no-excerpt';
	}

	return implode( ' ', $classes );
}

/**
 * 布局相关的动态 CSS。
 *
 * 只在需要覆盖主题内置的自适应网格时才输出；列表布局固定单栏。
 * 这段 CSS 走 wp_head 优先级 20，排在所有样式表之后，同优先级下后来者胜。
 *
 * @return string
 */
function aurora_star_layout_css() {
	if ( ! aurora_star_layout_applies() ) {
		return '';
	}

	$layout = aurora_star_list_layout();

	// 列表布局永远单栏。
	if ( 'list' === $layout ) {
		return '.post-grid.post-grid--list{grid-template-columns:1fr;}';
	}

	$cols = aurora_star_list_columns();

	// 自适应 = 沿用 main.css 里的 1 → 2 → 3。
	if ( 'auto' === $cols ) {
		return '';
	}

	$n = (int) $cols;
	if ( $n < 1 || $n > 4 ) {
		return '';
	}

	// 手机始终 1 栏，平板 2 栏，桌面用用户选的列数。
	return '.post-grid{grid-template-columns:1fr;}'
		. '@media(min-width:640px){.post-grid{grid-template-columns:repeat(2,1fr);}}'
		. '@media(min-width:1024px){.post-grid{grid-template-columns:repeat(' . $n . ',1fr);}}';
}
