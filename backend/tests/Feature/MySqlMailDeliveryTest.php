<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\MailDeliveryCases;
use Tests\MySqlTestCase;

/** 真MySQL交易與outbox重播, 仍使用Mail fake */
class MySqlMailDeliveryTest extends MySqlTestCase
{
    use DatabaseTransactions, MailDeliveryCases;
}
