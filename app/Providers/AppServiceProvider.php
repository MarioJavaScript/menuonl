<?php

namespace App\Providers;

use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Observers\AuditableObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Categoria::observe(AuditableObserver::class);
        Producto::observe(AuditableObserver::class);
        ProductoVariante::observe(AuditableObserver::class);
        Contacto::observe(AuditableObserver::class);
        Horario::observe(AuditableObserver::class);
        CodigoQr::observe(AuditableObserver::class);
        Restaurante::observe(AuditableObserver::class);
    }
}
