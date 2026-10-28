<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\ConfiarEnProxiesConfigurados;
use App\Services\WebpImageService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->replace(TrustProxies::class, ConfiarEnProxiesConfigurados::class);
        $middleware->append(CabecerasDeSeguridad::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Cuerpo mayor que post_max_size: ValidatePostSize lo corta con 413 antes de enrutar. La API vieja del panel ya no existe,
        // así que una subida grande de una pestaña antigua recibe el 410 acordado y no un 413. En la subida temporal de
        // Livewire se responde con el mismo formato de errores que el resto, para mostrar un mensaje claro y no uno genérico.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/v1/panel', 'api/v1/panel/*')) {
                return response()->json(['message' => 'Esta versión del panel ya no existe. Recarga la página.'], 410)->header('Cache-Control', 'no-store');
            }

            $mensaje = app(WebpImageService::class)->mensajeLimiteDelServidor();

            if ($request->isMethod('POST') && $request->is('livewire*/upload-file')) {
                return response()->json(['message' => $mensaje, 'errors' => ['files.0' => [$mensaje]]], 422);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
