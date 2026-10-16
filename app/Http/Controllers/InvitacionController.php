<?php

namespace App\Http\Controllers;

use App\Models\Invitacion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvitacionController extends Controller
{
    /**
     * Show or automatically accept invitation if user is authenticated.
     */
    public function show(Request $request, string $token): Response
    {
        $invitacion = $this->findInvitation($token);

        $user = $request->user();

        if ($user) {
            $this->attachUserToRestaurant($user, $invitacion);

            $slug = $invitacion->restaurante?->slug ?? '';

            return redirect("/admin/{$slug}");
        }

        // Guest user: redirect to login preserving redirect URL
        return redirect()->guest('/admin/login?redirect_to='.urlencode("/invitacion/{$token}"));
    }

    /**
     * Explicitly accept invitation for authenticated user.
     */
    public function accept(Request $request, string $token): Response
    {
        $invitacion = $this->findInvitation($token);

        $user = $request->user();
        if (! $user) {
            return redirect()->guest('/admin/login?redirect_to='.urlencode("/invitacion/{$token}"));
        }

        $this->attachUserToRestaurant($user, $invitacion);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Invitación aceptada correctamente.',
                'restaurante' => $invitacion->restaurante,
            ]);
        }

        $slug = $invitacion->restaurante?->slug ?? '';

        return redirect("/admin/{$slug}");
    }

    /**
     * Find and validate the invitation token.
     */
    protected function findInvitation(string $token): Invitacion
    {
        $invitacion = Invitacion::withoutGlobalScopes()
            ->where(function ($query) use ($token) {
                $query->where('token', $token)
                    ->orWhere('token', hash('sha256', $token));
            })
            ->first();

        if (! $invitacion) {
            abort(404, 'Invitación no encontrada.');
        }

        if ($invitacion->aceptada_en !== null) {
            abort(410, 'Esta invitación ya ha sido aceptada.');
        }

        if ($invitacion->isExpired() || ($invitacion->expira_en && $invitacion->expira_en->isPast())) {
            abort(410, 'Esta invitación ha expirado.');
        }

        return $invitacion;
    }

    /**
     * Attach user to the restaurant with the designated role.
     */
    protected function attachUserToRestaurant($user, Invitacion $invitacion): void
    {
        $pivotAttributes = [
            'rol' => $invitacion->rol,
            'user_id' => $user->id,
            'usuario_id' => $user->id,
        ];

        if (! $user->restaurantes()->where('restaurantes.id', $invitacion->restaurante_id)->exists()) {
            $user->restaurantes()->attach($invitacion->restaurante_id, $pivotAttributes);
        } else {
            $user->restaurantes()->updateExistingPivot($invitacion->restaurante_id, $pivotAttributes);
        }

        $invitacion->update(['aceptada_en' => now()]);
    }
}
