<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'es_superadmin',
        'activo',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
            'es_superadmin' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Determine if user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->activo;
    }

    /**
     * Restaurants associated with this user.
     */
    public function restaurantes(): BelongsToMany
    {
        return $this->belongsToMany(Restaurante::class, 'restaurante_usuario')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Restaurants the user can pick as the panel tenant.
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->isSuperadmin() ? Restaurante::all() : $this->restaurantes;
    }

    /**
     * Determine if the user can administer the given restaurant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Restaurante) {
            return false;
        }

        return $this->isSuperadmin() || $this->restaurantes()->whereKey($tenant->getKey())->exists();
    }

    /**
     * Check if user is superadmin.
     */
    public function isSuperadmin(): bool
    {
        return (bool) $this->es_superadmin;
    }
}
