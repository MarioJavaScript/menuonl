<?php

namespace Tests\Feature;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Rules\SubidaRecibida;
use App\Services\WebpImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Cada fallo de una subida de imagen tiene su propio mensaje en español, sin rutas ni detalles internos:
 * demasiado grande (nuestro límite), límite del servidor, subida incompleta, tipo no permitido y archivo temporal.
 */
class ErroresDeSubidaDeImagenTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $restaurante;

    private string $temporal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        Storage::fake('public');

        $this->restaurante = $this->crearRestaurante('subidas-fijo');
        $this->temporal = tempnam(sys_get_temp_dir(), 'sub');
        file_put_contents($this->temporal, 'contenido-cualquiera');
    }

    protected function tearDown(): void
    {
        @unlink($this->temporal);
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function servicio(): WebpImageService
    {
        return app(WebpImageService::class);
    }

    private function conCodigo(int $codigo): UploadedFile
    {
        return new UploadedFile($this->temporal, 'foto.jpg', 'image/jpeg', $codigo, true);
    }

    private function subirAlPanel(?UploadedFile $archivo): TestResponse
    {
        $usuario = $this->crearUsuario('propietario', $this->restaurante);

        return $this->actingAs($usuario)->post(
            "/api/v1/panel/{$this->restaurante->slug}/ajustes/hero",
            $archivo === null ? [] : ['imagen' => $archivo],
            ['Accept' => 'application/json'],
        );
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function erroresDePhp(): array
    {
        return [
            'upload_max_filesize' => [UPLOAD_ERR_INI_SIZE, 'mensajeLimiteDelServidor'],
            'MAX_FILE_SIZE del formulario' => [UPLOAD_ERR_FORM_SIZE, 'mensajeLimiteDelServidor'],
            'subida cortada' => [UPLOAD_ERR_PARTIAL, 'mensajeSubidaIncompleta'],
            'sin carpeta temporal' => [UPLOAD_ERR_NO_TMP_DIR, 'mensajeArchivoTemporal'],
            'no se pudo escribir' => [UPLOAD_ERR_CANT_WRITE, 'mensajeArchivoTemporal'],
            'extensión de PHP' => [UPLOAD_ERR_EXTENSION, 'mensajeArchivoTemporal'],
        ];
    }

    #[DataProvider('erroresDePhp')]
    public function test_la_api_del_panel_explica_cada_error_de_php(int $codigo, string $metodo): void
    {
        Log::spy();

        $respuesta = $this->subirAlPanel($this->conCodigo($codigo));

        $respuesta->assertUnprocessable();
        $this->assertSame($this->servicio()->{$metodo}(), $respuesta->json('errors.imagen.0'));
        Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje, array $contexto): bool => $contexto === ['codigo_php' => $codigo])->once();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('erroresDePhp')]
    public function test_ningun_mensaje_expone_rutas_ni_detalles_internos(int $codigo, string $metodo): void
    {
        $mensaje = (string) $this->subirAlPanel($this->conCodigo($codigo))->json('errors.imagen.0');

        $this->assertNotSame('', $mensaje);
        $this->assertStringNotContainsString(sys_get_temp_dir(), $mensaje);
        $this->assertDoesNotMatchRegularExpression('#[\\\\/]|UPLOAD_ERR|php\.ini|upload_max|post_max|tmp#i', $mensaje);
    }

    public function test_si_el_cuerpo_supera_post_max_size_la_api_responde_con_el_mensaje_del_servidor(): void
    {
        $usuario = $this->crearUsuario('propietario', $this->restaurante);

        $respuesta = $this->actingAs($usuario)->call(
            'POST',
            "/api/v1/panel/{$this->restaurante->slug}/ajustes/hero",
            [],
            [],
            [],
            ['CONTENT_LENGTH' => 5_000_000_000, 'HTTP_ACCEPT' => 'application/json'],
        );

        $respuesta->assertStatus(413);
        $this->assertSame($this->servicio()->mensajeLimiteDelServidor(), $respuesta->json('errors.imagen.0'));
        $this->assertSame($this->servicio()->mensajeLimiteDelServidor(), $respuesta->json('message'));
    }

    public function test_si_el_cuerpo_supera_post_max_size_en_la_subida_temporal_de_livewire_responde_422_con_el_mensaje(): void
    {
        $respuesta = $this->call(
            'POST',
            '/livewire-prueba/upload-file',
            [],
            [],
            [],
            ['CONTENT_LENGTH' => 5_000_000_000, 'HTTP_ACCEPT' => 'application/json'],
        );

        $respuesta->assertStatus(422);
        $this->assertSame($this->servicio()->mensajeLimiteDelServidor(), $respuesta->json('errors')['files.0'][0]);
    }

    public function test_sin_archivo_pide_elegir_una_imagen(): void
    {
        $this->subirAlPanel(null)->assertUnprocessable()->assertJsonPath('errors.imagen.0', 'Elige una imagen.');
    }

    public function test_un_archivo_de_otro_tipo_dice_que_el_tipo_no_se_puede_subir(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('menu.pdf', "%PDF-1.4\n%%EOF\n");

        $this->subirAlPanel($pdf)->assertUnprocessable()->assertJsonPath('errors.imagen.0', $this->servicio()->mensajeTipoNoPermitido());
    }

    public function test_un_archivo_que_supera_nuestro_limite_sigue_diciendo_demasiado_grande_con_el_limite_real(): void
    {
        config(['menuonl.imagenes.max_bytes' => 1024]);
        $grande = UploadedFile::fake()->createWithContent('foto.jpg', str_repeat('A', 2048));

        $respuesta = $this->subirAlPanel($grande);

        $respuesta->assertUnprocessable();
        $this->assertSame('La foto es demasiado grande. Prueba con una de menos de 1 KB.', $respuesta->json('errors.imagen.0'));
    }

    public function test_los_cinco_casos_tienen_mensajes_distintos_y_en_espanol(): void
    {
        $servicio = $this->servicio();
        $mensajes = [
            $servicio->mensajeDemasiadoGrande(),
            $servicio->mensajeLimiteDelServidor(),
            $servicio->mensajeSubidaIncompleta(),
            $servicio->mensajeTipoNoPermitido(),
            $servicio->mensajeArchivoTemporal(),
        ];

        $this->assertCount(5, array_unique($mensajes));
        $this->assertSame($servicio->mensajeLimiteDelServidor(), $servicio->mensajeParaErrorDeSubida(UPLOAD_ERR_INI_SIZE));
        $this->assertSame($servicio->mensajeArchivoTemporal(), $servicio->mensajeParaErrorDeSubida(99));
    }

    public function test_la_subida_temporal_de_livewire_y_filament_usa_la_regla_que_explica_el_error(): void
    {
        $reglas = FileUploadConfiguration::rules();

        $this->assertCount(1, $reglas);
        $this->assertInstanceOf(SubidaRecibida::class, $reglas[0]);
    }

    #[DataProvider('erroresDePhp')]
    public function test_la_subida_temporal_devuelve_el_mensaje_propio_y_solo_ese(int $codigo, string $metodo): void
    {
        $validador = Validator::make(['files' => [$this->conCodigo($codigo)]], ['files.*' => FileUploadConfiguration::rules()]);

        $this->assertTrue($validador->fails());
        $this->assertSame([$this->servicio()->{$metodo}()], $validador->errors()->get('files.0'));
    }

    public function test_la_subida_temporal_rechaza_lo_que_pesa_mas_que_el_limite_con_el_mensaje_de_demasiado_grande(): void
    {
        $grande = UploadedFile::fake()->create('foto.jpg', 12289);

        $validador = Validator::make(['files' => [$grande]], ['files.*' => FileUploadConfiguration::rules()]);

        $this->assertSame([$this->servicio()->mensajeDemasiadoGrande()], $validador->errors()->get('files.0'));
    }

    public function test_la_subida_temporal_valida_no_se_ve_afectada(): void
    {
        $validador = Validator::make(['files' => [UploadedFile::fake()->image('foto.jpg')]], ['files.*' => FileUploadConfiguration::rules()]);

        $this->assertFalse($validador->fails());
    }

    public function test_filament_ya_no_dice_demasiado_grande_cuando_falla_el_archivo_temporal(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->restaurante), $this->restaurante);

        $campo = ImagenRestauranteUpload::make('logo', 'logotipo');
        $mensajes = $campo->getValidationMessages();

        $this->assertSame($this->servicio()->mensajeArchivoTemporal(), $mensajes['uploaded']);
        $this->assertSame($this->servicio()->mensajeTipoNoPermitido(), $mensajes['image']);
        $this->assertSame($this->servicio()->mensajeTipoNoPermitido(), $mensajes['mimetypes']);
        $this->assertSame($this->servicio()->mensajeDemasiadoGrande(), $mensajes['max']);
    }
}
