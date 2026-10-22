<?php

namespace App\Filament\Pages;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Filament\Concerns\EditaAjustesDelRestaurante;
use App\Services\AparienciaSitio;
use App\Services\Catalogos;
use App\Services\WebpImageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ViewField;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Apariencia: primero se elige el diseño del sitio y después solo se muestran las opciones que le aplican
 * (estilo del menú, color principal, logotipo y portada).
 */
class Apariencia extends Page
{
    use EditaAjustesDelRestaurante;

    protected static ?string $slug = 'apariencia';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.grupos.apariencia');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.apariencia.nav');
    }

    public function getTitle(): string
    {
        return __('panel.apariencia.titulo');
    }

    public function getSubheading(): ?string
    {
        return __('panel.apariencia.subtitulo');
    }

    public function form(Schema $schema): Schema
    {
        $fichas = AparienciaSitio::fichas();
        $imagenes = app(WebpImageService::class);

        return $schema->components([
            Section::make(__('panel.apariencia.diseno.titulo'))
                ->description(__('panel.apariencia.diseno.descripcion'))
                ->schema([
                    ViewField::make('sitio_plantilla')
                        ->label(__('panel.apariencia.diseno.campo'))
                        ->view('filament.forms.selector-plantilla')
                        ->viewData(['fichas' => $fichas])
                        ->in(array_keys(Catalogos::plantillasSitio()))
                        ->default('a')
                        ->live()
                        ->required(),
                ]),
            Section::make(__('panel.apariencia.estilo.titulo'))
                ->description(__('panel.apariencia.estilo.descripcion'))
                ->visible(fn (Get $get): bool => AparienciaSitio::usaEstilo($get('sitio_plantilla')))
                ->schema([
                    Radio::make('tema')
                        ->label(__('panel.apariencia.estilo.campo'))
                        ->options(Catalogos::nombresDeTemas())
                        ->descriptions(array_map(fn (array $tema): string => $tema['descripcion'], Catalogos::temas()))
                        ->in(array_keys(Catalogos::temas()))
                        ->default('calido')
                        ->required(),
                ]),
            Section::make(__('panel.apariencia.color.titulo'))
                ->description(__('panel.apariencia.color.descripcion'))
                ->schema([
                    ColorPicker::make('color_acento')
                        ->label(__('panel.apariencia.color.campo'))
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->validationMessages(['regex' => __('panel.apariencia.color.invalido')])
                        ->live(),
                    Text::make(fn (Get $get): string => AparienciaSitio::estadoDelColor($get('sitio_plantilla'), $get('color_acento'))['mensaje']),
                    Actions::make([
                        Action::make('usar_color_original')
                            ->label(__('panel.apariencia.color.quitar'))
                            ->color('gray')
                            ->visible(fn (Get $get): bool => filled($get('color_acento')))
                            ->action(fn (Set $set) => $set('color_acento', null)),
                    ]),
                ]),
            Section::make(__('panel.apariencia.imagenes.titulo'))
                ->description(__('panel.apariencia.imagenes.descripcion'))
                ->schema([
                    ImagenRestauranteUpload::make('logo', 'logotipo')
                        ->label(__('panel.apariencia.imagenes.logo'))
                        ->helperText($imagenes->ayudaDeSubida('logotipo').' '.__('panel.apariencia.imagenes.logo_ayuda')),
                    ImagenRestauranteUpload::make('portada', 'portada')
                        ->label(__('panel.apariencia.imagenes.portada'))
                        ->helperText($imagenes->ayudaDeSubida('portada').' '.__('panel.apariencia.imagenes.portada_ayuda')),
                ])
                ->columns(2),
        ]);
    }
}
