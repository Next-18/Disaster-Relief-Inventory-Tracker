<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('reference_number')->nullable();
            $table->date('delivery_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->string('condition_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['stock_delivery_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_delivery_items');
        Schema::dropIfExists('stock_deliveries');
    }
};
