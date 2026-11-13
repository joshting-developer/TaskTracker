<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicAttachmentCases;
use Tests\MySqlTestCase;

/** 公開PAT附件的真MySQL交易與授權回歸 */
class MySqlPublicAttachmentsTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicAttachmentCases;
}
