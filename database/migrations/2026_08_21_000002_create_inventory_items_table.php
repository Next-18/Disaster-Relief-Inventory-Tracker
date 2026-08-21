<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('category', 60);
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 30)->default('pcs');
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->string('status', 20)->default('In Stock');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('inventory_items'); }
};
