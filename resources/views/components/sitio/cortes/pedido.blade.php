@props(['restaurante', 'numero', 'idioma'])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla K). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y el mensaje del restaurante ya limpio.
     * El cliente elige la entrega (Domicilio o Para recoger); no hay pedidos de mesa y ?mesa= no llega aquí.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="cor-pedido-raiz" data-cor-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mensaje="{{ $restaurante->mensajeDePedido() }}"
     data-clave="{{ 'menuonl_cart_k_'.$restaurante->slug }}">
    <button class="cor-barra" type="button" hidden data-cor-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="cor-barra__interior">
            <span class="cor-barra__cuenta" data-cor-cuenta>0</span>
            <span class="cor-barra__texto">Ver pedido</span>
            <span class="cor-barra__total" data-cor-barra-total></span>
        </span>
    </button>

    <p class="cor-toast" role="status" aria-live="polite" hidden data-cor-toast></p>

    <dialog class="cor-pedido" data-cor-dialogo aria-labelledby="pedido-titulo">
        <div class="cor-pedido__cabecera">
            <h2 class="cor-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="cor-pedido__cerrar" type="button" data-cor-cerrar aria-label="Cerrar pedido">
                <svg class="cor-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="cor-pedido__cuerpo">
            <p class="cor-pedido__vacio" data-cor-vacio>Tu pedido está vacío. Toca "+" junto a un platillo.</p>
            <ul class="cor-pedido__lista" role="list" data-cor-lineas></ul>
            <template data-cor-plantilla-linea>
                <li class="cor-linea">
                    <div class="cor-linea__fila">
                        <p class="cor-linea__nombre" data-cor-nombre></p>
                        <p class="cor-linea__subtotal" data-cor-subtotal></p>
                    </div>
                    <div class="cor-linea__fila">
                        <input class="cor-campo__entrada cor-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-cor-nota>
                        <div class="cor-linea__cantidad">
                            <button class="cor-cantidad" type="button" data-cor-menos><span aria-hidden="true">−</span></button>
                            <p class="cor-linea__numero"><span class="cor-solo-lectores">Cantidad: </span><span data-cor-cantidad></span></p>
                            <button class="cor-cantidad" type="button" data-cor-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <x-sitio.entrega :restaurante="$restaurante" prefijo="cor" clase-campo="cor-campo" clase-etiqueta="cor-campo__etiqueta" />
            <div class="cor-campo">
                <label class="cor-campo__etiqueta" for="pedido-cocina">Notas para la cocina (opcional)</label>
                <textarea class="cor-campo__entrada cor-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-cor-cocina></textarea>
            </div>
        </div>

        <div class="cor-pedido__pie">
            <p class="cor-pedido__total"><span>Total</span> <span data-cor-total></span></p>
            <p class="cor-pedido__nota">{{ $restaurante->textoDeModalidadesDePedido() }} El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="cor-aviso" role="status" hidden data-cor-aviso-envio></p>

            <div class="cor-aviso cor-aviso--exito" role="status" tabindex="-1" hidden data-cor-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="cor-pedido__botones">
                    <button class="cor-boton" type="button" data-cor-listo>Ya lo mandé</button>
                    <button class="cor-boton cor-boton--borde" type="button" data-cor-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="cor-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-cor-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="cor-pedido__botones">
                    <button class="cor-boton cor-boton--borde" type="button" data-cor-conservar>Conservar</button>
                    <button class="cor-boton" type="button" data-cor-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="cor-pedido__botones">
                <button class="cor-boton cor-pedido__enviar" type="button" disabled data-cor-enviar>
                    <svg class="cor-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="cor-boton cor-boton--borde" type="button" hidden data-cor-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
