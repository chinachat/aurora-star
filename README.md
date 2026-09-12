<div align="center">

# 🌌 Aurora Star 极光主题

现代化极简 **WordPress 主题** · 全量资源自托管 · 零外部 CDN 依赖

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?style=flat-square&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL%20v2-orange?style=flat-square)
![Version](https://img.shields.io/badge/Version-1.5.3-6366f1?style=flat-square)

</div>

---

## ✨ 功能特性

| 功能 | 说明 |
|------|------|
| 🌓 **暗黑 / 明亮双模式** | 一键切换，localStorage 记忆，跟随系统偏好，支持夜间（20:00–6:00）自动切换 |
| 📑 **文章浮动目录** | 服务端提取 h2-h4（利于 SEO），滚动高亮、阅读进度条、大类折叠随阅读进度自动展开 |
| 💻 **代码高亮** | Prism.js 自托管，37 种常用语言，支持行号、一键复制、语言标签 |
| 🖼️ **图片灯箱** | 点击放大、滚轮/按钮缩放、拖动平移、旋转、前后切换、触屏双指缩放 |
| 🎨 **Font Awesome 7** | 全量自托管，菜单项可直接填写图标类名（如 `fa-solid fa-home`） |
| 🧭 **全局浮动导航** | 右下角返回顶部 + 分享（微博 / QQ / 复制链接） |
| 🔧 **短码系统** | `[button]` `[alert]` `[tabs]` `[accordion]` `[code]` `[youtube]` `[icon]` 等 |
| 🌌 **极光背景** | 浅色/暗色主题各自适配的极光背景图，一键开关 |
| 🏷️ **备案信息** | ICP + 公安备案（带本地化徽章），**单独成行**，留空自动隐藏 |
| 🔗 **友情链接** | 独立的「友情链接」菜单位置，页脚自动渲染；未分配时不输出 |
| 💬 **评论增强** | Gravatar 头像（可设默认图案）；昵称后显示国旗 + IP 归属地、操作系统与浏览器版本 |
| 🌐 **多厂商 IP 库** | 兼容 MaxMind DB 格式的任意 IP 库（DB-IP / GeoLite2 / IPinfo）；后台一键上传，自动署名 |
| 📤 **IP 库后台上传** | 后台直接上传/更新 IP 库，识别 `.tar.gz` / `.gz` / `.zip` / `.mmdb`，显示构建时间 |
| 🇨🇳 **简体中文** | 内置 zh_CN 语言包，前后台界面全面中文化 |
| 🖼️ **特色图** | 文章页大图 + 列表缩略图，可单独关闭文章内显示 |

## 🚀 快速开始

### 安装

1. 从 [Releases](https://github.com/chinachat/aurora-star/releases) 下载 `aurora-star.zip`
2. WordPress 后台 → **外观 → 主题 → 添加新主题 → 上传主题**
3. 上传 zip → 安装 → **启用**
4. 进入 **外观 → 自定义 → Aurora Star 主题设置** 完成个性化配置

> 也可手动将 `aurora-star` 文件夹上传至 `wp-content/themes/` 目录。

### 最低要求

- WordPress ≥ 6.0
- PHP ≥ 7.4

## 📚 使用文档

完整使用说明请参阅 [使用文档](使用文档.md)，涵盖：

- 主题设置（自定义器）全部选项
- 暗黑模式 / 浮动目录 / 代码高亮 / 灯箱的使用
- 菜单图标配置
- 全部短代码参数与示例
- 常见问题排障

## 💡 快速上手

### 短代码示例

```text
[button href="https://example.com" color="primary" size="lg"]立即查看[/button]

[alert type="success" title="操作成功"]数据已保存。[/alert]

[tabs]
[tab title="介绍"]第一个标签的内容。[/tab]
[tab title="用法"]第二个标签的内容。[/tab]
[/tabs]

[code lang="php"]echo "Hello World";[/code]
```

### 菜单图标

在外观 → 菜单的菜单项「图标」字段填写 Font Awesome 类名：

```
fa-solid fa-house     首页
fa-solid fa-folder    归档
fa-brands fa-github   GitHub
```

## 💬 评论增强

评论区会显示 Gravatar 头像，并在昵称后附带徽章：

```
[头像] 张三  [🇨🇳 中国 · 广东 · 深圳]  [🪟 Windows 10/11]  [🌐 Chrome 120.0.0.0]
       2026-09-12 12:00
```

| 项目 | 数据来源 | 是否需要额外配置 |
|---|---|---|
| 头像 | Gravatar（`get_avatar()`），可在自定义器里选择默认图案 | 否 |
| 操作系统 / 浏览器 | 评论自带 `comment_agent`，**纯本地正则解析** | 否 |
| 国旗 + IP 归属地 | 本地 MaxMind GeoLite2 数据库 | **需要自行放置 mmdb** |

### 开启 IP 归属地

主题**不附带**数据库，也不绑定厂商——兼容**所有 MaxMind DB（mmdb）格式**的库。

> ⚠️ **MaxMind GeoLite2 现对部分地区不再开放注册**。拿不到账号的话，直接用
> **DB-IP Lite**（免注册、直接下载、每月更新）即可。

**方式一：后台直接上传（推荐）**

1. 从 <https://db-ip.com/db/lite.php> 直接下载（无需注册）：
   - 城市级：`dbip-city-lite-YYYY-MM.mmdb.gz`（gz ≈ 60 MB）
   - 国家级：`dbip-country-lite-YYYY-MM.mmdb.gz`（**gz 仅 4 MB，几乎不受上传限制**）
2. 进入 **后台 → Aurora Star 主题 → IP 归属地数据库**，选择文件后点「上传并安装」
3. 面板会显示数据库类型、**构建时间**、大小、节点数，便于确认是否已更新

支持官方下载的 `.tar.gz`，也支持 `.gz` / `.zip` / 直接上传 `.mmdb`。
上传后**先校验再替换**，校验失败会保留原有数据库，不会把功能弄坏。

**方式二：手动放置**

```php
add_filter( 'aurora_star_geoip_db_path', function () {
    return WP_CONTENT_DIR . '/uploads/geoip/ip-database.mmdb';
} );
```

**查找优先级**：过滤器 > 后台上传（`uploads/aurora-star-geoip/ip-database.mmdb`）> 主题目录。

详见 [`assets/geoip/README.md`](assets/geoip/README.md)。

> 未放置数据库时该功能静默关闭，评论区不会报错。
> 归属地查询**全部在本地完成**，不会把访客 IP 发送给任何第三方。
> **署名要求**：DB-IP Lite（CC BY 4.0）与 GeoLite2（CC BY-SA 4.0）都要求在展示其数据的
> 页面保留署名。主题会自动在页脚输出对应来源的署名链接，可在自定义器里关闭。

### 性能

归属地查询单次约 1 ms。为避免拖慢页面，解析结果会写入评论 meta（`_aurora_star_geo`），
**每条评论只查一次**；新评论在提交时即完成解析，已有评论会在首次被浏览时补上并缓存。

## 🔌 开发者钩子

| 过滤器 | 说明 |
|---|---|
| `aurora_star_code_scan` | 覆盖代码块扫描结果。正文由小工具/插件在渲染期生成代码块时使用：<br>`add_filter( 'aurora_star_code_scan', function ( $scan ) { $scan['has_code'] = true; return $scan; } );` |
| `aurora_star_prism_languages` | 覆盖入队的 Prism 语言组件（默认按正文实际用到的语言 + 自动识别集合计算） |
| `aurora_star_allow_svg_upload` | 默认 `false`。SVG 可携带脚本，开放前请确认上传权限仅限可信用户 |
| `aurora_star_should_track_view` | 返回 `false` 可完全关闭当前请求的阅读数统计 |
| `aurora_star_count_logged_in_views` | 默认 `false`（登录用户不计数），设为 `true` 可统计登录用户 |
| `aurora_star_geoip_db_path` | GeoLite2 / DB-IP 等数据库路径 |
| `aurora_star_comment_avatar_html` | 覆盖评论头像 HTML（如接入 CDN 头像） |
| `aurora_star_persist_comment_geo` | 返回 `false` 可关闭归属地写库（改为每次渲染实时查询） |
| `aurora_star_theme_url` | 页脚主题署名的链接地址，默认指向 GitHub 仓库 |
| `aurora_star_footer_links_new_tab` | 返回 `false` 可让页脚菜单链接在本页打开 |
| `aurora_star_content_width` | 内容宽度，默认 800 |

### 阅读数说明

- 统计对象：**单篇文章**的正常 GET 请求；预览、自定义器预览、Feed、robots、REST、AJAX、Cron 均不计入。
- 去重：基于 `aurora_star_views` Cookie 记住最近 **50** 篇已读文章（体积恒定约 200 字节）。
  超出 50 篇后最早的记录会被淘汰，此时重访那些旧文章会再计一次 —— 这是有界 Cookie 的固有取舍，
  换来的是不会像无上限追加那样在超过 4KB 后被浏览器整体丢弃、导致去重彻底失效。
- 反爬：空 User-Agent 与常见爬虫 / 监控探针 / 脚本客户端不计入。
- **页面缓存**：整页缓存命中时 PHP 不执行，阅读数不会增长；若需要缓存下的统计，请改用前端 beacon + REST 接口。
- 计数写入采用单条 `UPDATE ... meta_value + 1`，避免并发下丢失计数。

> 语言包放在主题的 `languages/` 目录时，文件名必须**严格等于 locale**（如 `zh_CN.mo`），
> 不能写成 `aurora-star-zh_CN.mo`——后者只有放在 `wp-content/languages/themes/` 下才会被识别。

## 🗂️ 项目结构

```
aurora-star/
├── style.css              # 主题信息 + 主样式
├── functions.php          # 入口文件
├── header.php / footer.php / index.php / single.php ...
├── template-parts/        # 文章卡片等模板片段
├── inc/
│   ├── setup.php          # 主题初始化
│   ├── enqueue.php        # 资源加载
│   ├── customizer.php     # 设置选项
│   ├── shortcodes.php     # 短码系统
│   ├── toc.php            # 服务端目录生成
│   ├── menu-walker.php    # 菜单图标
│   ├── comments.php       # 评论增强（头像 / 归属地 / UA）
│   ├── geoip-admin.php    # IP 数据库后台上传与状态
│   └── admin-menu.php     # 后台一级菜单
├── assets/
│   ├── css/               # 主题样式（含暗色、灯箱、目录等）
│   ├── js/                # 主题脚本
│   ├── img/               # 极光背景图、默认头像
│   ├── geoip/             # GeoLite2 数据库放置目录（见其中 README）
│   ├── icons/             # Font Awesome 7（自托管）
│   └── vendor/
│       ├── prism/             # Prism.js（自托管）
│       ├── maxmind-db-reader/ # MaxMind DB 读取库（Apache-2.0）
│       └── flag-icons/        # 国旗 SVG（MIT）
└── languages/             # 简体中文语言包
```

## 📦 发行说明

**v1.5.3** — 不让代码块里的短码被执行

- **修复** `[button]` / `[code]` / `[youtube]` 等短码写在 `<pre>` 代码块里时会被真的执行，
  代码示例因此变成渲染后的 UI。WordPress 核心并不保护 `<pre>` 内的短码，
  Gutenberg 代码块、Markdown 插件输出的代码块、粘贴的文档都会踩到。
  现于 `the_content` 优先级 8 把 `<pre>` 内的 `[` `]` 转义为实体：
  页面仍显示为 `[` `]`，但 `do_shortcode` 不再匹配
- **修复** 短码属性里的引号被转义成 `&quot;` 时产生垃圾值：
  `lang="&quot;python&quot;"` 会得到 `language-quotpythonquot`（无效类名、无法高亮），
  `color="&quot;primary&quot;"` 会得到 `aurora-star-btn-quotprimaryquot`。
  现先还原 HTML 实体再去掉包裹的引号，覆盖 `[code]` 的 `lang`、`[button]` 的
  `color`/`size`/`target`/`rel`/`class`/`icon`、`[icon]` 的 `name`/`size`/`color`
- **验证** 新增 7 项针对 https://8u8.club 线上页面实际问题的复现用例，
  短代码测试增至 73 项；其余修复均确认不会误伤正文里正常使用的短码

**v1.5.2** — 修复 `[code]` 与目录锚点的冲突

- **修复** 代码示例被「标题补锚点」功能污染：`aurora_star_heading_ids` 也在
  `the_content` 优先级 9，而它注册更早（`setup.php` 先加载），因此会先于
  `[code]` 保护过滤器执行，把代码示例里的 `<h2>` 当成真标题、注入
  `id="aurora-star-toc-N-…"`。现在保护过滤器提到优先级 **8**，早于 `do_blocks`
  与标题锚点，代码示例不再被改动，目录编号也不会被代码里的标题挤占
- 顺带一并避免了 `wptexturize` 把代码里的引号变成弯引号、`...` 变成省略号、
  `--` 变成破折号，以及 `convert_smilies` 把 `:-)` 变成表情图

**v1.5.1** — 修复短代码布局问题

- **修复** `[code]` **多行代码被破坏**（严重）：`wpautop` 在 `do_shortcode` 之前运行，
  会把代码里的换行变成 `<br />`、空行变成 `</p><p>`；这些标记随后被转义成字面量，
  于是代码块里显示成 `<br />` 与 `</p>` 垃圾文本。现改为在 `wpautop` 之前
  把 `[code]` 内容 base64 化，短码执行时再还原 —— 换行与空行均原样保留
- **修复** 嵌套在 `[tabs]` / `[accordion]` / `[alert]` 里的块级短码，
  其区块首尾会被 `wpautop` 顶出多余的 `<br />`（多出空白行）
- **验证** 新增 43 项短代码测试，用**真实的 WordPress 核心函数**
  （`wpautop` / `shortcode_unautop` / `do_shortcode` / `wptexturize` / `do_blocks`）
  按真实优先级顺序跑完整 `the_content` 管线，覆盖单行/多行/含空行/内嵌 HTML 的
  `[code]`、嵌套在 tabs 与 alert 中的代码块、区块编辑器短码块、
  相邻块级短码、以及占位短码不泄漏

**v1.5.0** — 页脚重构与友情链接

- **新增** 「友情链接」菜单位置，页脚自动渲染；未分配菜单时不输出任何内容
- **变更** 页脚改为**多行布局**：版权 / 备案 / 署名各自成行，不再全挤在一行用「·」分隔
- **变更** 备案与公安备案**单独占一行**（两者仍同行，用「·」分隔）
- **新增** 页脚主题名指向 [GitHub 仓库](https://github.com/chinachat/aurora-star)，
  可用 `aurora_star_theme_url` 过滤器改成自己的地址
- **变更** 页脚导航与友情链接的菜单链接**一律新标签页打开**（主导航不受影响），
  可用 `aurora_star_footer_links_new_tab` 过滤器关闭
- **修复** 备案链接的 `rel` 补上 `noopener`，避免新标签页拿到 `window.opener`

**v1.4.0** — 兼容多厂商 IP 库

- **新增** 兼容 MaxMind DB（mmdb）格式的**任意** IP 库：DB-IP Lite、GeoLite2、IPinfo 等，
  按文件内容识别，不再绑定 MaxMind
- **新增** 多 schema 兜底解析：同时支持 `country.iso_code`（MaxMind / DB-IP）与
  `country` / `country_code` / `country_name` / `region_name` / `city_name` 等扁平字段（IPinfo 系）
- **新增** 页脚自动输出 IP 数据来源署名，并按数据库类型匹配对应品牌
  （DB-IP Lite 的 CC BY 4.0 明确要求在展示数据的页面回链 db-ip.com），可关闭
- **变更** 上传槽位统一为 `ip-database.mmdb`，上传新库即替换旧库，
  不再按厂商命名（原先 DB-IP 会被存成 `GeoLite2-City.mmdb`，造成误解）；
  v1.3.0 的旧文件名仍被识别，升级无感
- **文档** 说明 GeoLite2 注册已对部分地区关闭，主推免注册的 DB-IP Lite
  （城市库 gz 约 60 MB，国家库 gz 仅 4 MB）

**v1.3.0** — IP 数据库后台上传

- **新增** 后台「Aurora Star 主题 → IP 归属地数据库」面板，可直接上传/更新 GeoLite2
- **新增** 自动识别 `.tar.gz`（MaxMind 官方格式）/ `.gz` / `.zip` / `.mmdb`，按文件头判断而非扩展名
- **新增** 上传后**先校验再替换**：检查 MaxMind 元数据标记并用 Reader 实际打开，
  校验失败保留原库，一次坏上传不会弄坏归属地功能
- **新增** 数据库信息面板：类型、**构建时间**（超过 45 天提示该更新了）、大小、节点数、IP 版本
- **新增** 上传库存放在 `wp-content/uploads/aurora-star-geoip/`，
  优先级高于主题目录，升级主题不会丢失；支持一键删除回退
- **修复** `tar` 大小字段解析：`octdec()` 在 PHP 8.1+ 对非八进制填充会触发 deprecation，
  改为正则提取八进制位并处理 base-256 编码

**v1.2.0** — 评论增强

- **新增** 评论区昵称后显示徽章：国旗 + IP 归属地、操作系统、浏览器版本
- **新增** Gravatar 头像默认图案可选（神秘人 / 几何图形 / 像素风等）
- **新增** 自定义器「评论」设置区，可分别开关归属地与系统/浏览器显示
- **新增** 自托管 SVG 旗帜资源（271 面，符合主题「零外部 CDN」原则）
- **新增** 内置 MaxMind DB Reader（Apache-2.0），归属地查询全本地完成，不外发 IP
- **性能** 归属地按评论缓存到 meta，每条评论只查一次；新评论在提交时解析
- **修复** 评论布局：原先 `.comment-body` 为 flex 导致头像/正文/回复挤成三列，
  已改为「头像 + 主内容」两列结构
- **无障碍** 元信息徽章整组标注，图片装饰性 `alt` 留空避免读屏重复

**v1.1.5** — 代码审查修复

- **修复** `[tabs]` / `[accordion]` 短码缺少前端切换脚本（手风琴内容此前完全不可见）
- **修复** `[youtube]` 短码视频 ID 被强制小写导致播放失败
- **修复** `[code line="true"]` 在关闭全局行号时静默失效
- **修复** 语言包文件名不合规（`aurora-star-zh_CN.mo` → `zh_CN.mo`），此前从未被加载
- **修复** 暗黑模式在页脚初始化导致的暗色首屏白闪
- **修复** 灯箱正则破坏 `<img>` 标签（`/ data-lightbox>`、属性值内含 `>` 时被误改）
- **修复** 灯箱触屏手势因 `passive` 监听器失效
- **修复** 主色之外的派生色 `--aurora-star-primary-soft` 未随主色更新
- **修复** 移动端带子菜单的父级页面无法访问
- **修复** 目录元框在区块编辑器下无法保存（已注册 meta + 侧栏面板）
- **修复** 菜单图标在非完整表单提交时被静默删除
- **性能** Prism 仅在正文含代码块时加载，并按实际语言按需加载
- **性能** 目录/灯箱的 `MutationObserver` 收窄作用域并防抖，滚动回调按帧合并
- **重构** 阅读数：去重 Cookie 改为有界列表（恒定约 200 字节）、加爬虫过滤、改用原子 `UPDATE` 自增、`setcookie` 提前到 `template_redirect` 避免 headers already sent
- **清理** 删除死代码：`template-parts/content.php`、`aurora_star_shortcode_content()`、`aurora_star_shortcodes_admin_notice()`
- **无障碍** 弹层关闭时同步 `inert`、标签页补全 ARIA 关系与方向键支持、封面卡片补阅读数读屏文本
- **安全** SVG 上传默认关闭（需通过 `aurora_star_allow_svg_upload` 过滤器显式开启）

**v1.1.4** — 补充通用行号 CSS
**v1.1.3** — 修复 Gutenberg 代码块行号不显示
**v1.1.2** — 禁用 Prism 自动高亮，消除行号竞态
**v1.1.1** — 代码高亮增加行号开关与自动换行设置

**v1.1.0** — 搜索与备案增强

- 页头新增搜索按钮与搜索面板（自动聚焦、ESC/点击外部关闭）
- 公安备案徽章本地化（官方徽章图已自托管，零外链）
- 新增 README 与主题预览图（screenshot.png）

**v1.0.0** — 首个正式版本

- 暗黑/明亮双模式、浮动目录、代码高亮、图片灯箱、菜单图标、短码系统
- 极光背景、备案信息、特色图开关、全局浮动导航
- 简体中文语言包

## 🤝 贡献

欢迎提交 [Issue](https://github.com/chinachat/aurora-star/issues) 反馈问题或 [Pull Request](https://github.com/chinachat/aurora-star/pulls) 贡献代码。

## 📄 许可证

[GPL v2 or later](http://www.gnu.org/licenses/gpl-2.0.html)

- 图标：[Font Awesome Free](https://fontawesome.com)（CC BY 4.0 / SIL OFL 1.1 / MIT）
- 代码高亮：[Prism.js](https://prismjs.com)（MIT）
- 国旗：[flag-icons](https://github.com/lipis/flag-icons)（MIT）
- MaxMind DB 读取：[maxmind-db/reader](https://github.com/maxmind/MaxMind-DB-Reader-php)（Apache-2.0）
- IP 数据库：由使用者自行获取。**GeoLite2 受 MaxMind EULA / CC BY-SA 4.0 约束，
  DB-IP Lite 受 CC BY 4.0 约束，两者都要求署名**——主题会在页脚自动输出对应署名链接

> Apache-2.0 与 GPLv3 兼容、但与 GPLv2 不兼容。本主题声明为 “GPL v2 **or later**”，
> 因此整体可按 GPLv3 分发。若你需要严格的 GPLv2-only 分发，请替换掉
> `assets/vendor/maxmind-db-reader/`（该库仅在开启 IP 归属地时才会被载入）。

---

<div align="center">

Made with ❤️ by [chinachat](https://github.com/chinachat)

</div>
