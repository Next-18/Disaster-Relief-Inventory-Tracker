<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users', 'beneficiaries', 'inventory_items', 'relief_packages', 'distributions',
        'announcements', 'support_tickets', 'stock_deliveries', 'audit_logs',
    ];

    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'municipality', 'province']);
        });

        $barangayId = DB::table('barangays')->insertGetId([
            'name' => Setting::get('barangay_name', 'Barangay San Juan'),
            'municipality' => Setting::get('municipality', 'Surigao City'),
            'province' => Setting::get('province', 'Surigao del Norte'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('barangay_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
            DB::table($tableName)->whereNull('barangay_id')->update(['barangay_id' => $barangayId]);
        }

        // Do not grant platform-wide access to an arbitrary existing Admin.
        // Promote the explicitly selected account with the console command instead.
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'barangay_id')) continue;
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('barangay_id');
            });
        }
        Schema::dropIfExists('barangays');
    }
};
