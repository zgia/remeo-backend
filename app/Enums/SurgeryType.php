<?php

namespace App\Enums;

enum SurgeryType: string
{
    case Initial = 'i'; // 初始时

    case Second = 's'; // 外院二次手术

    case Third = 't'; // 外院三次手术

    case Fourth = 'f'; // 外院四次手术

    case Surgery = 'y';  // 手术

    public static function getValue(string $key): string
    {
        $key = ucfirst(strtolower($key));

        return constant("static::{$key}")->value;
    }

    /**
     * Types
     *
     * @return array<string,string>
     */
    public static function getInitialStateTypes()
    {
        return [
            self::Initial->value => 'initial',
            self::Second->value  => 'second',
            self::Third->value   => 'third',
            self::Fourth->value  => 'fourth',
        ];
    }
}
