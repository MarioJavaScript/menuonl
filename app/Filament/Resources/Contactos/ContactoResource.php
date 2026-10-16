<?php

namespace App\Filament\Resources\Contactos;

use App\Filament\Resources\Contactos\Pages\CreateContacto;
use App\Filament\Resources\Contactos\Pages\EditContacto;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\Contactos\Schemas\ContactoForm;
use App\Filament\Resources\Contactos\Tables\ContactosTable;
use App\Models\Contacto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactoResource extends Resource
{
    protected static ?string $model = Contacto::class;

    protected static ?string $modelLabel = 'contacto';

    protected static ?string $pluralModelLabel = 'contactos';

    protected static ?string $slug = 'contactos';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    public static function form(Schema $schema): Schema
    {
        return ContactoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactos::route('/'),
            'create' => CreateContacto::route('/create'),
            'edit' => EditContacto::route('/{record}/edit'),
        ];
    }
}
