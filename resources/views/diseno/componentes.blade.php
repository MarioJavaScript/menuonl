@php
    $temas = \App\Services\Catalogos::temas();
    $tema = array_key_exists((string) request('tema'), $temas) ? (string) request('tema') : 'clasico';
    $colores = [
        'fondo', 'superficie', 'superficie-2', 'borde', 'borde-control', 'texto-fuerte', 'texto', 'texto-medio', 'texto-suave',
        'acento', 'acento-texto', 'foco', 'peligro', 'peligro-texto', 'whatsapp', 'oferta', 'exito-texto',
    ];
    $iconoAlerta = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7.5v5.5m0 3.5h.01"/></svg>';
    $iconoOk = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12.5 2.5 2.5L16 9.5"/></svg>';
@endphp
<x-layout.publico :titulo="'Componentes · '.config('app.name')" :tema="$tema" noindex>
    <x-slot:head>
        <style>
            .muestra { max-width: 64rem; margin: 0 auto; padding: var(--mo-espacio-8) var(--mo-espacio-4) var(--mo-espacio-16); }
            .muestra h1 { margin: 0; font-family: var(--mo-fuente-titulo); font-size: var(--mo-texto-xl); color: var(--mo-texto-fuerte); letter-spacing: var(--mo-espaciado-titulo); text-transform: var(--mo-mayusculas); }
            .muestra h2 { margin: 0 0 var(--mo-espacio-4); font-size: var(--mo-texto-lg); font-weight: var(--mo-peso-medio); color: var(--mo-texto-fuerte); }
            .muestra-subtexto { margin: var(--mo-espacio-1) 0 0; color: var(--mo-texto-suave); }
            .muestra-barra { display: flex; flex-wrap: wrap; align-items: center; gap: var(--mo-espacio-3); margin-top: var(--mo-espacio-6); }
            .muestra-bloque { margin-top: var(--mo-espacio-10); padding: var(--mo-espacio-6); border: 1px solid var(--mo-borde); border-radius: var(--mo-radio-tarjeta); background-color: var(--mo-superficie); }
            .muestra-fila { display: flex; flex-wrap: wrap; align-items: center; gap: var(--mo-espacio-3); }
            .muestra-rejilla { display: grid; gap: var(--mo-espacio-5); grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); }
            .muestra-colores { display: grid; gap: var(--mo-espacio-3); grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); }
            .muestra-color { display: grid; gap: var(--mo-espacio-1); font-size: var(--mo-texto-xs); color: var(--mo-texto-medio); }
            .muestra-color span:first-child { height: 3rem; border: 1px solid var(--mo-borde); border-radius: var(--mo-radio-control); }
            .muestra-tipo p { margin: 0 0 var(--mo-espacio-2); color: var(--mo-texto-fuerte); }
        </style>
    </x-slot:head>

    <main class="muestra">
        <h1>Componentes</h1>
        <p class="muestra-subtexto">Cada pieza del menú público en el estilo {{ $temas[$tema]['nombre'] }}. Cambia de estilo o de modo.</p>

        <div class="muestra-barra">
            <nav class="pestanas" aria-label="Estilo">
                @foreach ($temas as $clave => $datos)
                    <a href="?tema={{ $clave }}" class="pestana" @if ($clave === $tema) aria-current="page" @endif style="text-decoration: none">{{ $datos['nombre'] }}</a>
                @endforeach
            </nav>
            <x-apariencia />
        </div>

        <section class="muestra-bloque" aria-labelledby="t-color">
            <h2 id="t-color">Color</h2>
            <div class="muestra-colores">
                @foreach ($colores as $color)
                    <div class="muestra-color">
                        <span style="background-color: var(--mo-{{ $color }})"></span>
                        <span>{{ $color }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="muestra-bloque muestra-tipo" aria-labelledby="t-tipo">
            <h2 id="t-tipo">Tipografía</h2>
            <p style="font-family: var(--mo-fuente-titulo); font-size: var(--mo-texto-2xl); line-height: var(--mo-interlineado-titulo)">Tacos Don Pepe</p>
            <p style="font-size: var(--mo-texto-xl); font-weight: var(--mo-peso-medio)">Título de pantalla</p>
            <p style="font-size: var(--mo-texto-lg); font-weight: var(--mo-peso-medio)">Título de sección</p>
            <p style="font-size: var(--mo-texto-base)">Texto normal para leer con calma: $1,234.50 · 18 oct 2026 · 10:45 a. m.</p>
            <p style="font-size: var(--mo-texto-sm); color: var(--mo-texto-medio)">Texto secundario de 15 px.</p>
            <p style="font-size: var(--mo-texto-xs); color: var(--mo-texto-suave)">Ayuda y notas de 13 px.</p>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-botones">
            <h2 id="t-botones">Botones</h2>
            <div class="muestra-fila">
                <button type="button" class="btn-primario">Agregar platillo</button>
                <button type="button" class="btn-secundario">Ver horario</button>
                <button type="button" class="btn-texto">Vaciar pedido</button>
                <button type="button" class="btn-whatsapp">Mandar pedido por WhatsApp</button>
                <button type="button" class="btn-primario" aria-busy="true"><span class="giro" aria-hidden="true"></span> Guardando…</button>
                <button type="button" class="btn-primario" disabled>No disponible</button>
                <button type="button" class="btn-icono" aria-label="Cerrar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="muestra-fila" style="margin-top: var(--mo-espacio-4)">
                <button type="button" class="btn-primario btn-grande">Ver menú</button>
                <button type="button" class="btn-cart-add" aria-label="Agregar Tacos al pastor al pedido">Agregar</button>
                <button type="button" class="btn-cart-add" disabled>Agotado</button>
            </div>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-campos">
            <h2 id="t-campos">Campos</h2>
            <div class="muestra-rejilla">
                <div class="grupo-campo">
                    <label class="etiqueta-campo" for="m-mesa">Mesa</label>
                    <input id="m-mesa" class="campo" placeholder="Ej. 4, o escribe &quot;Para llevar&quot;">
                    <span class="ayuda-campo">El pedido llegará con este número de mesa.</span>
                </div>
                <div class="grupo-campo">
                    <label class="etiqueta-campo" for="m-whatsapp">Tu WhatsApp</label>
                    <input id="m-whatsapp" class="campo" value="55 1234" aria-invalid="true" aria-describedby="m-whatsapp-error">
                    <span id="m-whatsapp-error" class="error-campo">{!! $iconoAlerta !!} Escribe solo números, con la lada del país. Ej. 5215512345678</span>
                </div>
                <div class="grupo-campo">
                    <label class="etiqueta-campo" for="m-categoria">Categoría</label>
                    <div class="selector">
                        <select id="m-categoria" class="campo">
                            <option>Tacos</option>
                            <option>Bebidas</option>
                            <option>Postres</option>
                        </select>
                    </div>
                    <span class="ayuda-campo">Dónde aparece en tu menú.</span>
                </div>
                <div class="grupo-campo">
                    <label class="etiqueta-campo" for="m-nota">Nota para la cocina (opcional)</label>
                    <textarea id="m-nota" class="campo campo-area" placeholder="Ej. Sin cebolla, con cubiertos"></textarea>
                </div>
                <div class="grupo-campo">
                    <span class="etiqueta-campo">Interruptor</span>
                    <label class="interruptor"><input type="checkbox" role="switch" checked> Disponible</label>
                    <label class="interruptor"><input type="checkbox" role="switch"> Recomendado</label>
                </div>
                <div class="grupo-campo">
                    <span class="etiqueta-campo">Foto</span>
                    <label class="subir-imagen">
                        <input type="file" accept="image/jpeg,image/png,image/webp">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path stroke-linejoin="round" d="m21 16-5-5-8 8"/></svg>
                        <strong>Toca para elegir una foto</strong>
                        <span class="ayuda-campo">Una foto de tu celular sirve. Hasta 10 MB.</span>
                    </label>
                </div>
            </div>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-pestanas">
            <h2 id="t-pestanas">Pestañas</h2>
            <div class="pestanas" role="tablist" aria-label="Idioma del platillo">
                <button type="button" class="pestana" role="tab" aria-selected="true">Español</button>
                <button type="button" class="pestana" role="tab" aria-selected="false">English</button>
                <button type="button" class="pestana" role="tab" aria-selected="false">Français</button>
            </div>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-estados">
            <h2 id="t-estados">Insignias y avisos</h2>
            <div class="muestra-fila">
                <span class="insignia insignia-exito">{!! $iconoOk !!} Abierto ahora</span>
                <span class="insignia insignia-error">{!! $iconoAlerta !!} Cerrado</span>
                <span class="insignia insignia-info">Mesa 4</span>
                <span class="insignia">Vegano</span>
                <span class="etiqueta-agotado">Agotado</span>
            </div>
            <div style="display: grid; gap: var(--mo-espacio-3); margin-top: var(--mo-espacio-5)">
                <div class="aviso" role="status">{!! $iconoAlerta !!} <span>Estamos cerrados. Abrimos mañana a la 1:00 p. m.</span></div>
                <div class="aviso aviso-exito" role="status">{!! $iconoOk !!} <span><span class="aviso-titulo">Abrimos WhatsApp con tu pedido.</span> Tócale enviar ahí para que nos llegue.</span></div>
                <div class="aviso aviso-peligro" role="alert">{!! $iconoAlerta !!} <span><span class="aviso-titulo">No pudimos abrir WhatsApp.</span> Tu pedido sigue aquí. Vuelve a intentarlo.</span></div>
            </div>
            <div class="muestra-fila" style="margin-top: var(--mo-espacio-5)">
                <button type="button" class="btn-secundario" data-mostrar-aviso="exito">Mostrar aviso temporal</button>
                <button type="button" class="btn-secundario" data-mostrar-aviso="error">Mostrar aviso de error</button>
            </div>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-vacio">
            <h2 id="t-vacio">Vacío y cargando</h2>
            <div class="muestra-rejilla">
                <div class="vacio">
                    <span class="vacio-icono"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h12"/></svg></span>
                    <p class="vacio-titulo">Tu pedido está vacío</p>
                    <p>Toca "Agregar" en un platillo.</p>
                    <a href="#" class="btn-primario" style="text-decoration: none">Ver menú</a>
                </div>
                <div aria-busy="true" aria-label="Cargando platillos" style="display: grid; gap: var(--mo-espacio-3); align-content: center">
                    <span class="esqueleto" style="height: 1.25rem; width: 60%"></span>
                    <span class="esqueleto" style="height: 1rem; width: 90%"></span>
                    <span class="esqueleto" style="height: 1rem; width: 75%"></span>
                    <span class="esqueleto" style="height: 6rem; width: 6rem; border-radius: var(--mo-radio-tarjeta)"></span>
                </div>
            </div>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-tabla">
            <h2 id="t-tabla">Tabla y lista de tarjetas</h2>
            <table class="tabla tabla-tarjetas">
                <thead>
                    <tr><th scope="col">Platillo</th><th scope="col">Categoría</th><th scope="col" class="numero">Precio</th><th scope="col">Estado</th></tr>
                </thead>
                <tbody>
                    <tr><td data-principal>Tacos al pastor</td><td data-etiqueta="Categoría">Tacos</td><td data-etiqueta="Precio" class="numero">$85.50</td><td data-etiqueta="Estado"><span class="insignia insignia-exito">{!! $iconoOk !!} Disponible</span></td></tr>
                    <tr><td data-principal>Agua de horchata</td><td data-etiqueta="Categoría">Bebidas</td><td data-etiqueta="Precio" class="numero">$35.00</td><td data-etiqueta="Estado"><span class="insignia insignia-exito">{!! $iconoOk !!} Disponible</span></td></tr>
                    <tr><td data-principal>Flan napolitano</td><td data-etiqueta="Categoría">Postres</td><td data-etiqueta="Precio" class="numero">$1,234.50</td><td data-etiqueta="Estado"><span class="insignia insignia-error">{!! $iconoAlerta !!} Agotado</span></td></tr>
                </tbody>
            </table>
        </section>

        <section class="muestra-bloque" aria-labelledby="t-confirmar">
            <h2 id="t-confirmar">Confirmación</h2>
            <div class="aviso aviso-peligro" style="flex-direction: column; align-items: stretch">
                <span class="aviso-titulo">¿Quitar todo de tu pedido?</span>
                <span>Se quitarán 3 platillos.</span>
                <div class="muestra-fila" style="margin-top: var(--mo-espacio-2)">
                    <button type="button" class="btn-secundario">Conservar</button>
                    <button type="button" class="btn-peligro">Vaciar pedido</button>
                </div>
            </div>
        </section>
    </main>

    <div id="avisoMuestra" class="aviso-temporal" role="status" aria-live="polite">
        <span data-icono></span>
        <span data-texto></span>
    </div>

    <script>
        (function () {
            var aviso = document.getElementById('avisoMuestra');
            var temporizador;
            var iconos = { exito: @js($iconoOk), error: @js($iconoAlerta) };
            var textos = { exito: 'Tacos al pastor agregado', error: 'No pudimos agregar el platillo. Vuelve a intentarlo.' };
            document.addEventListener('click', function (evento) {
                var boton = evento.target.closest('[data-mostrar-aviso]');
                if (!boton) {
                    return;
                }
                var tipo = boton.getAttribute('data-mostrar-aviso');
                aviso.className = 'aviso-temporal aviso-temporal-' + tipo + ' visible';
                aviso.querySelector('[data-icono]').innerHTML = iconos[tipo];
                aviso.querySelector('[data-texto]').textContent = textos[tipo];
                clearTimeout(temporizador);
                temporizador = setTimeout(function () { aviso.classList.remove('visible'); }, 4000);
            });
        })();
    </script>
</x-layout.publico>
