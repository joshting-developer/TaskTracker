<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MailDeliveryCases;
use Tests\TestCase;

/** memory隔離Mail狀態機, 不連任何SMTP */
class MailDeliveryTest extends TestCase
{
    use MailDeliveryCases, RefreshDatabase;
}
