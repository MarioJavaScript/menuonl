<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Ficha Imprimible — {{ $restaurante->nombre }} ({{ $codigoQr->esMesa() ? 'Mesa ' . $codigoQr->mesa : 'Menú General' }})</title>
        <style>
            :root {
                --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            }
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            body {
                font-family: var(--font-sans);
                background-color: #f1f5f9;
                color: #0f172a;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 2rem 1rem;
            }
            .screen-actions {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            .btn-action,
            .btn-print {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background-color: #0f172a;
                color: #ffffff;
                font-size: 0.875rem;
                font-weight: 600;
                padding: 0.75rem 1.5rem;
                border-radius: 0.75rem;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                text-decoration: none;
                transition: background-color 0.15s, transform 0.1s;
            }
            .btn-action:hover,
            .btn-print:hover {
                background-color: #1e293b;
            }
            .btn-action:active,
            .btn-print:active {
                transform: scale(0.98);
            }
            .btn-back {
                background-color: #ffffff;
                color: #334155;
                border: 1px solid #cbd5e1;
            }
            .btn-back:hover {
                background-color: #f8fafc;
            }
            .print-hint {
                font-size: 0.75rem;
                color: #64748b;
                margin-bottom: 1.5rem;
                text-align: center;
            }
            .sheet-container {
                position: relative;
                background: #ffffff;
                width: 100%;
                max-width: 380px;
                padding: 2.5rem 2rem;
                border-radius: 1.5rem;
                text-align: center;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
            }
            .logo {
                max-width: 80px;
                max-height: 80px;
                object-fit: contain;
                border-radius: 0.75rem;
                margin: 0 auto 0.75rem auto;
                display: block;
            }
            .logo-fallback {
                width: 64px;
                height: 64px;
                border-radius: 1rem;
                background: #0f172a;
                color: #ffffff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                font-weight: 800;
                margin-bottom: 0.75rem;
            }
            .restaurant-name {
                font-size: 1.375rem;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 0.35rem;
                line-height: 1.2;
            }
            .badge-mesa {
                display: inline-block;
                padding: 0.35rem 1rem;
                background-color: #0f172a;
                color: #ffffff;
                font-size: 0.875rem;
                font-weight: 700;
                border-radius: 9999px;
                margin-bottom: 1.25rem;
                letter-spacing: 0.025em;
            }
            .qr-wrapper {
                margin: 0 auto 1.25rem auto;
                width: 230px;
                height: 230px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem;
                background: #ffffff;
                border-radius: 1rem;
                border: 1px solid #e2e8f0;
            }
            .qr-wrapper svg {
                width: 100%;
                height: 100%;
                display: block;
            }
            .steps {
                display: flex;
                flex-direction: column;
                gap: 0.35rem;
                margin-bottom: 1rem;
                text-align: left;
                background: #f8fafc;
                padding: 0.75rem 1rem;
                border-radius: 0.75rem;
                font-size: 0.75rem;
                color: #334155;
            }
            .steps-row {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .step-dot {
                width: 18px;
                height: 18px;
                border-radius: 9999px;
                background: #0f172a;
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.65rem;
                font-weight: 700;
                flex-shrink: 0;
            }
            .url {
                font-size: 0.8125rem;
                font-weight: 700;
                color: #0f172a;
                word-break: break-all;
                padding: 0.25rem 0.5rem;
                background: #f1f5f9;
                border-radius: 0.375rem;
                display: inline-block;
            }
            .footnote {
                font-size: 0.6875rem;
                color: #64748b;
                margin-top: 0.75rem;
            }

            /* Estilos de Impresión Fiel (@media print) */
            @media print {
                body {
                    background: transparent;
                    padding: 0;
                    margin: 0;
                    min-height: auto;
                    display: block;
                }
                .no-print {
                    display: none !important;
                }
                .sheet-container {
                    max-width: 100mm;
                    width: 100mm;
                    margin: 0 auto;
                    box-shadow: none;
                    border: 1px dashed #94a3b8;
                    border-radius: 0;
                    padding: 8mm 6mm;
                    page-break-inside: avoid;
                }
                .badge-mesa {
                    background: #000000 !important;
                    color: #ffffff !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .qr-wrapper {
                    border: 1px solid #000000;
                    width: 58mm;
                    height: 58mm;
                    padding: 2mm;
                }
                .steps {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .step-dot {
                    background: #000000 !important;
                    color: #ffffff !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .url {
                    background: transparent !important;
                    color: #000000 !important;
                    font-size: 9pt;
                }
            }
        </style>
    </head>
    <body>
        <div class="no-print screen-actions">
            <button class="btn-action btn-print" onclick="window.print()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir ficha de mesa
            </button>
            <button class="btn-action btn-back" onclick="window.close(); if(!window.closed) window.history.back();">
                Volver al panel
            </button>
        </div>

        <p class="no-print print-hint">
            Recomendación: Imprimir en tamaño real (escala 100%) sobre cartulina blanca (180g-250g) para insertar en el soporte acrílico de mesa.
        </p>

        <div class="sheet-container">
            @if (filled($restaurante->logo))
                <img src="{{ Storage::url($restaurante->logo) }}" alt="{{ $restaurante->nombre }}" class="logo">
            @else
                <div class="logo-fallback">
                    {{ strtoupper(substr($restaurante->nombre, 0, 2)) }}
                </div>
            @endif

            <h1 class="restaurant-name">{{ $restaurante->nombre }}</h1>

            <div class="badge-mesa">
                @if ($codigoQr->esMesa())
                    @if ($codigoQr->nombre && !str_contains($codigoQr->nombre, 'Mesa ' . $codigoQr->mesa))
                        {{ $codigoQr->nombre }} · Mesa {{ $codigoQr->mesa }}
                    @else
                        {{ $codigoQr->nombre ?: ('Mesa ' . $codigoQr->mesa) }}
                    @endif
                @else
                    {{ $codigoQr->nombre ?: 'Menú General' }}
                @endif
            </div>

            <div class="qr-wrapper">
                {!! $qrSvg !!}
            </div>

            <div class="steps">
                <div class="steps-row">
                    <span class="step-dot">1</span>
                    <span>Abre la cámara de tu teléfono móvil o lector de QR.</span>
                </div>
                <div class="steps-row">
                    <span class="step-dot">2</span>
                    <span>Apunta hacia el código o acerca tu teléfono si cuenta con NFC.</span>
                </div>
                <div class="steps-row">
                    <span class="step-dot">3</span>
                    <span>Explora la carta y haz tu pedido al instante.</span>
                </div>
            </div>

            <p class="url">{{ $url }}</p>
            <p class="footnote">Sin necesidad de descargar aplicaciones · {{ config('app.name') }}</p>
        </div>
    </body>
</html>
