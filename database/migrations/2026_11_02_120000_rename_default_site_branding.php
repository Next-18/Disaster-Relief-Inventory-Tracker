<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'site_name')
            ->where('value', 'Disaster Relief Inventory Tracker')
            ->update(['value' => 'Relief Inventory Tracker']);

        DB::table('settings')
            ->where('key', 'site_description')
            ->where('value', 'Track and manage disaster relief operations')
            ->update(['value' => 'Track and manage relief operations']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'site_name')
            ->where('value', 'Relief Inventory Tracker')
            ->update(['value' => 'Disaster Relief Inventory Tracker']);

        DB::table('settings')
            ->where('key', 'site_description')
            ->where('value', 'Track and manage relief operations')
            ->update(['value' => 'Track and manage disaster relief operations']);
    }
};
