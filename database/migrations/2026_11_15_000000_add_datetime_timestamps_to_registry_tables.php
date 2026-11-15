<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 把旧表的整数时间字段（createdat/updatedat/deletedat）改成 Laravel 约定的
// datetime 字段（created_at/updated_at/deleted_at）：先补新字段并回填，再删除旧字段。
// 旧值语义：0 表示未发生，1 是历史上手工写入的删除标记，其余为 Unix 时间戳。
return new class extends Migration
{
    /** 旧字段 => [新字段, 字段注释] */
    private const COLUMNS = [
        'createdat' => ['created_at', '创建时间'],
        'updatedat' => ['updated_at', '更新时间'],
        'deletedat' => ['deleted_at', '删除时间'],
    ];

    /** 表 => 该表实际存在的旧时间字段（空数组表示原本没有时间字段） */
    private const TABLES = [
        'category'       => [],
        'hospday'        => [],
        'hospital'       => ['createdat', 'updatedat', 'deletedat'],
        'log'            => ['createdat'],
        'media'          => ['createdat', 'updatedat', 'deletedat'],
        'pathology'      => ['createdat', 'updatedat', 'deletedat'],
        'patient'        => ['createdat', 'updatedat', 'deletedat'],
        'permission'     => ['createdat', 'updatedat', 'deletedat'],
        'progress'       => [],
        'radiotherapy'   => ['createdat', 'updatedat', 'deletedat'],
        'situation'      => ['createdat', 'updatedat', 'deletedat'],
        'smscode'        => ['createdat'],
        'smslog'         => ['createdat', 'updatedat'],
        'surgery'        => ['createdat', 'updatedat', 'deletedat'],
        'treatment'      => ['createdat', 'updatedat', 'deletedat'],
        'user'           => ['createdat', 'updatedat', 'deletedat'],
        'userpermission' => ['createdat', 'updatedat', 'deletedat'],
        'video'          => ['createdat', 'updatedat', 'deletedat'],
    ];

    /** 每张表都要有的 Laravel 时间字段 */
    private const TIMESTAMPS = ['created_at' => '创建时间', 'updated_at' => '更新时间'];

    /** 0/1 标记没有可还原的真实时间，统一映射为固定时间。 */
    private const LEGACY_FLAG_TIME = '2026-01-01 12:12:12';

    public function up(): void
    {
        foreach (self::TABLES as $table => $legacyColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (self::TIMESTAMPS as $column => $comment) {
                $this->addDateTimeColumn($table, $column, $comment);
            }

            foreach ($legacyColumns as $legacyColumn) {
                [$column, $comment] = self::COLUMNS[$legacyColumn];

                $this->addDateTimeColumn($table, $column, $comment);
                $this->backfill($table, $legacyColumn, $column);
            }

            foreach ($legacyColumns as $legacyColumn) {
                $this->dropColumn($table, $legacyColumn);
            }
        }
    }

    public function down(): void
    {
        // 回滚只恢复旧字段结构（整数默认 0），原始时间值无法还原。
        foreach (self::TABLES as $table => $legacyColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (self::TIMESTAMPS as $column => $comment) {
                $this->dropColumn($table, $column);
            }

            foreach ($legacyColumns as $legacyColumn) {
                $this->dropColumn($table, self::COLUMNS[$legacyColumn][0]);

                if (! Schema::hasColumn($table, $legacyColumn)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($legacyColumn): void {
                        $blueprint->integer($legacyColumn)->unsigned()->default(0);
                    });
                }
            }
        }
    }

    private function addDateTimeColumn(string $table, string $column, string $comment): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        Schema::table($table, function (Blueprint $blueprint) use ($column, $comment, $driver): void {
            if ($driver === 'sqlite') {
                // SQLite 不支持 ADD COLUMN 使用 CURRENT_TIMESTAMP 这类非常量默认值。
                $blueprint->dateTime($column)->nullable()->comment($comment);

                return;
            }

            if ($column === 'created_at') {
                $blueprint->dateTime($column)->useCurrent()->comment($comment);
            } elseif ($column === 'updated_at') {
                $blueprint->dateTime($column)->useCurrentOnUpdate()->useCurrent()->comment($comment);
            } else {
                $blueprint->dateTime($column)->nullable()->comment($comment);
            }
        });

        if ($driver === 'sqlite' && in_array($column, ['created_at', 'updated_at'], true)) {
            // 与 MySQL 的 DEFAULT CURRENT_TIMESTAMP 行为对齐：历史行按迁移时间补齐。
            DB::table($table)->whereNull($column)->update([$column => now()]);
        }
    }

    private function dropColumn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropColumn($column);
        });
    }

    /** 按 ID 分块回填，避免大表一次性载入内存。 */
    private function backfill(string $table, string $legacyColumn, string $column): void
    {
        if (! Schema::hasColumn($table, $legacyColumn)) {
            return;
        }

        DB::table($table)->where($legacyColumn, 1)->update([$column => self::LEGACY_FLAG_TIME]);

        DB::table($table)
            ->select(['id', $legacyColumn])
            ->where($legacyColumn, '>', 1)
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows) use ($table, $legacyColumn, $column): void {
                $groups = [];

                foreach ($rows as $row) {
                    $datetime = Carbon::createFromTimestamp((int) $row->{$legacyColumn}, (string) config('app.timezone'))->format('Y-m-d H:i:s');

                    $groups[$datetime][] = (int) $row->id;
                }

                // 相同时间戳合并成一次更新，减少回填语句数量。
                foreach ($groups as $datetime => $ids) {
                    DB::table($table)->whereIn('id', $ids)->update([$column => $datetime]);
                }
            });
    }
};
