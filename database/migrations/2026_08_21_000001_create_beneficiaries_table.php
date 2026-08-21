<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('beneficiary_no', 30)->unique();
            $table->string('full_name');
            $table->string('contact_number', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('household_size', 10)->nullable();
            $table->string('priority_type', 50)->default('Regular');
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('beneficiaries'); }
};
