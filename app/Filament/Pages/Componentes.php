<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Design system sample for the panel (DISEÑO.md). Only reachable in the local environment and never
 * listed in the navigation; it stores nothing.
 */
class Componentes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Componentes';

    protected static ?string $slug = 'componentes';

    protected static bool $shouldRegisterNavigation = false;

    protected ?string $subheading = 'Muestra de cada componente del panel. Cambia la apariencia en tu menú de usuario.';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app()->isLocal();
    }

    public function mount(): void
    {
        $this->form->fill([
            'nombre' => 'Tacos al pastor',
            'precio' => '85.50',
            'disponible' => true,
            'recomendado' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Platillo')
                    ->tabs([
                        Tab::make('Lo básico')
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre')
                                    ->placeholder('Ej. Tacos al pastor')
                                    ->required(),
                                TextInput::make('precio')
                                    ->label('Precio')
                                    ->prefix('$')
                                    ->helperText('Lo que pagas por una porción. Ej. 85.50'),
                                Select::make('categoria')
                                    ->label('Categoría')
                                    ->helperText('Dónde aparece en tu menú.')
                                    ->options(['tacos' => 'Tacos', 'bebidas' => 'Bebidas', 'postres' => 'Postres']),
                                Textarea::make('descripcion')
                                    ->label('Descripción (opcional)')
                                    ->placeholder('Ej. Tres tacos con piña, cebolla y cilantro.')
                                    ->rows(3),
                                Toggle::make('disponible')
                                    ->label('Disponible')
                                    ->helperText('Apágalo cuando se acabe; se mostrará como "Agotado".'),
                                Toggle::make('recomendado')
                                    ->label('Recomendado')
                                    ->helperText('Aparece al principio de tu menú.'),
                                TextInput::make('con_error')
                                    ->label('Precio de oferta (con error de ejemplo)')
                                    ->default('120')
                                    ->hint('Así se ve un error')
                                    ->extraInputAttributes(['aria-invalid' => 'true'])
                                    ->helperText('El precio de oferta debe ser menor que el precio normal.'),
                            ])
                            ->columns(2),
                        Tab::make('Foto')
                            ->schema([
                                FileUpload::make('foto')
                                    ->label('Foto')
                                    ->image()
                                    ->helperText('Una foto de tu celular sirve. Hasta 5 MB.')
                                    ->disk('local')
                                    ->directory('componentes-muestra'),
                            ]),
                        Tab::make('Opciones')
                            ->schema([
                                EmptyState::make('Sin opciones')
                                    ->description('Si se vende en tamaños con precio propio, agrégalos aquí.')
                                    ->icon(Heroicon::OutlinedSquares2x2),
                            ]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Botones')
                    ->description('Una sola acción principal por pantalla. El rojo sólido solo aparece dentro de la confirmación.')
                    ->schema([
                        Actions::make([
                            Action::make('guardar')
                                ->label('Guardar cambios')
                                ->action(fn () => Notification::make()->success()->title('Cambios guardados')->send()),
                            Action::make('cancelar')
                                ->label('Cancelar')
                                ->color('gray'),
                            Action::make('eliminar')
                                ->label('Eliminar platillo')
                                ->icon(Heroicon::OutlinedTrash)
                                ->color('gray')
                                ->requiresConfirmation()
                                ->modalHeading('¿Eliminar "Tacos al pastor"?')
                                ->modalDescription('Dejará de verse en tu menú. Puedes recuperarlo desde "Eliminados".')
                                ->modalSubmitActionLabel('Eliminar platillo')
                                ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                                ->action(fn () => Notification::make()->success()->title('Platillo eliminado')->send()),
                            ActionGroup::make([
                                Action::make('duplicar')->label('Duplicar platillo')->icon(Heroicon::OutlinedDocumentDuplicate),
                                Action::make('ocultar')->label('Ocultar del menú')->icon(Heroicon::OutlinedEyeSlash),
                            ])->label('Más acciones')->button()->color('gray'),
                        ]),
                    ]),

                Section::make('Formulario')
                    ->description('Etiqueta arriba, ayuda breve debajo y error con icono.')
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')]),
                    ]),

                Section::make('Avisos')
                    ->description('Fijos en la pantalla y temporales tras una acción.')
                    ->schema([
                        Callout::make('Activaste los pedidos, pero falta tu WhatsApp')
                            ->description('Sin él no te llegan los pedidos.')
                            ->warning(),
                        Callout::make('No pudimos guardar los cambios')
                            ->description('Tu información sigue aquí. Revisa tu conexión y vuelve a intentarlo.')
                            ->danger(),
                        Callout::make('Tu menú está en línea')
                            ->description('Los cambios se ven en segundos.')
                            ->success(),
                        Actions::make([
                            Action::make('avisoExito')
                                ->label('Mostrar aviso de éxito')
                                ->color('gray')
                                ->action(fn () => Notification::make()->success()->title('Precio de Tacos al pastor actualizado')->send()),
                            Action::make('avisoError')
                                ->label('Mostrar aviso de error')
                                ->color('gray')
                                ->action(fn () => Notification::make()->danger()->title('No pudimos guardar el precio')->body('Escribe un precio válido, por ejemplo 85.50.')->send()),
                        ]),
                    ]),

                Section::make('Tabla')
                    ->description('En el celular cada fila se vuelve una tarjeta.')
                    ->schema([
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [
                1 => ['nombre' => 'Tacos al pastor', 'categoria' => 'Tacos', 'precio' => 85.5, 'disponible' => true],
                2 => ['nombre' => 'Agua de horchata', 'categoria' => 'Bebidas', 'precio' => 35, 'disponible' => true],
                3 => ['nombre' => 'Flan napolitano', 'categoria' => 'Postres', 'precio' => 1234.5, 'disponible' => false],
            ])
            ->columns([
                TextColumn::make('nombre')
                    ->label('Platillo')
                    ->weight('bold')
                    ->description(fn (array $record): string => $record['categoria']),
                TextColumn::make('precio')
                    ->label('Precio')
                    ->money('MXN', locale: 'es-MX')
                    ->alignEnd(),
                ToggleColumn::make('disponible')
                    ->label('Disponible')
                    ->disabled(),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->extraAttributes(fn (array $record): array => ['aria-label' => 'Editar '.$record['nombre']]),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Todavía no tienes platillos')
            ->emptyStateDescription('Agrega el primero; puedes completar la foto y las opciones después.');
    }
}
