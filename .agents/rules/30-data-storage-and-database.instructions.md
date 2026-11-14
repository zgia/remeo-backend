---
description: 文件、外部集成、敏感信息、数据库结构与 Migration 安全
applyTo: "{app/**/*.php,config/**/*.php,database/migrations/**/*.php}"
---

# Data, Storage And Database Policy

## FILES AND STORAGE

- 原本就是完整外部 URL 的值按其外部协议处理。
- 上传入口必须在可信边界校验文件大小和允许类型；可行时同时校验扩展名、MIME 和实际对象元数据。
- 大文件、导出和图片处理优先采用流式或分块方案，并明确内存边界。
- 优先复用 `app/Functions`、`app/Support` 和现有文件 Service。

## EXTERNAL INTEGRATIONS

- 外部 HTTP/SDK 调用必须设置有限的连接和响应超时。
- 是否重试必须根据操作幂等性决定。
- 外部调用不得无明确边界地处于数据库事务或行锁保护期间。
- 凭证、令牌、临时签名 URL、完整隐私数据、支付敏感字段和未经脱敏的第三方载荷不得写入日志。
- 排查日志只保留定位问题所需的最小稳定标识。

## DATABASE

- 已部署 Migration 视为不可变事实；修改生产结构必须新增 Migration。
- 新建业务实体表默认使用自增 bigint 主键和项目既有表名规则。
- 字段的 `nullable`、默认值和类型必须表达真实领域语义。
- `NULL` 表示未知、未发生、无上限或不适用时必须保留该语义。
- 内部金额字段使用货币最小单位整数。
- 使用 `change()` 修改已有字段时，必须完整声明需要保留的长度、默认值、nullable、unsigned、注释等属性。
- 新建或修改业务表时，为表和业务字段添加准确注释。
- 新增索引必须有真实查询或唯一性约束依据。
- 不得使用数据库外键约束；表之间的关联通过字段约定和应用层业务逻辑维护。

## DATABASE SAFETY

未经用户明确许可，不得执行：

- `RefreshDatabase`
- `LazilyRefreshDatabase`
- `DatabaseMigrations`
- `DatabaseTruncation`
- `migrate:fresh`
- `migrate:refresh`
- `migrate:reset`
- `db:wipe`
- 其它删除、重建或截断共享数据库的操作

`php artisan tinker` 默认只读。

用户明确授权数据修复或数据库操作时，执行前确认目标环境、影响范围和恢复方式。
