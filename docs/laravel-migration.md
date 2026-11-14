# Laravel 迁移与认证契约

## 范围与运行环境

- 旧项目 `remeo` 保持原代码、配置与 Composer 锁文件；新项目为 `remeo-backend`。
- 新后端使用 Laravel 13.42.0、PHP 8.5；Neo、FastRoute、自定义 JWT 与 Doctrine 连接已移除。
- 查询、事务、路由、认证、Hash、会话、CSRF、HTTP 客户端、日志和请求校验由 Laravel 提供。
- 新库 `new_remeo` 与旧库 `remeo` 独立。不要让新旧后端共用一个库：旧后端无法识别已升级的密码散列。
- 用户提交的项目规则和开发工具保留；原有业务数组服务以 Repository 层迁入，不以 Eloquent 实体替换所有业务返回格式。

## 数据迁入

先创建独立目标数据库，并给运行账号授予目标库读写/迁移权限、源库读取权限。配置 `.env` 的目标库名称，然后运行：

```bash
php artisan migrate
php artisan remeo:import-legacy remeo
```

导入命令验证源目标库名不同、18 张业务表完整且目标业务表均为空；目标库已有记录时会停止。源连接使用只读、一致性事务；目标写入在同一事务内完成，并逐表核对数量。目标迁移表不会从旧库复制。导入异常会回滚目标数据，源库不变。

将旧项目 `public/file/` 复制到新项目相同位置；附件目录被 Git 忽略。数据库导入不修改任何账户密码；第一次成功登录才升级该账户散列。

`docs/mysql-schema.sql` 是本次读取的实际结构快照，不含业务记录。旧 `docs/meningioma.sql` 仅作为历史参考，初始化以 Laravel migrations 为准。

## 前后分离的认证接口

所有调用保留 `{code, msg, data}`，成功码仍为 `0`。前端发送 `Accept: application/json` 并启用 Axios `withCredentials`、`withXSRFToken`。

1. `GET /signin/csrf`：建立会话并返回 `XSRF-TOKEN` Cookie。
2. `POST /signin/dologin`：`mobile`、原始 `password`、图形验证码 `captcha`，成功返回 `data.user`。不再返回 JWT。
3. `GET /signin/islogin`：返回当前用户与权限；未登录返回 HTTP 401。
4. `POST /signin/logout`：注销服务端会话、轮换 CSRF Token。
5. `POST /signin/changepassword`：保留 `password/password1/password2`，使用 Laravel `current_password` 规则。当前会话保留；其他使用旧密码的会话下次请求失效。
6. 原短信发送/重置地址保留并限流。短信网关地址由 `ALIYUN_SMS_URL` 提供。日志不记录验证码或新密码。

登录页先调用 `GET /signin/captcha`，返回 `data.img`（JPEG Data URI），并在当前 Cookie 会话保存验证码。响应禁止缓存，不返回答案或校验密钥。验证码采用 `config/captcha.php` 的默认配置（当前 4 位、不区分大小写、60 秒有效）；刷新后旧图失效，校验尝试会消耗验证码，即使密码错误也必须换图。验证码缺失、错误、过期或来自其他会话时返回 HTTP 422，具体中文提示位于 `data.errors.captcha`。验证码接口每 IP 每分钟最多 30 次，登录仍为每分钟 5 次。前端加载失败可点击重试，登录失败自动刷新，提交过程中禁止重复提交和换图。

写请求（POST/DELETE 等）必须携带 `X-XSRF-TOKEN`。CSRF 过期返回 419，参数错误返回 422，限流返回 429。前端不在 localStorage 存放认证凭证，已有 `token` 在登录信息同步时清除；缓存姓名不能作为登录证明。

### 路由认证分组

`routes/web.php` 的业务路由统一放在 `Route::middleware(['auth', 'session.password'])->group(...)` 中：先由 Laravel 默认 `web` guard 验证会话，再校验登录时保存的密码散列，密码重置后的旧会话返回 HTTP 401。医院和医生管理路由嵌套 `can:manage-administration` 权限组；门诊、住院及下载仍保留各自的医院范围和下载权限检查。

公开入口为 `/signin/captcha`、`/`、`/index`、`/signin/csrf`、`/signin/dologin`、`/signin/sendsmscode`、`/signin/resetpassword`。`/signin/logout` 允许未登录或已失效会话调用，便于幂等清理；仍受 CSRF 保护。框架健康检查 `/up` 独立于业务路由。新增业务接口应注册在认证组内，不能因增加同控制器的方法而自动公开。

当前采用同源代理下的 Cookie 会话认证，使用 `auth` 即可；未安装 Sanctum，不使用 `auth:sanctum`。所有业务路由仍由 `web` 中间件组提供 Session 和 CSRF，接口路径、请求字段和响应格式保持不变。

默认 SessionGuard 使用文件会话；多节点部署应改用 Laravel 支持的共享会话存储。同站子域部署时配置 `SESSION_DOMAIN=.example.com`、精确的 `CORS_ALLOWED_ORIGINS`，生产环境必须 HTTPS 且 `SESSION_SECURE_COOKIE=true`。本地开发 `SESSION_DOMAIN` 留空、Secure 关闭；前端 `/api` 代理方式可避免跨站 Cookie 限制。

## Laravel 原生机制

