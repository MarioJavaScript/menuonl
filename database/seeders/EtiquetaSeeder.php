<?php

namespace Database\Seeders;

use App\Models\Etiqueta;
use Illuminate\Database\Seeder;

class EtiquetaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $etiquetas = [
            [
                'nombre' => 'Vegano',
                'slug' => 'vegano',
                'codigo' => 'vegano',
                'icono' => 'leaf',
                'color' => '#16a34a',
                'activo' => true,
            ],
            [
                'nombre' => 'Vegetariano',
                'slug' => 'vegetariano',
                'codigo' => 'vegetariano',
                'icono' => 'carrot',
                'color' => '#65a30d',
                'activo' => true,
            ],
            [
                'nombre' => 'Picante',
                'slug' => 'picante',
                'codigo' => 'picante',
                'icono' => 'flame',
                'color' => '#dc2626',
                'activo' => true,
            ],
            [
                'nombre' => 'Sin Gluten',
                'slug' => 'sin_gluten',
                'codigo' => 'sin_gluten',
                'icono' => 'wheat-off',
                'color' => '#d97706',
                'activo' => true,
            ],
            [
                'nombre' => 'Sin Lactosa',
                'slug' => 'sin_lactosa',
                'codigo' => 'sin_lactosa',
                'icono' => 'milk-off',
                'color' => '#0284c7',
                'activo' => true,
            ],
            [
                'nombre' => 'Nuevo',
                'slug' => 'nuevo',
                'codigo' => 'nuevo',
                'icono' => 'sparkles',
                'color' => '#8b5cf6',
                'activo' => true,
            ],
            [
                'nombre' => 'Recomendado',
                'slug' => 'recomendado',
                'codigo' => 'recomendado',
                'icono' => 'star',
                'color' => '#eab308',
                'activo' => true,
            ],
        ];

        foreach ($etiquetas as $etiqueta) {
            Etiqueta::firstOrCreate(
                ['slug' => $etiqueta['slug']],
                $etiqueta
            );
        }
    }
}
