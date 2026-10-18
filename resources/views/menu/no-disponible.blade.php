<x-layout.publico
    :titulo="'Menú no disponible — '.config('app.name', 'MENUONL')"
    class="flex min-h-screen items-center justify-center p-6"
>
    <main class="bg-superficie rounded-3xl p-8 max-w-sm w-full text-center shadow-xl border border-borde">
        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-superficie-2 text-texto-medio mb-5 mx-auto">
            <svg class="w-8 h-8" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>

        <span class="block w-fit mx-auto px-3 py-1 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs font-semibold rounded-full mb-3">
            Carta no disponible
        </span>

        <h1 class="text-xl font-bold text-texto-fuerte mb-2">
            Menú digital en pausa
        </h1>

        <p class="text-texto-suave text-sm leading-relaxed mb-5">
            El menú de este establecimiento no se encuentra disponible temporalmente o el código escaneado se encuentra inactivo.
        </p>

        <ol class="bg-superficie-2/60 rounded-2xl p-4 text-left text-xs text-texto-medio mb-6 space-y-2 border border-borde list-none">
            <li class="flex items-start gap-2">
                <span class="text-amber-700 dark:text-amber-400 font-bold" aria-hidden="true">1.</span>
                <span>Pide la carta impresa a tu mesero o al personal del restaurante.</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-amber-700 dark:text-amber-400 font-bold" aria-hidden="true">2.</span>
                <span>Comprueba que tu dispositivo cuente con conexión a internet.</span>
            </li>
        </ol>

        <button type="button" onclick="window.location.reload()" class="btn-primario w-full py-3 text-sm focus-visible:ring-2 focus-visible:ring-foco focus:outline-none">
            Reintentar lectura
        </button>

        <a href="{{ url('/') }}" class="mt-3 inline-flex items-center justify-center min-h-[44px] px-3 text-xs text-texto-suave hover:text-texto-fuerte transition-colors rounded-lg">
            Ir a la portada de MENUONL
        </a>
    </main>
</x-layout.publico>
