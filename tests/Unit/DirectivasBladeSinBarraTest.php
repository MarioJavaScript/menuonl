<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Una directiva Blade con barra invertida al inicio de la línea (por ejemplo `\if`) no se interpreta: se imprime como
 * texto en la pantalla. Esta prueba recorre todas las vistas y falla con archivo y línea si encuentra alguna.
 */
class DirectivasBladeSinBarraTest extends TestCase
{
    private const string DIRECTIVAS = 'if|endif|else|elseif|foreach|endforeach|forelse|endforelse|empty|endempty|isset|endisset'
        .'|unless|endunless|can|endcan|auth|endauth|guest|endguest|php|endphp|include|extends|section|endsection|yield'
        .'|push|endpush|props|aware';

    /**
     * @return list<string>
     */
    private static function vistas(): array
    {
        $raiz = dirname(__DIR__, 2).'/resources/views';
        $vistas = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if ($archivo->isFile() && str_ends_with($archivo->getFilename(), '.blade.php')) {
                $vistas[] = $archivo->getPathname();
            }
        }

        sort($vistas);

        return $vistas;
    }

    /**
     * Líneas (archivo:número) que empiezan, ignorando espacios, con una barra invertida y una directiva.
     *
     * @return list<string>
     */
    private static function hallazgos(string $contenido, string $nombre): array
    {
        $encontrados = [];

        foreach (preg_split('/\R/', $contenido) ?: [] as $indice => $linea) {
            if (preg_match('/^\s*\\\\('.self::DIRECTIVAS.')\b/', $linea) === 1) {
                $encontrados[] = $nombre.':'.($indice + 1).'  '.trim($linea);
            }
        }

        return $encontrados;
    }

    public function test_ninguna_vista_tiene_una_directiva_blade_con_barra_invertida_al_inicio(): void
    {
        $vistas = self::vistas();
        $this->assertNotEmpty($vistas, 'No se encontraron vistas para revisar.');

        $raiz = str_replace('\\', '/', dirname(__DIR__, 2)).'/';
        $errores = [];

        foreach ($vistas as $vista) {
            $nombre = str_replace($raiz, '', str_replace('\\', '/', $vista));
            array_push($errores, ...self::hallazgos((string) file_get_contents($vista), $nombre));
        }

        $this->assertSame([], $errores, "Directivas Blade con barra invertida (se muestran como texto):\n".implode("\n", $errores));
    }

    public function test_el_detector_reconoce_el_error_y_no_marca_codigo_legitimo(): void
    {
        $malas = ['    \\if ($a)', "\t\\endif", '\\foreach ($x as $y)', '  \\props([])'];
        foreach ($malas as $linea) {
            $this->assertCount(1, self::hallazgos($linea, 'v.blade.php'), "No detectó: {$linea}");
        }

        $buenas = ['    @if ($a)', '<a href="\\if">', 'const re = /\\d+\\s*if/;', '{{ __("ruta\\\\if") }}', 'texto \\iframe', '    \\n', '@@if'];
        foreach ($buenas as $linea) {
            $this->assertSame([], self::hallazgos($linea, 'v.blade.php'), "Falso positivo: {$linea}");
        }

        $this->assertSame(['v.blade.php:3  \\endif'], self::hallazgos("a\nb\n  \\endif\nc", 'v.blade.php'));
    }
}
