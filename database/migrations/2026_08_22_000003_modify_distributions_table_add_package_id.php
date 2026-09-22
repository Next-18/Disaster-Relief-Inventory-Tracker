<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('beneficiary_id')->constrained('relief_packages')->onDelete('set null');
            $table->dropColumn('package_name');
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
            $table->string('package_name')->after('beneficiary_id');
        });
    }
};
