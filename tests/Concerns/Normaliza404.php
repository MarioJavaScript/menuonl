<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * Compara páginas 404 que deben ser indistinguibles entre sí (docs/despliegue.md: no revelar si un código o un slug
 * existe, está inactivo, suspendido o eliminado).
 *
 * La vista `menu.no-disponible` enlaza "Reintentar lectura" a la dirección que pidió el propio visitante, así que esa
 * dirección es lo único que puede variar entre las respuestas. Se sustituye por un marcador antes de comparar; el marcador
 * debe aparecer exactamente una vez en cada cuerpo, para que la normalización no pueda ocultar otra diferencia.
 */
trait Normaliza404
{
    protected const string MARCADOR_URL_PEDIDA = '{URL_PEDIDA}';

    /**
     * Cabeceras que tampoco pueden delatar el motivo del 404.
     *
     * @var list<string>
     */
    private const array CABECERAS_DEL_404 = ['Cache-Control', 'Content-Type', 'Content-Security-Policy'];

    /**
     * El cuerpo con la dirección pedida (`$ruta`, relativa a la aplicación) reemplazada por el marcador.
     */
    protected function cuerpoSinLaUrlPedida(TestResponse $respuesta, string $ruta): string
    {
        $url = e(url($ruta));
        $cuerpo = (string) $respuesta->getContent();

        $this->assertSame(1, substr_count($cuerpo, $url), "La dirección pedida ({$ruta}) debe aparecer exactamente una vez en el 404.");

        return str_replace($url, self::MARCADOR_URL_PEDIDA, $cuerpo);
    }

    /**
     * Todas las respuestas (ruta pedida => respuesta) son 404 con el mismo cuerpo salvo la dirección pedida y con las mismas
     * cabeceras relevantes.
     *
     * @param  array<string, TestResponse>  $respuestas
     */
    protected function assertMismo404SalvoLaUrlPedida(array $respuestas, string $mensaje = ''): void
    {
        $this->assertGreaterThanOrEqual(2, count($respuestas), 'Hacen falta al menos dos respuestas para compararlas.');

        $referencia = null;
        $rutaDeReferencia = null;

        foreach ($respuestas as $ruta => $respuesta) {
            $respuesta->assertNotFound();
            $cuerpo = $this->cuerpoSinLaUrlPedida($respuesta, (string) $ruta);

            if ($referencia === null) {
                $referencia = ['cuerpo' => $cuerpo, 'cabeceras' => $this->cabecerasDelCuerpo404($respuesta)];
                $rutaDeReferencia = $ruta;

                continue;
            }

            $this->assertSame($referencia['cuerpo'], $cuerpo, trim($mensaje." El 404 de {$ruta} difiere del de {$rutaDeReferencia}."));
            $this->assertSame($referencia['cabeceras'], $this->cabecerasDelCuerpo404($respuesta), trim($mensaje." Las cabeceras del 404 de {$ruta} difieren de las de {$rutaDeReferencia}."));
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function cabecerasDelCuerpo404(TestResponse $respuesta): array
    {
        $cabeceras = [];

        foreach (self::CABECERAS_DEL_404 as $nombre) {
            $cabeceras[$nombre] = $respuesta->headers->get($nombre);
        }

        return $cabeceras;
    }
}
