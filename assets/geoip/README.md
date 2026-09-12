# IP 数据库说明

评论区的「IP 归属地」需要一份离线 IP 库。**主题不附带任何数据库**，需要你自行获取后
通过后台上传或手动放置。

主题兼容**所有 MaxMind DB（mmdb）格式**的库，按文件内容识别，不绑定厂商。

---

## 1. 选哪个库

> ⚠️ **MaxMind GeoLite2 目前对部分地区不再开放注册**，如果你拿不到账号，直接用下面的
> DB-IP Lite 即可，效果基本一致。

### 推荐：DB-IP Lite（免注册，直接下载）

<https://db-ip.com/db/lite.php>

| 数据库 | 下载地址 | 体积 | 能显示到 |
|---|---|---|---|
| IP to City Lite | `https://download.db-ip.com/free/dbip-city-lite-YYYY-MM.mmdb.gz` | gz ≈ 60 MB<br>解压 ≈ 121 MB | 国家 / 省 / 城市 |
| **IP to Country Lite** | `https://download.db-ip.com/free/dbip-country-lite-YYYY-MM.mmdb.gz` | gz ≈ 4 MB<br>解压 ≈ 8 MB | 国家 |

把 `YYYY-MM` 换成当前年月，例如 `dbip-city-lite-2026-09.mmdb.gz`。

- **无需注册**，直接下载
- **每月更新**（MaxMind GeoLite2 是每周）
- 国家名**含中文**（`zh-CN`）；省/市名只有英文（如 `Shandong` / `Jinan`）

> **许可（重要）**：DB-IP Lite 采用 [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)，
> **要求署名**：官方明确要求「在展示或使用其数据的页面上提供指向 DB-IP.com 的链接」。
> 主题会在页脚自动输出符合要求的署名链接（可在自定义器中关闭，但关闭后请自行确保合规）。

### 备选

| 数据源 | 格式 | 说明 |
|---|---|---|
| [MaxMind GeoLite2](https://www.maxmind.com/en/geolite2/signup) | mmdb | 城市级精度最好，但部分地区注册受限；许可 CC BY-SA 4.0，同样要求署名 |
| [IPinfo](https://ipinfo.io/) | mmdb | 免费版需申请 token；字段结构与 MaxMind 不同（扁平字段），主题已兼容 |
| [IP2Location LITE](https://lite.ip2location.com/) | BIN/CSV | ⚠️ **不是 mmdb 格式，主题不支持**，需自行转换 |

---

## 2. 安装方式

### 方式一：后台上传（推荐）

进入 **后台 → Aurora Star 主题 → IP 归属地数据库**，选择文件后点「上传并安装」。

- 支持 `.tar.gz` / `.gz` / `.zip` / 直接上传 `.mmdb`，按**文件头**识别格式
- **先校验再替换**：检查 MaxMind DB 元数据标记并用 Reader 实际打开，
  校验失败会保留原有数据库，不会把功能弄坏
- 上传后落在 `wp-content/uploads/aurora-star-geoip/ip-database.mmdb`（单一槽位，
  上传新库即替换旧库），升级主题不会丢失
- 面板显示数据库类型、**构建时间**、大小、节点数；超过 45 天会提示更新
- 可一键删除，回退到手动放置的库

> **上传失败提示超过服务器上限**：DB-IP 国家库（gz 仅 4 MB）几乎在任何主机都能上传；
> 城市库较大，若受限请改用方式二。

### 方式二：手动放置

```php
// 子主题 functions.php：把库放在主题目录之外（推荐）
add_filter( 'aurora_star_geoip_db_path', function () {
    return WP_CONTENT_DIR . '/uploads/geoip/ip-database.mmdb';
} );
```

或直接放到主题目录：

```
wp-content/themes/aurora-star/assets/geoip/ip-database.mmdb
```

---

## 3. 查找优先级

| 顺序 | 位置 |
|---|---|
| 1 | `aurora_star_geoip_db_path` 过滤器指定的路径 |
| 2 | 后台上传的库：`wp-content/uploads/aurora-star-geoip/ip-database.mmdb` |
| 3 | 兼容 v1.3.0 的旧文件名（`GeoLite2-City.mmdb` 等），便于平滑升级 |
| 4 | 主题目录 `assets/geoip/ip-database.mmdb` |

---

## 4. 生效条件

- 后台 **外观 → 自定义 → Aurora Star 主题设置 → 评论**，勾选「显示 IP 归属地」
- 数据库文件可读且校验通过

未放置数据库时该功能**静默关闭**，评论区不会报错，也不会显示归属地。

---

## 5. 性能

- 查询全部在**本地**完成，不会把访客 IP 发送给任何第三方
- 查询结果写入评论 meta（`_aurora_star_geo`），**每条评论只查一次**，
  之后渲染直接读 meta，不会在每次页面浏览时重复读库
- 已有评论会在首次被浏览时补上归属地并缓存

---

## 6. 常见问题

**换了数据库，但评论区还是旧数据？**

归属地按评论缓存在 meta 中，已有评论不会因为换库而自动重算。如需重算，
删除评论 meta 键 `_aurora_star_geo` 即可：

```php
delete_post_meta_by_key( '_aurora_star_geo' );
```

**省/市显示成英文**

DB-IP Lite 的省/市只有英文名（国家名有中文）。这是免费版的限制，付费版才有本地化名称。

**面板显示「只到国家」**

你装的是国家库（如 `DBIP-Country-Lite`），只有国家信息。换成 City 版即可显示省市。

**面板显示 zip 扩展未启用**

服务器没有 zip 扩展，属正常提示；`.tar.gz` / `.gz` / `.mmdb` 不受影响。
