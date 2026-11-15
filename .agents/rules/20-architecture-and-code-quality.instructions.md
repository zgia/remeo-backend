---
description: Laravel 架构、PHP 类型、代码风格与质量门禁
applyTo: "{app/**/*.php,routes/**/*.php,config/**/*.php}"
---

# Architecture And Code Quality Policy

## MUST

- Controller 保持薄，只负责请求接入、授权、调用用例和响应边界；业务流程放在 Service 或明确的应用用例中。
- Controller 继承 `App\Controller\BaseController`，通过 `success()/failure()` 返回 `{code, msg, data}`；文件下载返回 `BinaryFileResponse`。
- 请求存在业务校验、授权或归一化逻辑时使用 FormRequest。
- 调用其它业务模块时，通过目标模块 Service 进入，不绕过业务边界直接编排目标模块 Repository/Model。
- 新代码使用准确的参数和返回类型；框架动态 API 或兼容性边界按实际契约处理。
- 遵循仓库现有 Pint 配置和 PHP 风格。
- 新建 PHP 文件使用 `declare(strict_types=1);`。
- 不通过无关的大范围重构、格式化或抽象来完成局部任务。

## DEFAULT

- 默认沿用项目已有的 `Controller -> Service -> Repository/Model` 组织方式，但简单用例不机械补齐所有层。
- 多条 Eloquent 查询结果保持真实的 `Eloquent\Collection` 类型，不用 `collect()` 包装成普通 `Support\Collection` 伪装解耦。
- 注释只说明代码签名或实现本身无法清晰表达的信息；优先解释 **Why**，而不是重复描述 **What**，重点记录业务意图、设计原因、约束、取舍和非显然行为。
- PHPDoc 用于类、方法、属性等具有实际文档价值的声明；即使内容只有一行，也优先使用多行格式。
- 方法内部对局部实现细节的简短说明使用 `//`；不要为了“给代码加注释”而添加显而易见的注释。
- 优先复用项目已有的 QueryBuilder、Repository、Helper 和相邻实现。
- Controller 方法使用 PHP camelCase；路由参数遵循现有契约，表达多个实体时使用明确的 snake_case 参数名。

## Code Readability

- 保持代码具有良好的视觉层次和阅读节奏；在逻辑职责发生变化、代码较密集或表达式较长时，使用合理的空行和换行。
- 避免为了压缩代码而牺牲可读性，也避免不必要的过度格式化。

## QUALITY GATES

PHP 修改完成后：

```bash
./vendor/bin/pint --parallel <changed-php-files>
```

涉及 `app/` 的 PHP 文件：

```bash
./vendor/bin/phpstan analyse --memory-limit=2G <changed-app-files>
```

按风险运行最小相关测试；全量测试入口：

```bash
composer test
```

完成前检查最终 diff：

- 当前任务的修改完整；
- 无关修改没有被覆盖；
- 没有产生无意义的格式化；
- 没有遗漏测试或文档同步。
