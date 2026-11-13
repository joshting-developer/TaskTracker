<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicNotificationCases;
use Tests\TestCase;

/** TT-315：SQLite memory驗證公開API「我的通知」。 */
class PublicNotificationTest extends TestCase
{
    use PublicNotificationCases, RefreshDatabase;
}
