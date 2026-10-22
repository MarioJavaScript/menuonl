<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Los textos nuevos del panel viven en lang/es/panel.php (D10): todas las claves usadas existen y no hay jerga,
 * la palabra «carta» ni emojis.
 */
class PanelTextosDelDuenoTest extends TestCase
{
    public function test_toda_clave_panel_usada_en_el_codigo_existe_en_el_archivo_de_textos(): void
    {
        $faltantes = [];

        foreach ($this->archivosDelPanel() as $archivo) {
            preg_match_all("/(?:__|trans_choice)\\(\\s*'(panel\\.[a-z0-9_.]+)'/", (string) file_get_contents($archivo), $coincidencias);

            foreach ($coincidencias[1] as $clave) {
                if (! Lang::has($clave, 'es', false)) {
                    $faltantes[] = $clave.' ('.basename($archivo).')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($faltantes)));
    }

    public function test_los_textos_no_usan_jerga_carta_ni_emojis(): void
    {
        $textos = $this->aplanar(require base_path('lang/es/panel.php'));
        $this->assertNotEmpty($textos);

        foreach ($textos as $clave => $texto) {
            foreach (['slug', 'tema', 'plantilla_sitio', 'tenant', 'módulo', 'pedidos_activos'] as $jerga) {
                $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($jerga, '/').'\b/iu', $texto, "«{$jerga}» en {$clave}");
            }

            $this->assertDoesNotMatchRegularExpression('/\bcartas?\b/iu', $texto, "«carta» en {$clave}");
            $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $texto, "Emoji en {$clave}");
        }
    }

    public function test_los_grupos_de_navegacion_tienen_un_solo_nombre_en_todo_el_panel(): void
    {
        $this->assertSame(
            ['Mi menú', 'Apariencia', 'Información', 'Pedidos y reservas', 'QR y mesas'],
            array_values(__('panel.grupos')),
        );
    }

    /**
     * @return list<string>
     */
    private function archivosDelPanel(): array
    {
        $archivos = [];

        foreach ([app_path('Filament'), app_path('Services'), resource_path('views/filament')] as $carpeta) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $archivo) {
                if (in_array($archivo->getExtension(), ['php'], true)) {
                    $archivos[] = $archivo->getPathname();
                }
            }
        }

        return [...$archivos, app_path('Providers/Filament/AdminPanelProvider.php')];
    }

    /**
     * @param  array<string, mixed>  $arreglo
     * @return array<string, string>
     */
    private function aplanar(array $arreglo, string $prefijo = ''): array
    {
        $plano = [];

        foreach ($arreglo as $clave => $valor) {
            is_array($valor)
                ? $plano += $this->aplanar($valor, $prefijo.$clave.'.')
                : $plano[$prefijo.$clave] = (string) $valor;
        }

        return $plano;
    }
}
