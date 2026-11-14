<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PasswordRecoveryCases;
use Tests\MySqlTestCase;

/** 真實InnoDB重設與資料保存 */
class MySqlPasswordRecoveryTest extends MySqlTestCase
{
    use DatabaseTransactions, PasswordRecoveryCases;
}
