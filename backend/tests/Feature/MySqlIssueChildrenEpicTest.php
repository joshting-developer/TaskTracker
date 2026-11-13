<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueChildrenEpicCases;
use Tests\MySqlTestCase;

/** 原生MySQL驗證批次版本、歷史與回滾 */
class MySqlIssueChildrenEpicTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueChildrenEpicCases;
}
