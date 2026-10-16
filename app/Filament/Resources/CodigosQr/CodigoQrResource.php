<?php

namespace App\Filament\Resources\CodigosQr;

use App\Filament\Resources\CodigosQr\Pages\CreateCodigoQr;
use App\Filament\Resources\CodigosQr\Pages\EditCodigoQr;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Filament\Resources\CodigosQr\Schemas\CodigoQrForm;
use App\Filament\Resources\CodigosQr\Tables\CodigosQrTable;
use App\Models\CodigoQr;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CodigoQrResource extends Resource
{
    protected static ?string $model = CodigoQr::class;

    protected static ?string $modelLabel = 'código QR';

    protected static ?string $pluralModelLabel = 'códigos QR';

    protected static ?string $slug = 'codigos-qr';

    protected static ?string $tenantRelationshipName = 'codigosQr';

    protected static ?int $navigationSort = 50;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    public static function form(Schema $schema): Schema
    {
        return CodigoQrForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CodigosQrTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCodigosQr::route('/'),
            'create' => CreateCodigoQr::route('/create'),
            'edit' => EditCodigoQr::route('/{record}/edit'),
        ];
    }
}
