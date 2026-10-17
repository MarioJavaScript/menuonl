<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoCodigoAcceso: string implements HasColor, HasLabel
{
    case Qr = 'qr';
    case Nfc = 'nfc';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qr => 'Código QR',
            self::Nfc => 'Etiqueta NFC',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Qr => 'gray',
            self::Nfc => 'info',
        };
    }
}
