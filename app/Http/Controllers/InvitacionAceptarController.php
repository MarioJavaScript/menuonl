<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvitacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitacionAceptarController extends Controller
{
    public function __construct(private readonly InvitacionService $invitaciones) {}

    /**
     * Show the acceptance page. Every failure renders the same generic page.
     */
    public function mostrar(Request $request, string $token): View|Response
    {
        $invitacion = $this->invitaciones->buscarVigente($token);

        if ($invitacion === null) {
            return $this->invalida();
        }

        $existente = User::where('email', $invitacion->email)->first();

        if ($existente !== null && ! $request->user()?->is($existente)) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('invitaciones.aceptar', [
            'invitacion' => $invitacion,
            'token' => $token,
            'cuentaExistente' => $existente !== null,
            'sesionCorrecta' => $existente !== null && $request->user()?->is($existente),
            'rol' => $invitacion->rol === 'propietario' ? 'Propietario' : 'Editor',
        ]);
    }

    /**
     * Consume the invitation: link an existing account (signed in as the invited email) or create a new one.
     */
    public function aceptar(Request $request, string $token): RedirectResponse|Response
    {
        $invitacion = $this->invitaciones->buscarVigente($token);

        if ($invitacion === null) {
            return $this->invalida();
        }

        $existente = User::where('email', $invitacion->email)->first();

        if ($existente !== null) {
            // Possessing the link is not enough to attach a restaurant to an account that already exists.
            if (! $request->user()?->is($existente)) {
                return redirect()->route('invitaciones.mostrar', ['token' => $token]);
            }

            $usuario = $this->invitaciones->aceptar($invitacion, $existente);
        } else {
            $datos = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            ]);

            $usuario = $this->invitaciones->aceptar($invitacion, null, $datos['name'], $datos['password']);
        }

        if ($usuario === null) {
            return $this->invalida();
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('filament.admin.pages.dashboard', ['tenant' => $invitacion->restaurante]);
    }

    /**
     * One response for malformed, unknown, expired and already used tokens.
     */
    private function invalida(): Response
    {
        return response()->view('invitaciones.invalida', status: 404);
    }
}
