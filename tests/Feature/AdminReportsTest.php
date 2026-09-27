<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\InventoryItem;
use App\Models\ReliefPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_date_and_status_filters_keep_period_totals_consistent_and_show_current_snapshots(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = ReliefPackage::create(['package_name' => 'Family food pack', 'category' => 'Food', 'status' => 'Available']);
        $releasedBeneficiary = $this->createBeneficiary('BEN-3001', 'Ava Released');
        $pendingBeneficiary = $this->createBeneficiary('BEN-3002', 'Bea Pending');
        $outsideBeneficiary = $this->createBeneficiary('BEN-3003', 'Cal Outside');

        $this->createDistribution($user, $releasedBeneficiary, $package, '2026-09-10', 'Released');
        $this->createDistribution($user, $pendingBeneficiary, $package, '2026-09-12', 'Pending');
        $this->createDistribution($user, $outsideBeneficiary, $package, '2026-08-30', 'Released');

        InventoryItem::create([
            'item_name' => 'Rice', 'category' => 'Food', 'quantity' => 25,
            'unit' => 'bags', 'minimum_stock' => 5, 'status' => 'In Stock',
        ]);
        InventoryItem::create([
            'item_name' => 'Water', 'category' => 'Water', 'quantity' => 2,
            'unit' => 'cases', 'minimum_stock' => 4, 'status' => 'Low Stock',
        ]);

        $this->get(route('admin.reports', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'Pending',
        ]))
            ->assertOk()
            ->assertSee('aria-label="Report filters"', false)
            ->assertSee('All distributions')
            ->assertSee('Apply filters')
            ->assertSee('<b>2</b>', false)
            ->assertSee('Released')
            ->assertSee('Pending')
            ->assertSee('50.0%')
            ->assertSee('Bea Pending')
            ->assertSee('Showing 1–1 of 1 matching records')
            ->assertSee('Monthly distribution trend')
            ->assertSee('Sep 26: 2 total, 1 released, 1 pending, 0 other')
            ->assertSee('Current inventory snapshot')
            ->assertSee('Total units on hand')
            ->assertSee('1 low stock record and 1 record above minimum')
            ->assertSee('27');
    }

    public function test_report_rejects_a_date_range_where_end_precedes_start(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.reports', [
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-01',
        ]))->assertRedirect()->assertSessionHasErrors('end_date');
    }

    public function test_empty_report_period_displays_zero_totals_and_clear_empty_states(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.reports', [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertSee('0.0%')
            ->assertSee('No distribution activity in the months shown.')
            ->assertSee('No distributions found.')
            ->assertSee('No inventory records yet');
    }

    public function test_report_exports_respect_filters_and_escape_spreadsheet_formulas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = ReliefPackage::create(['package_name' => 'Family food pack', 'category' => 'Food', 'status' => 'Available']);
        $formulaBeneficiary = $this->createBeneficiary('BEN-3010', '=HYPERLINK("https://example.test")');
        $otherBeneficiary = $this->createBeneficiary('BEN-3011', 'Regular Recipient');
        $this->createDistribution($user, $formulaBeneficiary, $package, '2026-09-10', 'Released');
        $this->createDistribution($user, $otherBeneficiary, $package, '2026-09-12', 'Pending');

        $response = $this->get(route('admin.reports.export', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'Released',
        ]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Distribution ID', $csv);
        $this->assertStringContainsString('Recorded By', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Regular Recipient', $csv);

        $inventoryCsv = $this->get(route('admin.reports.inventory-export'));
        $inventoryCsv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Quantity on Hand', $inventoryCsv->streamedContent());
    }

    private function createBeneficiary(string $number, string $name): Beneficiary
    {
        return Beneficiary::create([
            'beneficiary_no' => $number,
            'full_name' => $name,
            'status' => 'Active',
        ]);
    }

    private function createDistribution(User $user, Beneficiary $beneficiary, ReliefPackage $package, string $date, string $status): Distribution
    {
        return Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => $date,
            'status' => $status,
            'distributed_by' => $user->id,
        ]);
    }
}
