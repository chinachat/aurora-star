# GeoLite2 数据库说明

评论区的「IP 归属地」需要一份 MaxMind GeoLite2 数据库。**主题不附带该数据库**，
需要你自行获取后通过后台上传或手动放置。

## 1. 获取数据库

到 MaxMind 注册免费账号后下载（GeoLite2 需免费 License Key）：

- 城市库（推荐，可显示到城市）：`GeoLite2-City.tar.gz`，解压后约 60–70 MB
- 国家库（体积小，只能显示到国家）：`GeoLite2-Country.tar.gz`，约 6 MB

<https://www.maxmind.com/en/geolite2/signup>

> ⚠️ **许可提醒**：GeoLite2 数据库受 MaxMind 最终用户许可协议约束（CC BY-SA 4.0），
> 允许免费使用但**要求保留署名**。若你的站点公开提供下载或再分发，请遵守其条款。
> 主题只读取该文件，不会打包或再分发它。

## 2. 安装方式

### 方式一：后台上传（推荐）

进入 **后台 → Aurora Star 主题 → IP 归属地数据库**，选择文件后点「上传并安装」。

- 支持 MaxMind 官方下载的 `.tar.gz`，也支持 `.gz` / `.zip` / 直接上传 `.mmdb`
- 按**文件头**识别格式，不依赖扩展名
- **先校验再替换**：检查 MaxMind 元数据标记并用 Reader 实际打开，
  校验失败会保留原有数据库，不会把功能弄坏
- 上传后落在 `wp-content/uploads/aurora-star-geoip/`，升级主题不会丢失
- 面板会显示数据库类型、**构建时间**、节点数与文件大小；超过 45 天会提示更新
- 可一键删除，回退到主题目录中的库

### 方式二：手动放置

**A. 放在主题目录（升级主题会丢失）**

```
wp-content/themes/aurora-star/assets/geoip/GeoLite2-City.mmdb
```

文件名必须完全一致。

**B. 放在主题目录之外（推荐）**

把 mmdb 放到例如 `wp-content/uploads/geoip/GeoLite2-City.mmdb`，
然后在子主题的 `functions.php` 中指定路径：

```php
add_filter( 'aurora_star_geoip_db_path', function () {
    return WP_CONTENT_DIR . '/uploads/geoip/GeoLite2-City.mmdb';
} );
```

## 3. 查找优先级

1. `aurora_star_geoip_db_path` 过滤器（最高优先级）
2. 后台上传的库：`wp-content/uploads/aurora-star-geoip/`（城市库优先于国家库）
3. 主题目录：`assets/geoip/GeoLite2-City.mmdb`

## 4. 生效条件

- 后台 **外观 → 自定义 → Aurora Star 主题设置 → 评论**，勾选「显示 IP 归属地」
- 数据库文件可读且校验通过

未放置数据库时该功能**静默关闭**，评论区不会报错，也不会显示归属地。

## 5. 性能说明

- 查询全部在**本地**完成，不会把访客 IP 发送给任何第三方。
- 查询结果会写入评论 meta（`_aurora_star_geo`），**每条评论只查一次**，
  之后渲染直接读 meta，不会在每次页面浏览时重复读取数据库。
- 已有评论会在首次被浏览时补上归属地并缓存。

## 6. 常见问题

**上传失败，提示超过服务器上限**

调大 `php.ini` 的 `upload_max_filesize` 与 `post_max_size`（后者要 ≥ 前者），
或改用方式二手动放置（例如通过 FTP / 面板文件管理器）。

**上传后提示「这不是有效的 MaxMind 数据库文件」**

确认下载的是 GeoLite2 而不是别的文件；MaxMind 下载的是 `.tar.gz`，里面才是 `.mmdb`。
主题会自动解出 mmdb，无需手动解压。

**面板显示「未启用（请改用 .tar.gz）」**

服务器没有 zip 扩展，属正常提示，`.tar.gz` 与 `.mmdb` 不受影响。
