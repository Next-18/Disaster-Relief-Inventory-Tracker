<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTopbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_finds_records_across_all_admin_modules(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-2042',
            'full_name' => 'Mia Santos',
            'address' => 'Rice Street',
            'status' => 'Active',
        ]);
        $item = InventoryItem::create([
            'item_name' => 'Rice sacks',
            'category' => 'Food',
            'quantity' => 12,
            'unit' => 'bags',
            'minimum_stock' => 4,
            'status' => 'In Stock',
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Rice relief pack',
            'category' => 'Food',
            'description' => 'Rice and pantry items',
            'status' => 'Available',
        ]);
        Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => now()->toDateString(),
            'status' => 'Released',
            'distributed_by' => $user->id,
        ]);

        $this->get(route('admin.search', ['q' => 'Rice']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Search')
                ->where('term', 'Rice')
                ->where('beneficiaries.0.full_name', 'Mia Santos')
                ->where('inventoryItems.0.item_name', $item->item_name)
                ->where('packages.0.package_name', $package->package_name)
                ->where('distributions.0.beneficiary.full_name', 'Mia Santos')
                ->where('distributions.0.relief_package.package_name', 'Rice relief pack'));

        $this->get(route('admin.packages', ['search' => 'Rice']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Packages')
                ->where('packages.data.0.package_name', $package->package_name)
                ->where('filters.search', 'Rice'));

        $this->get(route('admin.distribution', ['search' => 'Rice']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Distribution')
                ->where('distributions.data.0.beneficiary.full_name', $beneficiary->full_name)
                ->where('distributions.data.0.relief_package.package_name', $package->package_name));
    }

    public function test_topbar_notifications_use_current_records_and_quick_actions_target_creation_flows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-2043',
            'full_name' => 'Ana Rivera',
            'address' => 'Zone 2',
            'status' => 'Active',
        ]);
        InventoryItem::create([
            'item_name' => 'Water containers',
            'category' => 'Water',
            'quantity' => 1,
            'unit' => 'cases',
            'minimum_stock' => 5,
            'status' => 'Low Stock',
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Family essentials',
            'category' => 'General',
            'status' => 'Available',
        ]);
        Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => now()->toDateString(),
            'status' => 'Released',
            'distributed_by' => $user->id,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('totalBeneficiaries', 1)
                ->where('notifications.0.title', 'Low stock: Water containers')
                ->has('notifications', 3)
                ->where('routeUrls.beneficiaries', route('admin.beneficiaries'))
                ->where('routeUrls.distribution', route('admin.distribution'))
                ->where('routeUrls.inventory', route('admin.inventory')));
    }
}
