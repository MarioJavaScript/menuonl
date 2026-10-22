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
                            ->prefix('$')
                            ->placeholder('0.00')
                            ->helperText('Precio regular que pagarán tus comensales.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999999.99)
                            ->step(0.01)
                            ->required()
                            ->validationAttribute('precio')
                            ->validationMessages([
                                'required' => 'Escribe un precio para el platillo.',
                                'numeric' => 'Escribe un precio válido, por ejemplo 85.50.',
                                'min' => 'El precio no puede ser negativo.',
                                'max' => 'El precio es demasiado alto. Revisa la cantidad.',
                            ]),
                        Select::make('categoria_id')
                            ->label('Categoría')
                            ->placeholder('Elige una categoría')
                            ->helperText('Elige la sección de tu menú donde se ubicará este platillo.')
                            ->options(fn (): array => self::categorias())
                            ->required()
                            ->searchable()
                            ->validationAttribute('categoría')
                            ->rule(fn () => Rule::exists('categorias', 'id')
                                ->where('restaurante_id', Filament::getTenant()?->getKey())
                                ->whereNull('deleted_at')),
                        ImagenRestauranteUpload::make('imagen', 'platillo')
                            ->label('Fotografía')
                            ->columnSpanFull(),
                        Toggle::make('disponible')
                            ->label('Disponible para pedir')
                            ->helperText('Desmárcalo si se te agotó hoy; podrás volver a activarlo en cualquier momento.')
                            ->default(true),
                        Toggle::make('destacado')
                            ->label('Destacar como recomendado')
                            ->helperText('Actívalo para sugerirlo al inicio de tu menú como una de tus recomendaciones especiales.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Opciones adicionales')
                    ->description('Promociones, etiquetas dietéticas y orden en la carta.')
                    ->collapsible()
                    ->schema([
                        TextInput::make('precio_oferta')
                            ->label('Precio promocional (opcional)')
                            ->prefix('$')
                            ->placeholder('0.00')
                            ->helperText('Si lo indicas, se mostrará como precio vigente junto al precio regular tachado.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999999.99)
                            ->step(0.01)
                            ->lte('precio')
                            ->validationAttribute('precio promocional')
                            ->validationMessages([
                                'numeric' => 'Escribe un precio promocional válido, por ejemplo 65.00.',
                                'min' => 'El precio promocional no puede ser negativo.',
                                'max' => 'El precio promocional es demasiado alto. Revisa la cantidad.',
                                'lte' => 'El precio promocional debe ser menor o igual al precio regular.',
                            ]),
                        Select::make('etiquetas')
                            ->label('Etiquetas dietéticas')
                            ->placeholder('Selecciona etiquetas dietéticas')
                            ->helperText('Indica si es vegetariano, vegano, sin gluten o picante para informar a tus clientes.')
                            ->relationship('etiquetas', 'codigo')
                            ->getOptionLabelFromRecordUsing(fn (Etiqueta $record): string => Catalogos::nombreEtiqueta($record->codigo))
                            ->multiple()
                            ->preload(),
                        Toggle::make('visible')
                            ->label('Mostrar en el menú público')
                            ->helperText('Desmárcalo si quieres guardarlo u ocultarlo temporalmente de tu menú sin borrarlo.')
                            ->default(true),
                        TextInput::make('orden')
                            ->label('Orden de aparición')
                            ->placeholder('0')
                            ->helperText('Define el lugar en el que aparecerá este platillo dentro de su categoría.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->default(0)
                            ->required()
                            ->validationAttribute('orden')
                            ->validationMessages([
                                'required' => 'Escribe el número de orden.',
                                'integer' => 'El orden debe ser un número entero.',
                                'min' => 'El orden no puede ser negativo.',
                            ]),
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
                                    ->prefix('$')
                                    ->placeholder('0.00')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(99999999.99)
                                    ->step(0.01)
                                    ->required()
                                    ->validationAttribute('precio de variante')
                                    ->validationMessages([
                                        'required' => 'Escribe un precio para la variante.',
                                        'numeric' => 'Escribe un precio válido.',
                                        'min' => 'El precio no puede ser negativo.',
                                    ]),
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
                                    ->helperText('Desmárcalo si esta variante específica se agotó.')
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
