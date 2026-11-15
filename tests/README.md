# 后端接口测试

在 `remeo-backend` 根目录执行：

```bash
# 全部测试（含认证、权限、迁移回归和 Repository 测试）
composer test

# 逐条查看全部路由成功用例的执行结果
php vendor/bin/phpunit tests/Feature/RouteContractTest.php --testdox

# 使用 .env 中的 MySQL 连接和开发库结构执行全量测试
REMEO_MYSQL_TEST_DATABASE=new_remeo php vendor/bin/phpunit --configuration phpunit.mysql.xml

# 仅检查未登录访问和失效会话
php vendor/bin/phpunit tests/Feature/RouteAuthenticationTest.php
```

## 覆盖范围

`RouteContractTest` 为 `routes/web.php` 当前全部 **64 个 HTTP 方法 + 路径组合**提供独立成功用例，涵盖首页、会话与密码、短信、医院、医生、门诊、住院、复查、随访、上传和导出；另外检查 `/up` 健康检查及门诊、住院的单患者 Word 下载。

- 通过 Laravel HTTP Kernel 运行真实路由、中间件、FormRequest、Controller、Service 和数据库查询。
- GET 使用 query；POST、DELETE 使用表单参数；上传使用模拟图片。
- JSON 响应检查 HTTP 200、`code: 0`、`msg`、`data` 和接口关键字段。
- 写入接口检查数据库结果；删除检查 `deleted_at`；密码检查摘要和会话；再次住院检查历史记录和完整度重置。
- Excel 和 Word 下载检查响应头，并打开生成文件确认包含目标患者。
- 用例清单与 Laravel 实际注册的全部 `web` 路由比较。新增、移除或改变请求方法时，必须同步 `RouteContractTest::cases()`，否则测试失败。GET 自动附带的 HEAD 不计作独立业务接口。
- `RouteAuthenticationTest` 遍历全部受保护业务路由，确认未登录请求被拒绝；现有 `MigrationTest` 补充参数错误、跨医院访问、下载权限、CSRF 和业务流程回归。
- `ItemsSearchShapeTest` 固定真实库出现的脏数据形态：软删除的医生、父记录缺失、空身份证、空随访日，以及姓名/住院号两条搜索分支；列表与搜索在这类数据下必须返回 200。

## 数据与外部服务隔离

默认 `phpunit.xml` 使用 SQLite `:memory:`；`phpunit.mysql.xml` 使用 `.env` 中的 MySQL 开发连接，凭据不写入测试配置。两种模式均使用数组缓存、数组会话和 `DatabaseTransactions`，每个用例结束后回滚。

MySQL 模式要求 `REMEO_MYSQL_TEST_DATABASE` 与实际连接的库名完全一致，且全部原表使用 InnoDB。测试通过 `SHOW CREATE TABLE` 读取开发库真实结构，在当前连接内建立同名临时表；不复制数据、不重置开发表，也不在 MySQL 上执行迁移。固定测试 ID 只写入临时表，因此不会碰到已有患者和医生。临时表随连接关闭自动释放，测试禁止持久连接和自动重连，避免失去临时表后误访问原表。时间字段迁移回填用例会执行 DDL，只在 SQLite 内存库运行，MySQL 模式下跳过。

数据库账号需要读取结构和创建临时表的权限。如果提示 `CREATE TEMPORARY TABLE` 权限不足，由管理员按实际库名与账号授权，例如本机开发配置：

```sql
GRANT CREATE TEMPORARY TABLES ON `new_remeo`.* TO 'remeo'@'localhost';
```

无需授予 DROP 权限，也不执行 `migrate:fresh`、TRUNCATE 或清空开发表。MySQL 临时表隔离适用于当前单连接同步接口；跨连接、队列和并发锁测试需要另行设计专用测试库。

短信使用 Laravel `Http::fake()`，并禁止未匹配的网络请求；测试会检查模拟短信中的验证码/密码与内存库一致，不实际发送短信。上传和导出使用 `Storage::fake()`，下载临时文件在断言后清理。

这组测试验证应用内部接口契约，MySQL 模式还覆盖开发库实际字段、索引、类型与 SQL 规则；不替代 Nginx/PHP-FPM、浏览器 Cookie/CORS、跨连接并发和真实短信供应商的联调。Laravel 默认在测试环境跳过 CSRF；实际 CSRF 拦截由现有 `MigrationTest::test_csrf_is_required_and_issued_for_the_spa` 单独验证。

## 断言策略

`phpunit.xml` 与 `phpunit.mysql.xml` 开启 `failOnWarning`、`failOnNotice`、`failOnRisky` 和 `beStrictAboutOutputDuringTests`：PHP Warning/Notice、没有断言的用例和测试输出都会让测试失败。新增用例必须断言真实行为，不能靠“输出看着对”通过。

## 真实数据冒烟

fixture 无法表达真实库的数据形态，因此另有只读冒烟命令 `php artisan remeo:smoke`：

- 复用真实 HTTP Kernel、中间件、Controller、Service 和当前 `.env` 数据库；
- 只发送 GET，整轮包在一个事务里，结束时无条件回滚；
- 按当前医院的真实样本覆盖列表、分页、排序、中文姓名、住院号、软删除医生的患者搜索，以及脏数据患者的详情与首页；
- 捕获日志中的 `Undefined array key`、`Trying to access array offset`、`error` 级日志和 5xx，断言组任一出现即失败；
- 边界探测组（`%`、`_`、超长关键词、非法 `sort`/`order`、不存在的患者）只报告不失败，`--strict` 时也计为失败；
- 默认账号 3；production 环境默认拒绝执行，需要显式 `--force`。
