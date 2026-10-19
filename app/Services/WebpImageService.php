<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebpImageService
{
    /**
     * Allowed MIME types and their GD image loaders.
     *
     * @var array<string, string>
     */
    protected const ALLOWED_MIMES = [
        'image/jpeg' => 'jpeg',
        'image/pjpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Process, resize, strip EXIF metadata, convert to WebP, and store on public disk.
     *
     * @param  string  $directory  Target directory on public disk (e.g. "restaurantes/1/productos")
     * @param  int  $maxWidth  Maximum bounding box width (default: 1200)
     * @param  int  $maxHeight  Maximum bounding box height (default: 1200)
     * @param  int  $quality  WebP compression quality (default: 82)
     * @return string Relative storage path (e.g. "restaurantes/1/productos/{hash}.webp")
     *
     * @throws ValidationException
     */
    public function processAndStore(
        UploadedFile|string $file,
        string $directory,
        int $maxWidth = 1200,
        int $maxHeight = 1200,
        int $quality = 82
    ): string {
        $realPath = is_string($file) ? $file : $file->getRealPath();

        $this->validateImage($file, $realPath);

        $mime = $this->getRealMimeType($realPath);
        $sourceImage = $this->createGdResource($realPath, $mime);

        $origW = imagesx($sourceImage);
        $origH = imagesy($sourceImage);

        [$targetW, $targetH] = $this->calculateDimensions($origW, $origH, $maxWidth, $maxHeight);

        // Blank canvas: truecolor discards all EXIF metadata and non-pixel chunks
        $destImage = imagecreatetruecolor($targetW, $targetH);

        // Alpha channel preservation for transparent PNG/WebP
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
        imagefilledrectangle($destImage, 0, 0, $targetW, $targetH, $transparent);

        // Proportional downscaling
        imagecopyresampled(
            $destImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetW,
            $targetH,
            $origW,
            $origH
        );

        // Cryptographically secure random 40-character filename
        $filename = Str::random(40).'.webp';
        $relativePath = trim($directory, '/').'/'.$filename;

        // Capture WebP binary data in memory
        ob_start();
        imagewebp($destImage, null, $quality);
        $webpContent = ob_get_clean();

        imagedestroy($sourceImage);
        imagedestroy($destImage);

        Storage::disk('public')->put($relativePath, $webpContent);

        return $relativePath;
    }

    /**
     * Process the upload and store only the generated WebP under restaurantes/{id}/{carpeta}/.
     *
     * The original upload is never written to the public disk.
     *
     * @throws ValidationException
     */
    public function storeForRestaurante(UploadedFile|string $file, int $restauranteId, string $carpeta, int $maxWidth = 1200, int $maxHeight = 1200): string
    {
        if (! in_array($carpeta, config('menuonl.imagenes.carpetas', []), true)) {
            throw ValidationException::withMessages(['imagen' => 'Carpeta de imágenes no permitida.']);
        }

        return $this->processAndStore($file, "restaurantes/{$restauranteId}/{$carpeta}", $maxWidth, $maxHeight);
    }

    /**
     * Fixed crop ratios (width:height) of the site images.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const array RATIOS = [
        'platillo' => [4, 5],
        'galeria' => [4, 5],
        'hero' => [16, 9],
    ];

    /**
     * Widths (px) of the responsive copies; the widest copy that fits the source is the main file.
     *
     * @var list<int>
     */
    public const array ANCHOS = [480, 960, 1600];

    /**
     * Center-crop the upload to the fixed ratio, convert it to WebP and store one copy per responsive width.
     *
     * Returns the path of the main (widest) copy; the others sit next to it as "{name}-w{width}.webp".
     * Nothing but the generated WebP files is ever written to the public disk.
     *
     * @throws ValidationException
     */
    public function storeCroppedForRestaurante(UploadedFile|string $file, int $restauranteId, string $carpeta, string $ratio): string
    {
        if (! in_array($carpeta, config('menuonl.imagenes.carpetas', []), true) || ! isset(self::RATIOS[$ratio])) {
            throw ValidationException::withMessages(['imagen' => 'Carpeta de imágenes no permitida.']);
        }

        $realPath = is_string($file) ? $file : $file->getRealPath();
        $this->validateImage($file, $realPath);

        $origen = $this->createGdResource($realPath, $this->getRealMimeType($realPath));
        [$rw, $rh] = self::RATIOS[$ratio];
        $origW = imagesx($origen);
        $origH = imagesy($origen);

        // Largest centered rectangle with the target ratio
        $cropW = min($origW, (int) floor($origH * $rw / $rh));
        $cropH = min($origH, (int) floor($cropW * $rh / $rw));
        $x = intdiv($origW - $cropW, 2);
        $y = intdiv($origH - $cropH, 2);

        $anchos = array_values(array_filter(self::ANCHOS, fn (int $ancho): bool => $ancho <= $cropW));
        $maximo = min($cropW, max(self::ANCHOS));

        if (! in_array($maximo, $anchos, true)) {
            $anchos[] = $maximo;
        }

        $directorio = "restaurantes/{$restauranteId}/{$carpeta}";
        $nombre = Str::random(40);
        $principal = "{$directorio}/{$nombre}.webp";

        $contenido = '';

        foreach ($anchos as $ancho) {
            $alto = max(1, (int) round($ancho * $rh / $rw));
            $copia = imagecreatetruecolor($ancho, $alto);
            imagecopyresampled($copia, $origen, 0, 0, $x, $y, $ancho, $alto, $cropW, $cropH);

            ob_start();
            imagewebp($copia, null, 82);
            $contenido = (string) ob_get_clean();
            imagedestroy($copia);

            Storage::disk('public')->put("{$directorio}/{$nombre}-w{$ancho}.webp", $contenido);
        }

        // The main file is the widest copy
        Storage::disk('public')->put($principal, $contenido);

        imagedestroy($origen);

        return $principal;
    }

    /**
     * Public URL of a stored image, or null when there is none.
     */
    public function url(?string $ruta, ?int $ancho = null): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        if ($ancho !== null) {
            $variante = $this->rutaVariante($ruta, $ancho);

            if (Storage::disk('public')->exists($variante)) {
                return Storage::disk('public')->url($variante);
            }
        }

        return Storage::disk('public')->url($ruta);
    }

    /**
     * "url 480w, url 960w, ..." for an <img srcset>, or null when the image has no responsive copies
     * (images uploaded before this feature, or through the advanced admin).
     */
    public function srcset(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        $partes = [];

        foreach (self::ANCHOS as $ancho) {
            $variante = $this->rutaVariante($ruta, $ancho);

            if (Storage::disk('public')->exists($variante)) {
                $partes[] = Storage::disk('public')->url($variante)." {$ancho}w";
            }
        }

        return count($partes) > 1 ? implode(', ', $partes) : null;
    }

    /**
     * Path of the responsive copy of the given width.
     */
    public function rutaVariante(string $ruta, int $ancho): string
    {
        return preg_replace('/\.webp\z/', "-w{$ancho}.webp", $ruta) ?? $ruta;
    }

    /**
     * Whether the path is a plain relative path inside restaurantes/{id}/ (no traversal, no other tenant).
     */
    public function rutaPerteneceAlRestaurante(?string $ruta, int $restauranteId): bool
    {
        if ($ruta === null || $ruta === '' || str_contains($ruta, "\0") || str_contains($ruta, '\\')) {
            return false;
        }

        $prefijo = "restaurantes/{$restauranteId}/";

        if (! str_starts_with($ruta, $prefijo) || strlen($ruta) === strlen($prefijo)) {
            return false;
        }

        foreach (explode('/', substr($ruta, strlen($prefijo))) as $segmento) {
            if ($segmento === '' || $segmento === '.' || $segmento === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Reject (422) a path outside restaurantes/{id}/.
     *
     * @throws ValidationException
     */
    public function asegurarRutaDelRestaurante(?string $ruta, int $restauranteId, string $campo = 'imagen'): void
    {
        if (! $this->rutaPerteneceAlRestaurante($ruta, $restauranteId)) {
            throw ValidationException::withMessages([$campo => 'La ruta de la imagen no pertenece a este restaurante.']);
        }
    }

    /**
     * Delete an image only when it lives inside the restaurant's own directory.
     */
    public function deleteFromRestaurante(?string $ruta, int $restauranteId): bool
    {
        if (! $this->rutaPerteneceAlRestaurante($ruta, $restauranteId)) {
            return false;
        }

        foreach (self::ANCHOS as $ancho) {
            $this->deleteIfExists($this->rutaVariante($ruta, $ancho));
        }

        return $this->deleteIfExists($ruta);
    }

    /**
     * Alias for processAndStore.
     */
    public function processImage(
        UploadedFile|string $file,
        string $directory,
        int $maxWidth = 1200,
        int $maxHeight = 1200,
        int $quality = 82
    ): string {
        return $this->processAndStore($file, $directory, $maxWidth, $maxHeight, $quality);
    }

    /**
     * Strictly validate image MIME, dimensions, and reject SVG files.
     *
     * @throws ValidationException
     */
    public function validateImage(UploadedFile|string $file, ?string $realPath = null): void
    {
        $path = $realPath ?? (is_string($file) ? $file : $file->getRealPath());

        if (! file_exists($path) || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'imagen' => 'El archivo no existe o no se puede leer.',
            ]);
        }

        // Size cap in bytes, before reading or decoding anything
        $maxBytes = (int) config('menuonl.imagenes.max_bytes', 2 * 1024 * 1024);
        $size = filesize($path);
        if ($size === false || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'imagen' => 'La imagen pesa más del máximo permitido ('.round($maxBytes / 1024 / 1024, 1).' MB).',
            ]);
        }

        $originalName = is_string($file) ? basename($file) : $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 1. Strict SVG rejection by file extension or name
        if ($extension === 'svg' || str_ends_with(strtolower($originalName), '.svg')) {
            throw ValidationException::withMessages([
                'imagen' => 'El formato SVG no está permitido por motivos de seguridad.',
            ]);
        }

        // 2. Strict SVG rejection by inspecting binary/text header
        $header = @file_get_contents($path, false, null, 0, 1024);
        if ($header !== false) {
            if (stripos($header, '<svg') !== false || stripos($header, 'xmlns="http://www.w3.org/2000/svg"') !== false) {
                throw ValidationException::withMessages([
                    'imagen' => 'El formato SVG no está permitido por motivos de seguridad.',
                ]);
            }
        }

        // 3. Inspect real MIME type via finfo magic bytes
        $mime = $this->getRealMimeType($path);

        if ($mime === 'image/svg+xml' || $mime === 'image/svg') {
            throw ValidationException::withMessages([
                'imagen' => 'El formato SVG no está permitido por motivos de seguridad.',
            ]);
        }

        if (! array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw ValidationException::withMessages([
                'imagen' => 'El archivo debe ser una imagen válida (JPEG, PNG, WebP). Formato detectado: '.$mime,
            ]);
        }

        // 4. Inspect image dimensions to prevent decompression bombs (max 4096 x 4096)
        $info = @getimagesize($path);
        if ($info === false || $info[0] > 4096 || $info[1] > 4096) {
            throw ValidationException::withMessages([
                'imagen' => 'Las dimensiones de la imagen exceden el límite permitido (4096x4096px).',
            ]);
        }
    }

    /**
     * Delete an existing image file from public disk if it exists.
     */
    public function deleteIfExists(?string $path): bool
    {
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    /**
     * Get real MIME type using PHP fileinfo.
     */
    public function getRealMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: 'application/octet-stream';
    }

    /**
     * Create native GD image resource from file.
     */
    protected function createGdResource(string $path, string $mime): \GdImage
    {
        $resource = match (self::ALLOWED_MIMES[$mime] ?? null) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            default => null,
        };

        if (! $resource) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                $resource = @imagecreatefromstring($content);
            }
        }

        if (! $resource instanceof \GdImage) {
            throw new \RuntimeException('No se pudo decodificar el archivo de imagen.');
        }

        return $resource;
    }

    /**
     * Calculate downscaled dimensions preserving original aspect ratio.
     *
     * @return array{0: int, 1: int}
     */
    public function calculateDimensions(int $origW, int $origH, int $maxW, int $maxH): array
    {
        if ($origW <= $maxW && $origH <= $maxH) {
            return [$origW, $origH];
        }

        $ratio = min($maxW / $origW, $maxH / $origH);

        return [
            max(1, (int) round($origW * $ratio)),
            max(1, (int) round($origH * $ratio)),
        ];
    }
}
