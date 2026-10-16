<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces superadmins and propietarios to set up app-based two-factor authentication.
 *
 * Filament's own `isRequired` flag is evaluated once, when routes are registered and there is no
 * user, so it cannot depend on the role. This middleware does it per request.
 *
 * It is registered as persistent. On a Livewire update, Livewire verifies the signed snapshot and
 * then runs the persistent middleware against a copy of the request whose route is the page the
 * component was loaded on; a redirect from here is turned into a real redirect response. So a page
 * loaded before the requirement applied cannot keep running panel actions: only components of the
 * profile page (where the factor is configured) and the logout route are allowed.
 *
 * Limitation (documented by Filament): users who reach the panel already authenticated through a
 * route outside Filament's login page are not challenged for a factor they have configured; this
 * middleware only enforces that the factor is *set up*.
 */
class ExigeDosFactores
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->debeConfigurar($request->user()) || $request->routeIs('filament.*.auth.profile', 'filament.*.auth.logout')) {
            return $next($request);
        }

        return redirect()->to(Filament::getCurrentOrDefaultPanel()->getProfileUrl());
    }

    /**
     * Whether the user is required to have two-factor authentication and has not set it up yet.
     */
    private function debeConfigurar(mixed $usuario): bool
    {
        return config('menuonl.requerir_2fa')
            && $usuario instanceof User
            && $usuario->requiereDosFactores()
            && blank($usuario->getAppAuthenticationSecret());
    }
}
