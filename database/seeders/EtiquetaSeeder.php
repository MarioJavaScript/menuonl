<?php

namespace Database\Seeders;

use App\Models\Etiqueta;
use Illuminate\Database\Seeder;

class EtiquetaSeeder extends Seeder
{
    /**
     * Seed the fixed dietary tag catalog. Safe to run more than once.
     */
    public function run(): void
    {
        foreach (Etiqueta::CATALOGO as $codigo => $icono) {
            Etiqueta::updateOrCreate(['codigo' => $codigo], ['icono' => $icono]);
        }
    }
}
