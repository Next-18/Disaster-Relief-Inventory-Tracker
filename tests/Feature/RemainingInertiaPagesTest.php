<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\ReliefPackage;
use App\Models\Setting;
use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RemainingInertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_all_remaining_admin_pages_resolve_to_inertia_components(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('routeUrls.loginAttempt', route('login.attempt')));

        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.search', ['q' => 'record']))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Search')->where('term', 'record'));

        $this->get(route('admin.packages'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Packages')->has('packages.data', 0));

        $this->get(route('admin.qr-codes'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/QrCodes')->has('beneficiaries', 0));

        $this->get(route('admin.lost-qr'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/LostQr')->has('beneficiaries', 0));

        $this->get(route('admin.reports'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports')->has('distributions.data')->has('inventoryItems.data'));

        $this->get(route('admin.audit-logs'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/AuditLogs')->has('auditLogs.data', 0));

        $this->get(route('admin.settings'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Settings')->has('settings.site_name'));
    }

    public function test_qr_codes_inertia_navigation_does_not_return_the_legacy_ajax_json_payload(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $version = app(HandleInertiaRequests::class)->version(request());

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->get(route('admin.qr-codes'))
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Admin/QrCodes');
    }

    public function test_qr_codes_legacy_ajax_request_still_returns_its_json_payload(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('admin.qr-codes'))
            ->assertOk()
            ->assertJsonStructure(['beneficiaries', 'count']);
    }

    public function test_package_crud_and_settings_submission_keep_validation_and_flash_feedback(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $inventoryItem = \App\Models\InventoryItem::create([
            'item_name' => 'Rice',
            'category' => 'Food',
            'quantity' => 20,
            'unit' => 'bags',
            'minimum_stock' => 5,
        ]);

        $this->from(route('admin.packages'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.packages.store'), [
                'package_name' => 'Family food kit',
                'description' => 'Rice, canned food and water',
                'category' => 'Food',
                'status' => 'Available',
                'items' => [['inventory_item_id' => $inventoryItem->id, 'quantity_per_package' => 2]],
            ])
            ->assertRedirect(route('admin.packages'))
            ->assertSessionHas('success', 'Relief package added successfully.');

        $package = ReliefPackage::where('package_name', 'Family food kit')->firstOrFail();

        $this->from(route('admin.packages'))
            ->put(route('admin.packages.update', $package->id), [
                'package_name' => 'Family food pack',
                'description' => 'Updated contents',
                'category' => 'Food',
                'status' => 'Not Available',
                'items' => [['inventory_item_id' => $inventoryItem->id, 'quantity_per_package' => 3]],
            ])
            ->assertRedirect(route('admin.packages'));
        $this->assertDatabaseHas('relief_packages', ['id' => $package->id, 'package_name' => 'Family food pack', 'status' => 'Not Available']);

        $this->from(route('admin.packages'))
            ->post(route('admin.packages.store'), ['package_name' => '', 'category' => 'Food', 'status' => 'Available'])
            ->assertRedirect(route('admin.packages'))
            ->assertSessionHasErrors('package_name');

        $this->from(route('admin.packages'))
            ->delete(route('admin.packages.delete', $package->id))
            ->assertRedirect(route('admin.packages'));
        $this->assertDatabaseMissing('relief_packages', ['id' => $package->id]);

        $this->from(route('admin.settings'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.settings'), [
                'site_name' => 'Custom Relief Portal',
                'site_description' => 'Local relief operations',
                'contact_email' => 'help@example.test',
                'contact_phone' => '09170000000',
                'barangay_name' => 'San Isidro',
                'municipality' => 'Sample City',
                'province' => 'Sample Province',
            ])
                      ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success', 'Barangay location settings updated successfully.');

        $this->assertNotSame('Custom Relief Portal', Setting::where('key', 'site_name')->value('value'));
        $this->assertSame('San Isidro', \App\Models\Barangay::query()->oldest('id')->value('name'));
    }

    public function test_package_with_distribution_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create(['role' => 'staff']);
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-9020',
            'full_name' => 'Mina Cruz',
            'status' => 'Active',
        ]);
        $package = ReliefPackage::create([
            'package_name' => 'Emergency food kit',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        $distribution = Distribution::create([
            'beneficiary_id' => $beneficiary->id,
            'package_id' => $package->id,
            'date_released' => now()->toDateString(),
            'status' => 'Pending',
            'distributed_by' => $staff->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.packages'))
            ->delete(route('admin.packages.delete', $package->id))
            ->assertRedirect(route('admin.packages'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('relief_packages', ['id' => $package->id]);
        $this->assertDatabaseHas('distributions', ['id' => $distribution->id, 'package_id' => $package->id, 'status' => 'Pending']);
    }

    public function test_lost_qr_action_updates_the_existing_beneficiary_record(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-9001',
            'full_name' => 'Nina Dela Cruz',
            'status' => 'Active',
            'qr_code' => 'qr_BEN-9001.svg',
        ]);

        $this->from(route('admin.lost-qr'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.lost-qr.report', $beneficiary->id))
            ->assertRedirect(route('admin.qr-codes', ['tab' => 'lost']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiary->id, 'qr_code' => null]);
    }

    public function test_beneficiary_can_activate_admin_provisioned_account_with_temporary_password(): void
    {
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-9010',
            'full_name' => 'Paula Reyes',
            'status' => 'Active',
        ]);
        $user = User::factory()->create([
            'name' => $beneficiary->full_name,
            'email' => $beneficiary->beneficiary_no,
            'password' => 'TEMPORARY7',
            'beneficiary_id' => $beneficiary->id,
            'role' => 'user',
            'password_changed' => false,
        ]);

        $this->from(route('register'))
            ->post(route('register.attempt'), [
                'full_name' => $beneficiary->full_name,
                'beneficiary_no' => strtolower($beneficiary->beneficiary_no),
                'temporary_password' => 'TEMPORARY7',
                'password' => 'ReliefPass2026',
                'password_confirmation' => 'ReliefPass2026',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->password_changed);
        $this->assertTrue(Hash::check('ReliefPass2026', $user->password));
    }

    public function test_beneficiary_cannot_activate_an_account_that_was_not_provisioned(): void
    {
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-9011',
            'full_name' => 'Marco Reyes',
            'status' => 'Active',
        ]);

        $this->from(route('register'))
            ->post(route('register.attempt'), [
                'full_name' => $beneficiary->full_name,
                'beneficiary_no' => $beneficiary->beneficiary_no,
                'password' => 'ReliefPass2026',
                'password_confirmation' => 'ReliefPass2026',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('beneficiary_no');

        $this->assertDatabaseMissing('users', ['beneficiary_id' => $beneficiary->id]);
    }
}
