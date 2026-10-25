<?php

namespace App\Console\Commands;

use App\Models\EscaneoDiario;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PurgarEscaneosDiarios extends Command
{
    protected $signature = 'menuonl:purgar-escaneos';

    protected $description = 'Borra los conteos diarios de escaneos QR/NFC más viejos que la retención (config menuonl.metricas.retencion_dias, 400 días)';

    private const int LOTE = 1000;

    public function handle(): int
    {
        $dias = max(31, (int) config('menuonl.metricas.retencion_dias'));
        $corte = Carbon::now(config('app.timezone'))->subDays($dias)->toDateString();
        $borradas = 0;

        do {
            $ids = EscaneoDiario::withoutGlobalScopes()->where('fecha', '<', $corte)->limit(self::LOTE)->pluck('id');
            $borradas += $ids->isEmpty() ? 0 : EscaneoDiario::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        } while ($ids->count() === self::LOTE);

        $this->info("Escaneos anteriores a {$corte} ({$dias} días de retención): {$borradas} fila(s) borrada(s).");
        Log::info('menuonl:purgar-escaneos - Purga completada.', ['corte' => $corte, 'borradas' => $borradas]);

        return self::SUCCESS;
    }
}
