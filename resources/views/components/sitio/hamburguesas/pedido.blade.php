@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla M). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="ham-pedido-raiz" data-ham-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_ham_'.$restaurante->slug }}">
    <button class="ham-barra" type="button" hidden data-ham-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="ham-barra__interior">
            <span class="ham-barra__cuenta" data-ham-cuenta>0</span>
            <span class="ham-barra__texto">Ver pedido</span>
            <span class="ham-barra__total" data-ham-barra-total></span>
        </span>
    </button>

    <p class="ham-toast" role="status" aria-live="polite" hidden data-ham-toast></p>

    <dialog class="ham-pedido" data-ham-dialogo aria-labelledby="pedido-titulo">
        <div class="ham-pedido__cabecera">
            <h2 class="ham-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="ham-pedido__cerrar" type="button" data-ham-cerrar aria-label="Cerrar pedido">
                <svg class="ham-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="ham-pedido__cuerpo">
            <p class="ham-pedido__vacio" data-ham-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="ham-pedido__lista" role="list" data-ham-lineas></ul>
            <template data-ham-plantilla-linea>
                <li class="ham-linea">
                    <div class="ham-linea__fila">
                        <p class="ham-linea__nombre" data-ham-nombre></p>
                        <p class="ham-linea__subtotal" data-ham-subtotal></p>
                    </div>
                    <div class="ham-linea__fila">
                        <input class="ham-campo__entrada ham-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin cebolla, extra queso" data-ham-nota>
                        <div class="ham-linea__cantidad">
                            <button class="ham-cantidad" type="button" data-ham-menos><span aria-hidden="true">−</span></button>
                            <p class="ham-linea__numero"><span class="ham-solo-lectores">Cantidad: </span><span data-ham-cantidad></span></p>
                            <button class="ham-cantidad" type="button" data-ham-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="ham" clase-campo="ham-campo" clase-etiqueta="ham-campo__etiqueta" />
            <div class="ham-campo">
                <label class="ham-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="ham-campo__entrada ham-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-ham-cocina></textarea>
            </div>
        </div>

        <div class="ham-pedido__pie">
            <p class="ham-pedido__total"><span>Total</span> <span data-ham-total></span></p>
            <p class="ham-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="ham-aviso" role="status" hidden data-ham-aviso-envio></p>

            <div class="ham-aviso ham-aviso--exito" role="status" tabindex="-1" hidden data-ham-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="ham-pedido__botones">
                    <button class="ham-boton" type="button" data-ham-listo>Ya lo mandé</button>
                    <button class="ham-boton ham-boton--borde" type="button" data-ham-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="ham-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-ham-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="ham-pedido__botones">
                    <button class="ham-boton ham-boton--borde" type="button" data-ham-conservar>Conservar</button>
                    <button class="ham-boton" type="button" data-ham-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="ham-pedido__botones">
                <button class="ham-boton ham-pedido__enviar" type="button" disabled data-ham-enviar>
                    <svg class="ham-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="ham-boton ham-boton--borde" type="button" hidden data-ham-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
