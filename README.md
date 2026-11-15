# 脑膜瘤数据库 API

独立 Laravel 13.42 项目，要求 PHP 8.5。前端位于相邻 `remeo-frontend`。

查询、事务、路由、认证、Hash、会话、CSRF、HTTP 客户端、日志和请求校验均使用 Laravel 原生能力；业务接口保留原有的数组返回结构，不以 Eloquent 实体替换响应格式。

## 本地运行

```bash
composer install
cp .env.example .env
php artisan key:generate
# 配好独立数据库与 Cookie 域名后执行
php artisan migrate
```

本机已经配置 Nginx：`http://localhost:8977` → 本项目 `public/`，开发数据库为 `new_remeo`。
前端开发代理指向 8977。前端生产构建若通过 Nginx 的 8766 访问，需要 `/api/`、`/file/` 同样代理至 8977。

开发库独立部署，不与历史后端共用一个库。密码登录成功时 Laravel 会把该账户的密码原地升级为默认散列（`rehash_on_login`），而历史后端只识别 `md5(md5(password) + salt)`；共用一库会让这些账户在历史后端逐步无法登录，新增字段与表对历史代码也不可见。

```bash
php vendor/bin/phpunit
php vendor/bin/phpstan analyse --memory-limit=2G
php vendor/bin/pint --test app routes
```

测试强制使用 SQLite `:memory:`，逐次创建测试表，不连接业务库、不执行数据库重置命令。

`new_remeo` 等真实库的数据形态（软删除的医生/医院、空字符串、NULL 日期）由只读冒烟覆盖。命令复用真实路由与中间件，只发送 GET，整轮包在一个事务里并回滚：

```bash
php artisan remeo:smoke            # 断言组出现 5xx 或 Warning 日志时退出码非 0
php artisan remeo:smoke --strict   # 边界探测组的发现也计为失败
```

默认使用账号 3（`SUPER_USERS` 中的开发账号），可用 `--user` 指定；production 环境默认拒绝执行，需要显式 `--force`。

## 架构

- `routes/web.php`：业务接口沿用原路径，统一使用 Laravel 会话及 CSRF 中间件；分组规则见下文。
- `app/Controller/`：通过构造器注入业务 Service；输入校验使用 `app/Http/Requests/` 的 FormRequest，直接返回 Laravel JSON/文件响应。
- `app/Providers/AppServiceProvider.php`：注册认证 Provider 与 Gate；管理权限由路由 `can` 中间件执行。
- `app/Function/functions.php`：业务辅助函数。
- `app/Service/`：患者、医院、医生、影像、手术、病理、复查、随访及导出；事务由 Service 使用 `DB` 管理。
- `app/Repositories/`：医院名称、患者统计、用户查询、字典、权限和日志等有业务职责的仓库，内部直接使用 Laravel 查询构建器与 Eloquent。
- `app/Models/`：覆盖 18 张业务表，显式指定表名，时间字段统一为 `created_at/updated_at/deleted_at`，由框架与字段默认值维护；读取与写入统一经 `Model::query()`。
- `app/Models/User.php`：Eloquent 身份模型，映射现有 `user` 表。
- `app/Auth/LegacyUserProvider.php`：兼容历史账户密码散列；验证成功后由 Laravel 升级为默认散列。
- `database/migrations/`：实际 18 张业务表的基线及密码字段升级。

本项目没有机构端/运营端或 Team 模型，数据隔离使用原业务的 `hospitalid`。权限沿用超级用户、操作员、跨院查看及下载权限。接口保持 `code: 0` 成功响应，不引入其他项目的业务枚举和响应码。

## 认证与会话

