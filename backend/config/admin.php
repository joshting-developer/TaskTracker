<?php

return [
    // 未配置名稱與雜湊即停用管理登入, 不提供預設密碼
    'username' => env('TASKTRACKER_ADMIN_USERNAME', ''),
    'password_hash' => env('TASKTRACKER_ADMIN_PASSWORD_HASH', ''),
];
