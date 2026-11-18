<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueStatusNotificationCases;
use Tests\TestCase;

/** TT-314：隔離 MySQL驗證狀態調整通知。 */
class IssueStatusNotificationTest extends TestCase
{
    use IssueStatusNotificationCases, RefreshDatabase;
}
