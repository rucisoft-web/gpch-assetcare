<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\CurrentProperty;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    use Auditable, HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /** @var array<string,array<int,string>> */
    protected array $permissionCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'labor_rate' => 'decimal:2',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('property_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /** Peran yang berlaku untuk properti aktif (global + khusus properti). */
    public function activeRoles(?int $propertyId = null)
    {
        $propertyId ??= app(CurrentProperty::class)->id();
        $this->loadMissing('roles.permissions');

        return $this->roles->filter(fn ($r) => $r->pivot->property_id === null || (int) $r->pivot->property_id === (int) $propertyId);
    }

    /** @return array<int,string> */
    public function permissionSlugs(?int $propertyId = null): array
    {
        $key = (string) ($propertyId ?? app(CurrentProperty::class)->id());
        if (! isset($this->permissionCache[$key])) {
            $roles = $this->activeRoles($propertyId);
            $this->permissionCache[$key] = $roles->contains('slug', 'super-admin')
                ? Permission::pluck('slug')->all()
                : $roles->flatMap(fn ($r) => $r->permissions->pluck('slug'))->unique()->values()->all();
        }

        return $this->permissionCache[$key];
    }

    public function hasPermission(string $slug): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || in_array($slug, $this->permissionSlugs(), true));
    }

    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $s) {
            if ($this->hasPermission($s)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $slug): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('slug', $slug);
    }

    /** Hanya berperan Petugas Identifikasi Aset → hanya memakai menu Lapangan (/lapangan), tidak masuk panel admin. */
    public function isFieldOnly(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->isNotEmpty() && $this->roles->every(fn ($r) => $r->slug === 'field-officer');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function canSeeFinancials(): bool
    {
        return $this->hasPermission('asset.view_financial');
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = [];
        $this->unsetRelation('roles');
    }

    public function getRoleNamesAttribute(): string
    {
        return $this->roles->pluck('name')->join(', ');
    }

    public function getLabelAttribute(): string
    {
        return $this->name.($this->job_title ? ' — '.$this->job_title : '');
    }

    // ---- Filament MFA (aplikasi autentikator)
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /** Pengguna aktif dengan izin tertentu (untuk notifikasi & pilihan PIC). */
    public static function withPermission(string $slug)
    {
        return static::where('is_active', true)->with('roles.permissions')->get()
            ->filter(fn (User $u) => $u->hasPermission($slug))->values();
    }
}