- 控制器通过构造器注入具体 Service，导出服务通过 action 参数注入；不再使用 `addServices`、`addHelpers`、动态属性和 `loadClass/loadService/loadModel/loadHelper`。
- Service 和有业务职责的 Repository 通过 `Model::query()` 使用 Eloquent，`app/Models` 覆盖基线迁移中的全部 18 张业务表。模型显式指定旧表名并关闭自动时间戳，时间字段仍由业务层维护；已移除 `BaseRepository` 与仅声明表名的仓库，不再解析旧式条件键、查询选项、返回选项或字符串自增表达式。读取使用 `first/get/value/pluck/count`，写入使用 `insert/insertGetId/update/increment/delete`。
- 数组接口契约在调用处显式转换：需要按主键关联的导出结果使用 `keyBy`，列表保持连续索引，缺失的可选记录保留 `null`。Service 和 Repository 通过模型的 `getAttributes()` 保留原始字段数组（包括内部密码校验需要的凭据），对外响应仍负责隐藏敏感字段。软删除继续显式维护 `deletedat`，用户查询沿用 `User` 的有效账户全局作用域；短信日志和操作日志显式编码 JSON，不依赖通用写入封装。
- 删除 `BaseService` 及 `getXxxxModel()`、`tableModel()`，日志也通过注入的 `LogRepository::record()` 保存。涉及数据库的静态 Service 方法改为实例方法；事务由 Service 使用 `DB` 管理，认证实体仍使用 Eloquent `User`。
- 表单使用 FormRequest 的 `rules()`、`validated()`；`RegistryRequest` 仅负责保留旧数据表的字段缺省值。错误的嵌套结构返回 422，不会被默认值覆盖。
- 门诊复查和住院随访的 `video.cemrilength/cemriwidth/cemrithick`、`video.t2flairlength/t2flairwidth/t2flairthick` 与数据库 `SMALLINT UNSIGNED` 一致：接受 0–65535 的整数及整数字符串；缺省、null 和空字符串按既有字段默认值存为 0。负数、小数、越界、布尔值和非法结构返回 422，避免 MySQL 严格模式下写入空字符串导致 500。可选影像日期 `video.videoday` 的缺省、null 和空字符串保存为 NULL；非空值必须是有效的 `Y-m-d` 日期。
- 删除旧 `input/inputOne/inputArray`、`INPUT_TYPE_*`、响应码和路径运行时全局常量；查询参数使用 Request，配置与路径使用 Laravel helper。
- 控制器类名与文件名统一使用 `Controller` 后缀；路由路径保持不变。
- 控制器通过 `success($message, $data, $status)` / `failure($message, $data, $status)` 返回 JSON，业务码分别固定为 0 / 1，默认 HTTP 状态分别为 200 / 400；下载直接返回 `BinaryFileResponse`。异常统一由 `bootstrap/app.php` 渲染，预期业务拒绝沿用 418，未预期错误返回 500。
- 医院/医生管理使用 `can:manage-administration`，导出使用 `Gate::authorize('download-patient')`，权限不足返回 403 JSON。
- 附件使用 `UploadedFile` 和 `Storage` 的 `patient_media` 本地磁盘；原 `/file/` 地址与目录保留，文件名随机化，数据库写入失败会清理本次文件。
- 新增医生先取得真实用户 ID，再在 Laravel 数据库事务内写入权限，防止权限挂到 ID 0。

## 接口兼容与校验

- 门诊、住院、医院、医生、影像、手术、病理和随访的接口路径及主要字段保持。
- GET 参数走 query；POST/DELETE 支持表单体；上传使用 multipart/form-data。
- 影像上传只接收 JPG/PNG，最大 10 MiB，与前端说明一致。
- 患者查询保留 `deletedat=0`、医院范围及原门诊/住院删除标记。
- 导出使用 Laravel 文件响应；患者详情导出复用已授权的患者上下文。
- 新项目对空病历、缺失可选字典字段、无首诊/随访记录和空日期做兼容处理。

## 验证与回退

自动测试覆盖新旧密码、软删除账户、会话注销、密码变化失效、CSRF、医院隔离、分页分组/连接查询、事务回滚、患者创建、门诊首诊/复查、住院手术/病理/随访及 Excel/Word 导出；另外覆盖管理 CRUD、下载 Gate、FormRequest 非法参数与 Storage 影像上传。

本机源数据只读复制，旧项目保留。回退时需同时切回旧前端和旧后端的 6789/旧数据库；不得只把旧后端连接到 `new_remeo`。上线后新旧库会独立变化，切换前必须评估新增业务数据，不能用旧库覆盖新库。密码迁移的 down 不缩短字段，基线 down 不删除医疗数据表。

真实短信投递与完整人工临床录入验收需使用实际业务场景另行确认。

## 原生查询复审

- 患者多表写入统一使用 `DB::transaction()`，操作日志在同一事务中保存；数据库和日志异常由框架异常处理器返回 HTTP 500，业务异常保持原有契约。
- 门诊、住院列表通过 `PatientListRequest` 校验 `realname`、`hospitalid`、页码 `p` 和排序参数。`sort` 可选 `id/realname/age/gender/firstday/hospday/hospcode/hospseq/checkcount/followupcount/lastcheckday/nextcheckday/lastfollowupday/nextfollowupday/createdat/updatedat`；`order` 接受 `ascending/descending/asc/desc/ASC/DESC`。非法参数返回 HTTP 422。分页仍返回 `total/items`，不切换前端响应格式。
- Excel 和 Word 导出临时文件通过 `Storage::disk('local')` 写入 `exports/`，下载响应发送后删除，避免依赖系统固定的 `/tmp` 路径。
- 翻译文件改用 Laravel 标准的 `lang/zh_CN.json` 和 `lang/en.json`，移除不会被框架加载的旧 `app/Language` PHP 字典。
