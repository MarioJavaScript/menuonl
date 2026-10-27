{{-- Errores del sondeo y sesión caducada, compartido por Reservas e Inicio (docs/integracion-dashboard.md, secciones 18 y 20). La API es la de Livewire 4.4.8: Livewire.interceptRequest con onError, onFailure y onSuccess. --}}
@script
<script>
    if (! window.mbSondeoInterceptor) {
        window.mbSondeoInterceptor = true

        let aviso = () => document.querySelector('[data-sondeo-aviso]')

        let mostrar = (tipo) => {
            let caja = aviso()
            if (! caja) return
            caja.querySelector('[data-sondeo-aviso-texto]').textContent = tipo === 'sesion' ? caja.dataset.textoSesion : caja.dataset.textoRed
            caja.querySelector('[data-sondeo-entrar]').hidden = tipo !== 'sesion'
            caja.querySelector('.mb-aviso').setAttribute('role', tipo === 'sesion' ? 'alert' : 'status')
            caja.dataset.tipo = tipo
            caja.hidden = false
        }

        let ocultarRed = () => {
            let caja = aviso()
            if (caja && caja.dataset.tipo === 'red') caja.hidden = true
        }

        let pararSondeo = () => {
            document.querySelectorAll('[data-sondeo]').forEach((el) => {
                Array.from(el.attributes).filter((a) => a.name.startsWith('wire:poll')).forEach((a) => el.removeAttribute(a.name))
            })
        }

        Livewire.interceptRequest(({ request, onError, onFailure, onSuccess }) => {
            let propios = []

            try {
                propios = Array.from(request.messages).filter((m) => m.component.el.querySelector('[data-sondeo-pagina]'))
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
