<?php

namespace App\Filament\Resources\Productos\Schemas;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Filament\Components\TraduccionesRepeater;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Services\Catalogos;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProductoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos principales')
                    ->description('Información esencial del platillo tal como la verán tus comensales.')
                    ->schema([
                        TraduccionesRepeater::make('traducciones', 150)
                            ->label('Nombre y descripción')
                            ->columnSpanFull(),
                        TextInput::make('precio')
                            ->label('Precio')
                            ->placeholder('0.00')
                            ->helperText('Precio regular del platillo.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999999.99)
                            ->step(0.01)
                            ->required(),
                        Select::make('categoria_id')
                            ->label('Categoría')
                            ->placeholder('Selecciona una categoría')
                            ->options(fn (): array => self::categorias())
                            ->required()
                            ->searchable()
                            ->rule(fn () => Rule::exists('categorias', 'id')
                                ->where('restaurante_id', Filament::getTenant()?->getKey())
                                ->whereNull('deleted_at')),
                        ImagenRestauranteUpload::make('imagen', 'productos')
                            ->label('Fotografía')
                            ->columnSpanFull(),
                        Toggle::make('disponible')
                            ->label('Disponible para pedir')
                            ->helperText('Desmárcalo si el platillo se agotó temporalmente.')
                            ->default(true),
                        Toggle::make('destacado')
                            ->label('Destacar como recomendado')
                            ->helperText('Muestra este platillo en la sección de recomendados del menú.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Opciones adicionales')
                    ->description('Promociones, etiquetas dietéticas y orden en la carta.')
                    ->collapsible()
                    ->schema([
                        TextInput::make('precio_oferta')
                            ->label('Precio promocional (opcional)')
                            ->placeholder('0.00')
                            ->helperText('Si lo indicas, se mostrará junto al precio regular tachado.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999999.99)
                            ->step(0.01)
                            ->lte('precio'),
                        Select::make('etiquetas')
                            ->label('Etiquetas dietéticas')
                            ->placeholder('Selecciona etiquetas dietéticas')
                            ->relationship('etiquetas', 'codigo')
                            ->getOptionLabelFromRecordUsing(fn (Etiqueta $record): string => Catalogos::nombreEtiqueta($record->codigo))
                            ->multiple()
                            ->preload(),
                        Toggle::make('visible')
                            ->label('Mostrar en el menú público')
                            ->helperText('Desmárcalo para ocultar este platillo de la carta sin eliminarlo.')
                            ->default(true),
                        TextInput::make('orden')
                            ->label('Orden de aparición')
                            ->placeholder('0')
                            ->helperText('Posición numérica para ordenar el platillo dentro de su categoría.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Variantes')
                    ->description('Tamaños o presentaciones con precio propio (ej. Chico, Mediano, Grande).')
                    ->collapsible()
                    ->schema([
                        Repeater::make('variantes')
                            ->label('Variantes')
                            ->relationship('variantes')
                            ->schema([
                                TextInput::make('precio')
                                    ->label('Precio')
                                    ->placeholder('0.00')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(99999999.99)
                                    ->step(0.01)
                                    ->required(),
                                TextInput::make('orden')
                                    ->label('Orden')
                                    ->placeholder('0')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(1000000)
                                    ->default(0)
                                    ->required(),
                                Toggle::make('disponible')
                                    ->label('Disponible')
                                    ->default(true),
                                TraduccionesRepeater::make('traducciones', 80, conDescripcion: false)
                                    ->label('Nombre de la variante')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar variante'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function categorias(): array
    {
        return Categoria::query()
            ->with(['traducciones', 'restaurante'])
            ->orderBy('orden')
            ->get()
            ->mapWithKeys(fn (Categoria $categoria): array => [$categoria->getKey() => $categoria->traduccion()?->nombre ?? "#{$categoria->getKey()}"])
            ->all();
    }
}
