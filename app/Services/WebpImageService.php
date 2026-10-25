<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
     * getimagesize() type that each allowed MIME type must report.
     *
     * @var array<string, int>
     */
    protected const TIPOS_DE_IMAGEN = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/pjpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /**
     * Longest side (px) accepted on any upload.
     */
    public const int MAX_LADO = 4096;

    /**
     * Total pixels accepted on any upload (about 16 MP), so a few simultaneous uploads cannot exhaust memory.
     */
    public const int MAX_PIXELES = 16_000_000;

    /**
     * Longest side (px) a decoded photo is reduced to before it is rotated and cropped.
     */
    protected const int LADO_DE_TRABAJO = 2400;

    /**
     * Longest side (px) of a logo; logos are never cropped.
     */
    protected const int LADO_DE_LOGOTIPO = 800;

    /**
     * Content that never belongs in an image; a file carrying it is rejected even if it also decodes.
     *
     * @var list<string>
     */
    protected const array CONTENIDO_SOSPECHOSO = ['<?php', '<script'];

    /**
     * What each kind of image becomes: its folder, its crop ratio (null keeps the proportions)
     * and the recommendation shown next to the upload field.
     *
     * @var array<string, array{carpeta: string, ratio: string|null, recomendado: string}>
     */
    public const array PERFILES = [
        'logotipo' => ['carpeta' => 'logos', 'ratio' => null, 'recomendado' => 'Mejor con fondo transparente (PNG) y de al menos 400 px de ancho.'],
        'portada' => ['carpeta' => 'portadas', 'ratio' => 'hero', 'recomendado' => 'Foto horizontal de al menos 1600 px de ancho. Se recorta al centro en formato panorámico (16:9).'],
        'platillo' => ['carpeta' => 'productos', 'ratio' => 'platillo', 'recomendado' => 'Foto de al menos 800 px de ancho. Se recorta al centro en formato vertical (4:5).'],
        'galeria' => ['carpeta' => 'galeria', 'ratio' => 'galeria', 'recomendado' => 'Foto de al menos 800 px de ancho. Se recorta al centro en formato vertical (4:5).'],
    ];

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
     * Single entry point for the panels' uploads: validate, apply the EXIF orientation, crop or fit,
     * convert to WebP and store, always inside restaurantes/{id}/. Nothing is written when the file is rejected.
     *
     * @param  string  $perfil  One of the keys of PERFILES
     * @return string Stored path of the main file
     *
     * @throws ValidationException
     */
    public function procesar(UploadedFile|string $file, int $restauranteId, string $perfil): string
    {
        $config = self::PERFILES[$perfil] ?? null;

        if ($config === null) {
            throw ValidationException::withMessages(['imagen' => 'Tipo de imagen no permitido.']);
        }

        if ($config['ratio'] === null) {
            return $this->processAndStore($file, "restaurantes/{$restauranteId}/{$config['carpeta']}", self::LADO_DE_LOGOTIPO, self::LADO_DE_LOGOTIPO);
        }

        return $this->storeCroppedForRestaurante($file, $restauranteId, $config['carpeta'], $config['ratio']);
    }

    /**
     * Help text for an upload field, with the real limit read from the configuration.
     */
    public function ayudaDeSubida(string $perfil): string
    {
        return 'Formatos: JPG, PNG o WebP. Máximo '.$this->limiteLegible().'. '.(self::PERFILES[$perfil]['recomendado'] ?? '');
    }

    /**
     * Upload limit as people read it ("10 MB", "500 KB").
     */
    public function limiteLegible(): string
    {
        $bytes = (int) config('menuonl.imagenes.max_bytes');

        if ($bytes < 1024 * 1024) {
            return max(1, (int) round($bytes / 1024)).' KB';
        }

        return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1, '.', ''), '0'), '.').' MB';
    }

    public function mensajeDemasiadoGrande(): string
    {
        return 'La foto es demasiado grande. Prueba con una de menos de '.$this->limiteLegible().'.';
    }

    public function mensajeIlegible(): string
    {
        return 'No pudimos leer esa imagen. Prueba con otra en JPG, PNG o WebP.';
    }

    public function mensajeTipoNoPermitido(): string
    {
        return 'Ese tipo de archivo no se puede subir. Elige una foto en JPG, PNG o WebP.';
    }

    /**
     * PHP or the web server cut the upload by their own size limit (upload_max_filesize, post_max_size), which can be
     * lower than ours, so the limit quoted in mensajeDemasiadoGrande() would be misleading.
     */
    public function mensajeLimiteDelServidor(): string
    {
        return 'El servidor no pudo recibir la foto porque supera el tamaño que acepta. Prueba con una más pequeña; si ya es pequeña, avisa a soporte.';
    }

    public function mensajeSubidaIncompleta(): string
    {
        return 'La foto no llegó completa. Revisa tu conexión e inténtalo de nuevo.';
    }

    public function mensajeArchivoTemporal(): string
    {
        return 'No pudimos guardar tu foto en este momento. Intenta de nuevo en unos minutos; si sigue pasando, avisa a soporte.';
    }

    /**
     * Message for a PHP upload error code (UPLOAD_ERR_*). The detail goes to the log, never to the person.
     */
    public function mensajeParaErrorDeSubida(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->mensajeLimiteDelServidor(),
            UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE => $this->mensajeSubidaIncompleta(),
            default => $this->mensajeArchivoTemporal(),
        };
    }

    /**
     * Resize to fit the box, strip all metadata (the canvas is rebuilt), convert to WebP and store on the public disk.
     *
     * @param  string  $directory  Target directory on public disk (e.g. "restaurantes/1/logos")
     * @param  int  $maxWidth  Maximum bounding box width
     * @param  int  $maxHeight  Maximum bounding box height
     * @param  int  $quality  WebP compression quality
     * @return string Relative storage path (e.g. "restaurantes/1/logos/{random}.webp")
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
        $webpContent = $this->conGd(function () use ($file, $maxWidth, $maxHeight, $quality): string {
            $sourceImage = $this->abrir($file);

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

            imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

            return $this->codificarWebp($destImage, $quality);
        });

        // Cryptographically secure random filename; the original name is never used
        $relativePath = trim($directory, '/').'/'.Str::random(40).'.webp';

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
     * Center-crop the upload to the fixed ratio, convert it to WebP and store one copy per responsive width.
     *
     * Returns the path of the main (widest) copy; the others sit next to it as "{name}-w{width}.webp".
     * Nothing but the generated WebP files is ever written to the public disk, and nothing at all on failure.
     *
     * @throws ValidationException
     */
    public function storeCroppedForRestaurante(UploadedFile|string $file, int $restauranteId, string $carpeta, string $ratio): string
    {
        if (! in_array($carpeta, config('menuonl.imagenes.carpetas', []), true) || ! isset(self::RATIOS[$ratio])) {
            throw ValidationException::withMessages(['imagen' => 'Carpeta de imágenes no permitida.']);
        }

        /** @var array<int, string> $copias WebP content by width */
        $copias = $this->conGd(function () use ($file, $ratio): array {
            $origen = $this->abrir($file);
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

            $copias = [];

            foreach ($anchos as $ancho) {
                $alto = max(1, (int) round($ancho * $rh / $rw));
                $copia = imagecreatetruecolor($ancho, $alto);
                // Photos have no transparency: a transparent PNG gets a white background, not a black one
                imagefill($copia, 0, 0, (int) imagecolorallocate($copia, 255, 255, 255));
                imagecopyresampled($copia, $origen, 0, 0, $x, $y, $ancho, $alto, $cropW, $cropH);

                $copias[$ancho] = $this->codificarWebp($copia, 82);
            }

            return $copias;
        });

        $directorio = "restaurantes/{$restauranteId}/{$carpeta}";
        $nombre = Str::random(40);
        $principal = "{$directorio}/{$nombre}.webp";
        $maximo = max(array_keys($copias));

        // The main file is the widest copy; only the standard widths get a "-w" copy of their own
        Storage::disk('public')->put($principal, $copias[$maximo]);

        foreach ($copias as $ancho => $contenido) {
            if (in_array($ancho, self::ANCHOS, true)) {
                Storage::disk('public')->put("{$directorio}/{$nombre}-w{$ancho}.webp", $contenido);
            }
        }

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
     * Check the file before anything is decoded: size, real type (never the extension or the browser's
     * declared type), declared dimensions and total pixels, animation and memory headroom.
     *
     * @throws ValidationException
     */
    public function validateImage(UploadedFile|string $file, ?string $realPath = null): void
    {
        $path = $realPath ?? (is_string($file) ? $file : $file->getRealPath());

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            $this->rechazar($this->mensajeIlegible(), 'archivo-ausente');
        }

        $bytes = filesize($path);

        if ($bytes === false || $bytes === 0) {
            $this->rechazar($this->mensajeIlegible(), 'archivo-vacio');
        }

        if ($bytes > (int) config('menuonl.imagenes.max_bytes')) {
            $this->rechazar($this->mensajeDemasiadoGrande(), 'demasiado-pesado', ['bytes' => $bytes]);
        }

        $mime = $this->getRealMimeType($path);

        if (! array_key_exists($mime, self::ALLOWED_MIMES)) {
            $this->rechazar($this->mensajeTipoNoPermitido(), 'tipo-no-permitido', ['mime' => $mime]);
        }

        // Dimensions come from the header: nothing is decoded yet, so a decompression bomb costs nothing
        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[2] !== self::TIPOS_DE_IMAGEN[$mime]) {
            $this->rechazar($this->mensajeIlegible(), 'cabecera-ilegible', ['mime' => $mime]);
        }

        [$ancho, $alto] = $info;

        if ($ancho > self::MAX_LADO || $alto > self::MAX_LADO) {
            $this->rechazar('La foto tiene demasiados píxeles de ancho o de alto (el máximo es '.self::MAX_LADO.' px por lado). Prueba con una más pequeña.', 'lado-excedido', ['ancho' => $ancho, 'alto' => $alto]);
        }

        if ($ancho * $alto > self::MAX_PIXELES) {
            $this->rechazar('La foto tiene demasiados píxeles en total (el máximo son '.number_format(self::MAX_PIXELES / 1_000_000).' millones, por ejemplo 4000 por 4000). Prueba con una más pequeña.', 'pixeles-excedidos', ['ancho' => $ancho, 'alto' => $alto]);
        }

        $contenido = (string) file_get_contents($path);

        if ($mime === 'image/webp' && $this->esWebpAnimado($contenido)) {
            $this->rechazar('No aceptamos imágenes animadas. Sube una foto fija en JPG, PNG o WebP.', 'animada');
        }

        if (self::ALLOWED_MIMES[$mime] === 'jpeg' && ! $this->jpegCompleto($contenido)) {
            $this->rechazar($this->mensajeIlegible(), 'jpeg-truncado');
        }

        if ($mime === 'image/webp' && strlen($contenido) < 8 + (int) (unpack('V', substr($contenido, 4, 4))[1] ?? 0)) {
            $this->rechazar($this->mensajeIlegible(), 'webp-truncado');
        }

        foreach (self::CONTENIDO_SOSPECHOSO as $marca) {
            if (stripos($contenido, $marca) !== false) {
                $this->rechazar($this->mensajeIlegible(), 'contenido-sospechoso', ['mime' => $mime]);
            }
        }

        $this->asegurarMemoria($ancho, $alto);
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

    /**
     * Validate, decode and orient an upload: the shared first half of every processing path.
     *
     * The photo is reduced to a working size before it is rotated, so a large portrait photo never
     * needs two full-size copies in memory.
     */
    protected function abrir(UploadedFile|string $file): \GdImage
    {
        $path = is_string($file) ? $file : $file->getRealPath();

        $this->validateImage($file, $path);

        $mime = $this->getRealMimeType($path);
        $imagen = $this->reducir($this->decodificar($path, $mime), self::LADO_DE_TRABAJO);

        return $this->orientar($imagen, $this->orientacionExif($path, $mime));
    }

    /**
     * Decode with the loader of the real type only (no permissive fallback). Any GD warning means the
     * file is damaged or cut short, so it is rejected instead of being saved half drawn.
     */
    protected function decodificar(string $path, string $mime): \GdImage
    {
        $avisos = [];
        set_error_handler(function (int $nivel, string $mensaje) use (&$avisos): bool {
            $avisos[] = $mensaje;

            return true;
        });

        try {
            $imagen = match (self::ALLOWED_MIMES[$mime] ?? null) {
                'jpeg' => imagecreatefromjpeg($path),
                'png' => imagecreatefrompng($path),
                'webp' => imagecreatefromwebp($path),
                default => false,
            };
        } finally {
            restore_error_handler();
        }

        $graves = array_filter($avisos, fn (string $aviso): bool => ! str_contains($aviso, 'extraneous bytes'));

        if (! $imagen instanceof \GdImage || $graves !== []) {
            Log::warning('GD no pudo decodificar una imagen subida.', ['mime' => $mime, 'bytes' => filesize($path), 'avisos' => array_values($graves)]);

            throw ValidationException::withMessages(['imagen' => $this->mensajeIlegible()]);
        }

        return $imagen;
    }

    /**
     * Shrink so the longest side is at most $lado; returns the same image when it already fits.
     */
    protected function reducir(\GdImage $imagen, int $lado): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        if (max($ancho, $alto) <= $lado) {
            return $imagen;
        }

        [$nuevoAncho, $nuevoAlto] = $this->calculateDimensions($ancho, $alto, $lado, $lado);

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, (int) imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $destino;
    }

    /**
     * Turn the image upright according to its EXIF orientation (1 to 8).
     */
    protected function orientar(\GdImage $imagen, int $orientacion): \GdImage
    {
        // imagerotate() turns counterclockwise: 270 is a clockwise quarter turn
        $girada = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            5, 6, 7 => imagerotate($imagen, 270, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };

        if (! $girada instanceof \GdImage) {
            throw new \RuntimeException('imagerotate falló.');
        }

        match ($orientacion) {
            2, 5 => imageflip($girada, IMG_FLIP_HORIZONTAL),
            4, 7 => imageflip($girada, IMG_FLIP_VERTICAL),
            default => null,
        };

        return $girada;
    }

    /**
     * EXIF orientation of a JPEG (1 when absent or unreadable). Read by hand so it does not depend on ext-exif.
     */
    protected function orientacionExif(string $path, string $mime): int
    {
        if (self::ALLOWED_MIMES[$mime] !== 'jpeg') {
            return 1;
        }

        $datos = (string) file_get_contents($path, false, null, 0, 131072);

        if (! str_starts_with($datos, "\xFF\xD8")) {
            return 1;
        }

        $posicion = 2;
        $largo = strlen($datos);

        while ($posicion + 4 <= $largo && $datos[$posicion] === "\xFF") {
            $marcador = ord($datos[$posicion + 1]);

            if ($marcador === 0xDA || $marcador === 0xD9) {
                break;
            }

            $tamano = (int) unpack('n', substr($datos, $posicion + 2, 2))[1];

            if ($marcador === 0xE1 && substr($datos, $posicion + 4, 6) === "Exif\0\0") {
                return $this->orientacionDeTiff(substr($datos, $posicion + 10, max(0, $tamano - 8)));
            }

            $posicion += 2 + $tamano;
        }

        return 1;
    }

    /**
     * Orientation tag (0x0112) of the first IFD of an EXIF TIFF block.
     */
    protected function orientacionDeTiff(string $tiff): int
    {
        $orden = substr($tiff, 0, 2);

        if (strlen($tiff) < 8 || ($orden !== 'II' && $orden !== 'MM')) {
            return 1;
        }

        $corto = $orden === 'II' ? 'v' : 'n';
        $largo = $orden === 'II' ? 'V' : 'N';
        $leer = fn (string $formato, int $desde, int $bytes): ?int => strlen($tiff) >= $desde + $bytes
            ? (int) unpack($formato, substr($tiff, $desde, $bytes))[1]
            : null;

        $inicio = $leer($largo, 4, 4);
        $entradas = $inicio === null ? null : $leer($corto, $inicio, 2);

        for ($i = 0; $inicio !== null && $entradas !== null && $i < min($entradas, 64); $i++) {
            $entrada = $inicio + 2 + $i * 12;

            if ($leer($corto, $entrada, 2) === 0x0112) {
                $valor = $leer($corto, $entrada + 8, 2);

                return $valor !== null && $valor >= 1 && $valor <= 8 ? $valor : 1;
            }
        }

        return 1;
    }

    /**
     * Whether a WebP file is an animation (VP8X header with the animation flag).
     */
    protected function esWebpAnimado(string $contenido): bool
    {
        return substr($contenido, 12, 4) === 'VP8X' && (ord($contenido[20] ?? "\0") & 0x02) !== 0;
    }

    /**
     * Whether a JPEG reaches its end-of-image marker, walking its segments (GD would silently draw a cut file
     * half grey). Data after the marker, such as the video of an Android motion photo, is ignored.
     */
    protected function jpegCompleto(string $contenido): bool
    {
        $largo = strlen($contenido);
        $posicion = 2;

        while ($posicion + 2 <= $largo) {
            if ($contenido[$posicion] !== "\xFF") {
                return false;
            }

            $marcador = ord($contenido[$posicion + 1]);

            if ($marcador === 0xD9) {
                return true;
            }

            if ($marcador === 0xFF) {
                $posicion++;

                continue;
            }

            if ($marcador === 0x00 || $marcador === 0x01 || ($marcador >= 0xD0 && $marcador <= 0xD7)) {
                $posicion += 2;

                continue;
            }

            if ($posicion + 4 > $largo) {
                return false;
            }

            $posicion += 2 + (int) unpack('n', substr($contenido, $posicion + 2, 2))[1];

            // Entropy-coded data follows a scan header: skip to the next real marker
            if ($marcador === 0xDA) {
                if (preg_match('/\xFF[^\x00\xD0-\xD7\xFF]/', $contenido, $coincidencia, PREG_OFFSET_CAPTURE, min($posicion, $largo)) !== 1) {
                    return false;
                }

                $posicion = $coincidencia[0][1];
            }
        }

        return false;
    }

    /**
     * Refuse an image that would not fit in the memory still available (4 bytes per pixel when decoded).
     *
     * @throws ValidationException
     */
    protected function asegurarMemoria(int $ancho, int $alto): void
    {
        $limite = $this->limiteDeMemoria();

        if ($limite !== null && memory_get_usage(true) + $ancho * $alto * 4 + 8 * 1024 * 1024 > $limite) {
            $this->rechazar('No pudimos procesar una foto tan grande en este momento. Prueba con una más pequeña.', 'memoria-insuficiente', ['ancho' => $ancho, 'alto' => $alto]);
        }
    }

    /**
     * PHP memory_limit in bytes, or null when unlimited.
     */
    protected function limiteDeMemoria(): ?int
    {
        $valor = trim((string) ini_get('memory_limit'));

        if ($valor === '' || $valor === '-1') {
            return null;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    /**
     * Encode a GD image as WebP and return the bytes.
     */
    protected function codificarWebp(\GdImage $imagen, int $calidad): string
    {
        ob_start();
        $correcto = imagewebp($imagen, null, $calidad);
        $contenido = (string) ob_get_clean();

        if (! $correcto || $contenido === '') {
            throw new \RuntimeException('imagewebp no generó contenido.');
        }

        return $contenido;
    }

    /**
     * Run GD work so that any failure becomes a clear message (422) instead of a server error;
     * the technical detail goes to the log without any user data.
     *
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T
     *
     * @throws ValidationException
     */
    protected function conGd(callable $trabajo): mixed
    {
        try {
            return $trabajo();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Falló el procesamiento de una imagen subida.', ['excepcion' => $e::class, 'detalle' => $e->getMessage()]);

            throw ValidationException::withMessages(['imagen' => $this->mensajeIlegible()]);
        }
    }

    /**
     * Reject the upload with a message for the person and a technical reason for the log.
     *
     * @param  array<string, mixed>  $contexto
     *
     * @throws ValidationException
     */
    protected function rechazar(string $mensaje, string $motivo, array $contexto = []): never
    {
        Log::info('Imagen rechazada.', ['motivo' => $motivo, ...$contexto]);

        throw ValidationException::withMessages(['imagen' => $mensaje]);
    }
}
