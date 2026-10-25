<?php

namespace App\Http\Controllers;

use App\Models\CodigoQr;
use App\Rules\CodigoQrValido;
use App\Services\PoliticaDeContenido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrRedirectController extends Controller
{
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => PoliticaDeContenido::MENU,
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
    ];

    /**
     * Redirect a short QR code or NFC tag to the public menu or specific table.
     */
    public function redirigir(Request $request, string $codigo): Response|RedirectResponse
    {
        if (preg_match(CodigoQrValido::PATRON, $codigo) !== 1) {
            return response()
                ->view('menu.no-disponible', [], 404)
                ->withHeaders(self::SECURITY_HEADERS);
        }

        $qr = CodigoQr::withoutGlobalScopes()
            ->where('codigo', $codigo)
            ->with(['restaurante' => fn ($query) => $query->withTrashed()])
            ->first();

        if (
            $qr === null
            || ! $qr->isActivo()
            || $qr->restaurante === null
            || ! $qr->restaurante->isActivo()
            || $qr->restaurante->trashed()
        ) {
            return response()
                ->view('menu.no-disponible', [], 404)
                ->withHeaders(self::SECURITY_HEADERS);
        }

        if ($qr->esMesa()) {
            return redirect()->route('menu.publico', [
                'slug' => $qr->restaurante->slug,
                'mesa' => $qr->mesa,
            ], 302);
        }

        return redirect()->route('menu.publico', [
            'slug' => $qr->restaurante->slug,
        ], 302);
    }
}
