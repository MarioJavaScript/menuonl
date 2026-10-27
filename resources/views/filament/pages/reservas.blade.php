{{-- Reservas del servicio (docs/integracion-dashboard.md, sección 18). Con una ventana abierta el servidor ignora el sondeo (Reservas::refrescar). --}}
<x-filament-panels::page>
    <div class="mb-reservas" data-reservas-pagina>
        <div
            class="mb-reservas__aviso"
            data-reservas-aviso
            data-texto-red="{{ __('panel.reservas.sondeo.red') }}"
            data-texto-sesion="{{ __('panel.reservas.sondeo.sesion') }}"
            wire:ignore
            hidden
        >
            <x-mb.aviso tono="atencion" :anuncia="false">
                <span data-reservas-aviso-texto></span>
                <x-slot:accion>
                    <a class="mb-reservas__entrar" href="{{ filament()->getLoginUrl() ?? url('/admin') }}" data-reservas-entrar hidden>{{ __('panel.reservas.sondeo.entrar') }}</a>
                </x-slot:accion>
            </x-mb.aviso>
        </div>

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

        <div data-reservas-sondeo wire:poll.30s.visible="refrescar">
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

    {{-- Errores del sondeo y sesión caducada. La API es la de Livewire 4.4.8: Livewire.interceptRequest con onError, onFailure y onSuccess. --}}
    @script
    <script>
        if (! window.mbReservasInterceptor) {
            window.mbReservasInterceptor = true

            let aviso = () => document.querySelector('[data-reservas-aviso]')

            let mostrar = (tipo) => {
                let caja = aviso()
                if (! caja) return
                caja.querySelector('[data-reservas-aviso-texto]').textContent = tipo === 'sesion' ? caja.dataset.textoSesion : caja.dataset.textoRed
                caja.querySelector('[data-reservas-entrar]').hidden = tipo !== 'sesion'
                caja.querySelector('.mb-aviso').setAttribute('role', tipo === 'sesion' ? 'alert' : 'status')
                caja.dataset.tipo = tipo
                caja.hidden = false
            }

            let ocultarRed = () => {
                let caja = aviso()
                if (caja && caja.dataset.tipo === 'red') caja.hidden = true
            }

            let pararSondeo = () => {
                document.querySelectorAll('[data-reservas-sondeo]').forEach((el) => {
                    Array.from(el.attributes).filter((a) => a.name.startsWith('wire:poll')).forEach((a) => el.removeAttribute(a.name))
                })
            }

            Livewire.interceptRequest(({ request, onError, onFailure, onSuccess }) => {
                let propios = []

                try {
                    propios = Array.from(request.messages).filter((m) => m.component.el.querySelector('[data-reservas-pagina]'))
                } catch (e) {
                    return
                }

                if (propios.length === 0) return

                let esSondeo = propios.every((m) => Array.from(m.actions).every((a) => a.metadata && a.metadata.type === 'poll'))

                onError(({ response, preventDefault }) => {
                    if (response.status === 419 || response.status === 401) {
                        preventDefault()
                        pararSondeo()
                        mostrar('sesion')

                        return
                    }

                    if (esSondeo) {
                        preventDefault()
                        mostrar('red')
                    }
                })

                onFailure(() => mostrar('red'))
                onSuccess(() => ocultarRed())
            })
        }
    </script>
    @endscript
</x-filament-panels::page>
