@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla O). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="cob-pedido-raiz" data-cob-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_cob_'.$restaurante->slug }}">
    <button class="cob-barra" type="button" hidden data-cob-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="cob-barra__interior">
            <span class="cob-barra__cuenta" data-cob-cuenta>0</span>
            <span class="cob-barra__texto">Ver pedido</span>
            <span class="cob-barra__total" data-cob-barra-total></span>
        </span>
    </button>

    <p class="cob-toast" role="status" aria-live="polite" hidden data-cob-toast></p>

    <dialog class="cob-pedido" data-cob-dialogo aria-labelledby="pedido-titulo">
        <div class="cob-pedido__cabecera">
            <h2 class="cob-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="cob-pedido__cerrar" type="button" data-cob-cerrar aria-label="Cerrar pedido">
                <svg class="cob-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="cob-pedido__cuerpo">
            <p class="cob-pedido__vacio" data-cob-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="cob-pedido__lista" role="list" data-cob-lineas></ul>
            <template data-cob-plantilla-linea>
                <li class="cob-linea">
                    <div class="cob-linea__fila">
                        <p class="cob-linea__nombre" data-cob-nombre></p>
                        <p class="cob-linea__subtotal" data-cob-subtotal></p>
                    </div>
                    <div class="cob-linea__fila">
                        <input class="cob-campo__entrada cob-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin cebolla, extra queso" data-cob-nota>
                        <div class="cob-linea__cantidad">
                            <button class="cob-cantidad" type="button" data-cob-menos><span aria-hidden="true">−</span></button>
                            <p class="cob-linea__numero"><span class="cob-solo-lectores">Cantidad: </span><span data-cob-cantidad></span></p>
                            <button class="cob-cantidad" type="button" data-cob-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="cob" clase-campo="cob-campo" clase-etiqueta="cob-campo__etiqueta" />
            <div class="cob-campo">
                <label class="cob-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="cob-campo__entrada cob-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-cob-cocina></textarea>
            </div>
        </div>

        <div class="cob-pedido__pie">
            <p class="cob-pedido__total"><span>Total</span> <span data-cob-total></span></p>
            <p class="cob-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="cob-aviso" role="status" hidden data-cob-aviso-envio></p>

            <div class="cob-aviso cob-aviso--exito" role="status" tabindex="-1" hidden data-cob-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="cob-pedido__botones">
                    <button class="cob-boton" type="button" data-cob-listo>Ya lo mandé</button>
                    <button class="cob-boton cob-boton--borde" type="button" data-cob-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="cob-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-cob-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="cob-pedido__botones">
                    <button class="cob-boton cob-boton--borde" type="button" data-cob-conservar>Conservar</button>
                    <button class="cob-boton" type="button" data-cob-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="cob-pedido__botones">
                <button class="cob-boton cob-pedido__enviar" type="button" disabled data-cob-enviar>
                    <svg class="cob-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="cob-boton cob-boton--borde" type="button" hidden data-cob-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
