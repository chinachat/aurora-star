# Markdown 发布模块

主题内置的 Markdown 写作与渲染模块，**由独立插件 `wp-markdown-publisher` 1.0.1 集成而来**，
自主题 v2.0.0 起随主题分发。插件本身已退役。

## 目录结构

```
inc/markdown/
├── bootstrap.php            引导：常量、类加载、插件让路守卫、数据清理
├── class-markdown.php       零 WordPress 依赖的 GFM 解析器（可独立测试）
├── class-front-matter.php   front matter 解析
├── class-plugin.php         选项、解析器配置、渲染管线
├── class-post-editor.php    后台编辑器接管与保存
├── class-importer.php       .md 文件导入
├── class-shortcodes.php     [markdown] / [md] / [mdp_toc]
├── class-frontend.php       前台渲染、wpautop 处理、样式
├── class-rest.php           REST 接口（mdp/v1）
├── class-admin.php          后台菜单与设置
├── views/                   后台页面视图
├── languages/               翻译模板（文本域 wp-markdown-publisher）
└── examples/                示例文章

assets/markdown/             后台与前台静态资源
```

## 与独立插件的关系

主题与插件**不会同时渲染**。`bootstrap.php` 在加载时检测：

```php
if ( defined( 'MDP_VERSION' ) || class_exists( 'Mdp_Plugin', false ) ) {
    // 独立插件仍在运行 → 整个模块让路，只显示一条后台提示
    return;
}
```

插件先于主题加载，所以这个判断是可靠的。

## 数据兼容（重要）

以下键名**刻意与插件保持一致，不可更改**，否则已有文章的 Markdown 原文、
渲染状态与设置会全部失联：

| 类型 | 键名 |
| --- | --- |
| 选项 | `mdp_settings`、`mdp_version` |
| 文章元数据 | `_mdp_markdown`（原文）、`_mdp_enabled`、`_mdp_toc`、`_mdp_rendered_at`、`_mdp_parser_version` |

因此：插件 ↔ 主题可以随时互换，两边读写的是同一份数据。
文章正文（`post_content`）保存的始终是**渲染后的 HTML**，
所以即使哪天不用本主题了，文章内容也不会丢。

## 命名空间

模块内部沿用插件原来的 `Mdp_*` 类名、`MDP_*` 常量与 `_mdp_*` 键名，
`mdp()` 快捷函数也保留（带 `function_exists` 守卫）。
这样迁移的 diff 最小、风险最低；代价是主题里多了一套非 `aurora_star_` 前缀的标识符。

## the_content 过滤器优先级

模块与主题的钩子在 `the_content` 上不再撞档，执行顺序不依赖注册顺序：

| 优先级 | 回调 | 归属 |
| --- | --- | --- |
| 6 | `maybe_render_source` | 模块 |
| 7 | `pre_render_markdown_blocks` | 模块 |
| 8 | `aurora_star_protect_content` | 主题 |
| 9 | `maybe_toggle_wpautop` / `aurora_star_heading_ids` | 模块 / 主题 |
| 10 | `wptexturize`、`wpautop`、`shortcode_unautop` | 核心 |
| 11 | `do_shortcode`、`aurora_star_lightbox_images`（+ 被挪过来的 `wpautop`） | 核心 / 主题 |

原插件把预渲染挂在 8、与主题的保护过滤器同档，靠「谁先注册」决定顺序；
现在降到 7，改成显式先后关系。

## 生命周期

主题没有 `register_activation_hook` 与 `uninstall.php`，对应关系为：

| 插件 | 主题 |
| --- | --- |
| `register_activation_hook` → `Mdp_Plugin::on_activate()` | `after_switch_theme` |
| `uninstall.php` 的清理 | `switch_theme` → `aurora_star_markdown_cleanup_on_switch()` |

两者行为一致：只有勾选了设置页的「切换主题时同时删除设置项」才真的删除设置。
