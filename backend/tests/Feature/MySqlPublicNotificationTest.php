<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicNotificationCases;
use Tests\MySqlTestCase;

/** TT-315：獨立MySQL驗證公開API「我的通知」。 */
class MySqlPublicNotificationTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicNotificationCases;
}
