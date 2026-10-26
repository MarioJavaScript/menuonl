<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Filament\Pages\Componentes;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Componentes Blade x-mb.* (docs/integracion-dashboard.md, PR 3): glifo de plan, aviso, estado vacío, hoja y banner.
 * Se prueban renderizando las vistas de los componentes, no la página Componentes (su tabla necesita la extensión intl).
 */
class ComponentesMbTest extends TestCase
{
    public function test_el_nivel_de_cada_plan_sale_de_su_tabla_de_capacidades(): void
    {
        $this->assertSame(1, PlanRestaurante::SoloMenu->nivel());
        $this->assertSame(2, PlanRestaurante::SitioMenu->nivel());
        $this->assertSame(3, PlanRestaurante::Completo->nivel());
    }

    /**
     * @return array<string, array{0: PlanRestaurante, 1: int}>
     */
    public static function planes(): array
    {
        return [
            'completo' => [PlanRestaurante::Completo, 3],
            'sitio y menú' => [PlanRestaurante::SitioMenu, 2],
            'solo menú' => [PlanRestaurante::SoloMenu, 1],
        ];
    }

    #[DataProvider('planes')]
    public function test_el_glifo_llena_tantas_barras_como_el_nivel_y_muestra_el_nombre(PlanRestaurante $plan, int $nivel): void
    {
        $html = Blade::render('<x-mb.glifo-plan :plan="$plan" />', ['plan' => $plan]);

        $this->assertSame($nivel, substr_count($html, 'mb-plan__llena'));
        $this->assertSame(3, substr_count($html, '<i'));
        $this->assertStringContainsString('data-nivel="'.$nivel.'"', $html);
        $this->assertStringContainsString('<span>'.$plan->getLabel().'</span>', $html);
        $this->assertStringContainsString('class="mb-plan__glifo" aria-hidden="true"', $html);
        $this->assertStringNotContainsString('mb-sr', $html);
    }

    #[DataProvider('planes')]
    public function test_el_glifo_sin_texto_deja_el_nombre_solo_para_el_lector_de_pantalla(PlanRestaurante $plan, int $nivel): void
    {
        $html = Blade::render('<x-mb.glifo-plan :plan="$plan" sin-texto />', ['plan' => $plan]);

        $this->assertSame($nivel, substr_count($html, 'mb-plan__llena'));
        $this->assertStringContainsString('data-nivel="'.$nivel.'"', $html);
        $this->assertStringContainsString('<span class="mb-sr">Plan '.$plan->getLabel().'</span>', $html);
        $this->assertStringNotContainsString('<span>'.$plan->getLabel().'</span>', $html);
    }

