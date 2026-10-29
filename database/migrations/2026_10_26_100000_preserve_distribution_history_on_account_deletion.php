<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_id']);
            $table->dropForeign(['distributed_by']);

            $table->foreign('beneficiary_id')
                ->references('id')
                ->on('beneficiaries')
                ->restrictOnDelete();
            $table->foreign('distributed_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_id']);
            $table->dropForeign(['distributed_by']);

            $table->foreign('beneficiary_id')
                ->references('id')
                ->on('beneficiaries')
                ->cascadeOnDelete();
            $table->foreign('distributed_by')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
