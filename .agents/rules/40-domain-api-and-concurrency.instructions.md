---
description: 业务值、API 契约、异常、事务、队列与并发一致性
applyTo: "{app/**/*.php,routes/**/*.php,tests/**/*.php}"
---

# Domain, API And Concurrency Policy

## BUSINESS VALUES

- 新增或扩展会被持久化、校验、跨层传递或暴露为接口契约的封闭业务值集，必须先定义或复用 `app/Enums/*Enum.php`。
- 同一业务概念已有 Enum 时必须复用，不创建平行常量或重复值集合。
- 外部协议固定字面量、配置键、数组结构键和局部算法字符串不要求创建 Enum。

## API AND EXCEPTIONS

- 可预期的业务拒绝使用 `BusinessException` 和 `BusinessCode`。
- 参数校验、认证授权、基础设施故障和编程错误使用各自正确的异常机制。
- 实体响应优先使用项目 Resource 和统一成功响应。
- 路径、资源名和响应结构遵循现有接口契约，不因局部任务进行无关重命名。
- 内部金额使用货币最小单位整数存储和计算，不使用浮点数。

## TRANSACTIONS

- 事务边界由 Service、应用用例或职责明确的持久化方法拥有。
- 依赖事务内数据的 Event、Job、通知和缓存失效必须在事务提交后执行。
- 队列连接未启用 `after_commit` 时，使用项目既有的提交后分发机制。
- 不在持有数据库行锁或长事务期间执行不可控的外部网络调用。
- 无法避免外部调用时必须明确超时、幂等、失败补偿或恢复路径。

## QUEUES

- 队列按至少一次投递设计。
- Handler 必须能够安全处理重复、延迟、乱序和重试。
- Job Payload 默认使用稳定标识或小型不可变 DTO，不依赖 Request、Auth、闭包或生产者进程状态。
- 高风险 Job 根据任务风险明确配置 `tries`、`backoff`、`timeout` 和失败处理。

## CONCURRENCY AND CACHE

- 并发写入优先使用数据库唯一约束、条件更新、原子操作或事务内行锁保护业务不变量。
- Redis 锁不能替代数据库最终约束。
- 首次创建等“尚无可锁记录”的竞争路径必须单独处理。
- 缓存写入和失效必须遵守数据库提交顺序。
- 锁必须有有限等待和 TTL，并在 `finally` 中释放。
- 多行 `lockForUpdate()` 必须保持稳定的加锁顺序。
