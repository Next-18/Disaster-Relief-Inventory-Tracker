<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('recipient_role', 30)->default('admin')->after('status');
            $table->index(['recipient_role', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['recipient_role', 'status', 'created_at']);
            $table->dropColumn('recipient_role');
        });
    }
};
