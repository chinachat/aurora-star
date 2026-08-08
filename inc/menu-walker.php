<?php
/**
 * 菜单 Walker：支持图标与描述。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 自定义菜单 Walker，输出图标与描述。
 */
class Aurora_Walker_Nav_Menu extends Walker_Nav_Menu {

	/**
	 * 开始输出一个顶层/子菜单项。
	 *
	 * @param string   $output 引用输出。
	 * @param WP_Post  $item   菜单项。
	 * @param int      $depth  深度。
	 * @param stdClass $args   参数。
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
			$t = '';
			$n = '';
		} else {
			$t = "\t";
			$n = "\n";
		}
		$indent = ( $depth ) ? str_repeat( $t, $depth ) : '';

		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		if ( in_array( 'menu-item-has-children', $classes, true ) && 0 === $depth ) {
			$classes[] = 'has-children';
		}

		$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		$id_attr = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
		$id_attr = $id_attr ? ' id="' . esc_attr( $id_attr ) . '"' : '';

		$output .= $indent . '<li' . $id_attr . $class_names . '>';

		$atts           = array();
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
		$atts['href']   = ! empty( $item->url ) ? $item->url : '';
		$atts['class']  = '';

		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$icon      = get_post_meta( $item->ID, '_aurora_star_menu_icon', true );
		$icon_html = '';
		if ( $icon ) {
			$icon_html = '<i class="menu-item-icon ' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
		}

		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

		$item_output  = $args->before;
		$item_output .= '<a' . $attributes . '>';
		$item_output .= $icon_html;
		$item_output .= $args->link_before . '<span class="menu-item-title">' . $title . '</span>' . $args->link_after;

		if ( in_array( 'menu-item-has-children', $classes, true ) ) {
			$item_output .= '<i class="fa-solid fa-chevron-down menu-item-caret" aria-hidden="true"></i>';
		}

		$item_output .= '</a>';
		$item_output .= $args->after;

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	/**
	 * 子菜单容器。
	 *
	 * @param string   $output 引用输出。
	 * @param int      $depth  深度。
	 * @param stdClass $args   参数。
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
			$t = '';
			$n = '';
		} else {
			$t = "\t";
			$n = "\n";
		}
		$indent  = str_repeat( $t, $depth );
		$output .= "{$n}{$indent}<ul class=\"sub-menu\">{$n}";
	}
}

/**
 * 菜单项表单：图标字段。
 *
 * @param string $item_output 表单 HTML。
 * @param object $item        菜单项。
 * @param int    $depth       深度。
 * @param array  $args        参数。
 * @param int    $id          菜单 ID。
 * @return string
 */
function aurora_star_menu_item_icon_field( $item_output, $item, $depth, $args, $id ) {
	$icon = get_post_meta( $item->ID, '_aurora_star_menu_icon', true );

	$field  = '<p class="field-aurora-star-icon description description-wide">';
	$field .= '<label for="edit-menu-item-aurora-star-icon-' . $item->ID . '">';
	$field .= __( '图标（Font Awesome 类名，如：fa-solid fa-home）', 'aurora-star' );
	$field .= '<br><input type="text" id="edit-menu-item-aurora-star-icon-' . $item->ID . '" class="widefat edit-menu-item-aurora-star-icon" name="aurora_star_menu_icon[' . $item->ID . ']" value="' . esc_attr( $icon ) . '" placeholder="fa-solid fa-home" />';
	$field .= '</label></p>';

	$item_output .= $field;

	return $item_output;
}
add_filter( 'wp_nav_menu_item_custom_fields', 'aurora_star_menu_item_icon_field', 10, 5 );

/**
 * 保存菜单图标。
 *
 * @param int   $menu_id         菜单 ID。
 * @param array $menu_item_db_id 菜单项 ID 列表。
 */
function aurora_star_menu_item_icon_save( $menu_id, $menu_item_db_id ) {
	if ( isset( $_POST['aurora_star_menu_icon'][ $menu_item_db_id ] ) ) {
		$icon = sanitize_text_field( wp_unslash( $_POST['aurora_star_menu_icon'][ $menu_item_db_id ] ) );
		update_post_meta( $menu_item_db_id, '_aurora_star_menu_icon', $icon );
	} else {
		delete_post_meta( $menu_item_db_id, '_aurora_star_menu_icon' );
	}
}
add_action( 'wp_update_nav_menu_item', 'aurora_star_menu_item_icon_save', 10, 2 );
