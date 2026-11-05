@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla I). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="jap-pedido-raiz" data-jap-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_i_'.$restaurante->slug }}">
    <button class="jap-barra" type="button" hidden data-jap-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="jap-barra__interior">
            <span class="jap-barra__cuenta" data-jap-cuenta>0</span>
            <span class="jap-barra__texto">Ver pedido</span>
            <span class="jap-barra__total" data-jap-barra-total></span>
        </span>
    </button>

    <p class="jap-toast" role="status" aria-live="polite" hidden data-jap-toast></p>

    <dialog class="jap-pedido" data-jap-dialogo aria-labelledby="pedido-titulo">
        <div class="jap-pedido__cabecera">
            <h2 class="jap-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="jap-pedido__cerrar" type="button" data-jap-cerrar aria-label="Cerrar pedido">
                <svg class="jap-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="jap-pedido__cuerpo">
            <p class="jap-pedido__vacio" data-jap-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="jap-pedido__lista" role="list" data-jap-lineas></ul>
            <template data-jap-plantilla-linea>
                <li class="jap-linea">
                    <div class="jap-linea__fila">
                        <p class="jap-linea__nombre" data-jap-nombre></p>
                        <p class="jap-linea__subtotal" data-jap-subtotal></p>
                    </div>
                    <div class="jap-linea__fila">
                        <input class="jap-campo__entrada jap-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-jap-nota>
                        <div class="jap-linea__cantidad">
                            <button class="jap-cantidad" type="button" data-jap-menos><span aria-hidden="true">−</span></button>
                            <p class="jap-linea__numero"><span class="jap-solo-lectores">Cantidad: </span><span data-jap-cantidad></span></p>
                            <button class="jap-cantidad" type="button" data-jap-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="jap" clase-campo="jap-campo" clase-etiqueta="jap-campo__etiqueta" />
            <div class="jap-campo">
                <label class="jap-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="jap-campo__entrada jap-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-jap-cocina></textarea>
            </div>
        </div>

        <div class="jap-pedido__pie">
            <p class="jap-pedido__total"><span>Total</span> <span data-jap-total></span></p>
            <p class="jap-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="jap-aviso" role="status" hidden data-jap-aviso-envio></p>

            <div class="jap-aviso jap-aviso--exito" role="status" tabindex="-1" hidden data-jap-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="jap-pedido__botones">
                    <button class="jap-boton" type="button" data-jap-listo>Ya lo mandé</button>
                    <button class="jap-boton jap-boton--borde" type="button" data-jap-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="jap-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-jap-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="jap-pedido__botones">
                    <button class="jap-boton jap-boton--borde" type="button" data-jap-conservar>Conservar</button>
                    <button class="jap-boton" type="button" data-jap-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="jap-pedido__botones">
                <button class="jap-boton jap-boton--verde jap-pedido__enviar" type="button" disabled data-jap-enviar>
                    <svg class="jap-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="jap-boton jap-boton--borde" type="button" hidden data-jap-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
