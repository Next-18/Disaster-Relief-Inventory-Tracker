<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('relief_packages', function (Blueprint $table) {
            $table->unsignedInteger('quantity_on_hand')->default(0)->after('status');
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->string('stock_source', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropColumn('stock_source');
        });

        Schema::table('relief_packages', function (Blueprint $table) {
            $table->dropColumn('quantity_on_hand');
        });
    }
};
