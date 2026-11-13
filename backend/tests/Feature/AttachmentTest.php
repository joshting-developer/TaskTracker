<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AttachmentCases;
use Tests\TestCase;

/** memory附件安全及生命週期 */
class AttachmentTest extends TestCase
{
    use AttachmentCases, RefreshDatabase;
}
