<?php
/**
 * 后台视图：语法与说明。
 *
 * @package Mdp
 * @var Mdp_Plugin $plugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap mdp-wrap">
	<h1><?php esc_html_e( 'Markdown 语法与说明', 'wp-markdown-publisher' ); ?></h1>

	<div class="mdp-grid">
		<div class="mdp-card">
			<h2><?php esc_html_e( '支持的语法', 'wp-markdown-publisher' ); ?></h2>
			<table class="widefat striped mdp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( '写法', 'wp-markdown-publisher' ); ?></th>
						<th><?php esc_html_e( '效果', 'wp-markdown-publisher' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code># 标题</code> ~ <code>###### 标题</code></td><td><?php esc_html_e( '一至六级标题（自动生成锚点，可写 {#自定义id}）', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>**加粗**</code> / <code>*斜体*</code> / <code>~~删除线~~</code></td><td><?php esc_html_e( '强调语法', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>`行内代码`</code></td><td><?php esc_html_e( '行内代码', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>```php … ```</code></td><td><?php esc_html_e( '代码块（带 language-xxx 类名，可自行接入 Prism 等高亮）', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>[文字](https://a.com "标题")</code></td><td><?php esc_html_e( '行内链接', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>[文字][ref]</code> + <code>[ref]: https://a.com</code></td><td><?php esc_html_e( '引用式链接', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>![说明](图片地址)</code></td><td><?php esc_html_e( '图片', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>- 项目</code> / <code>1. 项目</code></td><td><?php esc_html_e( '无序 / 有序列表，支持嵌套', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>- [x] 任务</code></td><td><?php esc_html_e( '任务列表', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>&gt; 引用</code></td><td><?php esc_html_e( '引用块', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>| a | b |</code></td><td><?php esc_html_e( '表格（支持 :-- 对齐）', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>---</code></td><td><?php esc_html_e( '分隔线', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>[^1]</code> + <code>[^1]: 内容</code></td><td><?php esc_html_e( '脚注（自动编号并汇总到文末）', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>[TOC]</code></td><td><?php esc_html_e( '单独一行时插入目录', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>https://example.com</code></td><td><?php esc_html_e( '自动识别为链接', 'wp-markdown-publisher' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="mdp-card">
			<h2><?php esc_html_e( '三种使用方式', 'wp-markdown-publisher' ); ?></h2>

			<h3><?php esc_html_e( '1. 直接撰写', 'wp-markdown-publisher' ); ?></h3>
			<p><?php esc_html_e( '在启用的文章类型里新建文章，正文区域会变成 Markdown 编辑器：左侧写 Markdown，点「预览」实时查看 HTML 效果，保存时自动生成 HTML 正文。Markdown 原文保存在文章元数据中，随时可回来继续编辑。', 'wp-markdown-publisher' ); ?></p>

			<h3><?php esc_html_e( '2. 导入 .md 文件', 'wp-markdown-publisher' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: 菜单路径 */
					esc_html__( '进入「%s」，拖入 .md 文件即可批量生成文章。文件开头的 YAML front matter 可以指定标题、别名、日期、分类、标签、摘要、特色图片等。', 'wp-markdown-publisher' ),
					esc_html__( 'Markdown 发布 → 导入 Markdown', 'wp-markdown-publisher' )
				);
				?>
			</p>

			<h3><?php esc_html_e( '3. 短代码', 'wp-markdown-publisher' ); ?></h3>
			<p><?php esc_html_e( '在任意文章/页面/小工具里使用短代码渲染 Markdown：', 'wp-markdown-publisher' ); ?></p>
			<pre class="mdp-code-sample">[markdown]
## 小标题

- 列表项
- **加粗**
[/markdown]

[mdp_toc]          渲染当前文章的目录
[mdp_toc max="3"]  只显示到三级标题</pre>
		</div>

		<div class="mdp-card">
			<h2><?php esc_html_e( '保存与渲染规则（重要）', 'wp-markdown-publisher' ); ?></h2>
			<ul class="mdp-list">
				<li><?php esc_html_e( '文章正文（post_content）保存的是渲染后的 HTML，主题、搜索、RSS、SEO 插件都能正常读取。', 'wp-markdown-publisher' ); ?></li>
				<li><?php esc_html_e( 'Markdown 原文保存在元数据 _mdp_markdown 中，重新编辑时以它为准。', 'wp-markdown-publisher' ); ?></li>
				<li><?php esc_html_e( '已有文章不会自动进入 Markdown 模式：编辑页面板里勾选「用 Markdown 编辑此文章」并保存后，下次打开就会用 Markdown 编辑器。', 'wp-markdown-publisher' ); ?></li>
				<li><?php esc_html_e( '为避免误删，若 Markdown 编辑框为空而文章已有正文，保存时会保留原正文。', 'wp-markdown-publisher' ); ?></li>
				<li><?php esc_html_e( '关闭「用 Markdown 编辑此文章」后，文章回到普通编辑器，正文保持当前的 HTML。', 'wp-markdown-publisher' ); ?></li>
				<li><?php esc_html_e( '切换解析器版本或修改渲染设置后，可以在「批量重新渲染」里一次性刷新全部文章。', 'wp-markdown-publisher' ); ?></li>
			</ul>
		</div>

		<div class="mdp-card">
			<h2><?php esc_html_e( '开发者接口', 'wp-markdown-publisher' ); ?></h2>
			<table class="widefat striped mdp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( '名称', 'wp-markdown-publisher' ); ?></th>
						<th><?php esc_html_e( '类型', 'wp-markdown-publisher' ); ?></th>
						<th><?php esc_html_e( '说明', 'wp-markdown-publisher' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>mdp_enabled_post_types</code></td><td>filter</td><td><?php esc_html_e( '动态调整启用 Markdown 的文章类型', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>mdp_parser_options</code></td><td>filter</td><td><?php esc_html_e( '调整解析器选项', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>mdp_render_result</code></td><td>filter</td><td><?php esc_html_e( '渲染完成后处理 html / toc', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>mdp_import_extensions</code></td><td>filter</td><td><?php esc_html_e( '调整允许导入的文件扩展名', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>mdp()</code></td><td>function</td><td><?php esc_html_e( '取得模块实例，例如 mdp()->render( $md )', 'wp-markdown-publisher' ); ?></td></tr>
					<tr><td><code>Mdp_Markdown</code></td><td>class</td><td><?php esc_html_e( '独立解析器，可脱离 WordPress 使用：new Mdp_Markdown(); $md->toHtml( $text );', 'wp-markdown-publisher' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
