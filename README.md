<div align="center">

# 慢病健康监测 · health-monitor

**血糖、血压、体重、腰围——四个数字记三年，不如一张趋势图看明白。**

面向糖尿病、高血压人群的轻量微信小程序：录入足够简单，统计足够清楚，不做社交、不卖药、不弹广告。

![小程序](https://img.shields.io/badge/微信小程序-UniApp%20%2B%20Vue%203-07C160?logo=wechat&logoColor=white)
![后端](https://img.shields.io/badge/后端-Webman%20%2B%20PHP%208.2-4B8BBE)
![数据库](https://img.shields.io/badge/MySQL-8.0%20%2B%20Redis-4479A1?logo=mysql&logoColor=white)
![接口](https://img.shields.io/badge/API-17%20个-0EA5E9)
![MCP](https://img.shields.io/badge/MCP-17%20工具%20%2B%203%20资源-8B5CF6)

`BMR 自动算` · `BMI 自动算` · `7/30/90 天趋势` · `Token 24h 自动续期` · `数据按客户隔离`

</div>

---

## 为什么做这个

慢病管理的核心不是「今天量了多少」，而是「这三个月走势如何」。但现实是：

- 纸质本子记不住，翻不到三个月前的那一格
- 医院 App 只导得出自家设备的数，血糖一个 App、血压又一个
- 大而全的健康平台要注册、要绑定、要开会员，最后沦为装机即弃

这个项目只做一件事：**让四个关键指标在同一个地方长期沉淀，并且随时能看出趋势。**

- 首页一屏：BMR + 四项指标最新值 + 最近 10 条混合记录（按时间倒序，不用来回切页）
- 每个指标独立录入、独立历史、可删除（填错能改，不用找客服）
- 统计页：选指标 + 选周期（7 / 30 / 90 天）→ 折线图 + 均值 / 极值 / 次数
- 健康档案：性别、出生日期、身高，作为 BMR / BMI 的基准

> 设计取向：**录入路径不超过两次点击**。慢病人群年龄跨度大，能少一步就少一步。

---

## 一眼看懂的架构

```
┌──────────────────────────────────────────────┐
│        微信小程序（UniApp 3 + Vue 3）         │
│  首页 │ 血糖 │ 血压 │ 体重 │ 腰围 │ 统计 │ 我的│
│  9 个页面，统一 api.js，POST + JSON + Token   │
└────────────────────┬─────────────────────────┘
                     │ HTTP / JSON
┌────────────────────▼─────────────────────────┐
│            Webman 2.x（PHP 8.2+）             │
│  ┌─────────────┬──────────────┬───────────┐  │
│  │ API 接口层   │  管理后台     │  MCP 服务  │  │
│  │ 17 个接口    │ EasyAdmin8   │ 17 工具    │  │
│  │ HealthCtrl   │ + Layui      │ + 3 资源   │  │
│  └──────┬──────┴──────┬───────┴───────────┘  │
│  ┌──────▼─────────────▼───────────────────┐  │
│  │ Service：Health（口径/统计）· Token · Applet │
│  └──────┬─────────────────────────────────┘  │
│  ┌──────▼─────────────────────────────────┐  │
│  │ ThinkORM 2.x（连接池 max=5 + 断线重连）  │  │
│  └────────────────────────────────────────┘  │
└────────────────────┬─────────────────────────┘
                     │
        ┌────────────┴────────────┐
        ▼                         ▼
   MySQL（ed_ 前缀）          Redis（Token 24h）
```

三层收口：**Controller → Service → Model**。横切关注点（参数解析、Token 校验、响应封装、SQL 日志）全部压进中间件管道，控制器里只剩业务。

---

## 核心能力

| 模块 | 说明 |
| --- | --- |
| 首页仪表盘 | BMR + 四指标最新值 + 最近 10 条混合记录（四张表各取 5 条合并排序） |
| 四指标录入 | 血糖 / 血压 / 体重 / 腰围，各自独立列表与删除；体重录入自动算 BMI |
| 健康统计 | 指标 × 周期双维度，Canvas 手绘折线图 + 均值 / 极值 / 次数 |
| 健康档案 | 性别、出生日期、身高；为 BMR（Mifflin-St Jeor）与 BMI 提供基准 |
| 后台管理 | 客户管理、RBAC 权限、菜单配置、SQL 日志（EasyAdmin8 + Layui） |
| MCP 工具集 | `php-mcp/server` 暴露 17 个工具 + 3 个资源，AI 可直接读写开发期数据 |

---

## 几个真实踩过的坑

写在这里，是因为它们比功能列表更有信息量：

**1. 小程序 Canvas 和 H5 不是一个东西**
直接上 ECharts 会得到一屏空白。最后用原生 Canvas API 手绘折线：坐标映射、网格线、数据点、数值标签全部自己算，跨端表现一致。

**2. BMI 依赖身高，但用户可能没填档案**
不弹窗打断、不强行拦截。查 Profile，身高为 0 时 BMI 返回 0，前端显示 `--`。**没有数据比错误数据好。**

**3. 混合记录排序不能靠 SQL UNION**
四种指标时间戳混排，用 SQL 拼起来既难读又难索引。改成各查 5 条 → 合并 → `usort` 按时间降序 → 截前 10，逻辑在 Service 层，改动面最小。

**4. 常驻内存会被 MySQL 掐断**
Webman 是常驻进程，`wait_timeout` 一到连接就废。连接池 `max=5` + 断线重连 + 50 秒心跳，三个一起上才稳。

**5. Token 天天过期等于没登录**
`verifyToken` 校验成功后原地续期 24 小时（Redis），活跃用户永不掉线，沉默用户自然过期。

**6. 健康数据必须防越权**
所有查询强制带 `customer_id` 隔离，不依赖前端传参，从中间件注入上下文——这是底线，不是优化。

---

## 快速开始

**环境**：PHP 8.2+ · MySQL 8.0 · Redis · Node 18+（小程序构建）· Composer

```bash
git clone https://github.com/edrin09/health-monitor.git
cd health-monitor

# 1. 后端
cd service
composer install
cp .env.example .env            # 配 MySQL / Redis / 微信 AppID
php webman start                # 默认 8787
# 首次需导入数据库结构（见 service/build.md）

# 2. 小程序
cd ../uni
npm install
npm run dev:mp-weixin           # 产物用微信开发者工具打开
# 首次构建前把 src/manifest.json 的 mp-weixin.appid 填成你自己的小程序 AppID
```

后台入口：`http://localhost:8787/app/admin`（EasyAdmin8）。

---

## 数据表

| 表 | 说明 |
| --- | --- |
| `ed_health_glucose` | 血糖记录 |
| `ed_health_blood_pressure` | 血压记录（收缩压 / 舒张压 / 心率） |
| `ed_health_weight` | 体重记录（含 BMI 快照） |
| `ed_health_waist` | 腰围记录 |
| `ed_customer_profile` | 健康档案 |
| `ed_mall_customer` | 客户主表 |
| `ed_system_admin` / `ed_system_auth` / `ed_system_menu` | 权限体系 |

---

## 技术栈

| 层级 | 选型 |
| --- | --- |
| 小程序 | UniApp 3.x + Vue 3 + Vite 5 + SCSS |
| 后端 | Webman 2.x（PHP 8.2+） |
| 后台 | EasyAdmin8-webman + Layui + Blade |
| ORM | ThinkORM 2.x（连接池 + 断线重连） |
| 存储 | MySQL（`ed_` 前缀）+ Redis（Token 24h 续期） |
| 微信 | EasyWeChat 5.x |
| AI 辅助 | php-mcp/server 3.x |
| 其他 | PHPSpreadsheet、OSS / COS SDK |

---

## 适用与不适用

- ✅ 想给自己或家人长期记血糖血压、并且真的会看趋势图
- ✅ 想拿一套「小程序 + Webman + RBAC 后台 + MCP」的完整参考实现
- ❌ 想要医疗诊断建议——这里没有任何诊疗逻辑，只是记录与统计
- ❌ 想要接入血糖仪硬件直连——当前是手工录入

> 本项目为技术实现参考，不构成医疗建议。健康数据请遵医嘱解读。

---

## License

MIT。数据结构与接口文档见 `service/build.md`，产品侧说明见 `项目介绍.md`。
