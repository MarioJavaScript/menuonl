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
