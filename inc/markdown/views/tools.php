<?php
/**
 * 后台视图：批量重新渲染。
 *
 * @package Mdp
 * @var Mdp_Plugin $plugin
 */

defined( 'ABSPATH' ) || exit;

$post_types = $plugin->enabled_post_types();
$counts     = array();
foreach ( $post_types as $type ) {
	$query = new WP_Query(
		array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => MDP_META_SOURCE,
					'compare' => 'EXISTS',
				),
			),
		)
	);
	$counts[ $type ] = (int) $query->found_posts;
}
?>
<div class="wrap mdp-wrap">
	<h1><?php esc_html_e( '批量重新渲染', 'wp-markdown-publisher' ); ?></h1>

	<p class="mdp-lead">
		<?php esc_html_e( '解析器或渲染设置发生变化后，可以用这里把已保存的 Markdown 原文重新渲染成最新格式的 HTML。建议先用少量文章试一次。', 'wp-markdown-publisher' ); ?>
	</p>

	<div class="mdp-card">
		<table class="widefat striped mdp-table">
			<thead>
				<tr>
					<th><?php esc_html_e( '文章类型', 'wp-markdown-publisher' ); ?></th>
					<th><?php esc_html_e( 'Markdown 文章数', 'wp-markdown-publisher' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $post_types ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( '还没有启用任何文章类型，请先到设置里选择。', 'wp-markdown-publisher' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $post_types as $type ) : ?>
						<?php $object = get_post_type_object( $type ); ?>
						<tr>
							<td><?php echo esc_html( $object ? $object->labels->singular_name : $type ); ?> <code><?php echo esc_html( $type ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( isset( $counts[ $type ] ) ? $counts[ $type ] : 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="mdp-actions">
			<label for="mdp-rerender-per-page"><?php esc_html_e( '每批数量', 'wp-markdown-publisher' ); ?></label>
			<input type="number" id="mdp-rerender-per-page" value="20" min="1" max="100" class="small-text" />
			<button type="button" class="button button-primary" id="mdp-rerender-start"><?php esc_html_e( '开始重新渲染', 'wp-markdown-publisher' ); ?></button>
			<span class="spinner" id="mdp-rerender-spinner"></span>
		</p>

		<div class="mdp-progress" id="mdp-progress" hidden>
			<div class="mdp-progress__bar"><span id="mdp-progress-fill"></span></div>
			<div class="mdp-progress__text" id="mdp-progress-text"></div>
		</div>

		<div id="mdp-rerender-log" class="mdp-log"></div>
	</div>

	<div class="mdp-card">
		<h2><?php esc_html_e( '命令行方式', 'wp-markdown-publisher' ); ?></h2>
		<p><?php esc_html_e( '如果服务器上安装了 WP-CLI，也可以直接使用命令行：', 'wp-markdown-publisher' ); ?></p>
<pre class="mdp-code-sample">wp mdp rerender --post_type=post
wp mdp import ./posts --post_type=post --status=draft</pre>
	</div>
</div>
