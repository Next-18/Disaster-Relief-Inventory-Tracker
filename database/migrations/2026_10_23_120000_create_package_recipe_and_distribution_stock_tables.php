<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('relief_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relief_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_per_package');
            $table->timestamps();
            $table->unique(['relief_package_id', 'inventory_item_id']);
        });

        Schema::create('distribution_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('unit', 30);
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_inventory_items');
        Schema::dropIfExists('relief_package_items');
    }
};