    public function test_el_glifo_acepta_el_texto_del_plan_y_un_valor_desconocido_es_el_mas_restrictivo(): void
    {
        $this->assertStringContainsString('data-nivel="3"', Blade::render('<x-mb.glifo-plan plan="completo" />'));
        $this->assertStringContainsString('data-nivel="1"', Blade::render('<x-mb.glifo-plan plan="inventado" />'));
        $this->assertStringContainsString('data-nivel="1"', Blade::render('<x-mb.glifo-plan />'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rolesDelAviso(): array
    {
        return [
            'info' => ['info', 'status'],
            'exito' => ['exito', 'status'],
            'atencion' => ['atencion', 'status'],
            'neutro' => ['neutro', 'status'],
            'error' => ['error', 'alert'],
        ];
    }

    #[DataProvider('rolesDelAviso')]
    public function test_el_aviso_usa_alert_solo_en_error_y_status_en_el_resto(string $tono, string $rol): void
    {
        $html = Blade::render('<x-mb.aviso :tono="$tono" titulo="Título">Texto</x-mb.aviso>', ['tono' => $tono]);

        $this->assertStringContainsString('role="'.$rol.'"', $html);
        $this->assertStringContainsString('mb-aviso--'.$tono, $html);
        $this->assertStringContainsString('<strong>Título</strong>', $html);
        $this->assertStringContainsString('<span>Texto</span>', $html);
        $this->assertStringContainsString('class="mb-aviso__icono" aria-hidden="true"', $html);
    }

    public function test_el_aviso_puede_quitar_el_rol_y_un_tono_desconocido_es_info(): void
    {
        $sinRol = Blade::render('<x-mb.aviso tono="error" :anuncia="false">Texto</x-mb.aviso>');
        $this->assertStringNotContainsString('role=', $sinRol);

        $desconocido = Blade::render('<x-mb.aviso :tono="$raro">Texto</x-mb.aviso>', ['raro' => '"><script>']);
        $this->assertStringContainsString('mb-aviso--info', $desconocido);
        $this->assertStringNotContainsString('<script', $desconocido);
    }

    public function test_el_aviso_escapa_titulo_y_texto_y_admite_una_accion(): void
    {
        $html = Blade::render('<x-mb.aviso titulo="<b>x</b>">{{ $texto }}<x-slot:accion><a href="/ir">Ir</a></x-slot:accion></x-mb.aviso>', ['texto' => '<i>y</i>']);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;i&gt;y&lt;/i&gt;', $html);
        $this->assertStringContainsString('<a href="/ir">Ir</a>', $html);
    }

    public function test_el_vacio_usa_h2_por_defecto_y_acepta_h3_o_h4_pero_no_otro_nivel(): void
    {
        $this->assertStringContainsString('<h2 class="mb-vacio__titulo">Nada</h2>', Blade::render('<x-mb.vacio titulo="Nada" />'));
        $this->assertStringContainsString('<h4 class="mb-vacio__titulo">Nada</h4>', Blade::render('<x-mb.vacio titulo="Nada" :nivel="4" />'));
        $this->assertStringContainsString('<h2 class="mb-vacio__titulo">Nada</h2>', Blade::render('<x-mb.vacio titulo="Nada" :nivel="9" />'));
    }

    public function test_el_vacio_lleva_icono_decorativo_texto_opcional_y_accion(): void
    {
        $solo = Blade::render('<x-mb.vacio titulo="Nada" />');
        $this->assertStringContainsString('class="mb-vacio__icono" aria-hidden="true"', $solo);
        $this->assertStringNotContainsString('mb-vacio__texto', $solo);
        $this->assertStringNotContainsString('mb-vacio__accion', $solo);

        $completo = Blade::render('<x-mb.vacio titulo="Nada" texto="Agrega uno"><x-slot:accion><a href="/x">Agregar</a></x-slot:accion></x-mb.vacio>');
        $this->assertStringContainsString('<p class="mb-vacio__texto">Agrega uno</p>', $completo);
        $this->assertStringContainsString('<div class="mb-vacio__accion"><a href="/x">Agregar</a></div>', $completo);
    }

    public function test_la_ranura_de_arte_reemplaza_al_icono(): void
    {
        $html = Blade::render('<x-mb.vacio titulo="Nada"><x-slot:arte><img src="/a.svg" alt=""></x-slot:arte></x-mb.vacio>');

        $this->assertStringContainsString('<div class="mb-vacio__arte"><img src="/a.svg" alt=""></div>', $html);
        $this->assertStringNotContainsString('mb-vacio__icono', $html);
    }

    public function test_la_hoja_con_titulo_es_una_region_nombrada_por_su_encabezado(): void
    {
        $html = Blade::render('<x-mb.hoja titulo="Datos" descripcion="Tu información"><x-slot:acciones><a href="/e">Editar</a></x-slot:acciones>
Contenido</x-mb.hoja>');

        $this->assertSame(1, preg_match('/<section[^>]*aria-labelledby="(mb-hoja-[A-Za-z0-9]+)"/', $html, $coincidencia));
        $this->assertStringContainsString('<h2 id="'.$coincidencia[1].'" class="mb-hoja__titulo">Datos</h2>', $html);
        $this->assertStringContainsString('<p class="mb-hoja__descripcion">Tu información</p>', $html);
        $this->assertStringContainsString('<div class="mb-hoja__acciones"><a href="/e">Editar</a></div>', $html);
        $this->assertStringContainsString('Contenido</div>', $html);
    }

    public function test_la_hoja_sin_titulo_no_lleva_cabecera_ni_aria_labelledby_y_puede_quitar_el_relleno(): void
    {
        $html = Blade::render('<x-mb.hoja sin-relleno>Contenido</x-mb.hoja>');

        $this->assertStringNotContainsString('aria-labelledby', $html);
        $this->assertStringNotContainsString('mb-hoja__cab', $html);
        $this->assertStringContainsString('mb-hoja--sin-relleno', $html);
    }

    public function test_el_banner_es_una_region_con_nombre_accesible_y_ranura_de_accion(): void
    {
        $html = Blade::render('<x-mb.banner etiqueta="Contexto">Estás aquí<x-slot:accion><a href="/v">Volver</a></x-slot:accion></x-mb.banner>');

        $this->assertStringContainsString('role="region" aria-label="Contexto"', $html);
        $this->assertStringContainsString('<div class="mb-banner__texto">Estás aquí</div>', $html);
        $this->assertStringContainsString('<div class="mb-banner__accion"><a href="/v">Volver</a></div>', $html);
        $this->assertStringNotContainsString('mb-banner--atencion', $html);
    }

    public function test_el_banner_de_atencion_y_un_tono_desconocido(): void
    {
        $this->assertStringContainsString('mb-banner--atencion', Blade::render('<x-mb.banner etiqueta="Aviso" tono="atencion">x</x-mb.banner>'));
        $this->assertStringNotContainsString('mb-banner--atencion', Blade::render('<x-mb.banner etiqueta="Aviso" tono="otro">x</x-mb.banner>'));
    }

    public function test_el_banner_sin_etiqueta_se_rechaza(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('necesita una etiqueta accesible');

        Blade::render('<x-mb.banner>x</x-mb.banner>');
    }

    public function test_los_componentes_y_la_muestra_no_llevan_scripts_ni_atributos_on(): void
    {
        $html = View::make('filament.pages.componentes-mb')->render();

        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), 'Hay un atributo on* (manejador en línea).');
        $this->assertSame(0, preg_match('/javascript:/i', $html));

        foreach (glob(resource_path('views/components/mb/*.blade.php')) ?: [] as $archivo) {
            $codigo = (string) file_get_contents($archivo);
            $this->assertStringNotContainsString('<script', strtolower($codigo), basename($archivo));
            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $codigo), basename($archivo));
        }
    }

