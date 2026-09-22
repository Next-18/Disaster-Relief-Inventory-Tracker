<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("UPDATE relief_packages SET status = 'Available' WHERE status = 'Active'");
        DB::statement("UPDATE relief_packages SET status = 'Not Available' WHERE status = 'Inactive'");
    }

    public function down(): void
    {
        DB::statement("UPDATE relief_packages SET status = 'Active' WHERE status = 'Available'");
        DB::statement("UPDATE relief_packages SET status = 'Inactive' WHERE status = 'Not Available'");
    }
};
