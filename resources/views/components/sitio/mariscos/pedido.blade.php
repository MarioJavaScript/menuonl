@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla J). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="mar-pedido-raiz" data-mar-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_j_'.$restaurante->slug }}">
    <button class="mar-barra" type="button" hidden data-mar-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="mar-barra__interior">
            <span class="mar-barra__cuenta" data-mar-cuenta>0</span>
            <span class="mar-barra__texto">Ver pedido</span>
            <span class="mar-barra__total" data-mar-barra-total></span>
        </span>
    </button>

    <p class="mar-toast" role="status" aria-live="polite" hidden data-mar-toast></p>

    <dialog class="mar-pedido" data-mar-dialogo aria-labelledby="pedido-titulo">
        <div class="mar-pedido__cabecera">
            <h2 class="mar-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="mar-pedido__cerrar" type="button" data-mar-cerrar aria-label="Cerrar pedido">
                <svg class="mar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="mar-pedido__cuerpo">
            <p class="mar-pedido__vacio" data-mar-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="mar-pedido__lista" role="list" data-mar-lineas></ul>
            <template data-mar-plantilla-linea>
                <li class="mar-linea">
                    <div class="mar-linea__fila">
                        <p class="mar-linea__nombre" data-mar-nombre></p>
                        <p class="mar-linea__subtotal" data-mar-subtotal></p>
                    </div>
                    <div class="mar-linea__fila">
                        <input class="mar-campo__entrada mar-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-mar-nota>
                        <div class="mar-linea__cantidad">
                            <button class="mar-cantidad" type="button" data-mar-menos><span aria-hidden="true">−</span></button>
                            <p class="mar-linea__numero"><span class="mar-solo-lectores">Cantidad: </span><span data-mar-cantidad></span></p>
                            <button class="mar-cantidad" type="button" data-mar-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="mar" clase-campo="mar-campo" clase-etiqueta="mar-campo__etiqueta" />
            <div class="mar-campo">
                <label class="mar-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="mar-campo__entrada mar-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-mar-cocina></textarea>
            </div>
        </div>

        <div class="mar-pedido__pie">
            <p class="mar-pedido__total"><span>Total</span> <span data-mar-total></span></p>
            <p class="mar-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="mar-aviso" role="status" hidden data-mar-aviso-envio></p>

            <div class="mar-aviso mar-aviso--exito" role="status" tabindex="-1" hidden data-mar-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="mar-pedido__botones">
                    <button class="mar-boton" type="button" data-mar-listo>Ya lo mandé</button>
                    <button class="mar-boton mar-boton--borde" type="button" data-mar-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="mar-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-mar-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="mar-pedido__botones">
                    <button class="mar-boton mar-boton--borde" type="button" data-mar-conservar>Conservar</button>
                    <button class="mar-boton" type="button" data-mar-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="mar-pedido__botones">
                <button class="mar-boton mar-pedido__enviar" type="button" disabled data-mar-enviar>
                    <svg class="mar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="mar-boton mar-boton--borde" type="button" hidden data-mar-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
