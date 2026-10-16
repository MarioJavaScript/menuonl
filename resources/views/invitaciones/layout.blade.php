<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>@yield('titulo') · {{ config('app.name', 'MENUONL') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            :root { --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
            body { font-family: var(--font-sans); background-color: #f8fafc; color: #0f172a; margin: 0; }
            main { max-width: 28rem; margin: 3rem auto; padding: 2.25rem 2rem; background: #fff; border-radius: 1.25rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
            label { display: block; margin-top: 1.25rem; font-weight: 600; font-size: 0.875rem; }
            input { width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; margin-top: 0.35rem; border: 1px solid #cbd5e1; border-radius: 0.75rem; font-size: 0.95rem; }
            input:focus { outline: none; border-color: #0f172a; ring: 2px solid #0f172a; }
            button, .boton { display: inline-flex; align-items: center; justify-content: center; width: 100%; margin-top: 1.5rem; min-height: 48px; padding: 0.75rem 1.5rem; border: 0; border-radius: 0.75rem; background: #0f172a; color: #fff; font-weight: 600; text-decoration: none; cursor: pointer; }
            .error { color: #dc2626; font-size: 0.8125rem; margin-top: 0.35rem; }
        </style>
    @endif
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col justify-center items-center p-4 antialiased">
    <header class="mb-6 text-center">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 font-bold text-lg text-slate-900 dark:text-white" aria-label="MENUONL">
            <span class="w-8 h-8 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 flex items-center justify-center font-black text-sm">M</span>
            <span class="tracking-wide">MENUONL</span>
        </a>
    </header>

    <main class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-8 sm:p-10 shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200/80 dark:border-slate-800">
        @yield('contenido')
    </main>

    <footer class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
        <p>Plataforma de menú digital segura · MENUONL</p>
    </footer>
</body>
</html>
