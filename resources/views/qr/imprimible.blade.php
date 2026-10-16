<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Ficha QR — {{ $restaurante->nombre }} ({{ $codigoQr->esMesa() ? 'Mesa ' . $codigoQr->mesa : 'Menú General' }})</title>
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
            .no-print {
                margin-bottom: 1.5rem;
            }
            .btn-print {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background-color: #0f172a;
                color: #ffffff;
                font-size: 0.875rem;
                font-weight: 600;
                padding: 0.625rem 1.25rem;
                border-radius: 0.5rem;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                transition: background-color 0.2s, transform 0.1s;
            }
            .btn-print:hover {
                background-color: #334155;
            }
            .btn-print:active {
                transform: scale(0.98);
            }
            .sheet {
                background: #ffffff;
                width: 100%;
                max-width: 380px;
                padding: 2.5rem 2rem;
                border-radius: 1.25rem;
                text-align: center;
                border: 2px dashed #cbd5e1;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            }
            .logo {
                max-width: 90px;
                max-height: 90px;
                object-fit: contain;
                border-radius: 0.75rem;
                margin: 0 auto 1rem auto;
                display: block;
            }
            .logo-fallback {
                width: 72px;
                height: 72px;
                border-radius: 1rem;
                background: #0f172a;
                color: #ffffff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }
            .restaurant-name {
                font-size: 1.5rem;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 0.5rem;
                line-height: 1.2;
            }
            .badge {
                display: inline-block;
                padding: 0.35rem 1rem;
                background-color: #f1f5f9;
                color: #0f172a;
                font-size: 0.875rem;
                font-weight: 700;
                border-radius: 9999px;
                border: 1px solid #e2e8f0;
                margin-bottom: 1.5rem;
                letter-spacing: 0.025em;
            }
            .qr-wrapper {
                margin: 0 auto 1.5rem auto;
                width: 240px;
                height: 240px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0.75rem;
                background: #ffffff;
                border-radius: 1rem;
                border: 1px solid #e2e8f0;
            }
            .qr-wrapper svg {
                width: 100%;
                height: 100%;
                display: block;
            }
            .instruction {
                font-size: 0.875rem;
                font-weight: 600;
                color: #334155;
                margin-bottom: 0.5rem;
            }
            .url {
                font-size: 0.8125rem;
                font-weight: 500;
                color: #64748b;
                word-break: break-all;
            }

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
                .sheet {
                    max-width: 100%;
                    width: 100%;
                    box-shadow: none;
                    border: 1px solid #000;
                    border-radius: 0;
                    padding: 2cm 1.5cm;
                    page-break-inside: avoid;
                }
                .qr-wrapper {
                    border: none;
                    width: 280px;
                    height: 280px;
                }
                .badge {
                    border: 1px solid #000;
                }
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button class="btn-print" onclick="window.print()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir Ficha
            </button>
        </div>

        <div class="sheet">
            @if (filled($restaurante->logo))
                <img src="{{ Storage::url($restaurante->logo) }}" alt="{{ $restaurante->nombre }}" class="logo">
            @else
                <div class="logo-fallback">
                    {{ strtoupper(substr($restaurante->nombre, 0, 2)) }}
                </div>
            @endif

            <h1 class="restaurant-name">{{ $restaurante->nombre }}</h1>

            <div class="badge">
                @if ($codigoQr->esMesa())
                    {{ $codigoQr->nombre ?: ('Mesa ' . $codigoQr->mesa) }}
                @else
                    {{ $codigoQr->nombre ?: 'Menú General' }}
                @endif
            </div>

            <div class="qr-wrapper">
                {!! $qrSvg !!}
            </div>

            <p class="instruction">Escanea con la cámara de tu celular</p>
            <p class="url">{{ $url }}</p>
        </div>
    </body>
</html>
