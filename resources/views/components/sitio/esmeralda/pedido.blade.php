@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla L). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="esm-pedido-raiz" data-esm-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_l_'.$restaurante->slug }}">
    <button class="esm-barra" type="button" hidden data-esm-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="esm-barra__interior">
            <span class="esm-barra__cuenta" data-esm-cuenta>0</span>
            <span class="esm-barra__texto">Ver pedido</span>
            <span class="esm-barra__total" data-esm-barra-total></span>
        </span>
    </button>

    <p class="esm-toast" role="status" aria-live="polite" hidden data-esm-toast></p>

    <dialog class="esm-pedido" data-esm-dialogo aria-labelledby="pedido-titulo">
        <div class="esm-pedido__cabecera">
            <h2 class="esm-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="esm-pedido__cerrar" type="button" data-esm-cerrar aria-label="Cerrar pedido">
                <svg class="esm-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="esm-pedido__cuerpo">
            <p class="esm-pedido__vacio" data-esm-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="esm-pedido__lista" role="list" data-esm-lineas></ul>
            <template data-esm-plantilla-linea>
                <li class="esm-linea">
                    <div class="esm-linea__fila">
                        <p class="esm-linea__nombre" data-esm-nombre></p>
                        <p class="esm-linea__subtotal" data-esm-subtotal></p>
                    </div>
                    <div class="esm-linea__fila">
                        <input class="esm-campo__entrada esm-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-esm-nota>
                        <div class="esm-linea__cantidad">
                            <button class="esm-cantidad" type="button" data-esm-menos><span aria-hidden="true">−</span></button>
                            <p class="esm-linea__numero"><span class="esm-solo-lectores">Cantidad: </span><span data-esm-cantidad></span></p>
                            <button class="esm-cantidad" type="button" data-esm-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="esm" clase-campo="esm-campo" clase-etiqueta="esm-campo__etiqueta" />
            <div class="esm-campo">
                <label class="esm-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="esm-campo__entrada esm-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-esm-cocina></textarea>
            </div>
        </div>

        <div class="esm-pedido__pie">
            <p class="esm-pedido__total"><span>Total</span> <span data-esm-total></span></p>
            <p class="esm-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="esm-aviso" role="status" hidden data-esm-aviso-envio></p>

            <div class="esm-aviso esm-aviso--exito" role="status" tabindex="-1" hidden data-esm-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="esm-pedido__botones">
                    <button class="esm-boton" type="button" data-esm-listo>Ya lo mandé</button>
                    <button class="esm-boton esm-boton--borde" type="button" data-esm-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="esm-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-esm-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="esm-pedido__botones">
                    <button class="esm-boton esm-boton--borde" type="button" data-esm-conservar>Conservar</button>
                    <button class="esm-boton" type="button" data-esm-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="esm-pedido__botones">
                <button class="esm-boton esm-pedido__enviar" type="button" disabled data-esm-enviar>
                    <svg class="esm-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="esm-boton esm-boton--borde" type="button" hidden data-esm-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
