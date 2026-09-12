# GeoLite2 数据库放置说明

评论区的「IP 归属地」需要一份 MaxMind GeoLite2 数据库。**主题不附带该数据库**，
需要你自行获取后放到下面任一位置。

## 1. 获取数据库

到 MaxMind 注册免费账号后下载（GeoLite2 需免费 License Key）：

- 城市库（推荐，可显示到城市）：`GeoLite2-City.mmdb`，约 60–70 MB
- 国家库（体积小，只能显示到国家）：`GeoLite2-Country.mmdb`，约 6 MB

<https://www.maxmind.com/en/geolite2/signup>

> ⚠️ **许可提醒**：GeoLite2 数据库受 MaxMind 最终用户许可协议约束（CC BY-SA 4.0），
> 允许免费使用但**要求保留署名**。若你的站点公开提供下载或再分发，请遵守其条款。
> 主题只读取该文件，不会打包或再分发它。

## 2. 放置位置

**方式 A：放在主题目录（简单，但升级主题会丢失）**

```
wp-content/themes/aurora-star/assets/geoip/GeoLite2-City.mmdb
```

放在本目录即可，文件名必须完全一致。

**方式 B：放在主题目录之外（推荐）**

把 mmdb 放到例如 `wp-content/uploads/geoip/GeoLite2-City.mmdb`，
然后在子主题的 `functions.php` 中指定路径：

```php
add_filter( 'aurora_star_geoip_db_path', function () {
    return WP_CONTENT_DIR . '/uploads/geoip/GeoLite2-City.mmdb';
} );
```

这样升级主题不会覆盖数据库，也不会把几十 MB 的二进制文件塞进主题包。

## 3. 生效条件

- 后台 **外观 → 自定义 → Aurora Star 主题设置 → 评论**，勾选「显示 IP 归属地」
- 数据库文件可读

未放置数据库时该功能**静默关闭**，评论区不会报错，也不会显示归属地。

## 4. 性能说明

- 查询全部在**本地**完成，不会把访客 IP 发送给任何第三方。
- 查询结果会写入评论 meta（`_aurora_star_geo`），**每条评论只查一次**，
  之后渲染直接读 meta，不会在每次页面浏览时重复读取数据库。
- 已有评论会在首次被浏览时补上归属地并缓存。
