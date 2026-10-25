<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::firstOrNew(['email' => 'admin@barangay.gov.ph']);
        $admin->name = 'Barangay Administrator';
        $admin->role = 'admin';
        if (! $admin->exists || Hash::check('password', $admin->password)) {
            $password = env('ADMIN_SEED_PASSWORD');
            if (! is_string($password) || strlen($password) < 12) {
                throw new \RuntimeException('Set ADMIN_SEED_PASSWORD to a unique password of at least 12 characters before seeding.');
            }
            $admin->password = $password;
        }
        $admin->save();

        // Production seeding may create the initial administrator, but must
        // never populate live records with the local demonstration dataset.
        if (app()->isProduction()) {
            return;
        }

        Beneficiary::updateOrCreate(['beneficiary_no' => 'BEN-1024'], ['full_name' => 'Maria Reyes', 'contact_number' => '0917 555 1024', 'address' => 'Purok 2, San Juan', 'household_size' => 5, 'priority_type' => 'Senior Citizen']);
        Beneficiary::updateOrCreate(['beneficiary_no' => 'BEN-0981'], ['full_name' => 'Juan Dela Cruz', 'contact_number' => '0918 222 0981', 'address' => 'Purok 4, San Juan', 'household_size' => 4, 'priority_type' => 'Regular']);
        Beneficiary::updateOrCreate(['beneficiary_no' => 'BEN-1153'], ['full_name' => 'Angela Santos', 'contact_number' => '0917 333 1153', 'address' => 'Purok 1, San Juan', 'household_size' => 3, 'priority_type' => 'PWD']);

        InventoryItem::updateOrCreate(['item_name' => 'Rice (5kg)'], ['category' => 'Food Supplies', 'quantity' => 12, 'unit' => 'bags', 'minimum_stock' => 20, 'status' => 'Low Stock']);
        InventoryItem::updateOrCreate(['item_name' => 'Canned Goods'], ['category' => 'Food Supplies', 'quantity' => 24, 'unit' => 'units', 'minimum_stock' => 30, 'status' => 'Low Stock']);
        InventoryItem::updateOrCreate(['item_name' => 'Bottled Water'], ['category' => 'Drinking Water', 'quantity' => 8, 'unit' => 'cases', 'minimum_stock' => 15, 'status' => 'Low Stock']);
        InventoryItem::updateOrCreate(['item_name' => 'Hygiene Kits'], ['category' => 'Hygiene', 'quantity' => 96, 'unit' => 'kits', 'minimum_stock' => 25, 'status' => 'In Stock']);
    }
}
