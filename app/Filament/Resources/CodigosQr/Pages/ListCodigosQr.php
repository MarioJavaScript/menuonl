<?php

namespace App\Filament\Resources\CodigosQr\Pages;

use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Models\CodigoQr;
use App\Models\Scopes\RestauranteScope;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
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
                ->form([
                    TextInput::make('desde_mesa')
                        ->label('Desde mesa')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    TextInput::make('hasta_mesa')
                        ->label('Hasta mesa')
                        ->numeric()
                        ->minValue(1)
                        ->default(10)
                        ->required()
                        ->gte('desde_mesa'),
                ])
                ->action(function (array $data): void {
                    $desde = (int) $data['desde_mesa'];
                    $hasta = (int) $data['hasta_mesa'];
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
