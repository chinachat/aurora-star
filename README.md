<div align="center">

# 🌌 Aurora Star 极光主题

现代化极简 **WordPress 主题** · 全量资源自托管 · 零外部 CDN 依赖

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?style=flat-square&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL%20v2-orange?style=flat-square)
![Version](https://img.shields.io/badge/Version-1.1.0-6366f1?style=flat-square)

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
| 🏷️ **备案信息** | ICP 备案 + 公安备案，留空自动隐藏 |
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
│   └── admin-menu.php     # 后台一级菜单
├── assets/
│   ├── css/               # 主题样式（含暗色、灯箱、目录等）
│   ├── js/                # 主题脚本
│   ├── img/               # 极光背景图
│   ├── icons/             # Font Awesome 7（自托管）
│   └── vendor/prism/      # Prism.js（自托管）
└── languages/             # 简体中文语言包
```

## 📦 发行说明

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

---

<div align="center">

Made with ❤️ by [chinachat](https://github.com/chinachat)

</div>
