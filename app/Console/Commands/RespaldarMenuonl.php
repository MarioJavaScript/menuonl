<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RespaldarMenuonl extends Command
{
    protected $signature = 'menuonl:backup';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['menuonl:respaldar'];

    protected $description = 'Respalda la base y las imágenes en un zip cifrado fuera del VPS y aplica la retención (RNF-06)';

    public function handle(RespaldoService $respaldos): int
    {
        $this->info('Iniciando respaldo de MENUONL (base de datos e imágenes)...');

        try {
            $respaldo = $respaldos->crear();
        } catch (Throwable $e) {
            return $this->fallar('El respaldo falló', $e);
        }

        $this->info("Respaldo {$respaldo['nombre']} subido ({$respaldo['bytes']} bytes, {$respaldo['archivos']} imágenes).");

        try {
            $borrados = $respaldos->aplicarRetencion();
        } catch (Throwable $e) {
            return $this->fallar('El respaldo se subió, pero la retención falló', $e);
        }

        $this->info('Retención aplicada: '.count($borrados).' respaldo(s) antiguo(s) borrado(s).');
        Log::info('menuonl:backup - Respaldo completado.', $respaldo + ['borrados' => $borrados]);

        return self::SUCCESS;
    }

    private function fallar(string $mensaje, Throwable $e): int
    {
        Log::error("menuonl:backup - {$mensaje}.", ['error' => $e->getMessage(), 'excepcion' => $e::class]);
        $this->error("{$mensaje}: {$e->getMessage()}");

        return self::FAILURE;
    }
}
