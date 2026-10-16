<?php

namespace App\Filament\Resources\CodigosQr\Tables;

use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Models\CodigoQr;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CodigosQrTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('mesa')->label('Mesa'),
                TextColumn::make('codigo')->label('Código'),
                TextColumn::make('url_corta')
                    ->label('URL Corta')
                    ->state(fn (CodigoQr $record): string => url("/q/{$record->codigo}"))
                    ->url(fn (CodigoQr $record): string => url("/q/{$record->codigo}"))
                    ->copyable()
                    ->openUrlInNewTab(),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                Action::make('descargarSvg')
                    ->label('SVG')
                    ->tooltip('Descargar vector en formato SVG')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (CodigoQr $record): string => route('codigos-qr.descargar', ['codigoQr' => $record, 'formato' => 'svg']))
                    ->openUrlInNewTab(),
                Action::make('descargarPng')
                    ->label('PNG')
                    ->tooltip('Descargar imagen en formato PNG')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (CodigoQr $record): string => route('codigos-qr.descargar', ['codigoQr' => $record, 'formato' => 'png'])),
                Action::make('imprimirFicha')
                    ->label('Ficha')
                    ->tooltip('Abrir ficha imprimible optimizada')
                    ->icon('heroicon-o-printer')
                    ->url(fn (CodigoQr $record): string => route('codigos-qr.descargar', ['codigoQr' => $record, 'formato' => 'pdf']))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Sin códigos QR generados')
            ->emptyStateDescription('Genera un lote de mesas o crea códigos individuales para permitir que tus comensales accedan al menú escaneando el código desde su mesa.')
            ->emptyStateIcon('heroicon-o-qr-code')
            ->emptyStateActions([
                Action::make('crear')
                    ->label('Crear código individual')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(fn (): string => CodigoQrResource::getUrl('create')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
