<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PDO;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private startTestTransaction;
    }

    protected function issueLoginCaptcha(): string
    {
        // 使用真实生成接口和校验器，仅在测试中固定字符以获得可验证的答案。
        config(['captcha.characters' => ['a'], 'captcha.default.length' => 4]);
        $this->getJson('/signin/captcha')->assertOk()->assertJsonPath('code', 0);

        return 'aaaa';
    }

    /**
     * 必须先隔离表，再开启事务；MySQL 的临时表仅对当前连接可见。
     */
    public function beginDatabaseTransaction(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('数据库测试只能在 testing 环境执行，请检查配置缓存。');
        }

        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:') {
            (require database_path('migrations/2026_11_14_000000_create_registry_tables.php'))->up();
            (require database_path('migrations/2026_11_15_000000_add_datetime_timestamps_to_registry_tables.php'))->up();
        } elseif ($connection->getDriverName() === 'mysql') {
            $expectedDatabase = getenv('REMEO_MYSQL_TEST_DATABASE');
            if (! $expectedDatabase || $connection->getDatabaseName() !== $expectedDatabase) {
                throw new LogicException('运行 MySQL 测试前必须通过 REMEO_MYSQL_TEST_DATABASE 明确指定开发库名称。');
            }
            if ($connection->getPdo()->getAttribute(PDO::ATTR_PERSISTENT)) {
                throw new LogicException('MySQL 隔离测试不允许使用持久连接。');
            }

            // 重连会丢失临时表，禁止框架重试后意外访问同名开发表。
            $connection->setReconnector(static fn () => throw new LogicException('MySQL 测试连接断开，禁止自动重连。'));
            $tables = $connection->select("SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
            if ($tables === []) {
                throw new LogicException('开发库没有可复制结构的业务表。');
            }
            foreach ($tables as $table) {
                if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table->name) || $table->engine !== 'InnoDB') {
                    throw new LogicException('MySQL 测试要求表名为普通标识符且存储引擎为 InnoDB。');
                }
            }
            try {
                foreach ($tables as $table) {
                    // 同名临时表遮蔽原表，仅复制结构，不复制或清空开发数据。
                    $definition = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table->name.'`');
                    $sql        = $definition['Create Table'];
                    if (! str_starts_with($sql, 'CREATE TABLE `'.$table->name.'`')) {
                        throw new LogicException('无法识别开发库表结构，已停止创建临时表。');
                    }
                    $connection->statement('CREATE TEMPORARY TABLE '.substr($sql, strlen('CREATE TABLE ')));
                }
            } catch (\Throwable $exception) {
                $connection->disconnect();

                throw $exception;
            }
        } else {
            throw new LogicException('仅允许 SQLite 内存库或明确指定的 MySQL 开发库。');
        }

        $this->startTestTransaction();
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('patient_media');
    }
}
