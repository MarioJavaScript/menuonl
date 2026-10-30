@props(['restaurante', 'numero', 'idioma', 'mesa' => null])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla E). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y mesa de ?mesa= ya filtrada.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="cal-pedido-raiz" data-cal-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mesa="{{ $mesa }}"
     data-clave="{{ 'menuonl_cart_e_'.$restaurante->slug }}">
    <button class="cal-barra" type="button" hidden data-cal-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="cal-barra__cuenta" data-cal-cuenta>0</span>
        <span class="cal-barra__texto">Ver pedido</span>
        <span class="cal-barra__total" data-cal-barra-total></span>
    </button>

    <p class="cal-toast" role="status" aria-live="polite" hidden data-cal-toast></p>

    <dialog class="cal-pedido" data-cal-dialogo aria-labelledby="pedido-titulo">
        <div class="cal-pedido__cabecera">
            <h2 class="cal-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="cal-pedido__cerrar" type="button" data-cal-cerrar aria-label="Cerrar pedido">
                <svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="cal-pedido__cuerpo">
            <p class="cal-pedido__vacio" data-cal-vacio>Tu pedido está vacío. Toca "Agregar" en un platillo.</p>
            <ul class="cal-pedido__lista" role="list" data-cal-lineas></ul>
            <template data-cal-plantilla-linea>
                <li class="cal-linea">
                    <div class="cal-linea__fila">
                        <p class="cal-linea__nombre" data-cal-nombre></p>
                        <p class="cal-linea__subtotal" data-cal-subtotal></p>
                    </div>
                    <div class="cal-linea__fila">
                        <input class="cal-campo__entrada cal-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-cal-nota>
                        <div class="cal-linea__cantidad">
                            <button class="cal-cantidad" type="button" data-cal-menos><span aria-hidden="true">−</span></button>
                            <p class="cal-linea__numero"><span class="cal-visualmente-oculto">Cantidad: </span><span data-cal-cantidad></span></p>
                            <button class="cal-cantidad" type="button" data-cal-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <div class="cal-campo">
                <label class="cal-campo__etiqueta" for="pedido-mesa">Mesa</label>
                <input class="cal-campo__entrada" id="pedido-mesa" type="text" autocomplete="off" placeholder='Ej. 4, o escribe "Para llevar"' data-cal-mesa>
            </div>
            <div class="cal-campo">
                <label class="cal-campo__etiqueta" for="pedido-cocina">Nota para la cocina (opcional)</label>
                <textarea class="cal-campo__entrada cal-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-cal-cocina></textarea>
            </div>
        </div>

        <div class="cal-pedido__pie">
            <p class="cal-pedido__total"><span>Total</span> <span data-cal-total></span></p>
            <p class="cal-pedido__nota">Pedidos para domicilio o para recoger. El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="cal-aviso" role="status" hidden data-cal-aviso-envio></p>

            <div class="cal-aviso cal-aviso--exito" role="status" tabindex="-1" hidden data-cal-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="cal-pedido__botones">
                    <button class="cal-boton cal-boton--arcilla" type="button" data-cal-listo>Ya lo mandé</button>
                    <button class="cal-boton cal-boton--contorno" type="button" data-cal-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="cal-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-cal-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="cal-pedido__botones">
                    <button class="cal-boton cal-boton--contorno" type="button" data-cal-conservar>Conservar</button>
                    <button class="cal-boton cal-boton--arcilla" type="button" data-cal-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="cal-pedido__botones">
                <button class="cal-boton cal-boton--arcilla cal-pedido__enviar" type="button" disabled data-cal-enviar>
                    <svg class="cal-boton__icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="cal-boton cal-boton--contorno" type="button" hidden data-cal-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
