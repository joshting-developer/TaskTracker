<?php

use App\Database\IssueTypesUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IssueTypesUpgrade::run();
    }

    public function down(): void
    {
        // Task 不再區分原 Feature, 不猜測逆轉；回舊版請還原升級前備份
    }
};
