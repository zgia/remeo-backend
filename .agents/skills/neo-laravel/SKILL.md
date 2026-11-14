---

name: neo-laravel
description: 在本仓库中实现、修复、重构、测试或评审 Laravel 和 PHP 后端工作时使用。涉及 PHP、路由、API、Controller、Request、Resource、Service、Repository、Model、Enum、存储、Migration、测试或后端文档时使用。
---

# Neo Laravel

本 Skill 是本仓库 Laravel/PHP 任务的工作方法入口。

它负责定义**如何开展 Laravel 工作**；项目政策由 `.agents/rules/` 定义；业务事实以当前代码、Schema、测试和规范业务文档为准。

## 开始任务

1. 阅读根目录 `AGENTS.md`。
2. 运行 `git status --short`，确认并保留任务开始前的所有工作区修改。
3. 根据任务范围确定实际影响的入口、文件、行为和操作。
4. 从路由、Controller、Command、Job、Listener 或其它实际入口开始，沿调用链局部阅读。
5. 阅读 `AGENTS.md` 路由出的全部适用 Rules；多个 Rules 可以同时生效。
6. 任务涉及 Laravel 框架、Composer 依赖、脚本、测试、静态分析或运行时行为时，根据需要阅读 `composer.json`、`composer.lock` 和当前代码。
7. 优先复用当前项目已有的目录、命名、分层、Helper、Laravel 能力和相邻实现。
8. 只有现有模式无法满足需求时才引入新的抽象，并说明原因。

不要把文件名索引视为已经加载的上下文。在作出受某项 Rule 约束的决策前，必须实际读取对应 Rule。

## 分析与评审

分析、排查和代码评审默认保持只读。

* 从归属入口沿实际调用链追踪。
* 以当前代码、Schema、测试和规范业务文档为事实依据。
* 不因为发现其它历史问题而扩大任务范围。
* 发现 Rule、文档和代码不一致时，判断其属于：

  * 新代码默认；
  * 有意例外；
  * 历史欠账；
  * 政策漂移。
* 无法确认时，不静默猜测。

## 实现

实现任务遵循以下顺序：

1. 确认当前工作区状态。
2. 定位入口和调用链。
3. 阅读适用 Rules。
4. 阅读相关业务事实文档。
5. 阅读相邻实现和调用方。
6. 设计最小必要改动。
7. 实施修改。
8. 根据变更风险补充或修改测试。
9. 检查需要同步的 API、数据、状态、配置、事件或文档契约。
10. 执行质量检查。
11. 检查最终 diff，确认没有影响无关修改。

不要为了“顺便优化”而扩大修改范围。

## Laravel 项目事实

以下内容不要在 Skill 中复制具体业务事实：

* 数据模型和字段含义；
* API 参数和响应；
* 状态机；
* 配置值；
* Enum 的具体 case；
* 文案；
* 路由完整清单。

这些信息应从当前代码、Schema、测试和 `docs/` 中读取。

## 通用 Laravel 指导

需要通用 Laravel 最佳实践时，可以阅读：

`.agents/skills/laravel-best-practices/SKILL.md`

但它只是通用建议。

当它与以下内容冲突时，优先级为：

1. 用户当前指令；
2. 项目 Rules；
3. 当前代码、Schema、测试和规范业务文档；
4. 通用 Laravel 建议。

## 验证

PHP 改动完成后：

```bash
./vendor/bin/pint --parallel <changed-php-files...>
```

涉及 `app/` 的 PHP 文件：

```bash
./vendor/bin/phpstan analyse --memory-limit=2G <changed-app-files...>
```

根据风险运行最小且相关的测试：

```bash
php artisan test <test-path-or-filter>
```

如果仓库存在本地 PHPUnit 配置，优先使用该配置。

完成后：

```bash
git diff --check
git diff
```

检查最终 diff，确认：

* 当前任务的修改完整；
* 无关修改没有被覆盖；
* 没有产生无意义的格式化；
* 没有遗漏测试或文档同步。

不得通过破坏性数据库操作解决测试环境问题。

最终回复必须说明：

* 实际完成的修改；
* 已执行的验证；
* 未执行的验证及原因；
* 如有发现但未处理的无关问题，简要说明。

除非用户明确要求，否则不得：

* `git commit`
* `git push`
* 创建 Pull Request
* 发布
* 修改真实数据
* 执行破坏性数据库操作
