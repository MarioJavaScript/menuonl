{{-- Reservas del servicio (docs/integracion-dashboard.md, sección 18). Con una ventana abierta el servidor ignora el sondeo (Reservas::refrescar). --}}
<x-filament-panels::page>
    <div class="mb-reservas" data-sondeo-pagina>
        <x-mb.aviso-sondeo :red="__('panel.reservas.sondeo.red')" :sesion="__('panel.reservas.sondeo.sesion')" :entrar="__('panel.reservas.sondeo.entrar')" />

        {{-- Solo se llena cuando un sondeo trae reservas nuevas por confirmar; un sondeo sin cambios no anuncia nada. --}}
        <p class="mb-sr" role="status" aria-live="polite" aria-atomic="true">{{ $this->anuncio }}</p>

        <div class="mb-reservas__vistas" role="group" aria-label="{{ __('panel.reservas.vistas.grupo') }}">
            @foreach (['proximas', 'hoy'] as $clave)
                <button
                    type="button"
                    class="mb-reservas__vista"
                    wire:click="cambiarVista('{{ $clave }}')"
                    aria-pressed="{{ $vista === $clave ? 'true' : 'false' }}"
                >{{ __("panel.reservas.vistas.{$clave}") }}</button>
            @endforeach
            <label class="mb-reservas__fecha" data-activo="{{ $vista === 'fecha' ? 'true' : 'false' }}">
                <span class="mb-sr">{{ __('panel.reservas.vistas.fecha') }}</span>
                <input type="date" wire:model.live="fecha" aria-label="{{ __('panel.reservas.vistas.fecha') }}">
            </label>
        </div>

        <p class="mb-reservas__resumen">{{ trans_choice('panel.reservas.resumen.cuenta', $activas, ['n' => $activas, 'donde' => $donde]) }}</p>

        <div data-sondeo wire:poll.30s.visible="refrescar">
            @if ($eligeDia)
                <x-mb.hoja>
                    <x-mb.vacio :titulo="__('panel.reservas.vacio.elige_dia.titulo')" :texto="__('panel.reservas.vacio.elige_dia.texto')" :icono="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays" />
                </x-mb.hoja>
            @elseif ($grupos->isEmpty())
                <x-mb.hoja>
                    <x-mb.vacio :titulo="__('panel.reservas.vacio.'.$vista.'.titulo')" :texto="__('panel.reservas.vacio.'.$vista.'.texto')" :icono="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays">
                        <x-slot:accion>
                            <x-filament::button type="button" wire:click="mountAction('crear')" icon="heroicon-o-plus">{{ __('panel.reservas.acciones.crear') }}</x-filament::button>
                        </x-slot:accion>
                    </x-mb.vacio>
                </x-mb.hoja>
            @else
                @foreach ($grupos as $dia => $filas)
                    <section class="mb-reservas__grupo">
                        @if ($vista === 'proximas')
                            <h2 class="mb-reservas__dia">{{ $etiquetaDia($dia) }}</h2>
                        @endif
                        <x-mb.hoja :sin-relleno="true">
                            <ul class="mb-reservas__lista" @if ($vista !== 'proximas') aria-label="{{ trans_choice('panel.reservas.resumen.cuenta', $activas, ['n' => $activas, 'donde' => $donde]) }}" @endif>
                                @foreach ($filas as $fila)
                                    @php
                                        /** @var \App\Models\Reserva $reserva */
                                        $reserva = $fila['reserva'];
                                        $estado = $reserva->estado;
                                        $nombre = $reserva->nombre;
                                        $principal = ['pendiente' => 'confirmar', 'confirmada' => 'llego', 'cancelada' => 'restaurar'][$estado] ?? null;
                                    @endphp
                                    <li class="mb-reserva mb-reserva--{{ $estado }}" wire:key="reserva-{{ $reserva->getKey() }}">
                                        <span class="mb-reserva__hora">{{ $fila['hora'] }}</span>
                                        <div class="mb-reserva__datos">
                                            <strong class="mb-reserva__nombre">{{ $nombre }}</strong>
                                            <span class="mb-reserva__detalle">
                                                {{ trans_choice('panel.reservas.personas', $reserva->personas, ['n' => $reserva->personas]) }}
                                                @if (filled($reserva->nota)) · {{ $reserva->nota }} @endif
                                                @if ($fila['telefono']) · <a class="mb-reserva__tel" href="tel:{{ $fila['telefono'] }}">{{ $reserva->telefono }}</a> @endif
                                            </span>
                                            @if ($fila['rastro'])
                                                <span class="mb-reserva__rastro">{{ $fila['rastro'] }}</span>
                                            @endif
                                        </div>
                                        <div class="mb-reserva__chips">
                                            @if ($reserva->origen === 'sitio')
                                                <span class="mb-reserva__chip mb-reserva__chip--sitio">{{ __('panel.reservas.origen_sitio') }}</span>
                                            @endif
                                            <span class="mb-reserva__chip mb-reserva__chip--{{ $estado }}">{{ __("panel.reservas.estados.{$estado}") }}</span>
                                        </div>
                                        <div class="mb-reserva__acciones">
                                            @if ($principal)
                                                <x-filament::button type="button" size="sm" wire:click="mountAction('{{ $principal }}', { reserva: {{ (int) $reserva->getKey() }} })" aria-label="{{ __('panel.reservas.aria.'.$principal, ['nombre' => $nombre]) }}">{{ __("panel.reservas.acciones.{$principal}") }}</x-filament::button>
                                            @endif
                                            @if ($estado !== 'cancelada')
                                                <x-filament::button type="button" size="sm" color="gray" outlined wire:click="mountAction('mover', { reserva: {{ (int) $reserva->getKey() }} })" aria-label="{{ __('panel.reservas.aria.mover', ['nombre' => $nombre]) }}">{{ __('panel.reservas.acciones.mover') }}</x-filament::button>
                                            @endif
                                            @if (! in_array($estado, ['llego', 'cancelada'], true))
                                                <x-filament::button type="button" size="sm" color="danger" outlined wire:click="mountAction('cancelar', { reserva: {{ (int) $reserva->getKey() }} })" aria-label="{{ __('panel.reservas.aria.cancelar', ['nombre' => $nombre]) }}">{{ __('panel.reservas.acciones.cancelar') }}</x-filament::button>
                                            @endif
                                            <x-filament::button type="button" size="sm" color="gray" outlined wire:click="mountAction('historial', { reserva: {{ (int) $reserva->getKey() }} })" aria-label="{{ __('panel.reservas.aria.historial', ['nombre' => $nombre]) }}">{{ __('panel.reservas.acciones.historial') }}</x-filament::button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </x-mb.hoja>
                    </section>
                @endforeach
            @endif
        </div>
    </div>

    @include('filament.partials.sondeo-con-sesion')
</x-filament-panels::page>
