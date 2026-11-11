
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('beneficiaries', 'purok')) {
            Schema::table('beneficiaries', function (Blueprint $table) {
                $table->string('purok', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('beneficiaries', 'purok')) {
            Schema::table('beneficiaries', function (Blueprint $table) {
                $table->dropColumn('purok');
            });
        }
    }
};
