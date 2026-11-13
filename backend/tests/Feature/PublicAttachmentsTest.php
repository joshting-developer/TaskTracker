<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicAttachmentCases;
use Tests\TestCase;

/** 公開PAT附件的memory隔離安全回歸 */
class PublicAttachmentsTest extends TestCase
{
    use PublicAttachmentCases, RefreshDatabase;
}
