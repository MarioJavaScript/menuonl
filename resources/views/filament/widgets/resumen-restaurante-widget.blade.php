<x-filament-widgets::widget>
    @if ($restaurante)
        @php
            $pasos = $progreso['pasos'] ?? [];
            $pendientes = array_values(array_filter($pasos, fn (array $paso): bool => ! $paso['hecho']));
            $cumplidos = array_values(array_filter($pasos, fn (array $paso): bool => $paso['hecho']));
            $porcentaje = $progreso['porcentaje'] ?? null;
            $avisos = $progreso['avisos'] ?? [];
            $atencion = $progreso['atencion'] ?? [];
            $dia = $progreso['dia'] ?? null;
            $qrUrl = $progreso['codigo_qr_url'] ?? null;
        @endphp

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

            {{-- Accesos rápidos --}}
            <nav aria-label="{{ __('panel.inicio.accesos.titulo') }}" class="flex flex-wrap gap-2">
                @if ($sitioUrl)
                    <x-filament::button tag="a" :href="$sitioUrl" target="_blank" rel="noopener noreferrer" color="gray" size="sm" icon="heroicon-o-arrow-top-right-on-square" icon-position="after">
                        {{ __('panel.enlaces.ver_sitio') }}
                        <span class="sr-only">{{ __('panel.enlaces.abre_en_otra_pestana') }}</span>
                    </x-filament::button>
                @endif
                <x-filament::button tag="a" :href="$menuUrl" target="_blank" rel="noopener noreferrer" color="gray" size="sm" icon="heroicon-o-arrow-top-right-on-square" icon-position="after">
                    {{ __('panel.enlaces.ver_menu') }}
                    <span class="sr-only">{{ __('panel.enlaces.abre_en_otra_pestana') }}</span>
                </x-filament::button>
                @if ($qrUrl)
                    <x-filament::button tag="a" :href="$qrUrl" color="gray" size="sm" icon="heroicon-o-qr-code">
                        {{ __('panel.inicio.accesos.descargar_qr') }}
                    </x-filament::button>
                @endif
            </nav>

            {{-- Resumen del día: solo si hay reservas que mostrar --}}
            @if ($dia)
                <x-filament::section>
                    <x-slot name="heading">{{ __('panel.inicio.dia.titulo') }}</x-slot>

                    <ul class="flex flex-col gap-2 text-sm text-gray-950 dark:text-white" role="list">
                        @if ($dia['reservas_hoy'] > 0)
                            <li class="flex items-start gap-3">
                                <x-filament::icon icon="heroicon-o-calendar-days" class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" />
                                {{ trans_choice('panel.inicio.dia.reservas_hoy', $dia['reservas_hoy'], ['n' => $dia['reservas_hoy']]) }}
                            </li>
                        @endif
                        @if ($dia['por_confirmar'] > 0)
                            <li class="flex items-start gap-3">
                                <x-filament::icon icon="heroicon-o-clock" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
                                {{ trans_choice('panel.inicio.dia.por_confirmar', $dia['por_confirmar'], ['n' => $dia['por_confirmar']]) }}
                            </li>
                        @endif
                    </ul>

                    <x-filament::button tag="a" :href="$dia['url']" color="gray" size="sm" class="mt-4">
                        {{ __('panel.inicio.dia.ver') }}
                    </x-filament::button>
                </x-filament::section>
            @endif

            {{-- Avisos importantes --}}
            @foreach ($avisos as $aviso)
                <div role="status" class="flex flex-col gap-3 rounded-xl bg-warning-50 p-4 text-sm text-warning-900 ring-1 ring-warning-600/30 sm:flex-row sm:items-center sm:justify-between dark:bg-warning-500/10 dark:text-warning-100 dark:ring-warning-400/30">
                    <span class="flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                        {{ __('panel.inicio.avisos.'.$aviso['clave'].'.mensaje') }}
                    </span>
                    <x-filament::button tag="a" :href="$aviso['url']" color="gray" size="sm" class="shrink-0 self-start sm:self-auto">
                        {{ __('panel.inicio.avisos.'.$aviso['clave'].'.accion') }}
                    </x-filament::button>
                </div>
            @endforeach

            {{-- Qué falta configurar --}}
            @if (count($pasos) > 0)
                <x-filament::section>
                    <x-slot name="heading">{{ __('panel.inicio.falta.titulo') }}</x-slot>

                    <div class="mb-4 flex flex-col gap-2">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('panel.inicio.falta.avance', ['porcentaje' => $porcentaje]) }}
                            <span class="font-normal text-gray-600 dark:text-gray-300">
                                @if (count($pendientes) > 0)
                                    {{ trans_choice('panel.inicio.falta.te_faltan', count($pendientes), ['n' => count($pendientes)]) }}
                                @else
                                    {{ __('panel.inicio.falta.completo') }}
                                @endif
                            </span>
                        </p>
                        <div
                            role="progressbar"
                            aria-label="{{ __('panel.inicio.falta.titulo') }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $porcentaje }}"
                            class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
                        >
                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-500" style="width: {{ $porcentaje }}%"></div>
                        </div>
                    </div>

                    @if (count($pendientes) > 0)
                        <ul class="-my-2 divide-y divide-gray-200 dark:divide-white/10" role="list">
                            @foreach ($pendientes as $paso)
                                <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="flex items-start gap-3 text-sm text-gray-950 dark:text-white">
                                        <x-filament::icon
                                            :icon="$paso['importante'] ? 'heroicon-o-exclamation-circle' : 'heroicon-o-information-circle'"
                                            @class([
                                                'mt-0.5 h-5 w-5 shrink-0',
                                                'text-warning-700 dark:text-warning-400' => $paso['importante'],
                                                'text-gray-500 dark:text-gray-400' => ! $paso['importante'],
                                            ])
                                        />
                                        <span>
                                            <span class="sr-only">{{ __('panel.inicio.falta.pendiente') }}: </span>
                                            {{ __('panel.inicio.pasos.'.$paso['clave'].'.pendiente') }}
                                        </span>
                                    </span>
                                    <x-filament::button tag="a" :href="$paso['url']" color="gray" size="sm" class="shrink-0 self-start sm:self-auto">
                                        {{ __('panel.inicio.pasos.'.$paso['clave'].'.accion') }}
                                    </x-filament::button>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex items-center gap-3 text-sm text-gray-950 dark:text-white">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 shrink-0 text-success-600 dark:text-success-400" />
                            <p><span class="font-semibold">{{ __('panel.inicio.falta.todo_en_orden') }}</span> {{ __('panel.inicio.falta.menu_completo') }}</p>
                        </div>
                    @endif

                    @if (count($cumplidos) > 0 && count($pendientes) > 0)
                        <details class="mt-4 text-sm">
                            <summary class="cursor-pointer font-semibold text-gray-950 dark:text-white">
                                {{ trans_choice('panel.inicio.falta.listos', count($cumplidos), ['n' => count($cumplidos)]) }}
                            </summary>
                            <ul class="mt-2 flex flex-col gap-2" role="list">
                                @foreach ($cumplidos as $paso)
                                    <li class="flex items-start gap-3 text-gray-600 dark:text-gray-300">
                                        <x-filament::icon icon="heroicon-o-check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-600 dark:text-success-400" />
                                        <span>
                                            <span class="sr-only">{{ __('panel.inicio.falta.listo') }}: </span>
                                            {{ __('panel.inicio.pasos.'.$paso['clave'].'.hecho') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </x-filament::section>
            @endif

            {{-- Cosas que revisar (no cuentan para el porcentaje) --}}
            @if (count($atencion) > 0)
                <x-filament::section>
                    <x-slot name="heading">{{ __('panel.inicio.atencion.titulo') }}</x-slot>

                    <ul class="-my-2 divide-y divide-gray-200 dark:divide-white/10" role="list">
                        @foreach ($atencion as $cosa)
                            <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <span class="flex items-start gap-3 text-sm text-gray-950 dark:text-white">
                                    <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" />
                                    {{ trans_choice('panel.inicio.atencion.'.$cosa['clave'].'.mensaje', $cosa['cantidad'], ['n' => $cosa['cantidad']]) }}
                                </span>
                                <x-filament::button tag="a" :href="$cosa['url']" color="gray" size="sm" class="shrink-0 self-start sm:self-auto">
                                    {{ __('panel.inicio.atencion.'.$cosa['clave'].'.accion') }}
                                </x-filament::button>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
