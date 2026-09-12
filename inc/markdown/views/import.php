<?php
/**
 * 后台视图：导入 Markdown。
 *
 * @package Mdp
 * @var Mdp_Plugin $plugin
 */

defined( 'ABSPATH' ) || exit;

$post_types = Mdp_Plugin::post_type_choices();
$default_pt = $plugin->option( 'import_post_type', 'post' );
$statuses   = array(
	'draft'   => __( '草稿', 'wp-markdown-publisher' ),
	'publish' => __( '直接发布', 'wp-markdown-publisher' ),
	'pending' => __( '待审核', 'wp-markdown-publisher' ),
	'private' => __( '私密', 'wp-markdown-publisher' ),
);
$duplicates = array(
	'skip'   => __( '跳过（同 slug 已存在时）', 'wp-markdown-publisher' ),
	'update' => __( '更新已有文章', 'wp-markdown-publisher' ),
	'new'    => __( '始终新建', 'wp-markdown-publisher' ),
);
$authors    = get_users(
	array(
		'capability' => array( 'edit_posts' ),
		'number'     => 200,
		'fields'     => array( 'ID', 'display_name' ),
	)
);
?>
<div class="wrap mdp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( '导入 Markdown', 'wp-markdown-publisher' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=mdp-help' ) ); ?>"><?php esc_html_e( '语法与说明', 'wp-markdown-publisher' ); ?></a>
	<hr class="wp-header-end" />

	<p class="mdp-lead">
		<?php esc_html_e( '上传 .md / .markdown / .txt 文件（也支持包含多个 Markdown 文件的 .zip 压缩包），主题会把 Markdown 渲染成 HTML 并生成文章；文件开头的 front matter 可用于设置标题、slug、分类、标签、日期等。', 'wp-markdown-publisher' ); ?>
	</p>

	<form id="mdp-import-form" class="mdp-form" method="post" enctype="multipart/form-data">
		<div class="mdp-grid">
			<div class="mdp-card mdp-card--main">
				<h2><?php esc_html_e( '1. 选择文件', 'wp-markdown-publisher' ); ?></h2>

				<div class="mdp-dropzone" id="mdp-dropzone">
					<div class="mdp-dropzone__icon" aria-hidden="true">↓</div>
					<p class="mdp-dropzone__title"><?php esc_html_e( '把 .md 文件拖到这里', 'wp-markdown-publisher' ); ?></p>
					<p class="mdp-dropzone__hint"><?php esc_html_e( '或点击下面的按钮选择文件，可一次选多个', 'wp-markdown-publisher' ); ?></p>
					<input type="file" id="mdp-files" name="files[]" multiple accept=".md,.markdown,.mdown,.mkd,.txt,.zip" class="mdp-file-input" />
					<label for="mdp-files" class="button button-secondary"><?php esc_html_e( '选择文件', 'wp-markdown-publisher' ); ?></label>
					<ul class="mdp-filelist" id="mdp-filelist"></ul>
				</div>

				<h2><?php esc_html_e( '2. 导入选项', 'wp-markdown-publisher' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mdp-post-type"><?php esc_html_e( '文章类型', 'wp-markdown-publisher' ); ?></label></th>
						<td>
							<select id="mdp-post-type" name="post_type">
								<?php foreach ( $post_types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $default_pt ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'front matter 里的 type/post_type 优先于这里的设置。', 'wp-markdown-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mdp-status"><?php esc_html_e( '发布状态', 'wp-markdown-publisher' ); ?></label></th>
						<td>
							<select id="mdp-status" name="status">
								<?php foreach ( $statuses as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $plugin->option( 'import_status', 'draft' ) ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mdp-duplicate"><?php esc_html_e( '重复文章', 'wp-markdown-publisher' ); ?></label></th>
						<td>
							<select id="mdp-duplicate" name="duplicate">
								<?php foreach ( $duplicates as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $plugin->option( 'import_duplicate', 'skip' ) ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( '依据 front matter 的 slug / 自动生成的标题别名判断是否重复。', 'wp-markdown-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mdp-author"><?php esc_html_e( '作者', 'wp-markdown-publisher' ); ?></label></th>
						<td>
							<select id="mdp-author" name="author">
								<option value="0"><?php esc_html_e( '当前用户', 'wp-markdown-publisher' ); ?></option>
								<?php foreach ( $authors as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '分类目录', 'wp-markdown-publisher' ); ?></th>
						<td>
							<input type="text" class="regular-text" id="mdp-categories" name="categories" placeholder="<?php esc_attr_e( '多个用英文逗号分隔，可留空', 'wp-markdown-publisher' ); ?>" />
							<p class="description"><?php esc_html_e( '会追加到 front matter 中的分类之后。不存在的分类会自动创建（需要权限）。', 'wp-markdown-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '标签', 'wp-markdown-publisher' ); ?></th>
						<td>
							<input type="text" class="regular-text" id="mdp-tags" name="tags" placeholder="<?php esc_attr_e( '多个用英文逗号分隔，可留空', 'wp-markdown-publisher' ); ?>" />
						</td>
					</tr>
				</table>

				<p class="mdp-actions">
					<button type="submit" class="button button-primary button-hero" id="mdp-import-submit">
						<?php esc_html_e( '开始导入', 'wp-markdown-publisher' ); ?>
					</button>
					<span class="spinner" id="mdp-import-spinner"></span>
				</p>

				<div class="mdp-progress" id="mdp-progress" hidden>
					<div class="mdp-progress__bar"><span id="mdp-progress-fill"></span></div>
					<div class="mdp-progress__text" id="mdp-progress-text"></div>
				</div>

				<div id="mdp-import-results" class="mdp-results"></div>
			</div>

			<div class="mdp-card mdp-card--side">
				<h2><?php esc_html_e( '粘贴 Markdown 新建文章', 'wp-markdown-publisher' ); ?></h2>
				<p>
					<label for="mdp-paste-title"><?php esc_html_e( '标题', 'wp-markdown-publisher' ); ?></label>
					<input type="text" id="mdp-paste-title" class="widefat" />
				</p>
				<p>
					<textarea id="mdp-paste-content" rows="12" class="widefat code" placeholder="<?php esc_attr_e( '# 标题

正文…', 'wp-markdown-publisher' ); ?>"></textarea>
				</p>
				<p>
					<button type="button" class="button button-secondary" id="mdp-paste-submit"><?php esc_html_e( '用这段 Markdown 新建文章', 'wp-markdown-publisher' ); ?></button>
				</p>

				<hr />

				<h3><?php esc_html_e( 'front matter 示例', 'wp-markdown-publisher' ); ?></h3>
<pre class="mdp-code-sample">---
title: 文章标题
slug: my-post
date: 2024-05-20 10:00:00
status: draft
categories: [教程, WordPress]
tags: Markdown, 插件
excerpt: 自定义摘要
featured_image: https://example.com/a.jpg
---

正文…
</pre>
				<p class="description"><?php esc_html_e( '支持 title、slug、date、status、categories、tags、excerpt、author、featured_image，以及 type/post_type。', 'wp-markdown-publisher' ); ?></p>
			</div>
		</div>
	</form>
</div>
