<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\User;
use App\Services\DistributionStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_beneficiary_package_api_hides_exact_stock_and_unavailable_packages(): void
    {
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-5001',
            'full_name' => 'Mara Cruz',
            'status' => 'Active',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
            'password_changed' => true,
        ]);
        $item = InventoryItem::create([
            'item_name' => 'Rice',
            'category' => 'Food',
            'quantity' => 12,
            'unit' => 'bags',
            'minimum_stock' => 2,
        ]);
        $available = ReliefPackage::create([
            'package_name' => 'Food pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        $available->inventoryItems()->attach($item->id, ['quantity_per_package' => 2]);
        ReliefPackage::create([
            'package_name' => 'Empty stock pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        ReliefPackage::create([
            'package_name' => 'Paused pack',
            'category' => 'Food',
            'status' => 'Not Available',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/packages')
            ->assertOk()
            ->assertJsonPath('data.0.package_name', 'Food pack')
            ->assertJsonMissingPath('data.0.available_quantity')
            ->assertJsonMissingPath('data.0.items.0.stock_quantity')
            ->assertJsonMissing(['package_name' => 'Empty stock pack'])
            ->assertJsonMissing(['package_name' => 'Paused pack']);

        $this->getJson('/api/packages/available')
            ->assertOk()
            ->assertJsonMissing(['package_name' => 'Empty stock pack']);
    }

    public function test_beneficiary_api_limits_results_and_hides_private_fields_from_staff(): void
    {
        Beneficiary::create([
            'beneficiary_no' => 'BEN-5101',
            'full_name' => 'Ava Santos',
            'address' => 'Private Street',
            'contact_number' => '+639123456789',
            'qr_code' => 'private-qr.svg',
            'status' => 'Active',
        ]);
        Beneficiary::create([
            'beneficiary_no' => 'BEN-5102',
            'full_name' => 'Bea Santos',
            'status' => 'Active',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

        $this->getJson('/api/beneficiaries?limit=1')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('returned', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('next_offset', 1)
            ->assertJsonMissingPath('data.0.contact_number')
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.qr_code');

        $this->getJson('/api/beneficiaries?search=Private%20Street')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('returned', 0);
    }

    public function test_staff_can_release_a_pending_package_without_deducting_reserved_stock_twice(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-5002',
            'full_name' => 'Nina Reyes',
            'status' => 'Active',
        ]);
        $item = InventoryItem::create([
            'item_name' => 'Water',
            'category' => 'Water',
            'quantity' => 10,
            'unit' => 'cases',
            'minimum_stock' => 2,
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Water pack',
            'category' => 'Water',
            'status' => 'Available',
        ]);
        $package->inventoryItems()->attach($item->id, ['quantity_per_package' => 2]);
        $distribution = Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => now()->toDateString(),
            'status' => 'Pending',
            'distributed_by' => $staff->id,
        ]);

        app(DistributionStockService::class)->consumeFor($distribution);
        $this->assertSame(8, $item->fresh()->quantity);

        Sanctum::actingAs($staff);
        $this->postJson('/api/distributions/release-pending', [
            'beneficiary_no' => $beneficiary->beneficiary_no,
            'distribution_id' => $distribution->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'Released');

        $this->assertSame(8, $item->fresh()->quantity);
        $this->assertDatabaseHas('distributions', [
            'id' => $distribution->id,
            'status' => 'Released',
            'distributed_by' => $staff->id,
        ]);
    }

    public function test_non_staff_cannot_release_pending_packages_through_the_api(): void
    {
        $volunteer = User::factory()->create(['role' => 'volunteer']);
        Sanctum::actingAs($volunteer);

        $this->postJson('/api/distributions/release-pending', [
            'beneficiary_no' => 'BEN-5003',
            'distribution_id' => 1,
        ])->assertForbidden();
    }

    public function test_beneficiary_with_temporary_password_can_open_dashboard_and_change_password_later(): void
    {
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-5004',
            'full_name' => 'Mila Cruz',
            'status' => 'Active',
        ]);
        $user = User::factory()->create([
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
            'password_changed' => false,
            'username' => 'mila.cruz',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.next_action', 'open_dashboard')
            ->assertJsonPath('data.next_screen', null)
            ->assertJsonPath('data.user.requires_password_change', false);

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/dashboard/stats')->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'password_changed' => true]);

        $this->withToken($token)->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'new_password' => 'NewSecurePass2026',
            'new_password_confirmation' => 'NewSecurePass2026',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'password_changed' => true]);
    }

    public function test_pending_assignments_cannot_be_released_outside_their_distribution_window(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $beforeStart = Beneficiary::create([
            'beneficiary_no' => 'BEN-5010',
            'full_name' => 'Mila Santos',
            'status' => 'Active',
        ]);
        $afterDeadline = Beneficiary::create([
            'beneficiary_no' => 'BEN-5011',
            'full_name' => 'Lia Santos',
            'status' => 'Active',
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Family pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        $earlyAssignment = Distribution::create([
            'beneficiary_id' => $beforeStart->id,
            'package_id' => $package->id,
            'date_released' => today()->toDateString(),
            'start_date' => today()->addDay()->toDateString(),
            'deadline' => today()->addDays(3)->toDateString(),
            'status' => 'Pending',
            'distributed_by' => $staff->id,
        ]);
        $expiredAssignment = Distribution::create([
            'beneficiary_id' => $afterDeadline->id,
            'package_id' => $package->id,
            'date_released' => today()->subDays(3)->toDateString(),
            'start_date' => today()->subDays(3)->toDateString(),
            'deadline' => today()->subDay()->toDateString(),
            'status' => 'Pending',
            'distributed_by' => $staff->id,
        ]);
        Sanctum::actingAs($staff);

        $this->postJson('/api/distributions/release-pending', [
            'beneficiary_no' => $beforeStart->beneficiary_no,
            'distribution_id' => $earlyAssignment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('distribution_id');

        $this->postJson('/api/distributions/release-pending', [
            'beneficiary_no' => $afterDeadline->beneficiary_no,
            'distribution_id' => $expiredAssignment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('distribution_id');

        $this->assertDatabaseHas('distributions', ['id' => $earlyAssignment->id, 'status' => 'Pending']);
        $this->assertDatabaseHas('distributions', ['id' => $expiredAssignment->id, 'status' => 'Pending']);
    }
}
