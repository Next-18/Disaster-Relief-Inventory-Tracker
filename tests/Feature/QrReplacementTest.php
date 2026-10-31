<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrReplacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('qr_codes');
    }

    public function test_generating_a_replacement_qr_rotates_and_unifies_the_beneficiary_code(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-7001',
            'qr_code' => 'qr_old.svg',
            'qr_token' => 'OLDQR7',
            'qr_revoked' => true,
            'full_name' => 'Mila Santos',
            'status' => 'Active',
        ]);
        $user = User::factory()->create([
            'name' => $beneficiary->full_name,
            'email' => $beneficiary->beneficiary_no,
            'username' => 'mila.santos',
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
        ]);

        $csrfToken = Str::random(40);
        $this->withSession(['_token' => $csrfToken])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->post(route('admin.qr-codes.generate', $beneficiary->id))
            ->assertRedirect();

        $beneficiary->refresh();

        $this->assertNotSame('BEN-7001', $beneficiary->beneficiary_no);
        $this->assertSame($beneficiary->beneficiary_no, $beneficiary->qr_token);
        $this->assertFalse($beneficiary->qr_revoked);
        $this->assertSame($beneficiary->beneficiary_no, $user->fresh()->email);
        $this->assertFalse(Beneficiary::query()->forQrPayload('OLDQR7')->exists());
        $this->assertTrue(Beneficiary::query()->forQrPayload($beneficiary->qr_token)->exists());
        $this->getJson(route('admin.distribution.lookup', ['code' => 'OLDQR7']))->assertNotFound();
        $this->assertMatchesRegularExpression('/\Abeneficiary-qr\/[a-f0-9]{64}\.svg\z/', $beneficiary->qr_code);
        Storage::disk('qr_codes')->assertExists($beneficiary->qr_code);

        $this->get(route('admin.qr-codes.image', $beneficiary->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get(route('admin.qr-codes.download', $beneficiary->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_reporting_and_replacing_a_legacy_qr_revokes_the_old_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-7003',
            'qr_code' => 'qr_legacy.svg',
            'qr_token' => 'LEGACY7',
            'qr_revoked' => false,
            'full_name' => 'Test Beneficiary',
            'status' => 'Active',
        ]);
        $user = User::factory()->create([
            'name' => $beneficiary->full_name,
            'email' => $beneficiary->beneficiary_no,
            'username' => 'legacy.beneficiary',
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.lost-qr.report', $beneficiary->id))
            ->assertRedirect(route('admin.lost-qr'));

        $beneficiary->refresh();
        $this->assertNull($beneficiary->qr_code);
        $this->assertTrue($beneficiary->qr_revoked);
        $this->assertFalse(Beneficiary::query()->forQrPayload('LEGACY7')->exists());
        $this->assertFalse(Beneficiary::query()->forQrPayload('BEN-7003')->exists());
        $this->get(route('admin.qr-codes.image', $beneficiary->id))->assertNotFound();
        $this->get(route('admin.qr-codes.download', $beneficiary->id))->assertNotFound();

        $this->post(route('admin.qr-codes.generate', $beneficiary->id))
            ->assertRedirect();

        $replacement = $beneficiary->fresh();
        $this->assertTrue($replacement->qr_token === $replacement->beneficiary_no);
        $this->assertFalse($replacement->qr_revoked);
        $this->assertNotSame('BEN-7003', $replacement->beneficiary_no);
        $this->assertSame($replacement->beneficiary_no, $user->fresh()->email);
        $this->assertFalse(Beneficiary::query()->forQrPayload('LEGACY7')->exists());
        $this->assertFalse(Beneficiary::query()->forQrPayload('BEN-7003')->exists());
        $this->assertMatchesRegularExpression('/\Abeneficiary-qr\/[a-f0-9]{64}\.svg\z/', $replacement->qr_code);
        Storage::disk('qr_codes')->assertExists($replacement->qr_code);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);
        $this->getJson(route('admin.distribution.lookup', ['code' => 'LEGACY7']))->assertNotFound();
        $this->getJson(route('admin.distribution.lookup', ['code' => 'BEN-7003']))->assertNotFound();
        $this->getJson(route('admin.distribution.lookup', ['code' => $replacement->qr_token]))->assertOk();

        Sanctum::actingAs($staff);
        $this->getJson('/api/beneficiaries/qr/LEGACY7')->assertNotFound();
        $this->getJson('/api/beneficiaries/qr/BEN-7003')->assertNotFound();
        $this->getJson('/api/beneficiaries/qr/'.$replacement->qr_token)->assertOk();
    }

    public function test_storage_failure_after_reporting_lost_keeps_the_old_qr_revoked(): void
    {
        $admin = User::factory()->admin()->create();
        $beneficiary = Beneficiary::create([
            'beneficiary_no' => 'BEN-7004',
            'qr_code' => 'qr_legacy.svg',
            'qr_token' => 'FAILED7',
            'qr_revoked' => false,
            'full_name' => 'Test Beneficiary',
            'status' => 'Active',
        ]);
        $user = User::factory()->create([
            'name' => $beneficiary->full_name,
            'email' => $beneficiary->beneficiary_no,
            'username' => 'failed.beneficiary',
            'role' => 'user',
            'beneficiary_id' => $beneficiary->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.lost-qr.report', $beneficiary->id))
            ->assertRedirect(route('admin.lost-qr'));

        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::set('qr_codes', $disk);

        $this->withoutExceptionHandling();
        $storageFailure = null;
        try {
            $this->post(route('admin.qr-codes.generate', $beneficiary->id));
        } catch (\RuntimeException $exception) {
            $storageFailure = $exception;
        }

        $this->assertInstanceOf(\RuntimeException::class, $storageFailure);
        $this->withExceptionHandling();

        $beneficiary->refresh();
        $this->assertNull($beneficiary->qr_code);
        $this->assertTrue($beneficiary->qr_revoked);
        $this->assertFalse(Beneficiary::query()->forQrPayload('FAILED7')->exists());
        $this->assertSame('BEN-7004', $beneficiary->beneficiary_no);
        $this->assertSame('BEN-7004', $user->fresh()->email);
        $this->get(route('admin.qr-codes.image', $beneficiary->id))->assertNotFound();
        $this->get(route('admin.qr-codes.download', $beneficiary->id))->assertNotFound();

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)
            ->getJson(route('admin.distribution.lookup', ['code' => 'FAILED7']))
            ->assertNotFound();
        Sanctum::actingAs($staff);
        $this->getJson('/api/beneficiaries/qr/FAILED7')->assertNotFound();
    }

    public function test_qr_images_and_downloads_require_an_administrator(): void
    {
        $beneficiary = $this->createBeneficiaryWithStoredQr();

        $this->get(route('admin.qr-codes.image', $beneficiary->id))
            ->assertRedirect(route('login'));
        $this->get(route('admin.qr-codes.download', $beneficiary->id))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.qr-codes.image', $beneficiary->id))->assertForbidden();
        $this->get(route('admin.qr-codes.download', $beneficiary->id))->assertForbidden();
    }

    public function test_revoked_qrs_cannot_be_displayed_downloaded_or_verified(): void
    {
        $beneficiary = $this->createBeneficiaryWithStoredQr([
            'qr_revoked' => true,
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.qr-codes.image', $beneficiary->id))->assertNotFound();
        $this->get(route('admin.qr-codes.download', $beneficiary->id))->assertNotFound();
        $this->getJson(route('admin.distribution.lookup', ['code' => 'SCANQR7']))->assertNotFound();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/beneficiaries/qr/SCANQR7')->assertNotFound();
    }

    public function test_scanner_and_api_qr_verification_remain_available_to_authorized_staff(): void
    {
        $beneficiary = $this->createBeneficiaryWithStoredQr();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->getJson(route('admin.distribution.lookup', ['code' => 'SCANQR7']))
            ->assertOk()
            ->assertJsonPath('data.id', $beneficiary->id);

        Sanctum::actingAs($staff);
        $this->getJson('/api/beneficiaries/qr/SCANQR7')
            ->assertOk()
            ->assertJsonPath('data.id', $beneficiary->id);
    }

    private function createBeneficiaryWithStoredQr(array $attributes = []): Beneficiary
    {
        $fileName = 'beneficiary-qr/'.str_repeat('a', 64).'.svg';
        Storage::disk('qr_codes')->put($fileName, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        return Beneficiary::create(array_merge([
            'beneficiary_no' => 'BEN-7002',
            'qr_code' => $fileName,
            'qr_token' => 'SCANQR7',
            'qr_revoked' => false,
            'full_name' => 'Test Beneficiary',
            'status' => 'Active',
        ], $attributes));
    }
}
