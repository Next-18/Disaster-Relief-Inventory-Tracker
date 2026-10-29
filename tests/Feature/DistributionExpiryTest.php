<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\BeneficiaryNotification;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\User;
use App\Services\DistributionStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_pending_assignment_restores_stock_and_notifies_beneficiary(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-6001',
            'full_name' => 'Mila Cruz',
            'status' => 'Active',
        ]);
        $beneficiaryUser = User::factory()->create([
            'name' => $beneficiary->full_name,
            'email' => $beneficiary->beneficiary_no,
            'username' => 'mila.cruz',
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
        ]);
        $item = InventoryItem::create([
            'item_name' => 'Rice',
            'category' => 'Food',
            'quantity' => 10,
            'unit' => 'bags',
            'minimum_stock' => 2,
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Rice pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        $package->inventoryItems()->attach($item->id, ['quantity_per_package' => 2]);
        $distribution = Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => today()->subDays(3)->toDateString(),
            'start_date' => today()->subDays(3)->toDateString(),
            'deadline' => today()->subDay()->toDateString(),
            'status' => 'Pending',
            'distributed_by' => $staff->id,
        ]);

        app(DistributionStockService::class)->consumeFor($distribution);
        $this->assertSame(8, $item->fresh()->quantity);

        $this->artisan('distributions:expire-overdue')
            ->expectsOutput('Expired 1 pending distribution assignment(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('distributions', [
            'id' => $distribution->id,
            'status' => 'Cancelled',
        ]);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertDatabaseHas('beneficiary_notifications', [
            'user_id' => $beneficiaryUser->id,
            'title' => 'Relief collection deadline passed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'action' => 'expire',
            'module' => 'distribution',
        ]);

        $this->artisan('distributions:expire-overdue')
            ->expectsOutput('Expired 0 pending distribution assignment(s).')
            ->assertSuccessful();
        $this->assertSame(1, BeneficiaryNotification::where('user_id', $beneficiaryUser->id)->count());
        $this->assertSame(10, $item->fresh()->quantity);
    }
}
