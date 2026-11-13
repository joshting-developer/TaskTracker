<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AttachmentCases;
use Tests\MySqlTestCase;

/** MySQL私有附件生命週期 */
class MySqlAttachmentTest extends MySqlTestCase
{
    use AttachmentCases, DatabaseTransactions;
}