    public function test_la_muestra_dibuja_los_cinco_componentes_con_textos_del_idioma(): void
    {
        $html = View::make('filament.pages.componentes-mb')->render();

        $this->assertSame(3 + 3 + 1, substr_count($html, 'data-nivel="'), 'Tres glifos con texto, tres sin texto y uno en el banner.');
        foreach (['mb-plan', 'mb-aviso', 'mb-vacio', 'mb-hoja', 'mb-banner'] as $clase) {
            $this->assertStringContainsString('class="'.$clase, $html);
        }
        $this->assertStringContainsString(__('panel.componentes_mb.vacio.encabezado'), $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertSame(1, substr_count($html, 'role="alert"'), 'Solo el aviso de error interrumpe.');
    }

    public function test_los_textos_de_la_muestra_no_llevan_emojis_ni_la_palabra_carta(): void
    {
        $textos = __('panel.componentes_mb');
        $this->assertIsArray($textos);

        array_walk_recursive($textos, function (string $texto, string $clave): void {
            $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $texto), "Emoji en {$clave}.");
            $this->assertSame(0, preg_match('/\bcarta\b/iu', $texto), "\"carta\" en {$clave}.");
        });
    }

    public function test_la_pagina_de_componentes_solo_se_abre_en_local(): void
    {
        $this->assertFalse(Componentes::canAccess());

        $this->app['env'] = 'local';
        $this->assertTrue(Componentes::canAccess());
    }
}
