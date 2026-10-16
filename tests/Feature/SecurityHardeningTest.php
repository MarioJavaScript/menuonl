<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Rules\ValorContactoSeguro;
use App\Services\WebpImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::create([
            'nombre' => 'BistrÃ³ Seguro',
            'slug' => 'bistro-seguro',
        ]);
    }

    /**
     * Tier 1: Contacto rejects javascript: and data: URI schemes to prevent XSS.
     */
    public function test_contacto_rejects_javascript_and_data_schemes(): void
    {
        $maliciousUrls = [
            'javascript:alert(document.cookie)',
            'JavaScript:alert(1)',
            'JAVASCRIPT:void(0)',
            'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'data:image/svg+xml;base64,PHN2Zz4=',
            'vbscript:msgbox(1)',
        ];

        foreach ($maliciousUrls as $maliciousUrl) {
            $isValid = Validator::make(['valor' => $maliciousUrl], ['valor' => [new ValorContactoSeguro('otro')]])->passes();
            $this->assertFalse(
                $isValid,
                "Dangerous scheme [{$maliciousUrl}] must be rejected by Contacto validation."
            );
        }
    }

    /**
     * Tier 1: Contacto accepts safe schemes: http, https, tel, and mailto.
     */
    public function test_contacto_accepts_safe_schemes(): void
    {
        $safeUrls = [
            'https://instagram.com/bistroseguro',
            'http://bistroseguro.com',
            'tel:+525512345678',
            'mailto:reservas@bistroseguro.com',
            'https://wa.me/5215512345678',
            'https://maps.google.com/?q=bistro',
        ];

        foreach ($safeUrls as $safeUrl) {
            $isValid = Validator::make(['valor' => $safeUrl], ['valor' => [new ValorContactoSeguro('otro')]])->passes();
            $this->assertTrue(
                $isValid,
                "Legitimate contact URL [{$safeUrl}] must be accepted by Contacto validation."
            );
        }
    }

    /**
     * Tier 1: Producto rejects negative regular price.
     */
    public function test_producto_rejects_negative_price(): void
    {
        $this->expectException(\Throwable::class);

        Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => 1,
            'precio' => '-15.00',
            'disponible' => true,
            'visible' => true,
        ]);
    }

    /**
     * Tier 1: Producto rejects negative offer price.
     */
    public function test_producto_rejects_negative_offer_price(): void
    {
        $this->expectException(\Throwable::class);

        Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => 1,
            'precio' => '50.00',
            'precio_oferta' => '-5.00',
            'disponible' => true,
            'visible' => true,
        ]);
    }

    /**
     * Tier 1: ProductoVariante rejects negative variant price.
     */
    public function test_producto_variante_rejects_negative_price(): void
    {
        $this->expectException(\Throwable::class);

        ProductoVariante::create([
            'producto_id' => 1,
            'precio' => '-20.00',
            'disponible' => true,
            'orden' => 1,
        ]);
    }

    /**
     * Tier 2: WebpImageService converts valid JPEG and PNG to WebP with randomized filename.
     */
    public function test_webp_image_service_converts_raster_image_to_webp_with_random_name(): void
    {
        Storage::fake('public');

        $service = app(WebpImageService::class);
        $fakeFile = UploadedFile::fake()->image('delicious_dish.jpg', 640, 480);

        $savedPath = $service->processAndStore(
            $fakeFile,
            "restaurantes/{$this->restaurante->id}/productos",
            1200,
            1200,
            82
        );

        $this->assertStringEndsWith('.webp', $savedPath);
        $this->assertStringContainsString("restaurantes/{$this->restaurante->id}/productos/", $savedPath);

        // Filename portion must be 40 characters + .webp
        $filename = basename($savedPath);
        $this->assertEquals(45, strlen($filename)); // 40 chars + '.webp' (5)

        Storage::disk('public')->assertExists($savedPath);

        // Verify real MIME of saved file is image/webp
        $savedContent = Storage::disk('public')->get($savedPath);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $savedContent);
        finfo_close($finfo);

        $this->assertEquals('image/webp', $mime);
    }

    /**
     * Tier 2: WebpImageService strictly rejects SVG files to prevent stored XSS and XML attacks.
     */
    public function test_webp_image_service_strictly_rejects_svg_files(): void
    {
        Storage::fake('public');

        $service = app(WebpImageService::class);

        // SVG disguised as file
        $svgFile = UploadedFile::fake()->createWithContent(
            'malicious.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(\'xss\')"><circle r="10"/></svg>'
        );

        $this->expectException(ValidationException::class);

        $service->processAndStore(
            $svgFile,
            "restaurantes/{$this->restaurante->id}/productos"
        );
    }

    /**
     * Tier 2: WebpImageService rejects non-image files.
     */
    public function test_webp_image_service_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $service = app(WebpImageService::class);
        $textPayload = UploadedFile::fake()->createWithContent('exploit.php', '<?php phpinfo(); ?>');

        $this->expectException(ValidationException::class);

        $service->processAndStore(
            $textPayload,
            "restaurantes/{$this->restaurante->id}/productos"
        );
    }

    /**
     * Tier 2: WebpImageService preserves alpha channel on transparent PNGs.
     */
    public function test_webp_image_service_preserves_transparency(): void
    {
        Storage::fake('public');

        $service = app(WebpImageService::class);

        // Create transparent PNG
        $img = imagecreatetruecolor(100, 100);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);

        $tempPath = tempnam(sys_get_temp_dir(), 'png_test_');
        imagepng($img, $tempPath);
        imagedestroy($img);

        $file = new UploadedFile($tempPath, 'transparent_logo.png', 'image/png', null, true);

        $savedPath = $service->processAndStore(
            $file,
            "restaurantes/{$this->restaurante->id}/logo"
        );

        Storage::disk('public')->assertExists($savedPath);
        @unlink($tempPath);
    }
}
