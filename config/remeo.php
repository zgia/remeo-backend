<?php

return [
    'super_users' => array_map('intval', array_filter(explode(',', env('SUPER_USERS', '')))),
    'hospital'    => [
        'operator' => array_filter(explode(',', env('HOSPITAL_OPERATORS', ''))),
        'viewer'   => array_filter(explode(',', env('HOSPITAL_VIEWERS', ''))),
    ],
    'sms_url' => env('ALIYUN_SMS_URL', 'http://127.0.0.1:7890/'),
    'options' => [
        'perpage' => [
            'value' => 20,
            'desc'  => '每页显示数据',
        ],
        'pwdminlen' => [
            'value' => 6,
            'desc'  => '密码最小长度',
        ],
        'smslife' => [
            'value' => 900,
            'desc'  => '短信验证码时间900秒',
        ],
        'smsdaytotal' => [
            'value' => 10,
            'desc'  => '每天不能发送超过10次短信',
        ],
    ],
];
