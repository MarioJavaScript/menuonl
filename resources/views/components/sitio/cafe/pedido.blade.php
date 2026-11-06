@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla P). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="caf-pedido-raiz" data-caf-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_caf_'.$restaurante->slug }}">
    <button class="caf-barra" type="button" hidden data-caf-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="caf-barra__interior">
            <span class="caf-barra__cuenta" data-caf-cuenta>0</span>
            <span class="caf-barra__texto">Ver pedido</span>
            <span class="caf-barra__total" data-caf-barra-total></span>
        </span>
    </button>

    <p class="caf-toast" role="status" aria-live="polite" hidden data-caf-toast></p>

    <dialog class="caf-pedido" data-caf-dialogo aria-labelledby="pedido-titulo">
        <div class="caf-pedido__cabecera">
            <h2 class="caf-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="caf-pedido__cerrar" type="button" data-caf-cerrar aria-label="Cerrar pedido">
                <svg class="caf-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="caf-pedido__cuerpo">
            <p class="caf-pedido__vacio" data-caf-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="caf-pedido__lista" role="list" data-caf-lineas></ul>
            <template data-caf-plantilla-linea>
                <li class="caf-linea">
                    <div class="caf-linea__fila">
                        <p class="caf-linea__nombre" data-caf-nombre></p>
                        <p class="caf-linea__subtotal" data-caf-subtotal></p>
                    </div>
                    <div class="caf-linea__fila">
                        <input class="caf-campo__entrada caf-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin cebolla, extra queso" data-caf-nota>
                        <div class="caf-linea__cantidad">
                            <button class="caf-cantidad" type="button" data-caf-menos><span aria-hidden="true">−</span></button>
                            <p class="caf-linea__numero"><span class="caf-solo-lectores">Cantidad: </span><span data-caf-cantidad></span></p>
                            <button class="caf-cantidad" type="button" data-caf-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="caf" clase-campo="caf-campo" clase-etiqueta="caf-campo__etiqueta" />
            <div class="caf-campo">
                <label class="caf-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="caf-campo__entrada caf-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-caf-cocina></textarea>
            </div>
        </div>

        <div class="caf-pedido__pie">
            <p class="caf-pedido__total"><span>Total</span> <span data-caf-total></span></p>
            <p class="caf-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="caf-aviso" role="status" hidden data-caf-aviso-envio></p>

            <div class="caf-aviso caf-aviso--exito" role="status" tabindex="-1" hidden data-caf-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="caf-pedido__botones">
                    <button class="caf-boton" type="button" data-caf-listo>Ya lo mandé</button>
                    <button class="caf-boton caf-boton--borde" type="button" data-caf-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="caf-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-caf-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="caf-pedido__botones">
                    <button class="caf-boton caf-boton--borde" type="button" data-caf-conservar>Conservar</button>
                    <button class="caf-boton" type="button" data-caf-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="caf-pedido__botones">
                <button class="caf-boton caf-pedido__enviar" type="button" disabled data-caf-enviar>
                    <svg class="caf-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="caf-boton caf-boton--borde" type="button" hidden data-caf-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
