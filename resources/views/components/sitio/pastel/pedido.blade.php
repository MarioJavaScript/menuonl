@props(['restaurante', 'numero', 'idioma', 'mesa' => null])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla G). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y mesa de ?mesa= ya filtrada.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="pas-pedido-raiz" data-pas-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mesa="{{ $mesa }}"
     data-clave="{{ 'menuonl_cart_g_'.$restaurante->slug }}">
    <button class="pas-barra" type="button" hidden data-pas-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="pas-barra__cuenta" data-pas-cuenta>0</span>
        <span class="pas-barra__texto">Ver pedido</span>
        <span class="pas-barra__total" data-pas-barra-total></span>
    </button>

    <p class="pas-toast" role="status" aria-live="polite" hidden data-pas-toast></p>

    <dialog class="pas-pedido" data-pas-dialogo aria-labelledby="pedido-titulo">
        <div class="pas-pedido__cabecera">
            <h2 class="pas-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="pas-pedido__cerrar" type="button" data-pas-cerrar aria-label="Cerrar pedido">
                <svg class="pas-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="pas-pedido__cuerpo">
            <p class="pas-pedido__vacio" data-pas-vacio>Tu pedido está vacío. Toca "Agregar" en un platillo.</p>
            <ul class="pas-pedido__lista" role="list" data-pas-lineas></ul>
            <template data-pas-plantilla-linea>
                <li class="pas-linea">
                    <div class="pas-linea__fila">
                        <p class="pas-linea__nombre" data-pas-nombre></p>
                        <p class="pas-linea__subtotal" data-pas-subtotal></p>
                    </div>
                    <div class="pas-linea__fila">
                        <input class="pas-campo__entrada pas-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-pas-nota>
                        <div class="pas-linea__cantidad">
                            <button class="pas-cantidad" type="button" data-pas-menos><span aria-hidden="true">−</span></button>
                            <p class="pas-linea__numero"><span class="pas-solo-lectores">Cantidad: </span><span data-pas-cantidad></span></p>
                            <button class="pas-cantidad" type="button" data-pas-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <div class="pas-campo">
                <label class="pas-campo__etiqueta" for="pedido-mesa">Mesa</label>
                <input class="pas-campo__entrada" id="pedido-mesa" type="text" autocomplete="off" placeholder='Ej. 4, o escribe "Para llevar"' data-pas-mesa>
            </div>
            <div class="pas-campo">
                <label class="pas-campo__etiqueta" for="pedido-cocina">Nota para la cocina (opcional)</label>
                <textarea class="pas-campo__entrada pas-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-pas-cocina></textarea>
            </div>
        </div>

        <div class="pas-pedido__pie">
            <p class="pas-pedido__total"><span>Total</span> <span data-pas-total></span></p>
            <p class="pas-pedido__nota">Pedidos para domicilio o para recoger. El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="pas-aviso" role="status" hidden data-pas-aviso-envio></p>

            <div class="pas-aviso pas-aviso--exito" role="status" tabindex="-1" hidden data-pas-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="pas-pedido__botones">
                    <button class="pas-boton" type="button" data-pas-listo>Ya lo mandé</button>
                    <button class="pas-boton pas-boton--contorno" type="button" data-pas-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="pas-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-pas-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="pas-pedido__botones">
                    <button class="pas-boton pas-boton--contorno" type="button" data-pas-conservar>Conservar</button>
                    <button class="pas-boton" type="button" data-pas-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="pas-pedido__botones">
                <button class="pas-boton pas-pedido__enviar" type="button" disabled data-pas-enviar>
                    <svg class="pas-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="pas-boton pas-boton--contorno" type="button" hidden data-pas-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
