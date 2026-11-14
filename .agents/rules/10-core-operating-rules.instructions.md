---
description: Agent 共享工作基线、事实来源与安全边界
applyTo: "**"
---

# Core Operating Policy

## MUST

- 用户当前指令是最高任务约束。
- 更具体、更窄范围的项目政策优先于宽泛政策。
- 代码、Schema、测试和规范业务文档是业务事实来源；Agent 入口和 Rules 不复制业务事实。
- 技术版本和框架能力以 `composer.json`、`composer.lock`、当前代码和实际运行结果为准。
- 修改工作区前必须先确认当前 `git status --short`。
- 必须保留任务开始前已经存在的工作区修改，不得回退、覆盖或提交它们。
- 未经用户明确许可，不执行真实数据写入、删除、重建、截断或重置数据库的操作。
- `php artisan tinker` 默认只读。
- 未经用户明确要求，不执行 `git commit`、`git push`、创建 PR 或发布操作。

## DEFAULT

- 从需求所属路由、Command、Job、Listener 或其它入口开始，沿实际调用链局部阅读。
- 优先复用当前项目已有的目录、命名、分层、Helper、Laravel 能力和相邻代码模式。
- 只有现有模式无法满足需求时才引入新的抽象，并说明原因。
- 不因为规则存在就修改与当前任务无关的历史代码或文档欠账。

## EXCEPTIONS

- 用户明确授权的数据修复、Migration、测试数据库操作或其它写操作，可以执行，但应先确认目标环境、影响范围和恢复方式。
