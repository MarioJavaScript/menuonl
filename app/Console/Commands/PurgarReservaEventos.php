<?php

namespace App\Console\Commands;

use App\Models\ReservaEvento;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PurgarReservaEventos extends Command
{
    protected $signature = 'menuonl:purgar-reserva-eventos';

    protected $description = 'Borra el historial de reservas (quién confirmó, canceló o movió) más viejo que la retención (config menuonl.reservas.eventos_retencion_meses, 12 meses)';

    private const int LOTE = 1000;

    public function handle(): int
    {
        $meses = max(1, (int) config('menuonl.reservas.eventos_retencion_meses'));
        $corte = Carbon::now(config('app.timezone'))->subMonthsNoOverflow($meses);
        $borrados = 0;

        do {
            $ids = ReservaEvento::withoutGlobalScopes()->where('created_at', '<', $corte)->limit(self::LOTE)->pluck('id');
            $borrados += $ids->isEmpty() ? 0 : ReservaEvento::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        } while ($ids->count() === self::LOTE);

        $this->info("Eventos de reservas anteriores a {$corte->toDateTimeString()} ({$meses} meses de retención): {$borrados} fila(s) borrada(s).");
        Log::info('menuonl:purgar-reserva-eventos - Purga completada.', ['corte' => $corte->toDateTimeString(), 'borrados' => $borrados]);

        return self::SUCCESS;
    }
}
