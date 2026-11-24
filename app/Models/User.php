<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    use HasRoles {
        removeRole as protected traitRemoveRole;
        syncRoles as protected traitSyncRoles;
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
        ];
    }

    /** Hanya atribut non-sensitif; password dan remember_token tidak pernah dicatat. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public const SUPERADMIN_ROLE = 'superadmin';

    /**
     * Dicek di delete(), bukan di event deleting: listener Spatie mencabut role
     * lebih dulu sehingga status superadmin sudah hilang saat event berjalan.
     */
    public function delete()
    {
        if ($this->isLastSuperadmin()) {
            throw new LogicException('Superadmin terakhir tidak boleh dihapus.');
        }

        return parent::delete();
    }

    /** True bila akun ini superadmin satu-satunya. Berlaku di semua jalur, juga bila Gate::before meloloskan. */
    public function isLastSuperadmin(): bool
    {
        return $this->isSuperadmin()
            && ! static::role(self::SUPERADMIN_ROLE)->whereKeyNot($this->getKey())->exists();
    }

    public function removeRole(...$role): static
    {
        if ($this->rolesContainSuperadmin($role)) {
            $this->guardLastSuperadmin();
        }

        return $this->traitRemoveRole(...$role);
    }

    public function syncRoles(...$roles): static
    {
        if ($this->exists && ! $this->rolesContainSuperadmin($roles)) {
            $this->guardLastSuperadmin();
        }

        return $this->traitSyncRoles(...$roles);
    }

    /** Nama, id, model Role, atau koleksi/array dari semuanya. */
    private function rolesContainSuperadmin(array $roles): bool
    {
        $superadminId = Role::where('name', self::SUPERADMIN_ROLE)->value('id');

        return $superadminId !== null && in_array($superadminId, $this->collectRoles($roles), false);
    }

    private function guardLastSuperadmin(): void
    {
        if ($this->isLastSuperadmin()) {
            throw new LogicException('Role superadmin tidak boleh dicabut dari superadmin terakhir.');
        }
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole(self::SUPERADMIN_ROLE);
    }

    /** Hanya akun yang punya minimal satu role yang boleh masuk panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }
}
