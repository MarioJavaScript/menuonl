<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurante extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'restaurantes';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'logo',
        'portada',
        'moneda',
        'idioma_default',
        'zona_horaria',
        'whatsapp_pedidos',
        'pedidos_activos',
        'mostrar_agotados',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'pedidos_activos' => 'boolean',
            'mostrar_agotados' => 'boolean',
        ];
    }

    /**
     * Users associated with this restaurant.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'restaurante_usuario')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Check if restaurant is active.
     */
    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }
}
