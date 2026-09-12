<?php
/**
 * 后台视图：设置。
 *
 * @package Mdp
 * @var Mdp_Plugin $plugin
 */

defined( 'ABSPATH' ) || exit;

$options    = $plugin->options();
$post_types = Mdp_Plugin::post_type_choices();
$authors    = get_users(
	array(
		'capability' => array( 'edit_posts' ),
		'number'     => 200,
		'fields'     => array( 'ID', 'display_name' ),
	)
);

/**
 * 输出一个开关。
 *
 * @param string $name    字段名。
 * @param string $label   标签。
 * @param mixed  $value   值。
 * @param string $hint    说明。
 * @return void
 */
function mdp_checkbox( $name, $label, $value, $hint = '' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	printf(
		'<label class="mdp-check"><input type="checkbox" name="mdp[%1$s]" value="1" %2$s /> <span>%3$s</span></label>',
		esc_attr( $name ),
		checked( ! empty( $value ), true, false ),
		esc_html( $label )
	);
	if ( '' !== $hint ) {
		printf( '<p class="description">%s</p>', esc_html( $hint ) );
	}
}
?>
<div class="wrap mdp-wrap">
	<h1><?php esc_html_e( 'Markdown 发布器设置', 'wp-markdown-publisher' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mdp_save_settings" />
		<?php wp_nonce_field( 'mdp_save_settings' ); ?>

		<div class="mdp-card">
			<h2><?php esc_html_e( '编辑器接管', 'wp-markdown-publisher' ); ?></h2>
			<p class="description"><?php esc_html_e( '选中的文章类型中，新建文章默认使用 Markdown 编辑器；已有文章保持原有编辑器，可在编辑页的「Markdown 编辑器」面板里手动开启。', 'wp-markdown-publisher' ); ?></p>
			<fieldset>
				<?php foreach ( $post_types as $value => $label ) : ?>
					<label class="mdp-check">
						<input type="checkbox" name="mdp[post_types][]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, (array) $options['post_types'], true ) ); ?> />
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '标题与摘要', 'wp-markdown-publisher' ); ?></th>
					<td>
						<?php
						mdp_checkbox( 'auto_title', __( '标题为空时用正文的第一个 # 标题作为文章标题', 'wp-markdown-publisher' ), $options['auto_title'] );
						mdp_checkbox( 'strip_first_h1', __( '用作标题后，从正文中移除这个 # 标题', 'wp-markdown-publisher' ), $options['strip_first_h1'] );
						mdp_checkbox( 'auto_excerpt', __( '摘要为空时自动生成摘要', 'wp-markdown-publisher' ), $options['auto_excerpt'] );
						?>
					</td>
				</tr>
			</table>
		</div>

		<div class="mdp-card">
			<h2><?php esc_html_e( '解析选项', 'wp-markdown-publisher' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '语法支持', 'wp-markdown-publisher' ); ?></th>
					<td>
						<?php
						mdp_checkbox( 'tables', __( '表格', 'wp-markdown-publisher' ), $options['tables'] );
						mdp_checkbox( 'tasklists', __( '任务列表 - [x]', 'wp-markdown-publisher' ), $options['tasklists'] );
						mdp_checkbox( 'footnotes', __( '脚注 [^1]', 'wp-markdown-publisher' ), $options['footnotes'] );
						mdp_checkbox( 'autolink', __( '裸链接自动转成超链接', 'wp-markdown-publisher' ), $options['autolink'] );
						mdp_checkbox( 'heading_ids', __( '为标题生成锚点 id', 'wp-markdown-publisher' ), $options['heading_ids'] );
						mdp_checkbox( 'hard_wrap', __( '单个换行也换行（GitHub 评论风格）', 'wp-markdown-publisher' ), $options['hard_wrap'] );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '安全', 'wp-markdown-publisher' ); ?></th>
					<td>
						<?php
						mdp_checkbox( 'allow_html', __( '允许 Markdown 中的原始 HTML', 'wp-markdown-publisher' ), $options['allow_html'], __( '关闭后所有 HTML 标签都会被转义显示。', 'wp-markdown-publisher' ) );
						mdp_checkbox( 'sanitize_urls', __( '过滤 javascript: / data: 等危险链接', 'wp-markdown-publisher' ), $options['sanitize_urls'] );
						mdp_checkbox( 'kses_output', __( '始终用 WordPress 白名单过滤渲染结果', 'wp-markdown-publisher' ), $options['kses_output'], __( '开启后可能会移除任务列表复选框等自定义标记，一般无需开启。', 'wp-markdown-publisher' ) );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '目录 (TOC)', 'wp-markdown-publisher' ); ?></th>
					<td>
						<label><?php esc_html_e( '标题级别', 'wp-markdown-publisher' ); ?>
							<input type="number" name="mdp[toc_min]" value="<?php echo esc_attr( $options['toc_min'] ); ?>" min="1" max="6" class="small-text" />
							–
							<input type="number" name="mdp[toc_max]" value="<?php echo esc_attr( $options['toc_max'] ); ?>" min="1" max="6" class="small-text" />
						</label>
						<label class="mdp-inline-label"><?php esc_html_e( '目录标题', 'wp-markdown-publisher' ); ?>
							<input type="text" name="mdp[toc_title]" value="<?php echo esc_attr( $options['toc_title'] ); ?>" class="regular-text" />
						</label>
						<p class="description"><?php esc_html_e( '在 Markdown 中单独一行写 [TOC] 即可插入目录，也可以在文章中插入 [mdp_toc] 短代码。', 'wp-markdown-publisher' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '前台显示', 'wp-markdown-publisher' ); ?></th>
					<td>
						<?php
						mdp_checkbox( 'disable_wpautop', __( '对 Markdown 文章禁用 wpautop 自动分段', 'wp-markdown-publisher' ), $options['disable_wpautop'], __( '推荐开启，避免正文被二次处理产生多余空段落。', 'wp-markdown-publisher' ) );
						mdp_checkbox( 'frontend_css', __( '加载模块自带的前台样式（代码块 / 表格 / 目录）', 'wp-markdown-publisher' ), $options['frontend_css'] );
						?>
					</td>
				</tr>
			</table>
		</div>

		<div class="mdp-card">
			<h2><?php esc_html_e( '导入默认值', 'wp-markdown-publisher' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mdp-import-post-type"><?php esc_html_e( '默认文章类型', 'wp-markdown-publisher' ); ?></label></th>
					<td>
						<select id="mdp-import-post-type" name="mdp[import_post_type]">
							<?php foreach ( $post_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $options['import_post_type'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mdp-import-status"><?php esc_html_e( '默认发布状态', 'wp-markdown-publisher' ); ?></label></th>
					<td>
						<select id="mdp-import-status" name="mdp[import_status]">
							<?php
							$statuses = array(
								'draft'   => __( '草稿', 'wp-markdown-publisher' ),
								'publish' => __( '直接发布', 'wp-markdown-publisher' ),
								'pending' => __( '待审核', 'wp-markdown-publisher' ),
								'private' => __( '私密', 'wp-markdown-publisher' ),
							);
							foreach ( $statuses as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $options['import_status'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mdp-import-duplicate"><?php esc_html_e( '重复文章处理', 'wp-markdown-publisher' ); ?></label></th>
					<td>
						<select id="mdp-import-duplicate" name="mdp[import_duplicate]">
							<option value="skip" <?php selected( 'skip', $options['import_duplicate'] ); ?>><?php esc_html_e( '跳过', 'wp-markdown-publisher' ); ?></option>
							<option value="update" <?php selected( 'update', $options['import_duplicate'] ); ?>><?php esc_html_e( '更新已有文章', 'wp-markdown-publisher' ); ?></option>
							<option value="new" <?php selected( 'new', $options['import_duplicate'] ); ?>><?php esc_html_e( '始终新建', 'wp-markdown-publisher' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mdp-import-author"><?php esc_html_e( '默认作者', 'wp-markdown-publisher' ); ?></label></th>
					<td>
						<select id="mdp-import-author" name="mdp[import_author]">
							<option value="0"><?php esc_html_e( '当前用户', 'wp-markdown-publisher' ); ?></option>
							<?php foreach ( $authors as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( (int) $user->ID, (int) $options['import_author'] ); ?>><?php echo esc_html( $user->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'front matter', 'wp-markdown-publisher' ); ?></th>
					<td>
						<?php
						mdp_checkbox( 'import_taxonomy', __( '读取 front matter 中的分类与标签', 'wp-markdown-publisher' ), $options['import_taxonomy'] );
						mdp_checkbox( 'import_date', __( '读取 front matter 中的日期', 'wp-markdown-publisher' ), $options['import_date'] );
						mdp_checkbox( 'import_excerpt', __( '读取 front matter 中的摘要', 'wp-markdown-publisher' ), $options['import_excerpt'] );
						?>
					</td>
				</tr>
			</table>
		</div>

		<div class="mdp-card">
			<h2><?php esc_html_e( '数据清理', 'wp-markdown-publisher' ); ?></h2>
			<?php mdp_checkbox( 'delete_data', __( '切换主题时同时删除设置项', 'wp-markdown-publisher' ), $options['delete_data'], __( '文章中的 Markdown 原文与已渲染的 HTML 不会被删除。', 'wp-markdown-publisher' ) ); ?>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( '保存设置', 'wp-markdown-publisher' ); ?></button>
		</p>
	</form>
</div>
