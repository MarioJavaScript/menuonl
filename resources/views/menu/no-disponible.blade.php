<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Menú no disponible — {{ config('app.name', 'MENUONL') }}</title>
        <style>
            :root {
                --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            }
            body {
                margin: 0;
                font-family: var(--font-sans);
                background-color: #f8fafc;
                color: #1e293b;
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                box-sizing: border-box;
            }
            .card {
                background: #ffffff;
                border-radius: 1rem;
                padding: 2.5rem 2rem;
                max-width: 28rem;
                width: 100%;
                text-align: center;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
                border: 1px solid #e2e8f0;
            }
            .icon-wrapper {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 4.5rem;
                height: 4.5rem;
                border-radius: 9999px;
                background-color: #fee2e2;
                color: #ef4444;
                margin-bottom: 1.5rem;
            }
            h1 {
                font-size: 1.5rem;
                font-weight: 700;
                margin: 0 0 0.75rem 0;
                color: #0f172a;
            }
            p {
                font-size: 0.95rem;
                line-height: 1.5;
                color: #64748b;
                margin: 0 0 1.75rem 0;
            }
            .badge {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                background-color: #f1f5f9;
                color: #475569;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 9999px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 1rem;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.75rem 1.5rem;
                background-color: #0f172a;
                color: #ffffff;
                border-radius: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
                transition: background-color 0.2s;
            }
            .btn:hover {
                background-color: #334155;
            }
        </style>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-50 text-slate-800 flex min-h-screen items-center justify-center p-6">
        <main class="card bg-white rounded-2xl p-8 max-w-md w-full text-center shadow-lg border border-slate-200">
            <div class="icon-wrapper inline-flex items-center justify-center w-18 h-18 rounded-full bg-red-100 text-red-500 mb-6 mx-auto">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <span class="badge inline-block px-3 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-full uppercase tracking-wider mb-3">Error 404</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mb-3">Menú no disponible</h1>
            <p class="text-slate-600 text-sm leading-relaxed mb-6">
                El menú digital, código QR o restaurante solicitado no se encuentra disponible en este momento. Es posible que el enlace haya expirado o que el establecimiento se encuentre inactivo temporalmente.
            </p>
            <div>
                <a href="{{ url('/') }}" class="btn inline-flex items-center justify-center px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm rounded-lg transition-colors">
                    Ir al inicio
                </a>
            </div>
        </main>
    </body>
</html>
