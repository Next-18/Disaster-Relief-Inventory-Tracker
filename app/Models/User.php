<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'username', 'password', 'beneficiary_id', 'barangay_id', 'role', 'is_active', 'password_changed', 'mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $actor = auth()->user();
            $activeId = $actor?->role === 'super_admin' && request()->hasSession() ? request()->session()->get('active_barangay_id') : null;
            $defaultId = $actor && $actor->role !== 'super_admin'
                ? Barangay::query()->oldest('id')->value('id')
                : null;
            if (! $user->barangay_id && ($activeId || $actor?->barangay_id || $defaultId)) {
                $user->barangay_id = $activeId ?: ($actor?->barangay_id ?: $defaultId);
            }
        });
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public static function uniqueUsernameFor(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim(Str::ascii($fullName)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = preg_replace('/[^A-Za-z0-9]/', '', $parts[0] ?? 'Beneficiary');
        $lastName = preg_replace('/[^A-Za-z0-9]/', '', $parts[count($parts) - 1] ?? '');
        $base = mb_strtoupper(mb_substr($firstName !== '' ? $firstName : 'B', 0, 1))
            .mb_strtolower($lastName !== '' ? $lastName : 'beneficiary');
        $suffix = 1;
        $username = $base.$suffix;

        while (static::query()->whereRaw('LOWER(username) = ?', [Str::lower($username)])->exists()) {
            $username = $base.(++$suffix);
        }

        return $username;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed' => 'boolean',
            'is_active' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled_at' => 'datetime',
        ];
    }

    public function canSignIn(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->role === 'super_admin'
            || ! $this->barangay_id
            || $this->barangay?->is_active === true;
    }

    /**
     * Get the beneficiary associated with the user.
     */
    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }


    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function canManageBarangay(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public function canRecordDistributions(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'staff', 'volunteer'], true);
    }

    public function canReleasePackages(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'staff'], true);
    }
}
