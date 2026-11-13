<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueStatusNotificationCases;
use Tests\MySqlTestCase;

/** TT-314：獨立MySQL驗證狀態調整通知。 */
class MySqlIssueStatusNotificationTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueStatusNotificationCases;
}
