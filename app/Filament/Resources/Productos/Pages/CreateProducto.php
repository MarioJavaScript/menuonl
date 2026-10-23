<?php

namespace App\Filament\Resources\Productos\Pages;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use Filament\Resources\Pages\CreateRecord;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;

    /**
     * El orden ya no se escribe a mano: un platillo nuevo queda al final de su categoría y después se mueve arrastrando.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['orden'] = (int) Producto::query()->where('categoria_id', $data['categoria_id'] ?? null)->max('orden') + 1;

        return $data;
    }
}
