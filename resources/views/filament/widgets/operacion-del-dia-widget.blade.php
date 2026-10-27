{{-- Operación del día en Inicio (docs/integracion-dashboard.md, sección 20). Sin nada que mostrar no se pinta ni se sondea. --}}
<x-filament-widgets::widget>
    @if ($mostrar)
        @php
            $porConfirmar = $dia['por_confirmar'];
            $proxima = $dia['proxima'];
            $hayReservas = $dia['reservas_hoy'] > 0 || $porConfirmar > 0;
        @endphp

        <div data-sondeo-pagina>
            <x-mb.aviso-sondeo :red="__('panel.inicio.dia.sondeo.red')" :sesion="__('panel.inicio.dia.sondeo.sesion')" :entrar="__('panel.reservas.sondeo.entrar')" />

            <div data-sondeo wire:poll.60s.visible>
                <x-filament::section>
                    <x-slot name="heading">{{ __('panel.inicio.dia.titulo') }}</x-slot>

                    @if ($hayReservas)
                        <ul class="flex flex-col gap-2 text-sm text-gray-950 dark:text-white" role="list">
                            <li class="flex items-start gap-3">
                                <x-filament::icon icon="heroicon-o-calendar-days" class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" />
                                <span>
                                    {{ trans_choice('panel.inicio.dia.reservas_hoy', $dia['reservas_hoy'], ['n' => $dia['reservas_hoy']]) }}
                                    @if ($dia['comensales_esperados'] > 0)
                                        {{ trans_choice('panel.inicio.dia.comensales', $dia['comensales_esperados'], ['n' => $dia['comensales_esperados']]) }}
                                    @endif
                                </span>
                            </li>
                            @if ($porConfirmar > 0)
                                <li class="flex items-start gap-3">
                                    <x-filament::icon icon="heroicon-o-clock" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
                                    <span class="font-semibold text-warning-800 dark:text-warning-300">{{ trans_choice('panel.inicio.dia.por_confirmar', $porConfirmar, ['n' => $porConfirmar]) }}</span>
                                </li>
                            @endif
                            @if ($proxima)
                                <li class="flex items-start gap-3">
                                    <x-filament::icon icon="heroicon-o-arrow-right-circle" class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" />
                                    <span>
                                        @php($personas = trans_choice('panel.inicio.dia.personas', $proxima['personas'], ['n' => $proxima['personas']]))
                                        @if ($proxima['nombre'])
                                            {{ __('panel.inicio.dia.proxima_con_nombre', ['hora' => $proxima['hora'], 'nombre' => $proxima['nombre'], 'personas' => $personas]) }}
                                        @else
                                            {{ __('panel.inicio.dia.proxima', ['hora' => $proxima['hora'], 'personas' => $personas]) }}
                                        @endif
                                    </span>
                                </li>
                            @endif
                        </ul>

                        <x-filament::button tag="a" :href="$reservasUrl" :color="$porConfirmar > 0 ? 'primary' : 'gray'" size="sm" class="mt-4">
                            {{ $porConfirmar > 0 ? __('panel.inicio.dia.confirmar') : __('panel.inicio.dia.ver') }}
                        </x-filament::button>
                    @else
                        <x-mb.vacio :titulo="__('panel.inicio.dia.vacio.titulo')" :texto="__('panel.inicio.dia.vacio.texto')" :icono="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" :nivel="3">
                            <x-slot:accion>
                                <x-filament::button tag="a" :href="$reservasUrl" color="gray" size="sm">
                                    {{ __('panel.inicio.dia.ver') }}
                                </x-filament::button>
                            </x-slot:accion>
                        </x-mb.vacio>
                    @endif
                </x-filament::section>
            </div>
        </div>

        @include('filament.partials.sondeo-con-sesion')
    @endif
</x-filament-widgets::widget>
