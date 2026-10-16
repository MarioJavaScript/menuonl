<?php

namespace Tests\Feature;

use App\Services\WebpImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebpImageServiceChallengerTest extends TestCase
{
    protected WebpImageService $service;

    /** @var array<string> */
    protected array $tempFilesToCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WebpImageService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFilesToCleanup as $tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    /**
     * Helper to create a temporary file and track it for cleanup.
     */
    protected function createTempFile(string $prefix, string $suffix, string $content): string
    {
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid($prefix, true).$suffix;
        file_put_contents($tempPath, $content);
        $this->tempFilesToCleanup[] = $tempPath;

        return $tempPath;
    }

    /**
     * Challenge 1A: Disguised SVG named 'image.png' with script payload must be strictly rejected.
     */
    public function test_disguised_svg_named_image_png_is_strictly_rejected(): void
    {
        Storage::fake('public');

        $svgPayload = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $tempPath = $this->createTempFile('svg_png_', '.png', $svgPayload);

        $file = new UploadedFile($tempPath, 'image.png', 'image/png', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 1B: Disguised SVG named 'image.jpg' with script payload must be strictly rejected.
     */
    public function test_disguised_svg_named_image_jpg_is_strictly_rejected(): void
    {
        Storage::fake('public');

        $svgPayload = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $tempPath = $this->createTempFile('svg_jpg_', '.jpg', $svgPayload);

        $file = new UploadedFile($tempPath, 'image.jpg', 'image/jpeg', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 1C: Disguised SVG with uppercase XML / SVG tags named 'vector.png' must be rejected.
     */
    public function test_disguised_svg_with_xml_and_uppercase_tags_is_rejected(): void
    {
        Storage::fake('public');

        $svgPayload = '<?xml version="1.0" encoding="UTF-8"?><SVG xmlns="http://www.w3.org/2000/svg"><g><circle r="50"/></g></SVG>';
        $tempPath = $this->createTempFile('svg_upper_', '.png', $svgPayload);

        $file = new UploadedFile($tempPath, 'vector.png', 'image/png', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 1D: Padded SVG (padding exceeding 1KB header read) named 'padded.jpg' must be rejected.
     */
    public function test_padded_svg_exceeding_1kb_header_is_rejected(): void
    {
        Storage::fake('public');

        // 2048 bytes of whitespace / HTML comment before SVG tag
        $padding = str_repeat('<!-- benign padding --> ', 100);
        $svgPayload = $padding.'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $tempPath = $this->createTempFile('svg_pad_', '.jpg', $svgPayload);

        $file = new UploadedFile($tempPath, 'padded.jpg', 'image/jpeg', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 2A: Non-image PDF file disguised as image must be rejected.
     */
    public function test_non_image_pdf_disguised_as_image_is_rejected(): void
    {
        Storage::fake('public');

        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Title (Malicious) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $tempPath = $this->createTempFile('fake_pdf_', '.png', $pdfContent);

        $file = new UploadedFile($tempPath, 'document.png', 'image/png', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 2B: Non-image plain text file disguised as image must be rejected.
     */
    public function test_non_image_text_disguised_as_image_is_rejected(): void
    {
        Storage::fake('public');

        $textContent = "Hello world, this is a plain text file pretending to be a food picture.\n";
        $tempPath = $this->createTempFile('fake_txt_', '.jpg', $textContent);

        $file = new UploadedFile($tempPath, 'menu_dish.jpg', 'image/jpeg', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 2C: Non-image PHP script disguised as image must be rejected.
     */
    public function test_non_image_php_file_disguised_as_image_is_rejected(): void
    {
        Storage::fake('public');

        $phpContent = '<?php echo "injected_code"; exit(0); ?>';
        $tempPath = $this->createTempFile('fake_php_', '.png', $phpContent);

        $file = new UploadedFile($tempPath, 'shell.png', 'image/png', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 2D: Disguised HTML / JavaScript payload disguised as image must be rejected.
     */
    public function test_non_image_html_disguised_as_image_is_rejected(): void
    {
        Storage::fake('public');

        $htmlContent = '<html><head><script>alert("xss")</script></head><body><h1>Fake Image</h1></body></html>';
        $tempPath = $this->createTempFile('fake_html_', '.jpg', $htmlContent);

        $file = new UploadedFile($tempPath, 'dish.jpg', 'image/jpeg', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 3A: Image exceeding 4096px width must be rejected with validation error.
     */
    public function test_image_exceeding_4096px_width_is_rejected(): void
    {
        Storage::fake('public');

        // Create a 4097 x 50 test image
        $img = imagecreatetruecolor(4097, 50);
        $color = imagecolorallocate($img, 200, 100, 50);
        imagefill($img, 0, 0, $color);

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('wide_img_', true).'.png';
        imagepng($img, $tempPath);
        imagedestroy($img);
        $this->tempFilesToCleanup[] = $tempPath;

        $file = new UploadedFile($tempPath, 'too_wide.png', 'image/png', null, true);

        try {
            $this->service->processAndStore($file, 'challenger_test');
            $this->fail('Expected ValidationException was not thrown for image width > 4096px.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('imagen', $e->errors());
            $this->assertStringContainsString('4096x4096px', $e->errors()['imagen'][0]);
        }
    }

    /**
     * Challenge 3B: Image exceeding 4096px height must be rejected with validation error.
     */
    public function test_image_exceeding_4096px_height_is_rejected(): void
    {
        Storage::fake('public');

        // Create a 50 x 4097 test image
        $img = imagecreatetruecolor(50, 4097);
        $color = imagecolorallocate($img, 50, 100, 200);
        imagefill($img, 0, 0, $color);

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('tall_img_', true).'.png';
        imagepng($img, $tempPath);
        imagedestroy($img);
        $this->tempFilesToCleanup[] = $tempPath;

        $file = new UploadedFile($tempPath, 'too_tall.png', 'image/png', null, true);

        try {
            $this->service->processAndStore($file, 'challenger_test');
            $this->fail('Expected ValidationException was not thrown for image height > 4096px.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('imagen', $e->errors());
            $this->assertStringContainsString('4096x4096px', $e->errors()['imagen'][0]);
        }
    }

    /**
     * Challenge 4: Real transparent PNG must preserve transparency and exist on disk.
     * Tests real filesystem persistence (not faked) and cleans up afterwards.
     */
    public function test_real_transparent_png_preserves_transparency_and_exists_on_disk(): void
    {
        // 1. Create a truecolor PNG with alpha channel:
        // Size: 200 x 200
        // Top half (y < 100): Solid red (A=0, opaque)
        // Bottom half (y >= 100): Fully transparent (A=127, transparent)
        $w = 200;
        $h = 200;
        $source = imagecreatetruecolor($w, $h);
        imagesavealpha($source, true);

        // Fill background completely transparent
        $transparentColor = imagecolorallocatealpha($source, 0, 0, 0, 127);
        imagefill($source, 0, 0, $transparentColor);

        // Fill top half solid red
        $redOpaque = imagecolorallocatealpha($source, 255, 0, 0, 0);
        imagefilledrectangle($source, 0, 0, $w, 99, $redOpaque);

        $tempPngPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('alpha_src_', true).'.png';
        imagepng($source, $tempPngPath);
        imagedestroy($source);
        $this->tempFilesToCleanup[] = $tempPngPath;

        $uploadedFile = new UploadedFile($tempPngPath, 'transparent_logo.png', 'image/png', null, true);

        // 2. Process and store on real public disk
        $relativeDir = 'challenger_verify_alpha';
        $savedPath = $this->service->processAndStore($uploadedFile, $relativeDir, 200, 200);

        // Verify output file exists on real disk
        $disk = Storage::disk('public');
        $this->assertTrue($disk->exists($savedPath), "Output file [{$savedPath}] must exist on storage disk.");

        $absoluteSavedPath = $disk->path($savedPath);
        $this->assertFileExists($absoluteSavedPath, "File must exist on physical filesystem at [{$absoluteSavedPath}].");

        // 3. Inspect converted WebP for preserved transparency
        $webpResource = imagecreatefromwebp($absoluteSavedPath);
        $this->assertNotFalse($webpResource, 'Generated WebP must be decodable by GD.');

        // Top half check: pixel at (100, 50) should be opaque red (alpha ~ 0)
        $topRgb = imagecolorat($webpResource, 100, 50);
        $topColors = imagecolorsforindex($webpResource, $topRgb);
        $this->assertLessThan(10, $topColors['alpha'], 'Opaque area should have alpha near 0.');
        $this->assertGreaterThan(200, $topColors['red'], 'Opaque area red channel should be preserved.');

        // Bottom half check: pixel at (100, 150) must be transparent (alpha > 120 in GD 0-127 scale)
        $bottomRgb = imagecolorat($webpResource, 100, 150);
        $bottomColors = imagecolorsforindex($webpResource, $bottomRgb);
        $this->assertGreaterThanOrEqual(120, $bottomColors['alpha'], 'Transparent area alpha must be preserved in WebP.');

        imagedestroy($webpResource);

        // 4. Clean up output file from public disk
        $deleted = $this->service->deleteIfExists($savedPath);
        $this->assertTrue($deleted, 'deleteIfExists should return true for existing file.');
        $this->assertFalse($disk->exists($savedPath), 'File should no longer exist on storage disk after cleanup.');
        $this->assertFileDoesNotExist($absoluteSavedPath, 'File should no longer exist on physical filesystem.');
    }

    /**
     * Challenge 5: Empty zero-byte file disguised as image must be rejected.
     */
    public function test_empty_zero_byte_file_is_rejected(): void
    {
        Storage::fake('public');

        $tempPath = $this->createTempFile('empty_', '.png', '');
        $file = new UploadedFile($tempPath, 'zero_byte.png', 'image/png', null, true);

        $this->expectException(ValidationException::class);
        $this->service->processAndStore($file, 'challenger_test');
    }

    /**
     * Challenge 6: GIF image renamed to .png must be rejected (only JPEG, PNG, WebP allowed).
     */
    public function test_gif_image_renamed_to_png_is_rejected(): void
    {
        Storage::fake('public');

        $img = imagecreatetruecolor(50, 50);
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('gif_src_', true).'.gif';
        imagegif($img, $tempPath);
        imagedestroy($img);
        $this->tempFilesToCleanup[] = $tempPath;

        $file = new UploadedFile($tempPath, 'fake_png_actually_gif.png', 'image/png', null, true);

        try {
            $this->service->processAndStore($file, 'challenger_test');
            $this->fail('Expected ValidationException was not thrown for GIF format.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('imagen', $e->errors());
            $this->assertStringContainsString('image/gif', $e->errors()['imagen'][0]);
        }
    }

    /**
     * Challenge 7: Boundary dimension 4096px (exact limit) is accepted and downscaled.
     * Note: Decompressing 4096x2048 in GD requires ~32MB uncompressed canvas; requires 256M memory_limit.
     */
    public function test_boundary_exact_4096px_is_accepted_and_downscaled(): void
    {
        ini_set('memory_limit', '256M');
        Storage::fake('public');

        // Create 4096 x 2048 image (exactly on boundary)
        $img = imagecreatetruecolor(4096, 2048);
        $color = imagecolorallocate($img, 100, 150, 200);
        imagefill($img, 0, 0, $color);

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('exact_4096_', true).'.png';
        imagepng($img, $tempPath);
        imagedestroy($img);
        $this->tempFilesToCleanup[] = $tempPath;

        $file = new UploadedFile($tempPath, 'boundary_4096.png', 'image/png', null, true);

        $savedPath = $this->service->processAndStore($file, 'challenger_test', 1200, 1200);
        Storage::disk('public')->assertExists($savedPath);

        // Verify downscaled dimensions: 4096x2048 resized to 1200x600
        $content = Storage::disk('public')->get($savedPath);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertEquals(1200, $info[0]);
        $this->assertEquals(600, $info[1]);
    }

    /**
     * Challenge 8: Direct string path support and deleteIfExists safety with null.
     */
    public function test_direct_string_path_and_delete_if_exists_safety(): void
    {
        Storage::fake('public');

        $img = imagecreatetruecolor(100, 100);
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('direct_path_', true).'.png';
        imagepng($img, $tempPath);
        imagedestroy($img);
        $this->tempFilesToCleanup[] = $tempPath;

        $savedPath = $this->service->processAndStore($tempPath, 'challenger_test');
        Storage::disk('public')->assertExists($savedPath);

        $this->assertTrue($this->service->deleteIfExists($savedPath));
        $this->assertFalse($this->service->deleteIfExists(null));
        $this->assertFalse($this->service->deleteIfExists('non_existent_file.webp'));
    }
}
