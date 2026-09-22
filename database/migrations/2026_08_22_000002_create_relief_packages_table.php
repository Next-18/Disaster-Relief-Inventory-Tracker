<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('relief_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_name');
            $table->text('description')->nullable();
            $table->string('category')->default('General');
            $table->string('status')->default('Available');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('relief_packages'); }
};
