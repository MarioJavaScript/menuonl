<?php

namespace App\Providers;

use App\Http\Responses\LoginRespuesta;
use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Observers\AuditableObserver;
use App\Rules\SubidaRecibida;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, LoginRespuesta::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Subida temporal de Livewire/Filament (config/livewire.php): explica por qué falló (límite del servidor, subida incompleta, archivo temporal).
        Validator::extend('subida_recibida', function (string $attribute, mixed $value, array $parameters, ValidatorContract $validator): bool {
            $motivo = null;

            (new SubidaRecibida)->validate($attribute, $value, function (string $mensaje) use (&$motivo): void {
                $motivo ??= $mensaje;
            });

            if ($motivo !== null) {
                $validator->errors()->add($attribute, $motivo);
            }

            return true;
        });

        RateLimiter::for('invitaciones', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('menu-publico', fn (Request $request): Limit => Limit::perMinute(300)->by($request->ip()));

        RateLimiter::for('reservas-publicas', fn (Request $request): array => [
            Limit::perMinute(3)->by('reserva:min:'.$request->ip()),
            Limit::perHour(10)->by('reserva:hora:'.$request->ip().':'.$request->route('slug')),
        ]);

        Categoria::observe(AuditableObserver::class);
        Producto::observe(AuditableObserver::class);
        ProductoVariante::observe(AuditableObserver::class);
        Contacto::observe(AuditableObserver::class);
        Horario::observe(AuditableObserver::class);
        CodigoQr::observe(AuditableObserver::class);
        Restaurante::observe(AuditableObserver::class);
    }
}
