<x-filament-widgets::widget>
    @if ($restaurante)
        <x-filament::section>
            <div class="flex flex-col gap-6">
                {{-- 1. Estado de publicación y operación --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ $restaurante->nombre }}
                        </h2>
                        @if (filled($restaurante->eslogan))
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                {{ $restaurante->eslogan }}
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($estaPublicado)
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                Publicado
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="danger" icon="heroicon-m-pause-circle">
                                En pausa / Suspendido
                            </x-filament::badge>
                        @endif

                        @if ($estaAbierto)
                            <x-filament::badge color="success" icon="heroicon-m-clock">
                                Abierto ahora
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="gray" icon="heroicon-m-clock">
                                Cerrado en este momento
                            </x-filament::badge>
                        @endif

                        <x-filament::badge color="gray" icon="heroicon-m-swatch">
                            Estilo: {{ $temaNombre }}
                        </x-filament::badge>
                    </div>
                </div>

                {{-- 2. Acciones directas principales --}}
                <div class="flex flex-wrap items-center gap-2.5 border-t border-gray-100 pt-4 dark:border-white/10">
                    <x-filament::button
                        tag="a"
                        :href="$crearProductoUrl"
                        icon="heroicon-m-plus"
                    >
                        Agregar producto
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="$productosUrl"
                        color="gray"
                        outlined
                        icon="heroicon-o-cake"
                    >
                        Tus productos
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="$categoriasUrl"
                        color="gray"
                        outlined
                        icon="heroicon-o-rectangle-stack"
                    >
                        Categorías
                    </x-filament::button>

                    @if ($puedeEditarRestaurante && $perfilRestauranteUrl)
                        <x-filament::button
                            tag="a"
                            :href="$perfilRestauranteUrl"
                            color="gray"
                            outlined
                            icon="heroicon-o-paint-brush"
                        >
                            Diseño del menú y datos
                        </x-filament::button>
                    @endif

                    <x-filament::button
                        tag="a"
                        :href="$codigosQrUrl"
                        color="gray"
                        outlined
                        icon="heroicon-o-qr-code"
                    >
                        Códigos QR para compartir
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="$menuPublicoUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        color="gray"
                        outlined
                        icon="heroicon-o-arrow-top-right-on-square"
                    >
                        Ver menú
                    </x-filament::button>
                </div>

                {{-- 3. Avisos importantes que requieran atención --}}
                @if (count($avisos) > 0)
                    <div class="flex flex-col gap-2 border-t border-gray-100 pt-4 dark:border-white/10">
                        @foreach ($avisos as $aviso)
                            <div class="flex flex-col justify-between gap-2 rounded-lg bg-amber-50/80 px-3.5 py-2.5 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20 sm:flex-row sm:items-center dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20">
                                <span>{{ $aviso['mensaje'] }}</span>
                                @if ($aviso['url'])
                                    <a
                                        href="{{ $aviso['url'] }}"
                                        class="shrink-0 font-semibold text-amber-800 underline decoration-amber-600/50 underline-offset-2 hover:text-amber-950 dark:text-amber-100 dark:hover:text-white"
                                    >
                                        {{ $aviso['accion'] }} &rarr;
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
