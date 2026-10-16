<?php

namespace App\Filament\Resources\CodigoQrResource\Pages;

use App\Filament\Resources\CodigoQrResource;
use App\Models\CodigoQr;
use App\Models\Scopes\RestauranteScope;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListCodigoQrs extends ListRecords
{
    protected static string $resource = CodigoQrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('generarRango')
                ->label('Generar Rango de Mesas')
                ->icon('heroicon-o-squares-plus')
                ->modalHeading('Generar Códigos QR por Rango de Mesas')
                ->form([
                    TextInput::make('desde')
                        ->label('Mesa Inicial')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->required(),
                    TextInput::make('hasta')
                        ->label('Mesa Final')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->rules(['gte:desde'])
                        ->required(),
                    TextInput::make('prefijo')
                        ->label('Prefijo de Nombre')
                        ->default('Mesa')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $tenantId = RestauranteScope::getRestauranteId()
                        ?? Filament::getTenant()?->id;

                    $desde = (int) $data['desde'];
                    $hasta = (int) $data['hasta'];
                    $prefijo = $data['prefijo'];

                    if ($desde > $hasta) {
                        Notification::make()
                            ->title('Rango inválido')
                            ->body('El valor final debe ser mayor o igual al valor inicial.')
                            ->danger()
                            ->send();

                        return;
                    }

                    for ($i = $desde; $i <= $hasta; $i++) {
                        $codigo = strtolower(Str::random(16));
                        while (CodigoQr::withoutGlobalScopes()->where('codigo', $codigo)->exists()) {
                            $codigo = strtolower(Str::random(16));
                        }

                        CodigoQr::create([
                            'restaurante_id' => $tenantId,
                            'nombre' => "{$prefijo} {$i}",
                            'tipo' => 'mesa',
                            'mesa' => (string) $i,
                            'codigo' => $codigo,
                            'activo' => true,
                        ]);
                    }

                    Notification::make()
                        ->title('Códigos QR generados con éxito')
                        ->body("Se generaron los códigos para las mesas {$desde} a {$hasta}.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
