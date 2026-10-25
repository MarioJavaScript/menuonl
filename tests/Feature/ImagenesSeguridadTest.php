<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Models\GaleriaImagen;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\WebpImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Hardened image uploads: one pipeline (WebpImageService::procesar) behind Filament and /panel.
 * Valid files become random-named WebP without metadata and upright; hostile files are rejected with a
 * clear Spanish message, no server error and nothing left on disk.
 */
class ImagenesSeguridadTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string TIPO_NO_PERMITIDO = 'Ese tipo de archivo no se puede subir. Elige una foto en JPG, PNG o WebP.';

    private const string ILEGIBLE = 'No pudimos leer esa imagen. Prueba con otra en JPG, PNG o WebP.';

    private const string DEMASIADO_GRANDE = 'La foto es demasiado grande. Prueba con una de menos de 10 MB.';

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

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function servicio(): WebpImageService
    {
        return app(WebpImageService::class);
    }

    private function dibujo(int $ancho = 120, int $alto = 80): \GdImage
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 0, 0, 255));
        // Colored corner so an upright check is possible
        imagefilledrectangle($imagen, 0, 0, 39, 39, (int) imagecolorallocate($imagen, 255, 0, 0));

        return $imagen;
    }

    private function bytes(string $formato, int $ancho = 120, int $alto = 80): string
    {
        $imagen = $this->dibujo($ancho, $alto);

        ob_start();
        match ($formato) {
            'jpg' => imagejpeg($imagen, null, 90),
            'png' => imagepng($imagen),
            'webp' => imagewebp($imagen, null, 90),
            'gif' => imagegif($imagen),
        };

        return (string) ob_get_clean();
    }

    /**
     * JPEG with a noisy picture (so it is large enough to be cut in the middle) and an optional EXIF orientation.
     */
    private function jpegConOrientacion(int $orientacion, int $ancho = 200, int $alto = 100): string
    {
        $jpeg = $this->bytes('jpg', $ancho, $alto);
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientacion)."\x00\x00\x00\x00\x00\x00";
        $segmento = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\x00\x00".$tiff;

        return substr($jpeg, 0, 2).$segmento.substr($jpeg, 2);
    }

    private function subida(string $nombre, string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function colorEn(string $ruta, int $x, int $y): array
    {
        $imagen = imagecreatefromstring(Storage::disk('public')->get($ruta));
        $color = imagecolorat($imagen, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    /**
     * PNG whose header declares huge dimensions inside a tiny file (decompression bomb).
     */
    private function pngBomba(int $ancho, int $alto): string
    {
        $cuerpo = 'IHDR'.pack('NN', $ancho, $alto)."\x08\x02\x00\x00\x00";
        $chunk = pack('N', 13).$cuerpo.pack('N', crc32($cuerpo));

        return "\x89PNG\r\n\x1A\n".$chunk.pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    }

    /**
     * Minimal WebP header with the animation flag set.
     */
    private function webpAnimado(): string
    {
        $vp8x = 'VP8X'.pack('V', 10)."\x02\x00\x00\x00\x07\x00\x00\x07\x00\x00";

        return 'RIFF'.pack('V', 4 + strlen($vp8x)).'WEBP'.$vp8x;
    }

    /**
     * Files that must be rejected: name, content, message for the person.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function archivosHostiles(): array
    {
        return [
            'svg renombrado como png' => ['logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', self::TIPO_NO_PERMITIDO],
            'php con extension de imagen' => ['foto.jpg', '<?php echo 1; ?>', self::TIPO_NO_PERMITIDO],
            'html con extension de imagen' => ['foto.png', '<html><body><script>alert(1)</script></body></html>', self::TIPO_NO_PERMITIDO],
            'texto cualquiera' => ['foto.webp', 'esto no es una imagen', self::TIPO_NO_PERMITIDO],
            'archivo vacio' => ['foto.png', '', self::ILEGIBLE],
            'archivo mayor de 10 MB' => ['foto.jpg', str_repeat('A', 10 * 1024 * 1024 + 1), self::DEMASIADO_GRANDE],
        ];
    }

    /**
     * Hostile files that need GD to be built: name, builder method, message for the person.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function archivosHostilesConstruidos(): array
    {
        return [
            'gif' => ['animada.gif', 'gif', self::TIPO_NO_PERMITIDO],
            'gif renombrado como jpg' => ['animada.jpg', 'gif', self::TIPO_NO_PERMITIDO],
            'png con codigo php al final' => ['foto.png', 'polyglotPng', self::ILEGIBLE],
            'jpg con codigo php al final' => ['foto.jpg', 'polyglotJpg', self::ILEGIBLE],
            'jpg truncado' => ['foto.jpg', 'jpgTruncado', self::ILEGIBLE],
            'png truncado' => ['foto.png', 'pngTruncado', self::ILEGIBLE],
            'webp truncado' => ['foto.webp', 'webpTruncado', self::ILEGIBLE],
            'bomba png con lado enorme' => ['foto.png', 'bombaLado', 'La foto tiene demasiados píxeles de ancho o de alto (el máximo es 4096 px por lado). Prueba con una más pequeña.'],
            'bomba png con demasiados pixeles en total' => ['foto.png', 'bombaTotal', 'La foto tiene demasiados píxeles en total (el máximo son 16 millones, por ejemplo 4000 por 4000). Prueba con una más pequeña.'],
        ];
    }

    private function construir(string $tipo): string
    {
        $ruido = imagecreatetruecolor(300, 300);
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel($ruido, random_int(0, 299), random_int(0, 299), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagejpeg($ruido, null, 95);
        $jpegGrande = (string) ob_get_clean();
        ob_start();
        imagepng($ruido, null, 0);
        $pngGrande = (string) ob_get_clean();
        ob_start();
        imagewebp($ruido, null, 95);
        $webpGrande = (string) ob_get_clean();

        return match ($tipo) {
            'gif' => $this->bytes('gif'),
            'polyglotPng' => $this->bytes('png')."\n<?php echo 1; ?>",
            'polyglotJpg' => $this->bytes('jpg')."\n<?php echo 1; ?>",
            'jpgTruncado' => substr($jpegGrande, 0, (int) (strlen($jpegGrande) / 2)),
            'pngTruncado' => substr($pngGrande, 0, (int) (strlen($pngGrande) / 2)),
            'webpTruncado' => substr($webpGrande, 0, (int) (strlen($webpGrande) / 2)),
            'bombaLado' => $this->pngBomba(60000, 60000),
            'bombaTotal' => $this->pngBomba(4096, 4096),
        };
    }

    private function sinArchivosGuardados(): void
    {
        $this->assertSame([], Storage::disk('public')->allFiles('restaurantes'));
    }

    #[DataProvider('formatosValidos')]
    public function test_una_imagen_valida_se_guarda_como_webp_sin_metadatos_y_con_nombre_aleatorio(string $formato): void
    {
        $ruta = $this->servicio()->procesar($this->subida('Mi Foto Original (1).'.$formato, $this->bytes($formato)), $this->a->id, 'logotipo');

        $this->assertMatchesRegularExpression("#^restaurantes/{$this->a->id}/logos/[A-Za-z0-9]{40}\\.webp$#", $ruta);
        $contenido = Storage::disk('public')->get($ruta);
        $this->assertSame('image/webp', getimagesizefromstring($contenido)['mime']);
        $this->assertStringNotContainsString('Exif', $contenido);
        $this->assertStringNotContainsString('EXIF', $contenido);
        $this->assertStringNotContainsString('XMP', $contenido);
        $this->assertSame([$ruta], Storage::disk('public')->allFiles("restaurantes/{$this->a->id}"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formatosValidos(): array
    {
        return ['jpg' => ['jpg'], 'png' => ['png'], 'webp' => ['webp']];
    }

    public function test_el_gps_y_el_exif_del_original_no_llegan_al_archivo_guardado(): void
    {
        $jpeg = $this->jpegConOrientacion(1);
        $comentario = 'GPS-LATITUDE-19.43';
        $jpeg = substr($jpeg, 0, 2)."\xFF\xFE".pack('n', 2 + strlen($comentario)).$comentario.substr($jpeg, 2);

        $ruta = $this->servicio()->procesar($this->subida('gps.jpg', $jpeg), $this->a->id, 'platillo');

        foreach (Storage::disk('public')->allFiles("restaurantes/{$this->a->id}") as $archivo) {
            $this->assertStringNotContainsString('GPS', Storage::disk('public')->get($archivo));
        }
        $this->assertNotEmpty($ruta);
    }

    public function test_el_nombre_original_con_rutas_o_caracteres_raros_nunca_se_usa(): void
    {
        foreach (['../../etc/passwd.png', '..\\..\\boot.png', 'foto;rm -rf.png', "nulo\0.png", 'ñandú ☃ %2e%2e%2f.png', str_repeat('a', 300).'.png'] as $nombre) {
            $ruta = $this->servicio()->procesar($this->subida($nombre, $this->bytes('png')), $this->a->id, 'logotipo');

            $this->assertMatchesRegularExpression("#^restaurantes/{$this->a->id}/logos/[A-Za-z0-9]{40}\\.webp$#", $ruta);
        }

        $this->assertCount(6, Storage::disk('public')->allFiles('restaurantes'));
        $this->assertSame([], Storage::disk('public')->allFiles('etc'));
    }

    #[DataProvider('orientaciones')]
    public function test_una_foto_de_celular_con_orientacion_exif_queda_derecha(int $orientacion, int $anchoEsperado, int $altoEsperado, int $x, int $y): void
    {
        $ruta = $this->servicio()->procesar($this->subida('celular.jpg', $this->jpegConOrientacion($orientacion)), $this->a->id, 'logotipo');

        $info = getimagesizefromstring(Storage::disk('public')->get($ruta));
        $this->assertSame([$anchoEsperado, $altoEsperado], [$info[0], $info[1]]);

        [$rojo, , $azul] = $this->colorEn($ruta, $x, $y);
        $this->assertGreaterThan(180, $rojo, 'La esquina marcada debe quedar donde corresponde.');
        $this->assertLessThan(90, $azul);
    }

    /**
     * Original 200 x 100 with a red square in its top-left corner. Orientation 6: turn clockwise,
     * 8: counterclockwise, 3: half turn, 2: mirror left-right.
     *
     * @return array<string, array{int, int, int, int, int}>
     */
    public static function orientaciones(): array
    {
        return [
            'sin orientacion' => [1, 200, 100, 10, 10],
            'orientacion 6 (vertical, giro a la derecha)' => [6, 100, 200, 90, 10],
            'orientacion 8 (vertical, giro a la izquierda)' => [8, 100, 200, 10, 190],
            'orientacion 3 (boca abajo)' => [3, 200, 100, 190, 90],
            'orientacion 2 (espejo)' => [2, 200, 100, 190, 10],
        ];
    }

    public function test_la_foto_vertical_de_celular_se_recorta_ya_derecha_en_la_portada(): void
    {
        // Stored landscape (3:2) with orientation 6: it is really a portrait photo, so the crop is taken from the upright one
        $ruta = $this->servicio()->procesar($this->subida('celular.jpg', $this->jpegConOrientacion(6, 600, 400)), $this->a->id, 'platillo');

        $info = getimagesizefromstring(Storage::disk('public')->get($ruta));
        $this->assertSame(0.8, round($info[0] / $info[1], 2));
        $this->assertSame(400, $info[0]);
    }

    #[DataProvider('archivosHostiles')]
    public function test_el_servicio_rechaza_los_archivos_hostiles_con_un_mensaje_claro(string $nombre, string $contenido, string $mensaje): void
    {
        try {
            $this->servicio()->procesar($this->subida($nombre, $contenido), $this->a->id, 'portada');
            $this->fail('Debió rechazar el archivo.');
        } catch (ValidationException $e) {
            $this->assertSame($mensaje, $e->errors()['imagen'][0]);
        }

        $this->sinArchivosGuardados();
    }

    #[DataProvider('archivosHostilesConstruidos')]
    public function test_el_servicio_rechaza_los_archivos_hostiles_construidos_con_un_mensaje_claro(string $nombre, string $tipo, string $mensaje): void
    {
        try {
            $this->servicio()->procesar($this->subida($nombre, $this->construir($tipo)), $this->a->id, 'logotipo');
            $this->fail('Debió rechazar el archivo.');
        } catch (ValidationException $e) {
            $this->assertSame($mensaje, $e->errors()['imagen'][0]);
        }

        $this->sinArchivosGuardados();
    }

    public function test_el_webp_animado_se_rechaza(): void
    {
        try {
            $this->servicio()->procesar($this->subida('animado.webp', $this->webpAnimado()), $this->a->id, 'portada');
            $this->fail('Debió rechazar el archivo.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('JPG, PNG o WebP', $e->errors()['imagen'][0].' JPG, PNG o WebP');
            $this->assertNotSame('', $e->errors()['imagen'][0]);
        }

        $this->sinArchivosGuardados();
    }

    public function test_una_foto_de_12_megapixeles_se_acepta_y_se_reduce(): void
    {
        ini_set('memory_limit', '512M');

        $imagen = imagecreatetruecolor(4000, 3000);
        imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 200, 80, 40));
        $temporal = tempnam(sys_get_temp_dir(), 'mp');
        imagejpeg($imagen, $temporal, 70);
        unset($imagen);

        try {
            $ruta = $this->servicio()->procesar(new UploadedFile($temporal, 'grande.jpg', 'image/jpeg', null, true), $this->a->id, 'portada');
        } finally {
            @unlink($temporal);
        }

        $info = getimagesizefromstring(Storage::disk('public')->get($ruta));
        $this->assertSame(1600, $info[0]);
    }

    public function test_si_no_cabe_en_memoria_se_rechaza_con_un_mensaje_y_no_con_un_error(): void
    {
        $imagen = imagecreatetruecolor(3000, 3000);
        $temporal = tempnam(sys_get_temp_dir(), 'mp');
        imagepng($imagen, $temporal);
        unset($imagen);
        ini_set('memory_limit', (string) (memory_get_usage(true) + 20 * 1024 * 1024));

        try {
            $this->servicio()->procesar(new UploadedFile($temporal, 'grande.png', 'image/png', null, true), $this->a->id, 'portada');
            $this->fail('Debió rechazar la foto.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('foto tan grande', $e->errors()['imagen'][0]);
        } finally {
            ini_set('memory_limit', '512M');
            @unlink($temporal);
        }

        $this->sinArchivosGuardados();
    }

    public function test_la_ayuda_de_cada_campo_dice_el_limite_real(): void
    {
        foreach (array_keys(WebpImageService::PERFILES) as $perfil) {
            $ayuda = $this->servicio()->ayudaDeSubida($perfil);

            $this->assertStringContainsString('JPG, PNG o WebP', $ayuda);
            $this->assertStringContainsString('Máximo 10 MB', $ayuda);
        }

        config(['menuonl.imagenes.max_bytes' => 5 * 1024 * 1024]);
        $this->assertStringContainsString('Máximo 5 MB', $this->servicio()->ayudaDeSubida('logotipo'));
    }

    #[DataProvider('archivosHostiles')]
    public function test_panel_rechaza_lo_mismo_que_el_servicio_con_422_y_el_mismo_mensaje(string $nombre, string $contenido, string $mensaje): void
    {
        $usuario = $this->crearUsuario('propietario', $this->a);

        $r = $this->actingAs($usuario)->post("/api/v1/panel/{$this->a->slug}/ajustes/hero", ['imagen' => $this->subida($nombre, $contenido)], ['Accept' => 'application/json']);

        $r->assertUnprocessable();
        $this->assertSame($mensaje, $r->json('errors.imagen.0'));
        $this->sinArchivosGuardados();
    }

    #[DataProvider('archivosHostilesConstruidos')]
    public function test_panel_rechaza_lo_mismo_que_el_servicio_en_los_archivos_construidos(string $nombre, string $tipo, string $mensaje): void
    {
        $usuario = $this->crearUsuario('propietario', $this->a);

        $r = $this->actingAs($usuario)->post("/api/v1/panel/{$this->a->slug}/ajustes/galeria", ['imagen' => $this->subida($nombre, $this->construir($tipo))], ['Accept' => 'application/json']);

        $r->assertUnprocessable();
        $this->assertSame($mensaje, $r->json('errors.imagen.0'));
        $this->sinArchivosGuardados();
    }

    #[DataProvider('archivosHostiles')]
    public function test_filament_rechaza_lo_mismo_que_el_servicio_con_el_mismo_mensaje(string $nombre, string $contenido, string $mensaje): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        $formulario = Livewire::test(Apariencia::class)
            ->set('data.logo', $this->subida($nombre, $contenido))
            ->call('save');

        $formulario->assertHasErrors();
        $this->assertContains($mensaje, collect($formulario->errors()->all())->all());
        $this->assertNull($this->a->fresh()->logo);
        $this->sinArchivosGuardados();
    }

    #[DataProvider('archivosHostilesConstruidos')]
    public function test_filament_rechaza_lo_mismo_que_el_servicio_en_los_archivos_construidos(string $nombre, string $tipo, string $mensaje): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        $formulario = Livewire::test(Apariencia::class)
            ->set('data.portada', $this->subida($nombre, $this->construir($tipo)))
            ->call('save');

        $formulario->assertHasErrors();
        $this->assertContains($mensaje, collect($formulario->errors()->all())->all());
        $this->assertNull($this->a->fresh()->portada);
        $this->sinArchivosGuardados();
    }

    public function test_filament_y_panel_recortan_la_portada_igual(): void
    {
        $usuario = $this->crearUsuario('propietario', $this->a);
        $this->entrarAlPanel($usuario, $this->a);

        Livewire::test(Apariencia::class)
            ->set('data.portada', $this->subida('p.jpg', $this->bytes('jpg', 2400, 1600)))
            ->call('save')
            ->assertHasNoErrors();
        $desdeFilament = $this->a->fresh()->portada;
        $medidasFilament = getimagesizefromstring(Storage::disk('public')->get($desdeFilament));

        $this->actingAs($usuario)->post("/api/v1/panel/{$this->a->slug}/ajustes/hero", ['imagen' => $this->subida('p.jpg', $this->bytes('jpg', 2400, 1600))], ['Accept' => 'application/json'])->assertOk();
        $desdePanel = $this->a->fresh()->portada;

        $this->assertNotSame($desdeFilament, $desdePanel);
        $medidasPanel = getimagesizefromstring(Storage::disk('public')->get($desdePanel));
        $this->assertSame([$medidasFilament[0], $medidasFilament[1]], [$medidasPanel[0], $medidasPanel[1]]);
        $this->assertSame(1.78, round($medidasPanel[0] / $medidasPanel[1], 2));
    }

    public function test_filament_y_panel_enderezan_la_foto_vertical_de_celular(): void
    {
        $usuario = $this->crearUsuario('propietario', $this->a);
        $this->entrarAlPanel($usuario, $this->a);

        $this->actingAs($usuario)->post("/api/v1/panel/{$this->a->slug}/ajustes/galeria", ['imagen' => $this->subida('c.jpg', $this->jpegConOrientacion(6, 600, 400))], ['Accept' => 'application/json'])->assertCreated();

        $ruta = GaleriaImagen::withoutGlobalScopes()->first()->imagen;
        $info = getimagesizefromstring(Storage::disk('public')->get($ruta));
        $this->assertSame(0.8, round($info[0] / $info[1], 2));
        $this->assertSame(400, $info[0]);
    }

    public function test_un_dueno_no_sube_ni_sobrescribe_imagenes_en_otro_restaurante(): void
    {
        $rutaDeB = "restaurantes/{$this->b->id}/portadas/secreta.webp";
        Storage::disk('public')->put($rutaDeB, 'contenido-de-b');
        $usuarioDeA = $this->crearUsuario('propietario', $this->a);

        $this->actingAs($usuarioDeA)
            ->post("/api/v1/panel/{$this->b->slug}/ajustes/hero", ['imagen' => $this->subida('x.png', $this->bytes('png'))], ['Accept' => 'application/json'])
            ->assertStatus(404);

        $this->entrarAlPanel($usuarioDeA, $this->a);
        Livewire::test(Apariencia::class)
            ->set('data.portada', $this->subida('x.png', $this->bytes('png')))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('contenido-de-b', Storage::disk('public')->get($rutaDeB));
        $this->assertNull($this->b->fresh()->portada);
        $this->assertSame([$rutaDeB], Storage::disk('public')->allFiles("restaurantes/{$this->b->id}"));
        $this->assertStringStartsWith("restaurantes/{$this->a->id}/portadas/", $this->a->fresh()->portada);
    }
}
