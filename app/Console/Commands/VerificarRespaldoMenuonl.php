<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerificarRespaldoMenuonl extends Command
{
    protected $signature = 'menuonl:backup-verify {archivo? : Nombre del respaldo en el disco (por defecto, el más reciente)}';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['menuonl:verificar-respaldo'];

    protected $description = 'Descarga un respaldo, lo descifra en un directorio temporal y comprueba que se pueda restaurar';

    public function handle(RespaldoService $respaldos): int
    {
        try {
            $resultado = $respaldos->verificar($this->argument('archivo'));
        } catch (Throwable $e) {
            Log::error('menuonl:backup-verify - La verificación falló.', ['error' => $e->getMessage(), 'excepcion' => $e::class]);
            $this->error("La verificación falló: {$e->getMessage()}");

            return self::FAILURE;
        }

        $tablas = $resultado['tablas'] === null ? '' : ", {$resultado['tablas']} tablas";
        $this->info("Respaldo {$resultado['nombre']} verificado: se descifra y se puede restaurar ({$resultado['archivos']} imágenes{$tablas}).");

        return self::SUCCESS;
    }
}
