<?php
/**
 * 后台一级主题设置菜单。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册一级菜单 "Aurora Star 主题设置"。
 */
function aurora_star_admin_menu() {
	add_menu_page(
		__( 'Aurora Star 主题设置', 'aurora-star' ),
		__( 'Aurora Star 主题', 'aurora-star' ),
		'manage_options',
		'aurora-star-settings',
		'aurora_star_admin_settings_page',
		'dashicons-admin-generic',
		61
	);

	add_submenu_page(
		'aurora-star-settings',
		__( '设置', 'aurora-star' ),
		__( '常规设置', 'aurora-star' ),
		'manage_options',
		'aurora-star-settings',
		'aurora_star_admin_settings_page'
	);
}
add_action( 'admin_menu', 'aurora_star_admin_menu' );

/**
 * 设置页内容。
 */
function aurora_star_admin_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$customize_url = admin_url( 'customize.php?autofocus[panel]=aurora_star_panel' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Aurora Star 主题设置', 'aurora-star' ); ?></h1>
		<p><?php esc_html_e( '全部主题选项均在 WordPress 自定义器中进行可视化配置，修改可实时预览。', 'aurora-star' ); ?></p>

		<a class="button button-primary button-hero" href="<?php echo esc_url( $customize_url ); ?>" style="margin: 16px 0;">
			<?php esc_html_e( '打开主题自定义器', 'aurora-star' ); ?>
		</a>

		<h2 style="margin-top: 28px;"><?php esc_html_e( '快捷设置说明', 'aurora-star' ); ?></h2>
		<table class="widefat striped" style="max-width: 720px;">
			<thead>
				<tr>
					<th><?php esc_html_e( '功能', 'aurora-star' ); ?></th>
					<th><?php esc_html_e( '说明', 'aurora-star' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( '暗黑模式', 'aurora-star' ); ?></td>
					<td><?php esc_html_e( '自定义器 → Aurora Star 主题设置 → 暗黑模式', 'aurora-star' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '文章目录', 'aurora-star' ); ?></td>
					<td><?php esc_html_e( '自定义器 → Aurora Star 主题设置 → 文章目录；文章编辑页侧栏可单独隐藏目录', 'aurora-star' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '代码高亮', 'aurora-star' ); ?></td>
					<td><?php esc_html_e( '自定义器 → Aurora Star 主题设置 → 代码高亮', 'aurora-star' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '图片灯箱', 'aurora-star' ); ?></td>
					<td><?php esc_html_e( '自定义器 → Aurora Star 主题设置 → 图片灯箱', 'aurora-star' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '菜单图标', 'aurora-star' ); ?></td>
					<td><?php esc_html_e( '外观 → 菜单，编辑菜单项时填写 Font Awesome 图标类名（如 fa-solid fa-home）', 'aurora-star' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '常用短码', 'aurora-star' ); ?></td>
					<td><?php echo wp_kses_post( __( '[button] [alert] [note] [tabs] [accordion] [code lang="php"] [youtube id=""] [icon name="fa-solid fa-star"] [notice]', 'aurora-star' ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}
