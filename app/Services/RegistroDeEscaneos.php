<?php

namespace App\Services;

use App\Models\CodigoQr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cuenta los escaneos de /q/{codigo} en un agregado por (código, día local del restaurante, tipo).
 *
 * - Solo guarda un contador: ni IP, ni user-agent, ni cookies, ni hora. El user-agent solo se lee en memoria para descartar
 *   vistas previas y bots obvios.
 * - Una sola sentencia atómica (upsert con `conteo + 1`), nunca leer-sumar-escribir.
 * - Corre después de enviar la respuesta (defer) y nunca la afecta: si el conteo falla se registra el error y listo.
 * - Los conteos son aproximados: se pierde alguno si el proceso muere entre la respuesta y la escritura, y el filtro de
 *   bots es deliberadamente simple.
 * - Cuenta para todos los planes; el plan solo decide quién ve las métricas (EscaneoDiarioPolicy).
 */
class RegistroDeEscaneos
{
    /**
     * Palabra completa "bot" (así CUBOT_X30 sí cuenta) o rastreadores y vistas previas conocidos.
     */
    public const string PATRON_DE_USER_AGENT_NO_HUMANO = '/\bbot\b|crawler|spider|preview/i';

    /**
     * Cabeceras con las que un navegador avisa que carga la página por adelantado, sin que la persona la abra.
     *
     * @var list<string>
     */
    private const array CABECERAS_DE_CARGA_ANTICIPADA = ['Sec-Purpose', 'Purpose', 'X-Moz'];

    /**
     * Solo un GET de una persona real cuenta: no HEAD, no cargas anticipadas, no bots ni vistas previas.
     */
    public function debeContarse(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        foreach (self::CABECERAS_DE_CARGA_ANTICIPADA as $cabecera) {
            if (preg_match('/prefetch|prerender/i', (string) $request->header($cabecera)) === 1) {
                return false;
            }
        }

        return preg_match(self::PATRON_DE_USER_AGENT_NO_HUMANO, (string) $request->userAgent()) !== 1;
    }

    /**
     * Cuenta el escaneo después de enviar la respuesta, si corresponde. Se llama solo cuando la redirección es válida
     * (código y restaurante activos), así que los 404 nunca se cuentan.
     */
    public function contarDespuesDeResponder(Request $request, CodigoQr $codigo): void
    {
        if (! $this->debeContarse($request)) {
            return;
        }

        $fila = [
            'restaurante_id' => (int) $codigo->restaurante_id,
            'codigo_qr_id' => (int) $codigo->getKey(),
            'fecha' => $this->diaLocal($codigo->restaurante?->zona_horaria),
            'tipo' => $codigo->tipo->value,
            'conteo' => 1,
        ];

        defer(fn () => $this->registrar($fila));
    }

    /**
     * Suma 1 al contador de la fila, creándola si no existe. Atómico en SQLite y MariaDB; no lanza excepciones.
     *
     * @param  array{restaurante_id: int, codigo_qr_id: int, fecha: string, tipo: string, conteo: int}  $fila
     */
    public function registrar(array $fila): bool
    {
        try {
            DB::table('escaneos_diarios')->upsert(
                [$fila],
                ['codigo_qr_id', 'fecha', 'tipo'],
                ['conteo' => DB::raw('conteo + 1')],
            );

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * El día (Y-m-d) en la zona del restaurante; si la zona guardada no es válida, en la de la aplicación.
     */
    public function diaLocal(?string $zonaHoraria): string
    {
        try {
            return Carbon::now($zonaHoraria ?: config('app.timezone'))->toDateString();
        } catch (Throwable) {
            return Carbon::now(config('app.timezone'))->toDateString();
        }
    }
}
