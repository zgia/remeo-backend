<?php

namespace App\Enums;

enum ConstantType: string
{
    case HospitalizedOutpatient = 'o'; // 门诊

    case HospitalizedInhospital = 'i'; // 住院
}
