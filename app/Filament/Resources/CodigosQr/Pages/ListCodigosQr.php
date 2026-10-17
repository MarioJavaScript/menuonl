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
use Illuminate\Support\Str;

class ListCodigosQr extends ListRecords
{
    protected static string $resource = CodigoQrResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                    $tipo = TipoCodigoAcceso::from($data['tipo'] instanceof TipoCodigoAcceso ? $data['tipo']->value : $data['tipo']);
                    $tenantId = Filament::getTenant()?->id ?? RestauranteScope::resolveRestauranteId();

                    $creadas = 0;
                    $omitidas = 0;

                    for ($m = $desde; $m <= $hasta; $m++) {
                        $mesaStr = (string) $m;
                        $existe = CodigoQr::where('restaurante_id', $tenantId)
                            ->where('mesa', $mesaStr)
                            ->exists();

                        if ($existe) {
                            $omitidas++;

                            continue;
                        }

                        do {
                            $codigo = Str::lower(Str::random(8));
                        } while (CodigoQr::withoutGlobalScopes()->where('codigo', $codigo)->exists());

                        CodigoQr::create([
                            'restaurante_id' => $tenantId,
                            'nombre' => "Mesa {$mesaStr}",
                            'mesa' => $mesaStr,
                            'tipo' => $tipo,
                            'codigo' => $codigo,
                            'activo' => true,
                        ]);

                        $creadas++;
                    }

                    Notification::make()
                        ->success()
                        ->title("Lote de mesas generado: {$creadas} creadas, {$omitidas} omitidas.")
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
