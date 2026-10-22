<?php

namespace App\Filament\Resources\CodigosQr\Pages;

use App\Enums\TipoCodigoAcceso;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Models\CodigoQr;
use App\Models\Scopes\RestauranteScope;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListCodigosQr extends ListRecords
{
    protected static string $resource = CodigoQrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('crearQrMesas')
                ->label(__('panel.qr.mesas.accion'))
                ->icon('heroicon-o-qr-code')
                ->modalHeading(__('panel.qr.mesas.titulo'))
                ->modalDescription(__('panel.qr.mesas.descripcion'))
                ->modalSubmitActionLabel(__('panel.qr.mesas.boton'))
                ->schema([
                    TextInput::make('cantidad')
                        ->label(__('panel.qr.mesas.cantidad'))
                        ->helperText(__('panel.qr.mesas.cantidad_ayuda'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(10)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $tenantId = Filament::getTenant()?->id ?? RestauranteScope::resolveRestauranteId();

                    if ($tenantId === null) {
                        Notification::make()->danger()->title('No hay un restaurante activo seleccionado.')->send();

                        return;
                    }

                    ['creadas' => $creadas, 'omitidas' => $omitidas] = $this->crearMesas(
                        array_map('strval', range(1, (int) $data['cantidad'])),
                        TipoCodigoAcceso::Qr,
                        $tenantId,
                    );

                    $aviso = Notification::make()->success()->title(trans_choice('panel.qr.mesas.resultado', $creadas, ['n' => $creadas]));

                    if ($omitidas > 0) {
                        $aviso->body(__('panel.qr.mesas.omitidas', ['n' => $omitidas]));
                    }

                    $aviso->send();
                }),
            Action::make('generarLoteMesas')
                ->label('Generar lote de mesas')
                ->icon('heroicon-o-squares-plus')
                ->modalHeading('Generar lote de códigos QR para mesas')
                ->modalDescription('Crea de forma masiva códigos QR únicos para un rango continuo de mesas. Si alguna mesa ya existe para este restaurante, se conservará intacta.')
                ->modalSubmitActionLabel('Generar códigos QR')
                ->modalIcon('heroicon-o-squares-plus')
                ->form([
                    TextInput::make('desde_mesa')
                        ->label('Mesa inicial')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required()
                        ->helperText('Número de la primera mesa del rango (ej. 1).'),
                    TextInput::make('hasta_mesa')
                        ->label('Mesa final')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(500)
                        ->default(10)
                        ->required()
                        ->gte('desde_mesa')
                        ->helperText('Número de la última mesa del rango (ej. 20).'),
                    Select::make('tipo')
                        ->label('Tipo de código')
                        ->options(TipoCodigoAcceso::class)
                        ->default(TipoCodigoAcceso::Qr->value)
                        ->required()
                        ->enum(TipoCodigoAcceso::class)
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $desde = (int) $data['desde_mesa'];
                    $hasta = (int) $data['hasta_mesa'];

                    if ($hasta - $desde > 250) {
                        Notification::make()
                            ->danger()
                            ->title('El tamaño máximo por lote es de 250 mesas a la vez.')
                            ->send();

                        return;
                    }

                    $tipo = TipoCodigoAcceso::from($data['tipo'] instanceof TipoCodigoAcceso ? $data['tipo']->value : $data['tipo']);
                    $tenantId = Filament::getTenant()?->id ?? RestauranteScope::resolveRestauranteId();

                    if ($tenantId === null) {
                        Notification::make()
                            ->danger()
                            ->title('No hay un restaurante activo seleccionado.')
                            ->send();

                        return;
                    }

                    ['creadas' => $creadas, 'omitidas' => $omitidas] = $this->crearMesas(array_map('strval', range($desde, $hasta)), $tipo, $tenantId);

                    Notification::make()
                        ->success()
                        ->title("Lote de mesas generado: {$creadas} creadas, {$omitidas} omitidas.")
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    /**
     * Crea un código por mesa que aún no tiene uno en este restaurante; las mesas existentes no se tocan.
     *
     * @param  list<string>  $mesas
     * @return array{creadas: int, omitidas: int}
     */
    private function crearMesas(array $mesas, TipoCodigoAcceso $tipo, int $tenantId): array
    {
        // Consulta en lote único: evita N consultas de existencia
        $mesasExistentes = CodigoQr::where('restaurante_id', $tenantId)
            ->whereIn('mesa', $mesas)
            ->pluck('mesa')
            ->flip()
            ->all();

        $creadas = 0;

        DB::transaction(function () use ($mesas, $mesasExistentes, $tenantId, $tipo, &$creadas): void {
            foreach ($mesas as $mesa) {
                if (isset($mesasExistentes[$mesa])) {
                    continue;
                }

                do {
                    $codigo = Str::lower(Str::random(8));
                } while (CodigoQr::withoutGlobalScopes()->where('codigo', $codigo)->exists());

                CodigoQr::create([
                    'restaurante_id' => $tenantId,
                    'nombre' => "Mesa {$mesa}",
                    'mesa' => $mesa,
                    'tipo' => $tipo,
                    'codigo' => $codigo,
                    'activo' => true,
                ]);

                $creadas++;
            }
        });

        return ['creadas' => $creadas, 'omitidas' => count($mesasExistentes)];
    }
}
