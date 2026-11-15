<?php

declare(strict_types=1);

namespace App\Helper;

use Illuminate\Support\Carbon;

/**
 * 旧库整数时间字段与 Laravel datetime 字段之间的转换。
 *
 * 旧库用 0 表示未发生、1 表示手工写入的删除标记，其余为 Unix 时间戳。
 * 本转换与 2026_11_15_000000_add_datetime_timestamps_to_registry_tables 迁移保持一致。
 */
final class LegacyTimestampHelper
{
    /** 0/1 标记没有可还原的真实时间，统一映射为固定时间。 */
    public const FLAG_TIME = '2026-01-01 12:12:12';

    public static function toDateTime(mixed $value, ?string $fallback = null): ?string
    {
        $timestamp = (int) $value;

        if ($timestamp === 1) {
            return self::FLAG_TIME;
        }

        if ($timestamp > 1) {
            return Carbon::createFromTimestamp($timestamp, (string) config('app.timezone'))->format('Y-m-d H:i:s');
        }

        return $fallback;
    }

    /**
     * 把旧库行转换为目标表时间字段。
     *
     * 目标仍是旧结构时原样返回，方便迁移执行前先导入；只有目标表存在 Laravel
     * 时间字段时才移除旧键，避免向旧结构写入未知字段。
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    public static function convertRow(array $row, bool $hasCreatedAt, bool $hasUpdatedAt, bool $hasDeletedAt): array
    {
        if (! $hasCreatedAt) {
            return $row;
        }

        $now = now()->format('Y-m-d H:i:s');

        if (array_key_exists('createdat', $row)) {
            $row['created_at'] = self::toDateTime($row['createdat'], $now);
            unset($row['createdat']);
        }

        if ($hasUpdatedAt && array_key_exists('updatedat', $row)) {
            $row['updated_at'] = self::toDateTime($row['updatedat'], $now);
            unset($row['updatedat']);
        }

        if ($hasDeletedAt && array_key_exists('deletedat', $row)) {
            $row['deleted_at'] = self::toDateTime($row['deletedat']);
            unset($row['deletedat']);
        }

        $row['created_at'] ??= $now;

        if ($hasUpdatedAt) {
            $row['updated_at'] ??= $now;
        }

        if ($hasDeletedAt) {
            $row['deleted_at'] ??= null;

            // 旧库 creation=0 且 deletion=1 时没有真实创建时间；导入时把两者
            // 收敛到同一时间，避免出现“删除早于创建”的记录。
            if ($row['deleted_at'] !== null && $row['created_at'] > $row['deleted_at']) {
                $row['created_at'] = $row['deleted_at'];
            }
        }

        return $row;
    }
}
