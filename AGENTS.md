# AGENTS.md

本文件是仓库级 Agent 启动入口，只负责：工作基线、事实来源、Skill 与 Rules 路由、安全边界。

详细开发方法位于 `.agents/skills/`；业务事实位于 `README.md`、代码、Schema 与测试中。

## 启动协议

- 使用中文回复。Commit Message 必须遵循 Conventional Commits：`<type>[可选 scope][可选 !]: <中文说明>`，例如 `fix(form): 修复提报校验`；禁止省略 type 或只写 `fix`。
- 开始实现、修复、重构、删除或其它可能修改工作区的任务前，先运行 `git status --short`，确认并保留已有修改。
- 优先级：用户当前指令 > `.agents/rules/` 项目政策 > `README.md`、代码、Schema 与测试 > Skill 中的通用建议；同一层级内更具体、更窄范围的政策优先。
- Laravel/PHP 任务默认使用 `neo-laravel` Skill，并按任务类型加载其它 Skill（见「Skill 路由」）。
- 根据任务范围读取 `.agents/rules/` 中所有命中的专项政策；多个专项政策可以同时生效。
- 技术版本、依赖和框架能力以 `composer.json`、`composer.lock`、当前代码和实际运行结果为准。
- 业务事实以代码、Schema、测试和 `README.md` 为准，不以 Agent 入口文件中的示例为准。

## 事实来源

- `README.md`：运行方式、认证与会话、接口与数据契约，是当前唯一的规范后端文档。
- `tests/README.md`：测试模式、覆盖范围，以及新增或调整路由时必须同步 `RouteContractTest` 的要求。
- `app/`、`routes/`、`database/migrations/`、`tests/`：实现事实。
- `docs/`：病历、随访等参考附件（xlsx/docx/txt），不是规范文档。

## Skill 路由

Skill 由运行时自动发现 `.agents/skills/`；本文件只固定默认技能与适用边界。Skill 与 Rules 冲突时以 Rules 为准。

| 任务 | Skill | 边界 |
| --- | --- | --- |
| Laravel/PHP 后端实现、修复、重构、评审（默认） | `neo-laravel` | 本仓库工作方法入口，始终与命中的 Rules 一起使用 |
| 需要框架级通用建议（Eloquent、查询性能、迁移、测试等） | `laravel-best-practices` | 仅作知识库；与 Rules、现有代码冲突时以后者为准 |
| 使用 `laravel/ai` 开发 AI 功能 | `ai-sdk-development` | 当前 `composer.json` 未引入 `laravel/ai`，引入后再启用 |

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

Rules 文件头的 `applyTo` 仅供 IDE 提示，实际适用范围以本节路由为准。

## 本机开发

- Nginx 配置文件：/opt/homebrew/etc/nginx/servers/remeo.conf
- 本机测试账号：13801380138 / sEkgez-joxzem-myjze7（仅本地开发，不用于其它环境）

## 工作区安全

- 保留当前工作区已有修改。
- 不回退、覆盖、格式化或提交不属于当前任务的修改。
- 未经用户明确许可，不执行会删除、重建、截断或重置数据库的操作。
- `php artisan tinker` 默认只读；未经明确许可，不修改真实数据。
- 未经用户明确要求，不执行 `git commit`、`git push`、创建 PR 或发布操作。

## 实施与验证

PHP 改动完成后，按 [20 号规则](.agents/rules/20-architecture-and-code-quality.instructions.md) 的质量门禁执行 Pint、PHPStan，并按风险运行相关测试；全量测试入口为 `composer test`。

最终回复说明已执行的验证、未执行的验证及原因。

## 阅读边界

- 需求已定位到具体模块时，从路由或入口沿实际调用链局部阅读。
- 不重新通扫整个 `app/`、`routes/` 或 `docs/`，除非任务确实需要。
- 默认不扫描 `vendor/`、`node_modules/`、`storage/`、`bootstrap/cache/`、`public/build/`。
- 需要理解业务事实时优先查找对应模块的规范文档，而不是把历史代码或 Agent 规则当成业务事实。
