<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;

/**
 * Panel home. Keeps the `dashboard` slug so the route name (filament.admin.pages.dashboard) does not change.
 */
class Inicio extends Dashboard
{
    protected static ?string $slug = 'dashboard';

    protected static ?string $title = 'Inicio';

    public function getSubheading(): ?string
    {
        $restaurante = Filament::getTenant();

        return $restaurante ? "Lo que necesita tu atención hoy en {$restaurante->nombre}." : null;
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 1;
    }
}
