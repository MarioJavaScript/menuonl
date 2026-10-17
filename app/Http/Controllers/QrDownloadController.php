<?php

namespace App\Http\Controllers;

use App\Models\CodigoQr;
use App\Services\QrGeneratorService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QrDownloadController extends Controller
{
    /**
     * Download or render a QR code in SVG, PNG, or printable PDF/sheet format.
     */
    public function descargar(Request $request, CodigoQr $codigoQr, string $formato, QrGeneratorService $qrService): Response
    {
        Gate::authorize('view', $codigoQr);

        $user = $request->user();

        // Multi-tenant check: if tenant context is active, verify match. Non-superadmins must belong to the restaurant.
        $tenant = Filament::getTenant();
        if ($tenant !== null && (int) $codigoQr->restaurante_id !== (int) $tenant->id) {
            abort(403, 'No autorizado para acceder a este código QR.');
        }

        if ($user !== null && ! $user->isSuperadmin() && $user->rolEn($codigoQr->restaurante_id) === null) {
            abort(403, 'No autorizado para acceder a este código QR.');
        }

        abort_unless(in_array($formato, ['svg', 'png', 'pdf'], true), 404);

        $url = $codigoQr->urlCorta();

        return match ($formato) {
            'svg' => response($qrService->svg($url), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="qr-'.$codigoQr->codigo.'.svg"',
            ]),
            'png' => response($qrService->png($url), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="qr-'.$codigoQr->codigo.'.png"',
            ]),
            'pdf' => response()->view('qr.imprimible', [
                'codigoQr' => $codigoQr,
                'restaurante' => $codigoQr->restaurante,
                'qrSvg' => $qrService->svg($url),
                'url' => $url,
            ], 200),
        };
    }
}
