<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\ConfiarEnProxiesConfigurados;
use App\Http\Middleware\ContextoRestaurantePanel;
use App\Services\WebpImageService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $middleware->prependToPriorityList(SubstituteBindings::class, ContextoRestaurantePanel::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API vieja del panel ya no existe. ValidatePostSize y la protección CSRF corren antes que las rutas, así que un 413,
        // un 419 o cualquier error HTTP de una pestaña antigua debe salir como el 410 acordado, sea cual sea el método o la sesión.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/v1/panel', 'api/v1/panel/*') && ($e instanceof HttpExceptionInterface || $e instanceof TokenMismatchException)) {
                return response()->json(['message' => 'Esta versión del panel ya no existe. Recarga la página.'], 410)->header('Cache-Control', 'no-store');
            }

            return null;
        });

        // Cuerpo mayor que post_max_size: Laravel lo corta con 413 antes del controlador. En las subidas de fotos se responde
        // con el mismo formato de errores que el resto, para que el panel muestre un mensaje claro y no uno genérico.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
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
