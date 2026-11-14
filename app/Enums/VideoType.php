<?php

namespace App\Enums;

enum VideoType: string
{
    case OpFisrt = 'f'; // 门诊首诊

    case OpCheck = 'c'; // 门诊复查

    case IhBefore = 'b'; // 住院术前

    case IhFollowup = 'u';  // 住院随访
}
