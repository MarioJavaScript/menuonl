<x-layout.publico
    :titulo="trim($__env->yieldContent('titulo')).' · '.config('app.name')"
    noindex
    sin-referrer
    tema="calido"
    class="min-h-screen flex flex-col justify-center items-center p-4"
>
    <header class="mb-6 text-center">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 min-h-[44px] px-2.5 font-bold text-lg text-texto-fuerte rounded-lg focus-visible:ring-2 focus-visible:ring-foco focus:outline-none" aria-label="{{ config('app.name') }}">
            <x-mb.logo variante="horizontal" :alto="32" />
        </a>
    </header>

    <main class="w-full max-w-md bg-superficie rounded-3xl p-8 sm:p-10 shadow-xl shadow-slate-200/50 dark:shadow-none border border-borde">
        @yield('contenido')
    </main>

    <footer class="mt-8 text-center text-xs text-texto-suave">
        <p>Plataforma de menú digital segura · {{ config('app.name') }}</p>
    </footer>
</x-layout.publico>
