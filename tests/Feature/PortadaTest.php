<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Services\Catalogos;
use App\Services\MarcadoresPendientes;
use App\Services\PoliticaDeContenido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Portada pública (/) y páginas legales: sin sesión ni cookies, CSP propia, caché pública y solo recursos del propio dominio.
 */
class PortadaTest extends TestCase
{
    use RefreshDatabase;

    private const string NUMERO = '5210000000000';

    protected function setUp(): void
    {
        parent::setUp();

        config(['menuonl.soporte_whatsapp' => self::NUMERO, 'menuonl.demo_url' => null, 'app.url' => 'https://menubly.test']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function paginas(): array
    {
        return ['portada' => ['/'], 'privacidad' => ['/privacidad'], 'términos' => ['/terminos']];
    }

    #[DataProvider('paginas')]
    public function test_responde_200_sin_cookies_ni_sesion_y_con_cabeceras_propias(string $ruta): void
    {
        $antes = DB::table('sessions')->count();

        $respuesta = $this->get($ruta)->assertOk();

        $this->assertSame([], $respuesta->headers->getCookies());
        $this->assertSame($antes, DB::table('sessions')->count());
        $respuesta->assertHeader('Content-Security-Policy', PoliticaDeContenido::LANDING);

        $cache = (string) $respuesta->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=300', $cache);
        $this->assertStringContainsString('stale-while-revalidate=600', $cache);
        $this->assertStringNotContainsString('private', $cache);
    }

    #[DataProvider('paginas')]
    public function test_no_trae_codigo_en_linea_ni_recursos_externos_ni_sintaxis_sin_renderizar(string $ruta): void
    {
        $html = (string) $this->get($ruta)->assertOk()->getContent();

        preg_match_all('#<script\b([^>]*)>#i', $html, $scripts);
        foreach ($scripts[1] as $atributos) {
            $this->assertTrue(str_contains($atributos, 'src=') || str_contains($atributos, 'type="application/ld+json"'), "Script en línea: {$atributos}");
        }

        $this->assertSame(0, preg_match('/\sstyle=|<style\b|\son[a-z]+=/i', $html), 'Sin estilos ni manejadores en línea.');
        $this->assertSame(0, preg_match('/@js|@json|JSON\.parse|wire:|\{\{|@php|@include/', $html), 'Sintaxis sin renderizar o Livewire.');
        $this->assertStringContainsString('<meta name="color-scheme" content="light">', $html);

        preg_match_all('#(?:src|href|action)="(https?://[^"]+)"#i', $html, $urls);
        foreach ($urls[1] as $url) {
            $this->assertTrue(
                str_starts_with($url, url('/')) || str_starts_with($url, 'https://menubly.test') || str_starts_with($url, 'https://wa.me/'.self::NUMERO.'?text='),
                "Recurso externo: {$url}",
            );
        }
    }

    public function test_los_enlaces_de_whatsapp_usan_el_numero_configurado_y_rel_seguro(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('#<a\b[^>]*href="https://wa\.me/[^"]*"[^>]*>#i', $html, $enlaces);
        $this->assertNotEmpty($enlaces[0]);

        foreach ($enlaces[0] as $enlace) {
            $this->assertStringContainsString('https://wa.me/'.self::NUMERO.'?text=', $enlace);
            $this->assertStringContainsString('rel="noopener noreferrer"', $enlace);
        }

        $this->assertStringNotContainsString('target="_blank"', $html);
    }

    #[DataProvider('numerosInvalidos')]
    public function test_sin_whatsapp_valido_no_se_pinta_ningun_boton_de_whatsapp(?string $numero): void
    {
        config(['menuonl.soporte_whatsapp' => $numero]);

        $this->get('/')->assertOk()->assertDontSee('wa.me', false)->assertDontSee('WhatsApp</span>', false);
        $this->get('/privacidad')->assertOk()->assertDontSee('wa.me', false);
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function numerosInvalidos(): array
    {
        return ['sin definir' => [null], 'vacío' => [''], 'corto' => ['123456789'], 'largo' => ['1234567890123456'], 'texto' => ['sin-numero']];
    }

    public function test_el_pie_trae_el_enlace_discreto_de_entrada_al_panel_y_a_las_paginas_legales(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.url('/admin').'">Entrar</a>', false)
            ->assertSee('href="'.route('privacidad').'"', false)
            ->assertSee('href="'.route('terminos').'"', false);
    }

    public function test_las_paginas_legales_son_de_relleno_y_no_se_indexan(): void
    {
        foreach (['/privacidad', '/terminos'] as $ruta) {
            $this->get($ruta)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex">', false)
                ->assertSee('[PÁGINA POR ESCRIBIR]');
        }

        $this->get('/')->assertDontSee('noindex', false);
    }

    public function test_el_menu_de_ejemplo_usa_la_variable_si_es_ruta_relativa_o_url_propia(): void
    {
        foreach (['/la-esquina/menu', 'https://menubly.test/la-esquina/menu'] as $valida) {
            config(['menuonl.demo_url' => $valida]);
            $this->get('/')->assertOk()->assertSee('href="'.$valida.'"', false);
        }
    }

    #[DataProvider('demosInvalidas')]
    public function test_el_menu_de_ejemplo_rechaza_urls_de_otro_dominio_o_raras(string $invalida): void
    {
        config(['menuonl.demo_url' => $invalida]);

        $this->get('/')->assertOk()->assertDontSee($invalida, false)->assertDontSee('Ver un menú de ejemplo');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function demosInvalidas(): array
    {
        return [
            'otro dominio' => ['https://malo.example/menu'],
            'mismo prefijo' => ['https://menubly.test.malo.example/menu'],
            'sin esquema de red' => ['//malo.example/menu'],
            'javascript' => ['javascript:alert(1)'],
            'con usuario' => ['https://usuario@menubly.test/menu'],
            'con espacios' => ['/uno dos'],
            'con barra invertida' => ['/\\malo.example'],
        ];
    }

    public function test_sin_variable_el_menu_de_ejemplo_sale_del_restaurante_activo(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Ver un menú de ejemplo');

        $restaurante = Restaurante::factory()->create(['slug' => 'la-esquina', 'estado' => 'activo']);

        $this->get('/')->assertOk()->assertSee('Ver un menú de ejemplo')->assertSee(route('menu.publico', ['slug' => $restaurante->slug]), false);
    }

    public function test_privacidad_y_terminos_son_slugs_reservados_y_no_los_toma_un_restaurante(): void
    {
        $this->assertContains('privacidad', Catalogos::slugsReservados());
        $this->assertContains('terminos', Catalogos::slugsReservados());

        Restaurante::factory()->create(['slug' => 'privacidad-del-sabor', 'estado' => 'activo']);
        $this->get('/privacidad-del-sabor')->assertOk();
        $this->get('/privacidad')->assertOk()->assertSee('Aviso de privacidad');
    }

    public function test_la_lista_de_marcadores_visibles_esta_completa_y_se_detectan_en_la_portada(): void
    {
        $this->assertSame(
            ['[PRECIO]', '[CONFIRMAR]', '[CAPTURA DEL MENÚ]', '[CAPTURA DEL PANEL]', '[TESTIMONIO]', '[NOMBRE Y RESTAURANTE]', '[PÁGINA POR ESCRIBIR]'],
            MarcadoresPendientes::LISTA,
        );

        $pendientes = (new MarcadoresPendientes)->enPaginasPublicas();

        $this->assertSame(['/', '/privacidad', '/terminos'], array_keys($pendientes));
        $this->assertSame(['[PÁGINA POR ESCRIBIR]'], $pendientes['/privacidad']);

        $html = (string) $this->get('/')->getContent();
        foreach (array_diff(MarcadoresPendientes::LISTA, ['[PÁGINA POR ESCRIBIR]']) as $marcador) {
            $this->assertStringContainsString($marcador, $html, "La portada ya no muestra {$marcador}: quítalo de la lista si se resolvió.");
        }
    }

    public function test_cada_seccion_sale_una_sola_vez_en_orden_y_sin_ids_repetidos(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('#<h2 id="([^"]+)"#', $html, $titulos);
        $this->assertSame(
            ['titulo-pasos', 'titulo-incluye', 'titulo-planes', 'titulo-panel', 'titulo-preguntas', 'titulo-cierre'],
            $titulos[1],
        );

        preg_match_all('#\sid="([^"]+)"#', $html, $ids);
        $repetidos = array_keys(array_filter(array_count_values($ids[1]), fn (int $veces): bool => $veces > 1));
        $this->assertSame([], $repetidos, 'IDs repetidos en la portada.');

        $this->assertSame(1, substr_count($html, 'class="stage"'), 'El celular de la portada solo va en la portada.');
    }

    public function test_los_tres_planes_tienen_su_lista_y_su_boton(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('#<article class="plan"[^>]*>(.*?)</article>#s', $html, $planes);
        $this->assertCount(3, $planes[1]);

        $esperados = [
            'Completo' => 'Métricas de escaneos por mesa y de reservas',
            'Sitio y menú' => 'Para cambiar tu menú, escríbenos por WhatsApp',
            'Solo menú' => 'No incluye panel: los cambios se piden por WhatsApp',
        ];

        foreach (array_keys($esperados) as $i => $nombre) {
            $plan = $planes[1][$i];
            $this->assertStringContainsString(">{$nombre}</h3>", $plan);
            $this->assertStringContainsString($esperados[$nombre], $plan);
            $this->assertStringContainsString("<span class=\"sr-only\">: {$nombre}</span>", $plan);
        }
    }

    public function test_el_logo_de_la_cabecera_es_un_img_directo_sin_hidden_ni_lazy(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match_all('#<img\b[^>]*brand/menubly-horizontal-[a-z]+\.svg[^>]*>#', $html, $logos);
        $this->assertCount(2, $logos[0], 'Cabecera y pie.');

        foreach ($logos[0] as $logo) {
            $this->assertStringNotContainsString('hidden', $logo);
            $this->assertStringNotContainsString('loading=', $logo);
        }
    }
}
