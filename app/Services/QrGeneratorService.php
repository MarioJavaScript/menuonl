<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrGeneratorService
{
    /**
     * Generate vector SVG markup for the given data.
     */
    public function svg(string $data, int $size = 400): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    /**
     * Generate raster PNG binary string using the PHP GD extension.
     */
    public function png(string $data, int $size = 400): string
    {
        $scale = max(1, (int) round($size / 40));

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => false,
            'scale' => $scale,
        ]);

        return (new QRCode($options))->render($data);
    }
}
