# 脑膜瘤数据库 API

独立 Laravel 13.42 项目，要求 PHP 8.5。前端位于相邻 `remeo-frontend`，旧 Neo 项目保留在相邻 `remeo`。

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

```bash
php vendor/bin/phpunit
php vendor/bin/phpstan analyse --memory-limit=2G
php vendor/bin/pint --test app routes
```

测试强制使用 SQLite `:memory:`，逐次创建测试表，不连接业务库、不执行数据库重置命令。

## 架构

- `routes/web.php`：沿用旧接口路径，统一使用 Laravel 会话及 CSRF 中间件。
- `app/Controller/`：通过构造器注入业务 Service；输入校验使用 `app/Http/Requests/` 的 FormRequest，直接返回 Laravel JSON/文件响应。
- `app/Providers/AppServiceProvider.php`：注册认证 Provider 与 Gate；管理权限由路由 `can` 中间件执行。
- `app/Function/functions.php`：仅保留业务辅助函数，不包含 `loadXXX`、`inputXXX` 或动态依赖加载。
- `app/Service/`：患者、医院、医生、影像、手术、病理、复查、随访及导出。
- `app/Repositories/`：仅保留医院名称、患者统计、用户查询、字典、权限和日志等有业务职责的仓库，内部直接使用 Laravel Query Builder；Service 的读写使用原生 `where/first/get/pluck/count/insertGetId/update/increment`，不再提供通用 CRUD 兼容层。
- 删除旧 `BaseService`、`getXxxxModel()` 和 `tableModel()`；事务由 Service 使用 Laravel `DB` 管理。
- `app/Models/User.php`：Laravel Eloquent 身份模型，映射现有 `user` 表。
- `app/Auth/LegacyUserProvider.php`：仅兼容旧账户散列；验证成功后由 Laravel 升级为默认密码散列。
- `database/migrations/`：实际 18 张业务表的基线及密码字段升级。

本项目没有机构端/运营端或 Team 模型，数据隔离使用原业务的 `hospitalid`。权限沿用超级用户、操作员、跨院查看及下载权限。迁移保持 `code: 0` 成功响应，不引入其他项目的业务枚举和响应码。

认证协议、迁移与回退步骤见 [迁移说明](docs/laravel-migration.md)。
