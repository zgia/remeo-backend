<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 仅向空的独立目标库导入，源库连接保持只读。 */
class ImportLegacyDatabase extends Command
{
    protected $signature = 'remeo:import-legacy {source : 同一数据库服务器上的旧库名}';

    protected $description = '将旧库业务数据复制到已经迁移的空库';

    private const TABLES = ['category', 'hospday', 'hospital', 'log', 'media', 'pathology', 'patient', 'permission', 'progress', 'radiotherapy', 'situation', 'smscode', 'smslog', 'surgery', 'treatment', 'user', 'userpermission', 'video'];

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));
        $source = (string) $this->argument('source');
        if ($config['driver'] !== 'mysql' || ! preg_match('/^[a-zA-Z0-9_]+$/', $source) || $source === $config['database']) {
            $this->error('必须使用 MySQL，并指定不同于目标库的源库。');

            return self::FAILURE;
        }
        config(['database.connections.legacy_import' => array_merge($config, ['database' => $source])]);
        $legacy = DB::connection('legacy_import');
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::connection('legacy_import')->hasTable($table) || DB::table($table)->exists()) {
                $this->error('表结构不完整或目标库已有数据，已停止导入。');

                return self::FAILURE;
            }
        }
        $legacy->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $legacy->statement('SET TRANSACTION READ ONLY');
        $legacy->beginTransaction();
        try {
            DB::transaction(function () use ($legacy): void {
                foreach (self::TABLES as $table) {
                    $legacy->table($table)->orderBy('id')->chunk(200, function ($rows) use ($table): void {
                        DB::table($table)->insert($rows->map(fn ($row) => (array) $row)->all());
                    });
                    if (DB::table($table)->count() !== $legacy->table($table)->count()) {
                        throw new \RuntimeException('导入数量不一致，已回滚。');
                    }
                }
            });
        } finally {
            $legacy->rollBack();
            DB::disconnect('legacy_import');
        }
        $this->info('18 张业务表已导入并核对数量；源库未修改。');

        return self::SUCCESS;
    }
}
