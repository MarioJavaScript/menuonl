<?php

namespace Tests\Feature;

use App\Filament\Pages\SitioWeb;
use App\Filament\Resources\Restaurantes\Pages\EditSitioRestaurante;
use App\Jobs\PurgarCacheRestauranteJob;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Etiqueta;
use App\Models\HistorialCambio;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\SitioConfiguracion;
use App\Models\SitioGaleria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Contenido editable del sitio (/sitio/{slug}): configuración y galería por restaurante, panel del propietario,
 * vista del superadmin y degradación de la página pública cuando falta contenido.
 */
class SitioWebContenidoTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $a;

    private Restaurante $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        Storage::fake('public');
        $this->a = $this->crearRestaurante('resto-a');
        $this->b = $this->crearRestaurante('resto-b');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosBasicos(array $extra = []): array
    {
        return [
            'hero_subtitulo' => 'Cocina de barrio',
            'historia_etiqueta' => 'Nuestra historia',
            'historia_titulo' => 'Una esquina con lumbre',
            'historia_parrafo_1' => 'Primer párrafo de prueba.',
            'historia_parrafo_2' => 'Segundo párrafo de prueba.',
            'reservas_activas' => true,
            ...$extra,
        ];
    }

    private function rutaPropia(Restaurante $restaurante, string $nombre = 'foto.webp'): string
    {
        $ruta = "restaurantes/{$restaurante->id}/sitio/{$nombre}";
        Storage::disk('public')->put($ruta, 'contenido');

        return $ruta;
    }

    private function configuracion(Restaurante $restaurante, array $atributos = []): SitioConfiguracion
    {
        return SitioConfiguracion::withoutGlobalScopes()->create([
            'restaurante_id' => $restaurante->id,
            ...$this->datosBasicos(),
            ...$atributos,
        ]);
    }

    private function sitio(Restaurante $restaurante): TestResponse
    {
        return $this->get(route('sitio.prototipo', ['slug' => $restaurante->slug]));
    }

    public function test_el_propietario_guarda_textos_e_imagenes_y_la_vista_publica_los_muestra(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(SitioWeb::class)
            ->set('data.hero_subtitulo', 'Cocina de barrio')
            ->set('data.hero_imagen', UploadedFile::fake()->image('portada.jpg', 1600, 900))
            ->set('data.hero_imagen_creditos', 'Foto: Autora, CC BY 4.0')
            ->set('data.historia_etiqueta', 'Nuestra historia')
            ->set('data.historia_titulo', 'Una esquina con lumbre')
            ->set('data.historia_parrafo_1', 'Primer párrafo de prueba.')
            ->set('data.historia_parrafo_2', 'Segundo párrafo de prueba.')
            ->set('data.historia_imagen', UploadedFile::fake()->image('historia.png', 800, 600))
            ->set('data.estadisticas', [['etiqueta' => 'Salsas', 'valor' => '6'], ['etiqueta' => 'Desde', 'valor' => '1998']])
            ->set('data.galeria', [
                'g1' => ['id' => null, 'imagen' => [], 'titulo' => 'Pastor con piña', 'texto_alternativo' => 'Plato con tacos al pastor', 'creditos' => 'Foto: Ana, CC BY 2.0', 'visible' => true],
            ])
            ->set('data.galeria.g1.imagen.u1', UploadedFile::fake()->image('g1.jpg', 800, 600))
            ->set('data.reservas_activas', true)
            ->call('save')
            ->assertHasNoErrors();

        $config = SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $this->a->id)->sole();
        $this->assertStringStartsWith("restaurantes/{$this->a->id}/sitio/", $config->hero_imagen);
        $this->assertStringEndsWith('.webp', $config->historia_imagen);
        Storage::disk('public')->assertExists($config->hero_imagen);

        $respuesta = $this->sitio($this->a)->assertOk();
        $respuesta->assertSee('Cocina de barrio');
        $respuesta->assertSee('Una esquina con lumbre');
        $respuesta->assertSee('Segundo párrafo de prueba.');
        $respuesta->assertSee('Pastor con piña');
        $respuesta->assertSee('Plato con tacos al pastor');
        $respuesta->assertSee('Foto: Ana, CC BY 2.0');
        $respuesta->assertSee('Foto: Autora, CC BY 4.0');
        $respuesta->assertSee('1998');
        $respuesta->assertSee(Storage::url($config->hero_imagen), false);
    }

    public function test_el_contenido_de_un_restaurante_no_aparece_en_el_sitio_de_otro(): void
    {
        $this->configuracion($this->b, ['historia_titulo' => 'Titulo privado de B']);

        $this->sitio($this->a)->assertOk()->assertDontSee('Titulo privado de B');
        $this->sitio($this->b)->assertOk()->assertSee('Titulo privado de B');
    }

    public function test_el_propietario_a_no_puede_ver_ni_editar_el_sitio_de_b_por_url_directa(): void
    {
        $propietarioA = $this->crearUsuario('propietario', $this->a);

        $this->actingAs($propietarioA)->get('/admin/resto-b/sitio-web')->assertNotFound();
        $this->actingAs($propietarioA)->get('/admin/resto-a/restaurantes/'.$this->b->getRouteKey().'/sitio')->assertForbidden();
    }

    public function test_el_propietario_edita_solo_su_restaurante_aunque_manipule_el_formulario(): void
    {
        $this->configuracion($this->b, ['historia_titulo' => 'Original de B']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(SitioWeb::class)
            ->set('data.historia_titulo', 'Titulo de A')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Original de B', SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $this->b->id)->value('historia_titulo'));
        $this->assertSame('Titulo de A', SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $this->a->id)->value('historia_titulo'));
    }

    public function test_el_editor_no_ve_la_pagina_de_sitio_web(): void
    {
        $editor = $this->crearUsuario('editor', $this->a);

        $this->actingAs($editor)->get('/admin/resto-a/sitio-web')->assertNotFound();

        $this->entrarAlPanel($editor, $this->a);
        Livewire::test(SitioWeb::class)->assertNotFound();
    }

    public function test_el_superadmin_ve_y_edita_el_sitio_de_cualquier_restaurante(): void
    {
        $this->configuracion($this->b, ['historia_titulo' => 'Titulo de B']);
        $superadmin = $this->crearUsuario('superadmin');

        $this->actingAs($superadmin)->get('/admin/resto-a/restaurantes/'.$this->b->getRouteKey().'/sitio')->assertOk();

        $this->entrarAlPanel($superadmin, $this->a);
        Livewire::test(EditSitioRestaurante::class, ['record' => $this->b->getRouteKey()])
            ->assertSet('data.historia_titulo', 'Titulo de B')
            ->set('data.historia_titulo', 'Editado por el superadmin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Editado por el superadmin', SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $this->b->id)->value('historia_titulo'));
        $this->assertNull(SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $this->a->id)->first());
    }

    public function test_html_y_scripts_en_los_textos_se_muestran_escapados(): void
    {
        $this->configuracion($this->a, [
            'historia_titulo' => '<script>alert(1)</script>',
            'historia_parrafo_1' => '<img src=x onerror=alert(2)>',
            'hero_subtitulo' => '<b onclick="x()">sub</b>',
            'historia_imagen' => $this->rutaPropia($this->a, 'h.webp'),
            'historia_imagen_creditos' => '<script>alert(3)</script>',
        ]);
        SitioGaleria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->a->id,
            'imagen' => $this->rutaPropia($this->a, 'g.webp'),
            'titulo' => '<script>alert(4)</script>',
            'texto_alternativo' => '"><script>alert(5)</script>',
            'creditos' => '<script>alert(6)</script>',
            'orden' => 0,
            'visible' => true,
        ]);

        $contenido = $this->sitio($this->a)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert', $contenido);
        $this->assertStringNotContainsString('<img src=x', $contenido);
        $this->assertStringNotContainsString('<b onclick', $contenido);
        $this->assertStringNotContainsString('"><script', $contenido);
        $this->assertStringContainsString(e('<script>alert(1)</script>'), $contenido);
        $this->assertStringContainsString(e('<script>alert(6)</script>'), $contenido);
    }

    public function test_rutas_de_otra_carpeta_u_otro_restaurante_o_con_traversal_son_rechazadas_por_el_modelo(): void
    {
        $rutasInvalidas = [
            "restaurantes/{$this->b->id}/sitio/ajena.webp",
            "restaurantes/{$this->a->id}/productos/de-otra-carpeta.webp",
            "restaurantes/{$this->a->id}/logos/logo.webp",
            "restaurantes/{$this->a->id}/sitio/../../{$this->b->id}/sitio/ajena.webp",
            'restaurantes/'.$this->a->id.'/sitio/',
            '../etc/passwd',
            "restaurantes/{$this->a->id}/sitio\\x.webp",
        ];

        foreach ($rutasInvalidas as $ruta) {
            try {
                $this->configuracion($this->a, ['hero_imagen' => $ruta]);
                $this->fail("Se aceptó la ruta: {$ruta}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('hero_imagen', $e->errors());
            }

            try {
                SitioGaleria::withoutGlobalScopes()->create([
                    'restaurante_id' => $this->a->id, 'imagen' => $ruta, 'titulo' => 'x', 'texto_alternativo' => 'texto alternativo', 'orden' => 0, 'visible' => true,
                ]);
                $this->fail("Se aceptó la ruta en la galería: {$ruta}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('imagen', $e->errors());
            }
        }

        $this->assertSame(0, SitioConfiguracion::withoutGlobalScopes()->count());
        $this->assertSame(0, SitioGaleria::withoutGlobalScopes()->count());
    }

    public function test_livewire_rechaza_una_ruta_ajena_en_portada_y_galeria(): void
    {
        $rutaDeB = $this->rutaPropia($this->b, 'secreta.webp');
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(SitioWeb::class)
            ->set('data.hero_imagen', ['uuid-1' => $rutaDeB])
            ->call('save')
            ->assertHasErrors();

        Livewire::test(SitioWeb::class)
            ->set('data.galeria', ['g1' => ['id' => null, 'imagen' => ['uuid-2' => $rutaDeB], 'titulo' => 'x', 'texto_alternativo' => 'texto alternativo', 'creditos' => null, 'visible' => true]])
            ->call('save')
            ->assertHasErrors();

        $this->assertSame(0, SitioConfiguracion::withoutGlobalScopes()->whereNotNull('hero_imagen')->count());
        $this->assertSame(0, SitioGaleria::withoutGlobalScopes()->count());
        Storage::disk('public')->assertExists($rutaDeB);
    }

    public function test_svg_archivo_que_no_es_imagen_y_exceso_de_tamano_se_rechazan_con_mensaje_en_espanol(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $pdf = UploadedFile::fake()->createWithContent('doc.jpg', '%PDF-1.4 no soy una imagen');
        config(['menuonl.imagenes.max_bytes' => 20 * 1024]);
        $grande = UploadedFile::fake()->image('grande.jpg', 3000, 3000)->size(300);

        foreach ([$svg, $pdf, $grande] as $archivo) {
            $prueba = Livewire::test(SitioWeb::class)
                ->set('data.hero_imagen', $archivo)
                ->call('save')
                ->assertHasErrors();

            $mensaje = collect($prueba->errors()->all())->implode(' ');
            $this->assertDoesNotMatchRegularExpression('/\b(must be|The \w+ field|may not)\b/i', $mensaje, "Mensaje en inglés: {$mensaje}");
            $this->assertNotSame('', trim($mensaje));
        }

        $this->assertSame(0, SitioConfiguracion::withoutGlobalScopes()->whereNotNull('hero_imagen')->count());
        $this->assertSame([], Storage::disk('public')->allFiles("restaurantes/{$this->a->id}"));
    }

    public function test_el_texto_de_ayuda_de_tamano_coincide_con_la_configuracion_real(): void
    {
        config(['menuonl.imagenes.max_bytes' => 3 * 1024 * 1024]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(SitioWeb::class)->assertSee('3 MB')->assertDontSee('5 MB');
    }

    public function test_la_galeria_admite_como_maximo_doce_imagenes(): void
    {
        foreach (range(1, 12) as $i) {
            SitioGaleria::withoutGlobalScopes()->create(['restaurante_id' => $this->a->id, 'imagen' => $this->rutaPropia($this->a, "g{$i}.webp"), 'titulo' => "F{$i}", 'texto_alternativo' => "Descripcion {$i}", 'orden' => $i, 'visible' => true]);
        }

        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        $prueba = Livewire::test(SitioWeb::class);
        $filas = $prueba->get('data.galeria');
        $this->assertCount(12, $filas);

        $filas['nueva'] = ['id' => null, 'imagen' => ['u1' => $this->rutaPropia($this->a, 'g1.webp')], 'titulo' => 'Foto 13', 'texto_alternativo' => 'Descripcion 13', 'creditos' => null, 'visible' => true];
        $prueba->set('data.galeria', $filas)->call('save')->assertHasErrors(['data.galeria']);
        $this->assertSame(12, SitioGaleria::withoutGlobalScopes()->count());

        $this->expectException(ValidationException::class);
        SitioGaleria::withoutGlobalScopes()->create(['restaurante_id' => $this->a->id, 'imagen' => $this->rutaPropia($this->a, 'g13.webp'), 'titulo' => 'F13', 'texto_alternativo' => 'Descripcion 13', 'orden' => 13, 'visible' => true]);
    }

    public function test_los_limites_de_texto_y_de_estadisticas_se_validan(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(SitioWeb::class)
            ->set('data.historia_titulo', str_repeat('a', 81))
            ->set('data.historia_parrafo_1', str_repeat('b', 601))
            ->set('data.historia_etiqueta', str_repeat('c', 31))
            ->set('data.estadisticas', [['etiqueta' => str_repeat('d', 31), 'valor' => str_repeat('e', 31)]])
            ->call('save')
            ->assertHasErrors(['data.historia_titulo', 'data.historia_parrafo_1', 'data.historia_etiqueta']);

        Livewire::test(SitioWeb::class)
            ->set('data.estadisticas', array_fill(0, 4, ['etiqueta' => 'x', 'valor' => '1']))
            ->call('save')
            ->assertHasErrors();

        $this->assertNull(SitioConfiguracion::withoutGlobalScopes()->first());
    }

    public function test_un_restaurante_sin_contenido_ni_imagenes_responde_200_y_se_ve_completo(): void
    {
        $contenido = $this->sitio($this->a)->assertOk()->getContent();

        $this->assertStringContainsString($this->a->nombre, $contenido);
        $this->assertStringContainsString('id="menu"', $contenido);
        $this->assertStringContainsString('id="contacto"', $contenido);
        $this->assertStringContainsString('id="reservar"', $contenido);
        $this->assertStringNotContainsString('id="galeria"', $contenido);
        $this->assertStringNotContainsString('id="historia"', $contenido);
        $this->assertStringNotContainsString('<img', $contenido);
        $this->assertStringNotContainsString('Créditos de las fotos', $contenido);
    }

    public function test_sin_fotos_la_historia_y_la_portada_se_degradan_a_bloques_tipograficos(): void
    {
        $this->configuracion($this->a, ['reservas_activas' => false]);

        $contenido = $this->sitio($this->a)->assertOk()->getContent();

        $this->assertStringContainsString('Una esquina con lumbre', $contenido);
        $this->assertStringContainsString('id="historia"', $contenido);
        $this->assertStringNotContainsString('<img', $contenido);
        $this->assertStringNotContainsString('id="reservar"', $contenido);
        $this->assertStringNotContainsString('<dl', $contenido, 'Sin estadísticas la lista se oculta.');
    }

    public function test_cambiar_el_contenido_purga_la_cache_y_queda_en_la_auditoria(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);
        Queue::fake();

        Livewire::test(SitioWeb::class)
            ->set('data.historia_titulo', 'Titulo auditado')
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertPushed(PurgarCacheRestauranteJob::class, fn ($job): bool => $job->restauranteId === $this->a->id);

        $registro = HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $this->a->id)->where('entidad', 'sitio_configuracion')->latest('id')->first();
        $this->assertNotNull($registro);
        $this->assertSame('Titulo auditado', $registro->cambios['historia_titulo']['despues']);

        Queue::fake();
        Livewire::test(SitioWeb::class)->set('data.historia_titulo', 'Otro titulo')->call('save');
        Queue::assertPushed(PurgarCacheRestauranteJob::class);
        $this->assertTrue(HistorialCambio::withoutGlobalScopes()->where('entidad', 'sitio_configuracion')->where('accion', 'actualizar')->exists());
    }

    public function test_el_seeder_demo_carga_textos_e_imagenes_por_el_servicio_webp_con_creditos(): void
    {
        Restaurante::withTrashed()->forceDelete();
        $this->seed(DatabaseSeeder::class);

        $demo = Restaurante::where('slug', 'la-esquina')->sole();
        $config = SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $demo->id)->sole();

        $this->assertNotEmpty($config->historia_titulo);
        $this->assertCount(3, $config->estadisticas);
        $this->assertStringStartsWith("restaurantes/{$demo->id}/sitio/", $config->hero_imagen);
        $this->assertNotEmpty($config->hero_imagen_creditos);
        Storage::disk('public')->assertExists($config->hero_imagen);

        $galeria = SitioGaleria::withoutGlobalScopes()->where('restaurante_id', $demo->id)->orderBy('orden')->get();
        $this->assertCount(6, $galeria);
        $galeria->each(function (SitioGaleria $foto) use ($demo): void {
            $this->assertStringStartsWith("restaurantes/{$demo->id}/sitio/", $foto->imagen);
            $this->assertStringContainsString('CC BY', $foto->creditos);
            Storage::disk('public')->assertExists($foto->imagen);
        });

        $this->seed(DatabaseSeeder::class);
        $this->assertSame(6, SitioGaleria::withoutGlobalScopes()->where('restaurante_id', $demo->id)->count());
    }

    private function productoConFoto(?string $imagen, string $nombre = 'Taco con foto', array $extra = []): Producto
    {
        $categoria = Categoria::factory()->create(['restaurante_id' => $this->a->id, 'visible' => true, 'orden' => 1]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Tacos']);
        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id, 'categoria_id' => $categoria->id, 'precio' => 100, 'disponible' => true, 'visible' => true, 'orden' => 1, 'imagen' => $imagen, ...$extra]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $producto;
    }

    public function test_la_carta_muestra_la_miniatura_del_producto_con_la_url_del_storage_real(): void
    {
        Storage::disk('public')->put("restaurantes/{$this->a->id}/productos/taco.webp", 'x');
        $producto = $this->productoConFoto("restaurantes/{$this->a->id}/productos/taco.webp");

        $contenido = $this->sitio($this->a)->assertOk()->getContent();

        $this->assertStringContainsString('src="'.e(Storage::url($producto->imagen)).'" alt="Taco con foto" width="96" height="96" loading="lazy" decoding="async"', $contenido);
    }

    public function test_un_producto_sin_foto_no_genera_img_ni_hueco(): void
    {
        $this->productoConFoto(null, 'Taco sin foto');

        $contenido = $this->sitio($this->a)->assertOk()->getContent();

        $this->assertStringContainsString('Taco sin foto', $contenido);
        $this->assertStringNotContainsString('<img', $contenido);
        $this->assertStringNotContainsString('items-start gap-4', $contenido);
    }

    public function test_el_nombre_con_script_se_escapa_en_el_alt_de_la_miniatura(): void
    {
        $this->productoConFoto("restaurantes/{$this->a->id}/productos/x.webp", '"><script>alert(9)</script>');

        $contenido = $this->sitio($this->a)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(9)', $contenido);
        $this->assertStringContainsString('alt="'.e('"><script>alert(9)</script>').'"', $contenido);
    }

    public function test_con_foto_se_conservan_el_precio_tachado_y_las_etiquetas(): void
    {
        $producto = $this->productoConFoto("restaurantes/{$this->a->id}/productos/o.webp", 'Taco oferta', ['precio' => 120, 'precio_oferta' => 99]);
        $producto->etiquetas()->attach(Etiqueta::create(['codigo' => 'vegano', 'icono' => 'leaf'])->id);

        $respuesta = $this->sitio($this->a)->assertOk();

        $respuesta->assertSee('<s class="', false);
        $respuesta->assertSee('99.00');
        $respuesta->assertSee('#vegano');
        $respuesta->assertSee('<img', false);
    }

    public function test_cambiar_la_foto_del_platillo_purga_la_cache_y_el_sitio_muestra_la_nueva(): void
    {
        $vieja = "restaurantes/{$this->a->id}/productos/vieja.webp";
        $nueva = "restaurantes/{$this->a->id}/productos/nueva.webp";
        Storage::disk('public')->put($vieja, 'x');
        $producto = $this->productoConFoto($vieja);

        $this->get(route('menu.publico', ['slug' => $this->a->slug]))->assertOk();
        $this->sitio($this->a)->assertOk()->assertSee(Storage::url($vieja), false);

        Queue::fake();
        $producto->update(['imagen' => $nueva]);

        Queue::assertPushed(PurgarCacheRestauranteJob::class, fn ($job): bool => $job->restauranteId === $this->a->id);
        $this->sitio($this->a)->assertOk()->assertSee(Storage::url($nueva), false)->assertDontSee(Storage::url($vieja), false);
    }
}
