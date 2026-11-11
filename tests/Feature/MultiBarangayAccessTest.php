<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Beneficiary;
use App\Models\InventoryItem;
use App\Models\Announcement;
use App\Models\ReliefPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiBarangayAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_barangay_admin_sees_only_its_own_beneficiaries_in_web_and_api(): void
    {
        $firstBarangay = Barangay::create(['name' => 'North Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $secondBarangay = Barangay::create(['name' => 'South Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $firstAdmin = User::factory()->create(['role' => 'admin', 'barangay_id' => $firstBarangay->id]);
        $this->assertTrue($firstAdmin->is_active);
        $this->assertTrue($firstAdmin->canSignIn());
        $first = Beneficiary::create([
            'barangay_id' => $firstBarangay->id,
            'beneficiary_no' => 'NORTH-1001',
            'full_name' => 'North Household',
            'status' => 'Active',
        ]);
        $second = Beneficiary::create([
            'barangay_id' => $secondBarangay->id,
            'beneficiary_no' => 'SOUTH-1001',
            'full_name' => 'South Household',
            'status' => 'Active',
        ]);

        $this->actingAs($firstAdmin)
            ->get(route('admin.beneficiaries'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('beneficiaries.data', 1)
                ->where('beneficiaries.data.0.id', $first->id));

        Sanctum::actingAs($firstAdmin);
        $this->putJson('/api/beneficiaries/'.$second->id, ['full_name' => 'Changed From North'])
            ->assertNotFound();
        $this->getJson('/api/beneficiaries')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $first->id);
    }

    public function test_mobile_operational_lists_are_scoped_to_the_admins_barangay(): void
    {
        $firstBarangay = Barangay::create(['name' => 'List North', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $secondBarangay = Barangay::create(['name' => 'List South', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $admin = User::factory()->create(['role' => 'admin', 'barangay_id' => $firstBarangay->id]);

        $firstInventory = InventoryItem::create([
            'barangay_id' => $firstBarangay->id,
            'item_name' => 'North Rice',
            'category' => 'Food',
            'quantity' => 12,
            'unit' => 'bags',
            'minimum_stock' => 2,
        ]);
        InventoryItem::create([
            'barangay_id' => $secondBarangay->id,
            'item_name' => 'South Rice',
            'category' => 'Food',
            'quantity' => 24,
            'unit' => 'bags',
            'minimum_stock' => 2,
        ]);
        $firstPackage = ReliefPackage::create([
            'barangay_id' => $firstBarangay->id,
            'package_name' => 'North Family Pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        ReliefPackage::create([
            'barangay_id' => $secondBarangay->id,
            'package_name' => 'South Family Pack',
            'category' => 'Food',
            'status' => 'Available',
        ]);
        $firstAnnouncement = Announcement::create([
            'barangay_id' => $firstBarangay->id,
            'title' => 'North schedule',
            'body' => 'North schedule update',
            'is_published' => true,
            'created_by' => $admin->id,
        ]);
        Announcement::create([
            'barangay_id' => $secondBarangay->id,
            'title' => 'South schedule',
            'body' => 'South schedule update',
            'is_published' => true,
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/inventory')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $firstInventory->id);
        $this->getJson('/api/packages')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $firstPackage->id);
        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $firstAnnouncement->id);
    }

    public function test_only_super_admin_can_open_the_barangay_manager(): void
    {
        $barangayAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($barangayAdmin)
            ->get(route('super-admin.barangays'))
            ->assertForbidden();

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->get(route('super-admin.barangays'))
            ->assertOk();
    }

    public function test_super_admin_can_create_a_barangay_and_assign_its_first_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->from(route('super-admin.barangays'))
            ->post(route('super-admin.barangays.store'), [
                'name' => 'East Demo',
                'municipality' => 'Sample City',
                'province' => 'Sample Province',
                'admin_name' => 'East Barangay Admin',
                'admin_email' => 'east.admin@example.test',
                'admin_password' => 'StrongPassword123',
                'admin_password_confirmation' => 'StrongPassword123',
            ])
            ->assertRedirect(route('super-admin.barangays'));

        $barangay = Barangay::query()->where('name', 'East Demo')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'email' => 'east.admin@example.test',
            'role' => 'admin',
            'barangay_id' => $barangay->id,
        ]);

        $this->post(route('super-admin.barangays.activate', $barangay))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_barangay_id', $barangay->id);
    }

    public function test_super_admin_must_select_a_workspace_before_operational_writes(): void
    {
        $barangay = Barangay::create(['name' => 'Write Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $payload = ['item_name' => 'Rice', 'category' => 'Food', 'unit' => 'bags', 'minimum_stock' => 2];

        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->from(route('super-admin.barangays'))
            ->post(route('admin.inventory.store'), $payload)
            ->assertRedirect(route('super-admin.barangays'))
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('inventory_items', ['item_name' => 'Rice']);

        $this->withSession(['active_barangay_id' => $barangay->id])
            ->post(route('admin.inventory.store'), $payload)
            ->assertRedirect(route('admin.inventory'));

        $this->assertDatabaseHas('inventory_items', [
            'item_name' => 'Rice',
            'barangay_id' => $barangay->id,
        ]);
    }

    public function test_super_admin_scope_is_shared_with_every_inertia_page(): void
    {
        $barangay = Barangay::create(['name' => 'Scope Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeBarangayId', null)
                ->where('activeBarangayName', null));

        $this->withSession(['active_barangay_id' => $barangay->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeBarangayId', $barangay->id)
                ->where('activeBarangayName', $barangay->name));
    }

    public function test_super_admin_is_denied_mobile_api_data_even_with_a_sanctum_token(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $superAdmin->createToken('legacy-mobile-token');

        Sanctum::actingAs($superAdmin);
        $this->getJson('/api/beneficiaries')
            ->assertForbidden()
            ->assertJsonPath('message', 'Super Admin accounts use the web portal and cannot access barangay mobile API data.');
    }

    public function test_barangay_deactivation_revokes_mobile_tokens_and_blocks_admin_sessions(): void
    {
        $barangay = Barangay::create(['name' => 'Inactive Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $secondBarangay = Barangay::create(['name' => 'Active Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $defaultBarangay = Barangay::query()->oldest('id')->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'barangay_id' => $barangay->id]);
        $token = $admin->createToken('mobile-test-token')->accessToken;
        DB::table('sessions')->insert([
            'id' => 'inactive-barangay-admin-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->patch(route('super-admin.barangays.status', $barangay), ['is_active' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('barangays', ['id' => $barangay->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'inactive-barangay-admin-session']);
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->patch(route('super-admin.barangays.status', $barangay), ['is_active' => true])
            ->assertRedirect();
        $this->assertTrue($admin->fresh()->canSignIn());

        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->patch(route('super-admin.barangays.status', $barangay), ['is_active' => false])
            ->assertRedirect();
        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->patch(route('super-admin.barangays.status', $secondBarangay), ['is_active' => false])
            ->assertRedirect();
        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->patch(route('super-admin.barangays.status', $defaultBarangay), ['is_active' => false])
            ->assertRedirect()
            ->assertSessionHas('error', 'Keep at least one active barangay workspace.');
        $this->assertDatabaseHas('barangays', ['id' => $defaultBarangay->id, 'is_active' => true]);
    }

    public function test_admin_can_deactivate_team_accounts_but_cannot_disable_the_last_admin(): void
    {
        $barangay = Barangay::create(['name' => 'Accounts Demo', 'municipality' => 'Sample City', 'province' => 'Sample Province']);
        $admin = User::factory()->create(['role' => 'admin', 'barangay_id' => $barangay->id]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'barangay_id' => $barangay->id]);
        $staff = User::factory()->create(['role' => 'staff', 'barangay_id' => $barangay->id]);
        $token = $staff->createToken('staff-mobile-token')->accessToken;
        DB::table('sessions')->insert([
            'id' => 'inactive-staff-session',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $staff->id), ['is_active' => false])
            ->assertRedirect(route('admin.users'));
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'inactive-staff-session']);
        $this->assertSame(2, User::query()->where('barangay_id', $barangay->id)->where('role', 'admin')->where('is_active', true)->count());

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $admin->id), ['is_active' => false])
            ->assertForbidden();

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->withSession(['active_barangay_id' => $barangay->id])
            ->from(route('admin.users'))
            ->patch(route('admin.users.status', $otherAdmin->id), ['is_active' => false])
            ->assertRedirect(route('admin.users'));
        $this->actingAsVerifiedSuperAdmin($superAdmin)
            ->withSession(['active_barangay_id' => $barangay->id])
            ->patch(route('admin.users.status', $admin->id), ['is_active' => false])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_staff_can_view_but_cannot_publish_announcements(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.announcements'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canPublishAnnouncements', false));

        $this->post(route('admin.announcements.store'), ['title' => 'Schedule', 'body' => 'Distribution starts tomorrow.'])
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), ['title' => 'Schedule', 'body' => 'Distribution starts tomorrow.'])
            ->assertRedirect(route('admin.announcements'));
        $this->assertDatabaseHas('announcements', ['title' => 'Schedule']);
    }
    private function actingAsVerifiedSuperAdmin(User $user): self
    {
        return $this->actingAs($user)->withSession([
            'super_admin_mfa_verified_user_id' => $user->id,
        ]);
    }
}

