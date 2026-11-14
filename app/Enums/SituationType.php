<?php

namespace App\Enums;

/**
 * 患者情况
 */
enum SituationType: string
{
    case OpFisrt = 'f'; // 门诊首诊

    case OpCheck = 'c'; // 门诊复查

    case IhInhospital = 'i'; // 住院入院

    case IhDischarge = 'd'; // 住院出院

    case IhFollowup = 'u';  // 住院出院后随访
}
