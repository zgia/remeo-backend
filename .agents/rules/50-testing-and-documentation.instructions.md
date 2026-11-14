---
description: 测试契约、数据库测试安全与业务文档一致性
applyTo: "{app/**/*.php,config/**/*.php,routes/**/*.php,database/migrations/**/*.php,tests/**/*.php,docs/**/*.md}"
---

# Testing And Documentation Policy

## TESTING

- 根据变更风险覆盖关键行为。
- API 契约、FormRequest 校验、Service 业务规则和回归边界，在最能证明行为的层级测试。
- 测试失败时先确认 Request、Schema、Service、现有文档和调用方的真实契约。
- 正确测试暴露生产缺陷时修复生产代码，不为了迎合错误测试修改生产逻辑。
- 数据库断言使用真实整数 ID；Hashid 只在接口输入输出边界使用。
- 默认使用 `DatabaseTransactions` 隔离数据库测试。
- 需要跨连接观察提交状态或并发测试时，可以不用事务，但必须使用专用测试数据并可靠清理。
- 测试数据使用唯一值，避免依赖共享数据库中的历史数据。
- PHPUnit 测试方法遵循仓库现行的 `test_行为描述` snake_case 命名，并声明 `void` 返回类型。
- 优先使用 Laravel fake；没有合适 fake 时再使用 Mockery。

## DANGEROUS DATABASE TESTS

未经用户明确许可，不得执行或引入：

- `RefreshDatabase`
- `LazilyRefreshDatabase`
- `DatabaseMigrations`
- `DatabaseTruncation`
- `migrate:fresh`
- `migrate:refresh`
- `migrate:reset`
- `db:wipe`

测试不得通过重置共享数据库解决环境问题。

## DOCUMENTATION

当修改以下内容时，必须检查对应事实文档：

- API 参数或响应
- 公开业务行为
- 状态机
- 配置结构
- 事件副作用
- 跨模块契约
- 数据字段
- Enum 含义

如果代码只是恢复已有文档描述的行为，不为了制造变更记录重复修改文档。

新增长期存在且没有归属文档的业务契约时，应为所属模块建立或选择一份规范事实文档。

## SCOPE CONTROL

- 不写无法从代码、测试或产品材料追溯的猜测。
- 不把历史大规模文档欠账混入局部代码任务。
