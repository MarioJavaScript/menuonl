<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitacion extends Model
{
    use BelongsToRestaurante, HasFactory;

    protected $table = 'invitaciones';

    protected $fillable = [
        'restaurante_id',
        'email',
        'rol',
        'token',
        'invitado_por',
        'expira_en',
        'aceptada_en',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'aceptada_en' => 'datetime',
        ];
    }

    /**
     * User who created the invitation.
     */
    public function invitador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por');
    }

    /**
     * Alias for invitador.
     */
    public function invitadoPor(): BelongsTo
    {
        return $this->invitador();
    }

    /**
     * Check if invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expira_en !== null && $this->expira_en->isPast();
    }

    /**
     * Check if invitation has already been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->aceptada_en !== null;
    }

    /**
     * Scope to only pending (unaccepted and unexpired) invitations.
     */
    public function scopePendiente(Builder $query): Builder
    {
        return $query->whereNull('aceptada_en')
            ->where('expira_en', '>', now());
    }
}
