<?php

namespace App\Filament\Concerns;

use App\Filament\Components\SitioWebForm;
use App\Models\Restaurante;
use App\Services\SitioWebService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Lógica común de las dos pantallas del sitio (propietario y superadmin): formulario, carga y guardado.
 * La pantalla decide qué restaurante se edita (restauranteDelSitio) y quién puede entrar (autorizar).
 *
 * @property-read Schema $form
 */
trait GestionaSitioWeb
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    abstract protected function restauranteDelSitio(): Restaurante;

    /**
     * Aborta (403/404) si el usuario actual no puede editar el sitio de este restaurante.
     */
    abstract protected function autorizar(): void;

    protected function cargarFormulario(): void
    {
        $this->form->fill(app(SitioWebService::class)->datosParaFormulario($this->restauranteDelSitio()));
    }

    public function hydrate(): void
    {
        $this->autorizar();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model($this->restauranteDelSitio())
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return SitioWebForm::configure($schema);
    }

    /**
     * Una ruta de imagen enviada como texto solo es válida si ya estaba guardada para este restaurante.
     */
    public function rutaGuardadaDelSitio(string $ruta): bool
    {
        return in_array($ruta, app(SitioWebService::class)->rutasGuardadas($this->restauranteDelSitio()), true);
    }

    public function save(): void
    {
        $this->autorizar();

        $datos = $this->form->getState();

        try {
            app(SitioWebService::class)->guardar($this->restauranteDelSitio(), $datos);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $mensajes, string $campo): array => [
                    'data.'.($campo === 'imagen' ? 'galeria' : $campo) => $mensajes,
                ])->all()
            );
        }

        Notification::make()->success()->title('Sitio web actualizado')->send();

        $this->cargarFormulario();
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
                Actions::make([
                    Action::make('save')->label('Guardar cambios')->submit('save')->keyBindings(['mod+s']),
                ])->key('form-actions'),
            ]);
    }
}
