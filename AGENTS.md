# AGENTS.md

本文件是仓库级 Agent 启动入口。

它只负责：
- 定义 Agent 工作基线；
- 路由项目 Rules；
- 指定事实来源；
- 定义不可违反的安全边界。

详细开发方法位于 `.agents/skills/`，项目业务事实位于 `docs/`、代码、Schema 与测试中。

## 启动协议

- 使用中文回复。Commit Message 必须遵循 Conventional Commits：`<type>[可选 scope][可选 !]: <中文说明>`，例如 `fix(form): 修复提报校验`；禁止省略 type 或只写 `fix`。
- 开始实现、修复、重构、删除或其它可能修改工作区的任务前，先运行 `git status --short`，确认并保留已有修改。
- Codex 处理本仓库 Laravel/PHP 任务时，使用 `neo-laravel` Skill。
- 根据任务范围读取 `.agents/rules/` 中所有命中的专项政策；多个专项政策可以同时生效。
- 用户当前指令优先于项目政策；更具体、更窄范围的项目政策优先于宽泛政策。
- 技术版本、依赖和框架能力以 `composer.json`、`composer.lock`、当前代码和实际运行结果为准。
- 业务事实以代码、Schema、测试和规范业务文档为准，不以 Agent 入口文件中的示例为准。

## 本机开发

- Nginx 配置文件：/opt/homebrew/etc/nginx/servers/remeo.conf
- 测试用户：13801380138，sEkgez-joxzem-myjze7

## Rules 路由

所有任务：

- `.agents/rules/10-core-operating-rules.instructions.md`

PHP、Laravel 架构、类型、格式和质量：

- `.agents/rules/20-architecture-and-code-quality.instructions.md`

文件、外部集成、敏感信息、数据库和 Migration：

- `.agents/rules/30-data-storage-and-database.instructions.md`

Enum、API、业务异常、事务、队列、缓存、锁和并发：

- `.agents/rules/40-domain-api-and-concurrency.instructions.md`

测试、文档和接口契约一致性：

- `.agents/rules/50-testing-and-documentation.instructions.md`


## 工作区安全

- 保留当前工作区已有修改。
- 不回退、覆盖、格式化或提交不属于当前任务的修改。
- 未经用户明确许可，不执行会删除、重建、截断或重置数据库的操作。
- `php artisan tinker` 默认只读；未经明确许可，不修改真实数据。
- 未经用户明确要求，不执行 `git commit`、`git push`、创建 PR 或发布操作。

## 实施与验证

PHP 改动完成后：

- 对本次涉及 PHP 文件运行 Pint。
- 对本次涉及 `app/` PHP 文件运行 PHPStan。
- 根据风险运行相关测试。
- 最终回复说明已执行的验证、未执行的验证及原因。

## 阅读边界

- 需求已定位到具体模块时，从路由或入口沿实际调用链局部阅读。
- 不重新通扫整个 `app/`、`routes/` 或 `docs/`，除非任务确实需要。
- 默认不扫描 `vendor/`、`node_modules/`、`storage/`、`bootstrap/cache/`、`public/build/`。
- 需要理解业务事实时优先查找对应模块的规范文档，而不是把历史代码或 Agent 规则当成业务事实。
