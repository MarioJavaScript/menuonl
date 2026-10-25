<?php

namespace App\Filament\Resources\Contactos;

use App\Filament\Resources\Contactos\Pages\CreateContacto;
use App\Filament\Resources\Contactos\Pages\EditContacto;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\Contactos\Schemas\ContactoForm;
use App\Filament\Resources\Contactos\Tables\ContactosTable;
use App\Models\Contacto;
use App\Policies\ContactoPolicy;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactoResource extends Resource
{
    protected static ?string $model = Contacto::class;

    protected static ?string $modelLabel = 'contacto';

    protected static ?string $pluralModelLabel = 'contactos';

    protected static ?string $slug = 'contactos';

    protected static ?string $navigationLabel = 'Contactos';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('panel.grupos.informacion');
    }

    /**
     * Tipos de contacto que la persona puede ver y cambiar según el plan del restaurante; null significa todos.
     *
     * @return list<string>|null
     */
    public static function tiposPermitidos(): ?array
    {
        $usuario = auth()->user();

        if ($usuario === null) {
            return [];
        }

        return app(ContactoPolicy::class)->tiposPermitidos($usuario, Filament::getTenant()?->getKey());
    }

    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery();
        $tipos = static::tiposPermitidos();

        return $tipos === null ? $consulta : $consulta->whereIn('tipo', $tipos);
    }

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
