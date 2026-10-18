<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Menú no disponible — {{ config('app.name', 'MENUONL') }}</title>
        <style>
            /* Respaldo sin Vite: en capas declaradas antes que Tailwind para que sus utilidades (incluidas dark:) ganen. */
            @layer theme, base, components, utilities;
            @layer base, components;
            @layer components {
            :root {
                --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            }
            body {
                margin: 0;
                font-family: var(--font-sans);
                background-color: #fbfbfd;
                color: #1d1d1f;
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                box-sizing: border-box;
            }
            .card {
                background: #ffffff;
                border-radius: 1.5rem;
                padding: 2.5rem 2rem;
                max-width: 26rem;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
                border: 1px solid #e5e7eb;
            }
            .icon-wrapper {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 4.5rem;
                height: 4.5rem;
                border-radius: 9999px;
                background-color: #f3f4f6;
                color: #4b5563;
                margin-bottom: 1.5rem;
            }
            h1 {
                font-size: 1.375rem;
                font-weight: 700;
                letter-spacing: -0.025em;
                margin: 0 0 0.5rem 0;
                color: #111827;
            }
            p {
                font-size: 0.9375rem;
                line-height: 1.5;
                color: #4b5563;
                margin: 0 0 1.5rem 0;
            }
            .guidance-box {
                background-color: #f9fafb;
                border: 1px solid #f3f4f6;
                border-radius: 1rem;
                padding: 1rem;
                text-align: left;
                font-size: 0.8125rem;
                color: #374151;
                margin-bottom: 1.5rem;
            }
            .guidance-item {
                display: flex;
                align-items: flex-start;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }
            .guidance-item:last-child {
                margin-bottom: 0;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-height: 44px;
                padding: 0.75rem 1.5rem;
                background-color: #111827;
                color: #ffffff;
                border-radius: 0.75rem;
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
                transition: background-color 0.2s;
                cursor: pointer;
                border: none;
            }
            .btn:hover {
                background-color: #1f2937;
            }
            .btn-secondary {
                display: inline-block;
                margin-top: 0.75rem;
                font-size: 0.8125rem;
                color: #6b7280;
                text-decoration: none;
            }
            .btn-secondary:hover {
                color: #111827;
            }
            }
        </style>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex min-h-screen items-center justify-center p-6 antialiased">
        <main class="card bg-white dark:bg-slate-900 rounded-3xl p-8 max-w-sm w-full text-center shadow-xl border border-slate-200/80 dark:border-slate-800">
            <div class="icon-wrapper flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 mb-5 mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>

            <span class="block w-fit mx-auto px-3 py-1 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs font-semibold rounded-full mb-3">
                Carta no disponible
            </span>

            <h1 class="text-xl font-bold text-slate-950 dark:text-white mb-2">
                Menú digital en pausa
            </h1>

            <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed mb-5">
                El menú de este establecimiento no se encuentra disponible temporalmente o el código escaneado se encuentra inactivo.
            </p>

            <div class="guidance-box bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-4 text-left text-xs text-slate-600 dark:text-slate-300 mb-6 space-y-2 border border-slate-100 dark:border-slate-700">
                <div class="guidance-item flex items-start gap-2">
                    <span class="text-amber-500 font-bold">1.</span>
                    <span>Pide la carta impresa a tu mesero o al personal del restaurante.</span>
                </div>
                <div class="guidance-item flex items-start gap-2">
                    <span class="text-amber-500 font-bold">2.</span>
                    <span>Comprueba que tu dispositivo cuente con conexión a internet.</span>
                </div>
            </div>

            <button type="button" onclick="window.location.reload()" class="btn inline-flex items-center justify-center w-full min-h-[44px] px-6 py-3 bg-slate-900 hover:bg-slate-800 dark:bg-white dark:text-slate-950 text-white font-semibold text-sm rounded-xl transition-all shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">
                Reintentar lectura
            </button>

            <a href="{{ url('/') }}" class="btn-secondary inline-block mt-3 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors">
                Ir a la portada de MENUONL
            </a>
        </main>
    </body>
</html>
