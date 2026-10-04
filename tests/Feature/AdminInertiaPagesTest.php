<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\ReliefPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminInertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_beneficiaries_page_uses_inertia_and_keeps_create_update_delete_routes_working(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.beneficiaries'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Beneficiaries')
                ->has('beneficiaries.data', 0)
                ->where('totalBeneficiaries', 0));

        $attributes = [
            'full_name' => 'Mia Santos',
            'contact_number' => '09171234567',
            'address' => 'Zone 1',
            'household_size' => 4,
            'priority_type' => 'Regular',
            'status' => 'Active',
        ];

        $this->from(route('admin.beneficiaries'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.beneficiaries.store'), $attributes)
            ->assertRedirect(route('admin.beneficiaries'))
            ->assertSessionHas('success', 'Beneficiary added successfully.');

        $beneficiary = Beneficiary::where('full_name', 'Mia Santos')->firstOrFail();
        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiary->id, 'status' => 'Active']);

        $this->from(route('admin.beneficiaries'))
            ->put(route('admin.beneficiaries.update', $beneficiary->id), [...$attributes, 'status' => 'Inactive'])
            ->assertRedirect(route('admin.beneficiaries'));
        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiary->id, 'status' => 'Inactive']);

        $this->from(route('admin.beneficiaries'))
            ->delete(route('admin.beneficiaries.delete', $beneficiary->id))
            ->assertRedirect(route('admin.beneficiaries'));
        $this->assertDatabaseMissing('beneficiaries', ['id' => $beneficiary->id]);
    }

    public function test_distribution_page_uses_inertia_and_keeps_create_update_delete_routes_working(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-4001',
            'full_name' => 'Ava Rivera',
            'status' => 'Active',
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Family essentials',
            'category' => 'General',
            'status' => 'Available',
        ]);

        $this->get(route('admin.distribution'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Distribution')
                ->has('distributions.data', 0)
                ->has('beneficiaries', 1)
                ->has('packages', 1));

        $attributes = [
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => now()->toDateString(),
            'status' => 'Released',
            'notes' => 'Initial delivery',
        ];

        $this->from(route('admin.distribution'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.distribution.store'), $attributes)
            ->assertRedirect(route('admin.distribution'))
            ->assertSessionHas('success', 'Distribution recorded successfully.');

        $distribution = Distribution::firstOrFail();
        $this->assertDatabaseHas('distributions', ['id' => $distribution->id, 'status' => 'Released']);

        $this->put(route('admin.distribution.update', $distribution->id), [...$attributes, 'status' => 'Pending'])
            ->assertRedirect(route('admin.distribution'));
        $this->assertDatabaseHas('distributions', ['id' => $distribution->id, 'status' => 'Pending']);

        $this->delete(route('admin.distribution.delete', $distribution->id))
            ->assertRedirect(route('admin.distribution'));
        $this->assertDatabaseMissing('distributions', ['id' => $distribution->id]);
    }
}
