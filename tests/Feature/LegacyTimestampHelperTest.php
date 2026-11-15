<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helper\LegacyTimestampHelper;
use Tests\TestCase;

class LegacyTimestampHelperTest extends TestCase
{
    public function test_legacy_timestamps_are_converted_for_new_schema_rows(): void
    {
        $row = LegacyTimestampHelper::convertRow([
            'id'        => 1,
            'createdat' => 1700000000,
            'updatedat' => 0,
            'deletedat' => 1,
        ], true, true, true);

        $this->assertSame('2023-11-15 06:13:20', $row['created_at']);
        $this->assertNotNull($row['updated_at']);
        $this->assertSame(LegacyTimestampHelper::FLAG_TIME, $row['deleted_at']);
        $this->assertArrayNotHasKey('createdat', $row);
        $this->assertArrayNotHasKey('updatedat', $row);
        $this->assertArrayNotHasKey('deletedat', $row);
    }

    public function test_rows_without_legacy_timestamps_get_current_values(): void
    {
        $row = LegacyTimestampHelper::convertRow(['id' => 1], true, true, false);

        $this->assertNotNull($row['created_at']);
        $this->assertNotNull($row['updated_at']);
        $this->assertArrayNotHasKey('deleted_at', $row);
    }

    public function test_rows_for_old_target_schema_are_left_untouched(): void
    {
        $legacy = [
            'id'        => 1,
            'createdat' => 1700000000,
            'updatedat' => 0,
            'deletedat' => 0,
        ];

        $this->assertSame($legacy, LegacyTimestampHelper::convertRow($legacy, false, false, false));
    }

    public function test_unknown_creation_time_is_clamped_to_deletion_time(): void
    {
        $row = LegacyTimestampHelper::convertRow([
            'id'        => 1,
            'createdat' => 0,
            'deletedat' => 1,
        ], true, true, true);

        $this->assertSame(LegacyTimestampHelper::FLAG_TIME, $row['created_at']);
        $this->assertSame(LegacyTimestampHelper::FLAG_TIME, $row['deleted_at']);
    }
}
