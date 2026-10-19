<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\InvitacionService;
use App\Services\PermisosPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Team management from Ajustes: invite by email, change role, deactivate. Only for the "ajustes" module
 * (propietario, gerente, superadmin).
 */
class EquipoController extends Controller
{
    public function index(Request $request, Restaurante $restaurante): JsonResponse
    {
        $miembros = $restaurante->usuarios()->orderBy('name')->get()->map(fn (User $u): array => [
            'id' => $u->id,
            'nombre' => $u->name,
            'email' => $u->email,
            'rol' => $u->pivot->rol,
            'activo' => (bool) $u->pivot->activo,
            'es_yo' => $u->is($request->user()),
        ])->values();

        $pendientes = Invitacion::withoutGlobalScopes()
            ->where('restaurante_id', $restaurante->id)
            ->whereNull('aceptada_en')
            ->where('expira_en', '>', now())
            ->orderBy('email')
            ->get(['id', 'email', 'rol'])
            ->map(fn (Invitacion $i): array => ['id' => $i->id, 'email' => $i->email, 'rol' => $i->rol])
            ->values();

        return response()->json(['data' => [
            'miembros' => $miembros,
            'invitaciones' => $pendientes,
            'roles_asignables' => PermisosPanel::rolesAsignables($request->user(), $restaurante->id),
        ]]);
    }

    public function invitar(Request $request, Restaurante $restaurante, InvitacionService $invitaciones): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'rol' => ['required', Rule::in(PermisosPanel::rolesAsignables($request->user(), $restaurante->id))],
        ]);

        $invitacion = $invitaciones->invitar($restaurante, $datos['email'], $datos['rol'], $request->user());

        return response()->json(['data' => ['id' => $invitacion->id, 'email' => $invitacion->email, 'rol' => $invitacion->rol]], 201);
    }

    public function update(Request $request, Restaurante $restaurante, User $usuario): JsonResponse
    {
        $actual = DB::table('restaurante_usuario')->where('restaurante_id', $restaurante->id)->where('user_id', $usuario->id)->first();

        if ($actual === null) {
            abort(404, 'Usuario no encontrado.');
        }

        $asignables = PermisosPanel::rolesAsignables($request->user(), $restaurante->id);

        $datos = $request->validate([
            'rol' => ['sometimes', 'required', Rule::in($asignables)],
            'activo' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($usuario->is($request->user())) {
            return response()->json(['message' => 'No puedes cambiar tu propio rol ni desactivarte.'], 422);
        }

        if (! in_array($actual->rol, $asignables, true)) {
            return response()->json(['message' => 'Solo un propietario puede modificar a otro propietario.'], 403);
        }

        $restaurante->usuarios()->updateExistingPivot($usuario->id, $datos);
        $usuario->olvidarRoles();

        return response()->json(['data' => ['id' => $usuario->id, 'rol' => $datos['rol'] ?? $actual->rol, 'activo' => (bool) ($datos['activo'] ?? $actual->activo)]]);
    }

    public function revocar(Restaurante $restaurante, int $invitacion): JsonResponse
    {
        Invitacion::withoutGlobalScopes()
            ->where('restaurante_id', $restaurante->id)
            ->whereNull('aceptada_en')
            ->whereKey($invitacion)
            ->delete();

        return response()->json(null, 204);
    }
}
