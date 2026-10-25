<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use App\Services\RestauracionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Restaura un respaldo (base e imágenes) de forma segura. Ver docs/despliegue.md, punto 4.
 *
 * Por defecto NO toca la base activa ni las imágenes en uso: restaura en una base/archivo/carpeta nuevos. Reemplazar lo
 * activo exige opciones explícitas y la confirmación escrita del nombre de la base. Antes de tocar nada se descarga,
 * descifra y verifica el respaldo (suma SHA-256 incluida). Nunca imprime credenciales.
 */
class RestaurarRespaldoMenuonl extends Command
{
    protected $signature = 'menuonl:restaurar-respaldo
        {nombre? : Nombre del respaldo (por defecto, el más reciente)}
        {--base-destino= : Base (MariaDB) o archivo (SQLite) donde restaurar; por defecto una nueva, distinta de la activa}
        {--sobrescribir-base-activa : Reemplaza la base activa (la anterior se conserva con otro nombre)}
        {--carpeta-imagenes= : Carpeta donde dejar las imágenes; por defecto storage/app/restauraciones/<respaldo>/restaurantes}
        {--sobrescribir-imagenes : Reemplaza storage/app/public/restaurantes (la carpeta anterior se conserva)}
        {--sin-imagenes : No restaura las imágenes}
        {--confirmo : Confirma sin preguntar (obligatorio si no hay terminal interactiva)}
        {--confirmo-base= : Con --sobrescribir-base-activa y sin terminal: el nombre exacto de la base activa}';

    protected $description = 'Restaura un respaldo (base e imágenes) sin tocar lo activo salvo que se pida de forma explícita';

    public function handle(RespaldoService $respaldos, RestauracionService $restauracion): int
    {
        $preparado = null;
        $imagenes = null;

        try {
            $sobrescribirBase = (bool) $this->option('sobrescribir-base-activa');
            $sobrescribirImagenes = (bool) $this->option('sobrescribir-imagenes');
            $nombreSolicitado = $this->argument('nombre') !== null ? (string) $this->argument('nombre') : null;

            // 1. Se valida todo lo que se pueda ANTES de descargar o tocar nada.
            $explicito = $this->option('base-destino') ?: ($sobrescribirBase ? $restauracion->baseActiva() : null);

            if ($explicito !== null) {
                $restauracion->validarDestino((string) $explicito, $sobrescribirBase);
            }

            // 2. Se descarga, descifra y verifica el respaldo. Si algo falla aquí, no se ha tocado nada.
            $this->info('Verificando la integridad del respaldo...');
            $preparado = $respaldos->prepararRestauracion($nombreSolicitado);
            $this->line("Respaldo {$preparado['nombre']} verificado ({$preparado['archivos']} imágenes).");

            $destino = (string) ($explicito ?? $restauracion->destinoPorDefecto($preparado['nombre']));
            $restauracion->validarDestino($destino, $sobrescribirBase);

            $incluyeImagenes = ! $this->option('sin-imagenes') && $preparado['imagenes'] !== null;
            $carpetaImagenes = $sobrescribirImagenes
                ? storage_path('app/public/restaurantes')
                : (string) ($this->option('carpeta-imagenes') ?: storage_path('app/restauraciones/'.basename($preparado['nombre'], '.zip').'/restaurantes'));

            // 3. Resumen y confirmación explícita.
            $this->mostrarPlan($preparado['nombre'], $destino, $sobrescribirBase, $incluyeImagenes, $carpetaImagenes, $sobrescribirImagenes, $restauracion);

            if (! $this->confirmado($sobrescribirBase, $restauracion->baseActiva())) {
                $this->warn('Restauración cancelada. No se modificó nada.');

                return self::FAILURE;
            }

            // 4. Las imágenes se copian primero junto a su lugar final (sin tocar las que están en uso)...
            if ($incluyeImagenes) {
                $imagenes = $restauracion->prepararImagenes((string) $preparado['imagenes'], $carpetaImagenes, $sobrescribirImagenes);
            }

            // 5. ...luego la base (importación a una base temporal y publicación atómica)...
            $this->info($restauracion->restaurarBase($preparado, $destino, $sobrescribirBase));

            // 6. ...y al final se publican las imágenes con un rename.
            if ($imagenes !== null) {
                $this->info($restauracion->publicarImagenes($imagenes));
                $imagenes = null;
            }
        } catch (Throwable $e) {
            if ($imagenes !== null) {
                $restauracion->descartarImagenes($imagenes);
            }

            Log::error('menuonl:restaurar-respaldo - La restauración falló.', ['error' => $e->getMessage(), 'excepcion' => $e::class]);
            $this->error("La restauración falló: {$e->getMessage()}");
            $this->line('No se dejó la base a medias: lo que estaba activo sigue como estaba.');

            return self::FAILURE;
        } finally {
            if ($preparado !== null) {
                $respaldos->limpiar($preparado['raiz']);
            }
        }

        Log::info('menuonl:restaurar-respaldo - Restauración completada.', [
            'respaldo' => $preparado['nombre'],
            'sobrescribio_base_activa' => $sobrescribirBase,
            'sobrescribio_imagenes' => $sobrescribirImagenes,
        ]);
        $this->info('Restauración terminada. Revisa el resultado antes de apuntar la aplicación a la base restaurada.');

        return self::SUCCESS;
    }

    private function mostrarPlan(string $respaldo, string $destino, bool $sobrescribirBase, bool $incluyeImagenes, string $carpeta, bool $sobrescribirImagenes, RestauracionService $restauracion): void
    {
        $this->newLine();
        $this->line("  Respaldo:        {$respaldo}");
        $this->line('  Base de destino: '.$destino.($sobrescribirBase ? '  (ES LA BASE ACTIVA: se reemplazará; la anterior se conserva con otro nombre)' : '  (distinta de la activa; la activa no se toca)'));
        $this->line('  Imágenes:        '.($incluyeImagenes ? $carpeta.($sobrescribirImagenes ? '  (REEMPLAZA las imágenes en uso; las anteriores se conservan)' : '') : 'no se restauran'));
        $this->newLine();
    }

    private function confirmado(bool $sobrescribirBase, string $baseActiva): bool
    {
        if (! $this->input->isInteractive()) {
            if (! $this->option('confirmo')) {
                $this->error('Sin terminal interactiva hay que confirmar con --confirmo.');

                return false;
            }

            if ($sobrescribirBase && ! hash_equals($baseActiva, (string) $this->option('confirmo-base'))) {
                $this->error('Para reemplazar la base activa sin terminal pasa --confirmo-base=<nombre exacto de la base activa>.');

                return false;
            }

            return true;
        }

        if (! $this->option('confirmo') && ! $this->confirm('¿Continuar con la restauración?', false)) {
            return false;
        }

        if ($sobrescribirBase) {
            $escrito = (string) $this->ask("Vas a REEMPLAZAR la base activa. Escribe su nombre exacto para confirmar ({$baseActiva})");

            if (! hash_equals($baseActiva, $escrito)) {
                $this->error('El nombre no coincide.');

                return false;
            }
        }

        return true;
    }
}
