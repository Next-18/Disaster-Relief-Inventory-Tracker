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

class AdminInertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_beneficiaries_page_uses_inertia_and_keeps_create_update_delete_routes_working(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.beneficiaries'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Beneficiaries')
                ->has('beneficiaries.data', 0)
                ->where('totalBeneficiaries', 0));

        $attributes = [
            'full_name' => 'Mia Santos',
            'contact_number' => '+639171234567',
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

    public function test_distribution_page_uses_inertia_and_keeps_create_update_cancel_routes_working(): void
    {
        $user = User::factory()->admin()->create();
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
        $item = InventoryItem::create([
            'item_name' => 'Rice',
            'category' => 'Food',
            'quantity' => 10,
            'unit' => 'bags',
            'minimum_stock' => 2,
        ]);
        $package->inventoryItems()->attach($item->id, ['quantity_per_package' => 1]);

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
            'start_date' => today()->toDateString(),
            'deadline' => today()->addDay()->toDateString(),
            'notes' => 'Initial delivery',
        ];

        $this->from(route('admin.distribution'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.distribution.store'), $attributes)
            ->assertRedirect(route('admin.distribution'))
            ->assertSessionHas('success', '1 distribution record(s) created successfully.');

        $distribution = Distribution::firstOrFail();
        $this->assertSame('Pending', $distribution->status);
        $this->assertSame(today()->toDateString(), $distribution->start_date->toDateString());
        $this->assertSame(today()->addDay()->toDateString(), $distribution->deadline->toDateString());

        $updatedAttributes = [...$attributes, 'deadline' => today()->addDays(2)->toDateString()];
        $this->put(route('admin.distribution.update', $distribution->id), $updatedAttributes)
            ->assertRedirect(route('admin.distribution'));
        $distribution->refresh();
        $this->assertSame('Pending', $distribution->status);
        $this->assertSame(today()->addDays(2)->toDateString(), $distribution->deadline->toDateString());

        $this->delete(route('admin.distribution.delete', $distribution->id))
            ->assertRedirect(route('admin.distribution'));
        $this->assertDatabaseHas('distributions', ['id' => $distribution->id, 'status' => 'Cancelled']);
        $this->assertSame(10, $item->fresh()->quantity);
    }

    public function test_beneficiary_and_staff_with_distribution_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin);

        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-4999',
            'full_name' => 'Ana Santos',
            'status' => 'Active',
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Food pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        $distribution = Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => now()->toDateString(),
            'status' => 'Released',
            'distributed_by' => $staff->id,
        ]);

        $this->delete(route('admin.beneficiaries.delete', $beneficiary->id))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->from(route('admin.users'))
            ->delete(route('admin.users.delete', $staff->id))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiary->id]);
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('distributions', ['id' => $distribution->id, 'distributed_by' => $staff->id]);
    }

    public function test_user_role_requires_a_unique_linked_beneficiary(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-4988',
            'full_name' => 'Lia Santos',
            'status' => 'Active',
        ]);
        $beneficiaryUser = User::factory()->create([
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
        ]);
        $unlinkedBeneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-4989',
            'full_name' => 'Mia Santos',
            'status' => 'Active',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $staffAttributes = [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'user',
        ];

        $this->from(route('admin.users'))
            ->put(route('admin.users.update', $staff->id), $staffAttributes)
            ->assertSessionHasErrors('beneficiary_id');

        $this->from(route('admin.users'))
            ->put(route('admin.users.update', $staff->id), [
                ...$staffAttributes,
                'beneficiary_id' => $beneficiary->id,
            ])
            ->assertSessionHasErrors('beneficiary_id');

        $this->from(route('admin.users'))
            ->put(route('admin.users.update', $staff->id), [
                ...$staffAttributes,
                'beneficiary_id' => $unlinkedBeneficiary->id,
            ])
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'user', 'beneficiary_id' => $unlinkedBeneficiary->id]);
        $this->assertDatabaseHas('users', ['id' => $beneficiaryUser->id, 'role' => 'user', 'beneficiary_id' => $beneficiary->id]);
    }
}
