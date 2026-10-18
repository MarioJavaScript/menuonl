<x-filament-widgets::widget>
    @if ($restaurante)
        <div class="flex flex-col gap-6">
            {{-- Estado del menú en una línea, con texto e icono (no solo color) --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($estaPublicado)
                        <x-filament::badge color="success" icon="heroicon-m-check-circle">
                            Tu menú está en línea
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="danger" icon="heroicon-m-pause-circle">
                            Tu menú está pausado
                        </x-filament::badge>
                    @endif

                    @if ($estaAbierto)
                        <x-filament::badge color="success" icon="heroicon-m-clock">
                            Abierto ahora
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="gray" icon="heroicon-m-clock">
                            Cerrado ahora
                        </x-filament::badge>
                    @endif

                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        @if (count($turnosHoy) > 0)
                            Hoy: {{ implode(' y ', $turnosHoy) }}
                        @else
                            Hoy no abres
                        @endif
                    </span>
                </div>

                @if ($agregarPlatilloUrl)
                    <x-filament::button tag="a" :href="$agregarPlatilloUrl" icon="heroicon-m-plus" size="lg">
                        Agregar platillo
                    </x-filament::button>
                @endif
            </div>

            @unless ($estaPublicado)
                <div role="status" class="flex items-start gap-3 rounded-xl bg-danger-50 p-4 text-sm text-danger-900 ring-1 ring-danger-600/30 dark:bg-danger-500/10 dark:text-danger-100 dark:ring-danger-400/30">
                    <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-5 w-5 shrink-0" />
                    <p>Tus clientes ven "Menú no disponible". Escríbenos para reactivarlo.</p>
                </div>
            @endunless

            <x-filament::section>
                <x-slot name="heading">Pendientes</x-slot>

                @if (count($pendientes) > 0)
                    <ul class="-my-2 divide-y divide-gray-200 dark:divide-white/10" role="list">
                        @foreach ($pendientes as $pendiente)
                            <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <span class="flex items-start gap-3 text-sm text-gray-950 dark:text-white">
                                    <x-filament::icon
                                        :icon="$pendiente['importante'] ? 'heroicon-o-exclamation-circle' : 'heroicon-o-information-circle'"
                                        @class([
                                            'mt-0.5 h-5 w-5 shrink-0',
                                            'text-warning-700 dark:text-warning-400' => $pendiente['importante'],
                                            'text-gray-500 dark:text-gray-400' => ! $pendiente['importante'],
                                        ])
                                    />
                                    {{ $pendiente['mensaje'] }}
                                </span>
                                @if ($pendiente['url'])
                                    <x-filament::button tag="a" :href="$pendiente['url']" color="gray" size="sm" class="shrink-0 self-start sm:self-auto">
                                        {{ $pendiente['accion'] }}
                                    </x-filament::button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="flex items-center gap-3 text-sm text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 shrink-0 text-success-600 dark:text-success-400" />
                        <p><span class="font-semibold">Todo listo.</span> Tu menú está completo.</p>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-widgets::widget>
