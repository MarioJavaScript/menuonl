<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\InvitacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitacion extends Model
{
    /** @use HasFactory<InvitacionFactory> */
    use BelongsToRestaurante, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'invitaciones';

    /**
     * The token column stores the SHA-256 hash of the emailed token, never the plain value.
     */
    protected $fillable = [
        'restaurante_id',
        'email',
        'rol',
        'token',
        'invitado_por',
        'expira_en',
        'aceptada_en',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'aceptada_en' => 'datetime',
            'enviada_en' => 'datetime',
            'envio_fallido_en' => 'datetime',
        ];
    }

    /**
     * User who sent the invitation.
     */
    public function invitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitado_por');
    }

    /**
     * Whether the invitation is past its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->expira_en->isPast();
    }

    /**
     * Whether the invitation has already been used.
     */
    public function isAccepted(): bool
    {
        return $this->aceptada_en !== null;
    }

    /**
     * Whether the last attempt to email the invitation failed and it is waiting to be resent.
     */
    public function envioFallido(): bool
    {
        return $this->enviada_en === null && $this->envio_fallido_en !== null;
    }

    /**
     * Whether the email is still waiting in the queue (not sent, not failed).
     */
    public function envioEnCola(): bool
    {
        return $this->enviada_en === null && $this->envio_fallido_en === null;
    }

    /**
     * Invitations whose email could not be sent and have not been accepted.
     */
    public function scopeConEnvioFallido(Builder $query): Builder
    {
        return $query->whereNull('aceptada_en')->whereNull('enviada_en')->whereNotNull('envio_fallido_en');
    }

    /**
     * Scope to invitations that are neither accepted nor expired.
     */
    public function scopePendiente(Builder $query): Builder
    {
        return $query->whereNull('aceptada_en')->where('expira_en', '>', now());
    }
}
