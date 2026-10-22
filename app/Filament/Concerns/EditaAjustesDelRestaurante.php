<?php

namespace App\Filament\Concerns;

use App\Models\Restaurante;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

/**
 * Página del panel que edita columnas del restaurante activo. Mismo permiso que "Datos del restaurante":
 * solo quien puede `update` sobre el restaurante (propietario y superadmin) la ve y la guarda.
 */
trait EditaAjustesDelRestaurante
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $restaurante = Filament::getTenant();

        return $restaurante instanceof Restaurante && (auth()->user()?->can('update', $restaurante) ?? false);
    }

    public function mount(): void
    {
        $this->form->fill($this->datosIniciales(Filament::getTenant()));
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 404);
    }

    /**
     * Valores que se muestran al abrir la pantalla.
     *
     * @return array<string, mixed>
     */
    protected function datosIniciales(Restaurante $restaurante): array
    {
        return $restaurante->attributesToArray();
    }

    /**
     * Ajusta lo que se guarda (por ejemplo, convertir un enlace en coordenadas).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function prepararDatos(Restaurante $restaurante, array $datos): array
    {
        return $datos;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $restaurante = Filament::getTenant();
        $datos = $this->prepararDatos($restaurante, $this->form->getState());

        $restaurante->update($datos);

        Notification::make()->success()->title(__('panel.comun.guardado'))->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model(Filament::getTenant())
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('panel.comun.guardar'))
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->sticky()
                    ->key('form-actions'),
            ]);
    }
}
