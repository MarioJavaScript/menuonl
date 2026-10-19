<?php

namespace App\Filament\Components;

use App\Models\SitioConfiguracion;
use App\Models\SitioGaleria;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Formulario del contenido del sitio (/sitio/{slug}), compartido por la página del propietario y la del superadmin.
 * Los límites salen de las constantes de los modelos, las mismas que dimensionan las columnas.
 */
class SitioWebForm
{
    public static function configure(Schema $schema): Schema
    {
        $ruta = fn (string $file, $livewire): bool => $livewire->rutaGuardadaDelSitio($file);

        return $schema->components([
            Section::make('Portada')
                ->description('Foto principal y frase bajo el nombre del restaurante. Sin foto se muestra un fondo de color.')
                ->schema([
                    ImagenRestauranteUpload::make('hero_imagen', 'sitio', $ruta)->label('Foto de portada'),
                    TextInput::make('hero_imagen_creditos')->label('Créditos de la foto de portada')->helperText('Solo si la licencia lo exige (por ejemplo, autor y licencia). Se muestra en el pie del sitio.')->maxLength(SitioConfiguracion::MAX_CREDITOS),
                    TextInput::make('hero_subtitulo')->label('Frase de portada')->helperText('Si se deja vacía se usa la descripción del restaurante.')->maxLength(SitioConfiguracion::MAX_SUBTITULO),
                ]),
            Section::make('Historia')
                ->description('Si no escribes ningún texto ni subes foto, la sección se oculta.')
                ->schema([
                    TextInput::make('historia_etiqueta')->label('Etiqueta')->placeholder('Nuestra historia')->maxLength(SitioConfiguracion::MAX_ETIQUETA),
                    TextInput::make('historia_titulo')->label('Título')->maxLength(SitioConfiguracion::MAX_TITULO),
                    Textarea::make('historia_parrafo_1')->label('Primer párrafo')->rows(4)->maxLength(SitioConfiguracion::MAX_PARRAFO),
                    Textarea::make('historia_parrafo_2')->label('Segundo párrafo')->rows(4)->maxLength(SitioConfiguracion::MAX_PARRAFO),
                    ImagenRestauranteUpload::make('historia_imagen', 'sitio', $ruta)->label('Foto de la historia'),
                    TextInput::make('historia_imagen_creditos')->label('Créditos de la foto de la historia')->maxLength(SitioConfiguracion::MAX_CREDITOS),
                ]),
            Section::make('Datos destacados')
                ->description('Hasta '.SitioConfiguracion::MAX_ESTADISTICAS.' cifras breves junto a la historia (por ejemplo: "Desde" y "1998").')
                ->schema([
                    Repeater::make('estadisticas')
                        ->label('Datos')
                        ->schema([
                            TextInput::make('etiqueta')->label('Etiqueta')->required()->maxLength(SitioConfiguracion::MAX_DATO_ESTADISTICA),
                            TextInput::make('valor')->label('Valor')->required()->maxLength(SitioConfiguracion::MAX_DATO_ESTADISTICA),
                        ])
                        ->columns(2)
                        ->maxItems(SitioConfiguracion::MAX_ESTADISTICAS)
                        ->addActionLabel('Agregar dato')
                        ->defaultItems(0),
                ]),
            Section::make('Galería')
                ->description('Hasta '.SitioGaleria::MAX_POR_RESTAURANTE.' fotos. Usa las flechas para cambiar el orden. Sin fotos la sección se oculta.')
                ->schema([
                    Repeater::make('galeria')
                        ->label('Fotos')
                        ->schema([
                            Hidden::make('id'),
                            ImagenRestauranteUpload::make('imagen', 'sitio', $ruta)->label('Foto')->required(),
                            TextInput::make('titulo')->label('Título')->required()->maxLength(SitioGaleria::MAX_TITULO),
                            TextInput::make('texto_alternativo')->label('Texto alternativo')->helperText('Describe la foto para quien no puede verla.')->required()->minLength(5)->maxLength(SitioGaleria::MAX_TEXTO_ALTERNATIVO),
                            TextInput::make('creditos')->label('Créditos')->helperText('Solo si la licencia lo exige.')->maxLength(SitioGaleria::MAX_CREDITOS),
                            Toggle::make('visible')->label('Visible en el sitio')->default(true),
                        ])
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['titulo'] ?? null)
                        ->maxItems(SitioGaleria::MAX_POR_RESTAURANTE)
                        ->addActionLabel('Agregar foto')
                        ->defaultItems(0),
                ]),
            Section::make('Reservaciones')
                ->schema([
                    Toggle::make('reservas_activas')
                        ->label('Mostrar la sección de reservaciones')
                        ->helperText('Si se desactiva, el sitio oculta el formulario y los botones de reserva.')
                        ->default(true),
                ]),
        ]);
    }
}
