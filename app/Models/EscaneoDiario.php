<?php

namespace App\Models;

use App\Enums\TipoCodigoAcceso;
use App\Traits\BelongsToRestaurante;
use Database\Factories\EscaneoDiarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escaneos de un código QR o NFC en un día local del restaurante: un solo contador, nada que identifique a la persona.
 * Se escribe con RegistroDeEscaneos (upsert atómico) y se lee con MetricasDeEscaneos. Sin Auditable: no se audita cada conteo.
 */
class EscaneoDiario extends Model
{
    /** @use HasFactory<EscaneoDiarioFactory> */
    use BelongsToRestaurante, HasFactory;

    protected $table = 'escaneos_diarios';

    public $timestamps = false;

    protected $fillable = [
        'restaurante_id',
        'codigo_qr_id',
        'fecha',
        'tipo',
        'conteo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'tipo' => TipoCodigoAcceso::class,
            'conteo' => 'integer',
        ];
    }

    /**
     * El código escaneado.
     */
    public function codigoQr(): BelongsTo
    {
        return $this->belongsTo(CodigoQr::class, 'codigo_qr_id');
    }
}
