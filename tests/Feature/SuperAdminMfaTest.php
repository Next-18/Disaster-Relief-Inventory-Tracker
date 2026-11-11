<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc_6238_six_digit_vector(): void
    {
        $this->assertTrue(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59));
        $this->assertFalse(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287083', 59));
    }

    public function test_super_admin_must_enroll_and_verify_an_authenticator_before_sign_in(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->post(route('login.attempt'), ['email' => $superAdmin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        $this->get(route('mfa.challenge'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/MfaChallenge')
                ->where('needsSetup', true)
                ->has('secret')
                ->has('provisioningUri')
                ->where('qrCodeDataUri', fn ($value) => str_starts_with($value, 'data:image/svg+xml;base64,')));

        $secret = session('pending_super_admin_mfa_secret');
        $this->post(route('mfa.verify'), ['code' => $this->totpCode($secret)])
            ->assertRedirect(route('admin.settings'));

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertNotNull($superAdmin->fresh()->mfa_enabled_at);
        $this->get(route('admin.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('mfaEnabled', true)->has('mfaRecoveryCodes', 8));
        $this->get(route('admin.settings'))
            ->assertInertia(fn ($page) => $page->has('mfaRecoveryCodes', 0));
    }

    public function test_recovery_code_can_be_used_once_for_a_later_super_admin_sign_in(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->post(route('login.attempt'), ['email' => $superAdmin->email, 'password' => 'password']);
        $this->get(route('mfa.challenge'));
        $secret = session('pending_super_admin_mfa_secret');
        $this->post(route('mfa.verify'), ['code' => $this->totpCode($secret)]);
        $recoveryCode = session('mfa_recovery_codes_once')[0] ?? null;
        $this->assertNotNull($recoveryCode);
        $this->get(route('admin.settings'));

        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['email' => $superAdmin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->get(route('mfa.challenge'))->assertInertia(fn ($page) => $page->where('needsSetup', false));
        $this->post(route('mfa.verify'), ['code' => $recoveryCode])
            ->assertRedirect(route('super-admin.barangays'));

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertCount(7, $superAdmin->fresh()->mfa_recovery_codes);
    }

    public function test_existing_super_admin_session_must_verify_mfa_before_opening_protected_pages(): void
    {
        $secret = Totp::secret();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => [],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.barangays'))
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        $this->get(route('mfa.challenge'))->assertInertia(fn ($page) => $page->where('needsSetup', false));
        $this->post(route('mfa.verify'), ['code' => $this->totpCode($secret)])
            ->assertRedirect(route('super-admin.barangays'));
        $this->get(route('super-admin.barangays'))->assertOk();
    }

    private function totpCode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) $key .= chr(bindec($chunk));
        }

        $counter = intdiv(now()->timestamp, 30);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($number % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
