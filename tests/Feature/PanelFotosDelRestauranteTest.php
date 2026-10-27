<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Filament\Pages\FotosDelRestaurante;
use App\Models\GaleriaImagen;
use App\Models\Restaurante;
use App\Services\AparienciaSitio;
use App\Services\Catalogos;
use App\Services\WebpImageService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Fotos de tu restaurante: la galería del sitio en el panel del dueño.
 */
class PanelFotosDelRestauranteTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function foto(string $nombre = 'local.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 1000, 1250);
    }

    /**
     * Foto de la galería con archivos reales en el disco (principal y copias).
     */
    private function fotoConArchivos(Restaurante $restaurante, int $orden = 1): GaleriaImagen
    {
        $ruta = app(WebpImageService::class)->procesar($this->foto(), $restaurante->id, 'galeria');

        return GaleriaImagen::withoutGlobalScopes()->create(['restaurante_id' => $restaurante->id, 'imagen' => $ruta, 'orden' => $orden]);
    }

    private function entrarComo(string $rol, string $slug = 'la-esquina'): Restaurante
    {
        $restaurante = $this->crearRestaurante($slug);
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        return $restaurante;
    }

    public function test_el_propietario_ve_la_pantalla_en_apariencia_con_el_contador(): void
    {
        $restaurante = $this->entrarComo('propietario');
        $this->fotoConArchivos($restaurante);

        $this->get(FotosDelRestaurante::getUrl())
            ->assertOk()
            ->assertSee('Fotos de tu restaurante')
            ->assertSee('Apariencia')
            ->assertSee('Tienes 1 de 12 fotos.')
            ->assertSee('Subir fotos');
    }

    public function test_sube_varias_fotos_validas_en_varios_formatos_y_quedan_en_webp_dentro_del_restaurante(): void
    {
        $restaurante = $this->entrarComo('propietario');

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [
                $this->foto('a.jpg'),
                UploadedFile::fake()->image('b.png', 900, 900),
            ]])
            ->assertHasNoActionErrors()
            ->assertNotified('Se subieron 2 fotos.');

        $fotos = GaleriaImagen::orderBy('orden')->get();
        $this->assertCount(2, $fotos);
        $this->assertSame([1, 2], $fotos->pluck('orden')->all());

        foreach ($fotos as $foto) {
            $this->assertStringStartsWith("restaurantes/{$restaurante->id}/galeria/", $foto->imagen);
            $this->assertStringEndsWith('.webp', $foto->imagen);
            Storage::disk('public')->assertExists($foto->imagen);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function archivosHostiles(): array
    {
        return [
            'svg con script' => ['logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'php con extension de imagen' => ['foto.jpg', '<?php echo 1; ?>'],
            'texto cualquiera' => ['foto.webp', 'esto no es una imagen'],
        ];
    }

    #[DataProvider('archivosHostiles')]
    public function test_un_archivo_hostil_se_rechaza_con_mensaje_claro_y_sin_guardar_nada(string $nombre, string $contenido): void
    {
        $this->entrarComo('propietario');

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [UploadedFile::fake()->createWithContent($nombre, $contenido)]])
            ->assertNotified('No se pudo subir ninguna foto.');

        $this->assertSame(0, GaleriaImagen::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_una_imagen_real_con_codigo_php_pegado_no_se_guarda_y_se_explica(): void
    {
        $this->entrarComo('propietario');
        $buena = $this->foto('buena.png');
        $poliglota = UploadedFile::fake()->createWithContent('truco.png', file_get_contents($buena->getRealPath()).'
<?php echo 1; ?>');

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [$poliglota]])
            ->assertNotified('No se pudo subir ninguna foto.');

        $this->assertSame(0, GaleriaImagen::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_una_foto_cortada_se_avisa_sin_error_500_y_las_buenas_si_se_guardan(): void
    {
        $this->entrarComo('propietario');
        $buena = $this->foto('buena.jpg');
        $completa = (string) file_get_contents($buena->getRealPath());
        $cortada = UploadedFile::fake()->createWithContent('cortada.jpg', substr($completa, 0, (int) (strlen($completa) / 2)));

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [$buena, $cortada]])
            ->assertNotified('Se subió 1 foto, pero otras no se pudieron subir.');

        $this->assertSame(1, GaleriaImagen::count());
        // Principal y dos copias (480 y 960): la foto cortada no deja nada
        $this->assertCount(3, Storage::disk('public')->allFiles());
    }

    public function test_una_foto_mas_pesada_que_el_limite_se_rechaza_con_el_mensaje_del_limite(): void
    {
        $this->entrarComo('propietario');
        config(['menuonl.imagenes.max_bytes' => 1024]);

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [$this->foto()]])
            ->assertHasActionErrors();

        $this->assertSame(0, GaleriaImagen::count());
    }

    public function test_no_deja_pasar_del_maximo_de_fotos(): void
    {
        config(['menuonl.imagenes.max_galeria' => 2]);
        $restaurante = $this->entrarComo('propietario');
        $this->fotoConArchivos($restaurante, 1);

        Livewire::test(FotosDelRestaurante::class)
            ->assertSee('Tienes 1 de 2 fotos.')
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [$this->foto('a.jpg'), $this->foto('b.jpg')]])
            ->assertHasActionErrors();

        $this->assertSame(1, GaleriaImagen::count());

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [$this->foto('c.jpg')]])
            ->assertNotified('Se subió 1 foto.');

        $this->assertSame(2, GaleriaImagen::count());

        Livewire::test(FotosDelRestaurante::class)
            ->assertSee('Tienes 2 de 2 fotos.')
            ->assertActionDisabled(TestAction::make('subir')->table());
    }

    public function test_reordena_arrastrando_y_el_sitio_publico_usa_ese_orden(): void
    {
        $restaurante = $this->entrarComo('propietario');
        $primera = $this->fotoConArchivos($restaurante, 1);
        $segunda = $this->fotoConArchivos($restaurante, 2);
        $tercera = $this->fotoConArchivos($restaurante, 3);

        Livewire::test(FotosDelRestaurante::class)
            ->call('reorderTable', [(string) $tercera->id, (string) $primera->id, (string) $segunda->id]);

        $this->assertSame(
            [$tercera->imagen, $primera->imagen, $segunda->imagen],
            GaleriaImagen::orderBy('orden')->orderBy('id')->pluck('imagen')->all()
        );
    }

    public function test_quitar_una_foto_borra_el_registro_y_sus_archivos_del_disco(): void
    {
        $restaurante = $this->entrarComo('propietario');
        $foto = $this->fotoConArchivos($restaurante);
        $servicio = app(WebpImageService::class);
        Storage::disk('public')->assertExists($foto->imagen);
        Storage::disk('public')->assertExists($servicio->rutaVariante($foto->imagen, 480));

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('quitar')->table($foto))
            ->assertNotified('La foto se quitó.');

        $this->assertDatabaseMissing('galeria_imagenes', ['id' => $foto->id]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_guarda_la_descripcion_opcional_y_la_vacia_se_guarda_como_nula(): void
    {
        $restaurante = $this->entrarComo('propietario');
        $foto = $this->fotoConArchivos($restaurante);

        Livewire::test(FotosDelRestaurante::class)
            ->call('updateTableColumnState', 'descripcion', (string) $foto->id, '  Terraza al atardecer ');
        $this->assertSame('Terraza al atardecer', $foto->fresh()->descripcion);

        Livewire::test(FotosDelRestaurante::class)
            ->call('updateTableColumnState', 'descripcion', (string) $foto->id, '');
        $this->assertNull($foto->fresh()->descripcion);

        Livewire::test(FotosDelRestaurante::class)
            ->call('updateTableColumnState', 'descripcion', (string) $foto->id, str_repeat('a', 121));
        $this->assertNull($foto->fresh()->descripcion);
    }

    public function test_el_gerente_puede_usar_la_pantalla(): void
    {
        $restaurante = $this->entrarComo('gerente');
        $foto = $this->fotoConArchivos($restaurante);

        Livewire::test(FotosDelRestaurante::class)
            ->callAction(TestAction::make('subir')->table(), ['fotos' => [$this->foto()]])
            ->assertNotified('Se subió 1 foto.')
            ->callAction(TestAction::make('quitar')->table($foto));

        $this->assertSame(1, GaleriaImagen::count());
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_quien_no_tiene_permiso_no_ve_la_pantalla_ni_el_enlace(string $rol): void
    {
        $this->entrarComo($rol);

        $this->assertFalse(FotosDelRestaurante::canAccess());
        $this->get(FotosDelRestaurante::getUrl())->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinPermiso(): array
    {
        return ['editor' => ['editor'], 'recepcion' => ['recepcion']];
    }

    public function test_un_usuario_de_otro_restaurante_no_entra(): void
    {
        $this->crearRestaurante('ajeno');
        $this->entrarComo('propietario');

        $this->get('/admin/ajeno/fotos')->assertNotFound();
    }

    public function test_un_dueno_no_ve_ni_reordena_ni_borra_ni_edita_fotos_de_otro_restaurante(): void
    {
        $ajeno = $this->crearRestaurante('ajeno');
        $fotoAjena = $this->fotoConArchivos($ajeno, 5);
        $propio = $this->entrarComo('propietario');
        $fotoPropia = $this->fotoConArchivos($propio, 1);

        $pantalla = Livewire::test(FotosDelRestaurante::class)
            ->assertSee('Tienes 1 de 12 fotos.')
            ->assertCanSeeTableRecords([$fotoPropia])
            ->assertCanNotSeeTableRecords([$fotoAjena]);

        $pantalla->call('reorderTable', [(string) $fotoAjena->id, (string) $fotoPropia->id]);
        $pantalla->call('updateTableColumnState', 'descripcion', (string) $fotoAjena->id, 'hackeada');

        try {
            $pantalla->callAction(TestAction::make('quitar')->table($fotoAjena));
        } catch (\Throwable) {
            // Un id ajeno no se encuentra: no debe borrar nada
        }

        $fotoAjena = GaleriaImagen::withoutGlobalScopes()->findOrFail($fotoAjena->id);
        $this->assertSame(5, $fotoAjena->orden);
        $this->assertNull($fotoAjena->descripcion);
        Storage::disk('public')->assertExists($fotoAjena->imagen);
        $this->assertSame(1, GaleriaImagen::count());
    }

    public function test_las_plantillas_con_galeria_coinciden_con_las_vistas_del_sitio_publico(): void
    {
        $conGaleria = [];

        foreach (array_keys(Catalogos::plantillasSitio()) as $plantilla) {
            $vista = (string) file_get_contents(resource_path("views/sitio/{$plantilla}.blade.php"));

            if (str_contains($vista, '@if ($galeria)')) {
                $conGaleria[] = $plantilla;
            }
        }

        $this->assertSame($conGaleria, AparienciaSitio::PLANTILLAS_CON_GALERIA, 'La lista de plantillas con galería no coincide con las vistas del sitio.');
        $this->assertSame(['a', 'b', 'c'], AparienciaSitio::PLANTILLAS_CON_GALERIA);
        $this->assertSame(['Claro y sencillo', 'Oscuro elegante', 'Clásico y cálido'], AparienciaSitio::nombresConGaleria());
    }

    #[DataProvider('plantillasConGaleria')]
    public function test_con_un_diseno_que_muestra_la_galeria_no_hay_aviso(string $plantilla): void
    {
        $restaurante = $this->entrarComo('propietario');
        $restaurante->update(['sitio_plantilla' => $plantilla]);

        $this->get(FotosDelRestaurante::getUrl())
            ->assertOk()
            ->assertDontSee('no muestra estas fotos en tu sitio');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasConGaleria(): array
    {
        return array_combine(AparienciaSitio::PLANTILLAS_CON_GALERIA, array_map(fn (string $p): array => [$p], AparienciaSitio::PLANTILLAS_CON_GALERIA));
    }

    #[DataProvider('plantillasSinGaleria')]
    public function test_con_un_diseno_sin_galeria_el_propietario_ve_el_aviso_con_los_disenos_y_el_enlace(string $plantilla): void
    {
        $restaurante = $this->entrarComo('propietario');
        $restaurante->update(['sitio_plantilla' => $plantilla]);

        $this->get(FotosDelRestaurante::getUrl())
            ->assertOk()
            ->assertSee('Tu diseño actual ('.AparienciaSitio::nombre($plantilla).') no muestra estas fotos en tu sitio. Se ven en los diseños Claro y sencillo, Oscuro elegante y Clásico y cálido.')
            ->assertSee('Cambiar el diseño en Apariencia')
            ->assertSee(Apariencia::getUrl());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasSinGaleria(): array
    {
        $sin = array_values(array_diff(array_keys(Catalogos::plantillasSitio()), AparienciaSitio::PLANTILLAS_CON_GALERIA));

        return array_combine($sin, array_map(fn (string $p): array => [$p], $sin));
    }

    public function test_el_gerente_ve_el_aviso_pero_sin_enlace_a_una_pantalla_que_no_puede_abrir(): void
    {
        $restaurante = $this->entrarComo('gerente');
        $restaurante->update(['sitio_plantilla' => 'h']);

        $this->assertFalse(Apariencia::canAccess());
        $this->get(FotosDelRestaurante::getUrl())
            ->assertOk()
            ->assertSee('no muestra estas fotos en tu sitio')
            ->assertDontSee('Cambiar el diseño en Apariencia')
            ->assertDontSee(Apariencia::getUrl());
    }

    public function test_el_superadmin_ve_el_aviso_con_el_enlace(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['sitio_plantilla' => 'f']);
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $this->get(FotosDelRestaurante::getUrl())
            ->assertOk()
            ->assertSee('Tu diseño actual (Bar y coctelería) no muestra estas fotos en tu sitio')
            ->assertSee('Cambiar el diseño en Apariencia');
    }

    public function test_quien_no_tiene_permiso_no_ve_el_aviso_porque_no_entra(): void
    {
        $restaurante = $this->entrarComo('editor');
        $restaurante->update(['sitio_plantilla' => 'h']);

        $this->get(FotosDelRestaurante::getUrl())->assertForbidden();
    }
}
