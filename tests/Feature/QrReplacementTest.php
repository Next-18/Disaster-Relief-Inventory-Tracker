<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrReplacementTest extends TestCase
{
    use RefreshDatabase;

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

        $newQrPath = null;
        try {
            $csrfToken = Str::random(40);
            $this->withSession(['_token' => $csrfToken])
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->post(route('admin.qr-codes.generate', $beneficiary->id))
                ->assertRedirect();

            $beneficiary->refresh();
            $newQrPath = public_path('qr-codes/'.$beneficiary->qr_code);

            $this->assertNotSame('BEN-7001', $beneficiary->beneficiary_no);
            $this->assertSame($beneficiary->beneficiary_no, $beneficiary->qr_token);
            $this->assertFalse($beneficiary->qr_revoked);
            $this->assertSame($beneficiary->beneficiary_no, $user->fresh()->email);
            $this->assertFalse(Beneficiary::query()->forQrPayload('OLDQR7')->exists());
            $this->assertFileExists($newQrPath);
        } finally {
            if ($newQrPath && File::exists($newQrPath)) {
                File::delete($newQrPath);
            }
        }
    }
}
