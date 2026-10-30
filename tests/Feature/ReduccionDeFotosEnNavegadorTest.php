<?php

namespace Tests\Feature;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Filament\Pages\Apariencia;
use App\Filament\Pages\FotosDelRestaurante;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Models\Restaurante;
use App\Services\WebpImageService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * El navegador reduce la foto antes de subirla (lado largo, sin ampliar y sin recortar); el servidor sigue validando
 * y reconstruyendo todo como si el navegador no hubiera hecho nada.
 */
class ReduccionDeFotosEnNavegadorTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function perfiles(): array
    {
        return [
            'logotipo' => ['logotipo', 1600],
            'portada' => ['portada', 2400],
            'platillo' => ['platillo', 2400],
            'galeria' => ['galeria', 2400],
        ];
    }

    #[DataProvider('perfiles')]
    public function test_cada_subida_reduce_al_lado_acordado_sin_ampliar_ni_recortar(string $perfil, int $lado): void
    {
        $campo = ImagenRestauranteUpload::make('imagen', $perfil);

        $this->assertSame((string) $lado, $campo->getAutomaticallyResizeImagesWidth());
        $this->assertSame((string) $lado, $campo->getAutomaticallyResizeImagesHeight());
        $this->assertSame('contain', $campo->getAutomaticallyResizeImagesMode());
        $this->assertFalse((bool) $campo->evaluate(fn (): bool => $this->leerPropiedad($campo, 'shouldAutomaticallyUpscaleImagesWhenResizing')));
        $this->assertFalse($campo->shouldAutomaticallyCropImagesToAspectRatio());
        $this->assertNull($campo->getAutomaticallyCropImagesAspectRatio());
    }

    #[DataProvider('perfiles')]
    public function test_los_limites_y_tipos_de_la_subida_no_cambian(string $perfil): void
    {
        $campo = ImagenRestauranteUpload::make('imagen', $perfil);

        $this->assertSame((int) ceil((int) config('menuonl.imagenes.max_bytes') / 1024), $campo->getMaxSize());
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], $campo->getAcceptedFileTypes());
    }

    public function test_el_lado_del_navegador_siempre_cabe_en_los_topes_del_servidor(): void
    {
        $this->assertSame(array_keys(WebpImageService::PERFILES), array_keys(ImagenRestauranteUpload::LADO_EN_NAVEGADOR));

        foreach (ImagenRestauranteUpload::LADO_EN_NAVEGADOR as $perfil => $lado) {
            $this->assertLessThanOrEqual(WebpImageService::MAX_LADO, $lado, $perfil);
            $this->assertLessThanOrEqual(WebpImageService::MAX_PIXELES, $lado * $lado, $perfil);
        }
    }

    public function test_la_ayuda_dice_el_tope_real_y_que_la_foto_se_reduce_sola(): void
    {
        foreach (array_keys(WebpImageService::PERFILES) as $perfil) {
            $ayuda = app(WebpImageService::class)->ayudaDeSubida($perfil);

            $this->assertStringContainsString('Máximo 10 MB por foto.', $ayuda);
            $this->assertStringContainsString('Se reduce sola antes de subir.', $ayuda);
        }
    }

    public function test_el_html_de_apariencia_pide_reducir_logo_y_portada(): void
    {
        $this->entrarComoPropietario();

        $html = $this->htmlDe(Livewire::test(Apariencia::class));

        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesWidth: '1600'"));
        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesHeight: '1600'"));
        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesWidth: '2400'"));
        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesHeight: '2400'"));
        $this->assertSame(2, substr_count($html, "automaticallyResizeImagesMode: 'contain'"));
        $this->assertSame(2, substr_count($html, 'shouldAutomaticallyUpscaleImagesWhenResizing: false'));
        $this->assertSame(2, substr_count($html, 'automaticallyCropImagesAspectRatio: null'));
    }

    public function test_la_subida_de_la_galeria_pide_reducir_a_2400(): void
    {
        $this->entrarComoPropietario();

        Livewire::test(FotosDelRestaurante::class)
            ->mountAction(TestAction::make('subir')->table())
            ->assertSchemaComponentExists('fotos', 'mountedActionSchema0', function (FileUpload $campo): bool {
                $html = html_entity_decode($campo->toHtml(), ENT_QUOTES | ENT_HTML5);

                return $campo->getAutomaticallyResizeImagesWidth() === '2400'
                    && $campo->getAutomaticallyResizeImagesHeight() === '2400'
                    && $campo->getAutomaticallyResizeImagesMode() === 'contain'
                    && ! $campo->shouldAutomaticallyCropImagesToAspectRatio()
                    && str_contains($html, "automaticallyResizeImagesWidth: '2400'")
                    && str_contains($html, 'shouldAutomaticallyUpscaleImagesWhenResizing: false');
            });
    }

    public function test_el_html_del_platillo_pide_reducir_a_2400(): void
    {
        $this->entrarComoPropietario();

        $html = $this->htmlDe(Livewire::test(CreateProducto::class));

        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesWidth: '2400'"));
        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesHeight: '2400'"));
        $this->assertSame(1, substr_count($html, "automaticallyResizeImagesMode: 'contain'"));
        $this->assertSame(1, substr_count($html, 'shouldAutomaticallyUpscaleImagesWhenResizing: false'));
        $this->assertSame(1, substr_count($html, 'automaticallyCropImagesAspectRatio: null'));
    }

    public function test_el_servidor_rechaza_una_foto_gigante_aunque_el_navegador_hubiera_podido_reducirla(): void
    {
        Storage::fake('public');
        $gigante = UploadedFile::fake()->image('gigante.png', WebpImageService::MAX_LADO + 1, 10);

        try {
            app(WebpImageService::class)->procesar($gigante, 1, 'platillo');
            $this->fail('El servidor debió rechazar una foto de más de '.WebpImageService::MAX_LADO.' px por lado.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_el_servidor_sigue_reconstruyendo_la_foto_y_guardando_solo_webp(): void
    {
        Storage::fake('public');

        $ruta = app(WebpImageService::class)->procesar(UploadedFile::fake()->image('foto.jpg', 1200, 900), 1, 'platillo');

        $this->assertStringEndsWith('.webp', $ruta);
        $this->assertStringStartsWith('restaurantes/1/productos/', $ruta);
        $this->assertSame(['webp'], array_values(array_unique(array_map(
            fn (string $archivo): string => pathinfo($archivo, PATHINFO_EXTENSION),
            Storage::disk('public')->allFiles(),
        ))));
    }

    /**
     * @return array{0: Restaurante}
     */
    private function entrarComoPropietario(): array
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        return [$restaurante];
    }

    /**
     * HTML que pinta Livewire para el componente, con los atributos ya sin escapar: ahí va la configuración de cada campo.
     */
    private function htmlDe(Testable $componente): string
    {
        return html_entity_decode($componente->html(), ENT_QUOTES | ENT_HTML5);
    }

    private function leerPropiedad(object $objeto, string $propiedad): mixed
    {
        return (fn () => $this->{$propiedad})->call($objeto);
    }
}
