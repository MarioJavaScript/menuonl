@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla N). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="mex-pedido-raiz" data-mex-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_mex_'.$restaurante->slug }}">
    <button class="mex-barra" type="button" hidden data-mex-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="mex-barra__interior">
            <span class="mex-barra__cuenta" data-mex-cuenta>0</span>
            <span class="mex-barra__texto">Ver pedido</span>
            <span class="mex-barra__total" data-mex-barra-total></span>
        </span>
    </button>

    <p class="mex-toast" role="status" aria-live="polite" hidden data-mex-toast></p>

    <dialog class="mex-pedido" data-mex-dialogo aria-labelledby="pedido-titulo">
        <div class="mex-pedido__cabecera">
            <h2 class="mex-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="mex-pedido__cerrar" type="button" data-mex-cerrar aria-label="Cerrar pedido">
                <svg class="mex-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="mex-pedido__cuerpo">
            <p class="mex-pedido__vacio" data-mex-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="mex-pedido__lista" role="list" data-mex-lineas></ul>
            <template data-mex-plantilla-linea>
                <li class="mex-linea">
                    <div class="mex-linea__fila">
                        <p class="mex-linea__nombre" data-mex-nombre></p>
                        <p class="mex-linea__subtotal" data-mex-subtotal></p>
                    </div>
                    <div class="mex-linea__fila">
                        <input class="mex-campo__entrada mex-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin cebolla, extra queso" data-mex-nota>
                        <div class="mex-linea__cantidad">
                            <button class="mex-cantidad" type="button" data-mex-menos><span aria-hidden="true">−</span></button>
                            <p class="mex-linea__numero"><span class="mex-solo-lectores">Cantidad: </span><span data-mex-cantidad></span></p>
                            <button class="mex-cantidad" type="button" data-mex-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="mex" clase-campo="mex-campo" clase-etiqueta="mex-campo__etiqueta" />
            <div class="mex-campo">
                <label class="mex-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="mex-campo__entrada mex-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-mex-cocina></textarea>
            </div>
        </div>

        <div class="mex-pedido__pie">
            <p class="mex-pedido__total"><span>Total</span> <span data-mex-total></span></p>
            <p class="mex-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="mex-aviso" role="status" hidden data-mex-aviso-envio></p>

            <div class="mex-aviso mex-aviso--exito" role="status" tabindex="-1" hidden data-mex-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="mex-pedido__botones">
                    <button class="mex-boton" type="button" data-mex-listo>Ya lo mandé</button>
                    <button class="mex-boton mex-boton--borde" type="button" data-mex-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="mex-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-mex-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="mex-pedido__botones">
                    <button class="mex-boton mex-boton--borde" type="button" data-mex-conservar>Conservar</button>
                    <button class="mex-boton" type="button" data-mex-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="mex-pedido__botones">
                <button class="mex-boton mex-pedido__enviar" type="button" disabled data-mex-enviar>
                    <svg class="mex-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="mex-boton mex-boton--borde" type="button" hidden data-mex-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