所有接口返回 `{code, msg, data}`，成功码为 `0`。前端发送 `Accept: application/json`，并启用 Axios 的 `withCredentials` 与 `withXSRFToken`。

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/signin/csrf` | 建立会话并返回 `XSRF-TOKEN` Cookie |
| GET | `/signin/captcha` | 返回 `data.img`（JPEG Data URI） |
| POST | `/signin/dologin` | `mobile`、原始 `password`、图形验证码 `captcha`，成功返回 `data.user`，不返回 JWT |
| GET | `/signin/islogin` | 返回当前用户与权限；未登录返回 HTTP 401 |
| POST | `/signin/logout` | 注销服务端会话、轮换 CSRF Token |
| POST | `/signin/changepassword` | `password/password1/password2`，使用 `current_password` 规则 |
| GET | `/signin/sendsmscode` | 发送短信验证码，受限于每分钟 3 次 |
| POST | `/signin/resetpassword` | 短信重置密码，受限于每分钟 3 次 |

验证码在 Cookie 会话中保存，响应禁止缓存且不返回答案或校验密钥。配置取 `config/captcha.php` 默认项（当前 4 位、不区分大小写、60 秒有效）：刷新后旧图失效，校验尝试即消耗验证码，密码错误也必须换图。缺失、错误、过期或来自其他会话时返回 HTTP 422，中文提示位于 `data.errors.captcha`。验证码接口每 IP 每分钟最多 30 次，登录每分钟 5 次。前端加载失败可点击重试，登录失败自动刷新，提交过程中禁止重复提交和换图。

短信网关地址由 `ALIYUN_SMS_URL` 提供；日志不记录验证码或新密码。

修改密码后当前会话保留，其他使用旧密码的会话在下一次请求失效。前端不在 localStorage 存放认证凭证，登录信息同步时清除已有的 `token`；缓存姓名不能作为登录证明。

### 请求保护

写请求（POST/DELETE 等）必须携带 `X-XSRF-TOKEN`。CSRF 过期返回 419，参数错误返回 422，限流返回 429。

### 路由认证分组

`routes/web.php` 的业务路由统一放在 `Route::middleware(['auth', 'session.password'])->group(...)` 中：先由 `web` guard 验证会话，再校验登录时保存的密码散列，密码重置后的旧会话返回 HTTP 401。医院和医生管理路由嵌套 `can:manage-administration` 权限组；门诊、住院及下载仍保留各自的医院范围和下载权限检查。

公开入口仅 `/signin/captcha`、`/`、`/index`、`/signin/csrf`、`/signin/dologin`、`/signin/sendsmscode`、`/signin/resetpassword`。`/signin/logout` 允许未登录或已失效会话调用，便于幂等清理，仍受 CSRF 保护。框架健康检查 `/up` 独立于业务路由。新增业务接口应注册在认证组内，不能因增加同控制器的方法而自动公开。

认证使用同源代理下的 Cookie 会话，路由中间件用 `auth` 即可，未安装 Sanctum，不使用 `auth:sanctum`。所有业务路由仍由 `web` 中间件组提供 Session 和 CSRF，接口路径、请求字段和响应格式保持不变。

### 部署与会话存储

默认使用文件会话（`SESSION_DRIVER=file`）；多节点部署应改用 Laravel 支持的共享会话存储。同站子域部署时配置 `SESSION_DOMAIN=.example.com` 和精确的 `CORS_ALLOWED_ORIGINS`，生产环境必须 HTTPS 且 `SESSION_SECURE_COOKIE=true`。本地开发 `SESSION_DOMAIN` 留空、Secure 关闭；前端走 `/api` 代理可避免跨站 Cookie 限制。

## 接口与数据契约

- 门诊、住院、医院、医生、影像、手术、病理和随访的接口路径及主要字段保持不变。
- GET 参数走 query；POST/DELETE 支持表单体；上传使用 multipart/form-data。
- 影像上传只接收 JPG/PNG，最大 10 MiB。
- 患者查询保留软删除作用域、医院范围及原门诊/住院删除标记。
- 导出使用 Laravel 文件响应；患者详情导出复用已授权的患者上下文。
- 空病历、缺失可选字典字段、无首诊/随访记录和空日期均做兼容处理。

### 响应与异常

- 控制器通过 `success($message, $data, $status)` / `failure($message, $data, $status)` 返回 JSON，业务码固定为 0 / 1，默认 HTTP 状态分别为 200 / 400；下载直接返回 `BinaryFileResponse`。
- 异常统一由 `bootstrap/app.php` 渲染：预期业务拒绝返回 418，未预期错误返回 500。
- 医院/医生管理使用 `can:manage-administration`，导出使用 `Gate::authorize('download-patient')`，权限不足返回 403 JSON。

### 参数校验

- 表单使用 FormRequest 的 `rules()`、`validated()`；`RegistryRequest` 负责数据表的字段缺省值。错误的嵌套结构返回 422，不会被默认值覆盖。
- 门诊复查和住院随访的 `video.cemrilength/cemriwidth/cemrithick`、`video.t2flairlength/t2flairwidth/t2flairthick` 与数据库 `SMALLINT UNSIGNED` 一致：接受 0–65535 的整数及整数字符串；缺省、null 和空字符串存为 0。负数、小数、越界、布尔值和非法结构返回 422。可选影像日期 `video.videoday` 的缺省、null 和空字符串保存为 NULL，非空值必须是有效的 `Y-m-d` 日期。
- 门诊、住院列表通过 `PatientListRequest` 校验 `realname`、`hospitalid`、页码 `p` 和排序参数。`sort` 可选 `id/realname/age/gender/firstday/hospday/hospcode/hospseq/checkcount/followupcount/lastcheckday/nextcheckday/lastfollowupday/nextfollowupday/created_at/updated_at`；`order` 接受 `ascending/descending/asc/desc/ASC/DESC`。非法参数返回 HTTP 422。
- 分页返回 `total/items`，不切换前端响应格式。

### 写入、事务与文件

- 患者多表写入统一使用 `DB::transaction()`，操作日志在同一事务中保存；数据库和日志异常由框架异常处理器返回 HTTP 500。
- 软删除由 `SoftDeletes` 全局作用域负责（`deleted_at IS NULL`），查询一律通过模型进入；短信日志和操作日志显式编码 JSON。
- 数组契约在调用处显式转换：按主键关联的导出结果使用 `keyBy`，列表保持连续索引，缺失的可选记录保留 `null`。Service 和 Repository 通过 `getAttributes()` 保留原始字段数组（含内部密码校验所需凭据），对外响应隐藏敏感字段。
- 附件使用 `UploadedFile` 和 `Storage` 的 `patient_media` 本地磁盘；`/file/` 地址与目录保留，文件名随机化，数据库写入失败会清理本次文件。
- Excel 和 Word 导出临时文件通过 `Storage::disk('local')` 写入 `exports/`，下载响应发送后删除。
- 新增医生先取得真实用户 ID，再在数据库事务内写入权限。
- 翻译文件使用 `lang/zh_CN.json` 和 `lang/en.json`。

## 验证

自动测试覆盖历史账户散列与默认散列密码、软删除账户、会话注销、密码变化失效、CSRF、医院隔离、分页分组/连接查询、事务回滚、患者创建、门诊首诊/复查、住院手术/病理/随访及 Excel/Word 导出；另外覆盖管理 CRUD、下载 Gate、FormRequest 非法参数与 Storage 影像上传。

真实短信投递与完整人工临床录入验收需使用实际业务场景另行确认。
