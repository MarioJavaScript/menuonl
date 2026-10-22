@props(['restaurante', 'numero', 'idioma', 'mesa' => null])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla H). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y mesa de ?mesa= ya filtrada.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="piz-pedido-raiz" data-piz-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mesa="{{ $mesa }}"
     data-clave="{{ 'menuonl_cart_h_'.$restaurante->slug }}">
    <button class="piz-barra" type="button" hidden data-piz-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="piz-barra__cuenta" data-piz-cuenta>0</span>
        <span class="piz-barra__texto">Ver pedido</span>
        <span class="piz-barra__total" data-piz-barra-total></span>
    </button>

    <p class="piz-toast" role="status" aria-live="polite" hidden data-piz-toast></p>

    <dialog class="piz-pedido" data-piz-dialogo aria-labelledby="pedido-titulo">
        <div class="piz-pedido__cabecera">
            <h2 class="piz-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="piz-pedido__cerrar" type="button" data-piz-cerrar aria-label="Cerrar pedido">
                <svg class="piz-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="piz-pedido__cuerpo">
            <p class="piz-pedido__vacio" data-piz-vacio>Tu pedido está vacío. Toca "Agregar" en un platillo.</p>
            <ul class="piz-pedido__lista" role="list" data-piz-lineas></ul>
            <template data-piz-plantilla-linea>
                <li class="piz-linea">
                    <div class="piz-linea__fila">
                        <p class="piz-linea__nombre" data-piz-nombre></p>
                        <p class="piz-linea__subtotal" data-piz-subtotal></p>
                    </div>
                    <div class="piz-linea__fila">
                        <input class="piz-campo__entrada piz-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-piz-nota>
                        <div class="piz-linea__cantidad">
                            <button class="piz-cantidad" type="button" data-piz-menos><span aria-hidden="true">−</span></button>
                            <p class="piz-linea__numero"><span class="piz-solo-lectores">Cantidad: </span><span data-piz-cantidad></span></p>
                            <button class="piz-cantidad" type="button" data-piz-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <div class="piz-campo">
                <label class="piz-campo__etiqueta" for="pedido-mesa">Mesa</label>
                <input class="piz-campo__entrada" id="pedido-mesa" type="text" autocomplete="off" placeholder='Ej. 4, o escribe "Para llevar"' data-piz-mesa>
            </div>
            <div class="piz-campo">
                <label class="piz-campo__etiqueta" for="pedido-cocina">Nota para la cocina (opcional)</label>
                <textarea class="piz-campo__entrada piz-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-piz-cocina></textarea>
            </div>
        </div>

        <div class="piz-pedido__pie">
            <p class="piz-pedido__total"><span>Total</span> <span data-piz-total></span></p>
            <p class="piz-pedido__nota">El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="piz-aviso" role="status" hidden data-piz-aviso-envio></p>

            <div class="piz-aviso piz-aviso--exito" role="status" tabindex="-1" hidden data-piz-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="piz-pedido__botones">
                    <button class="piz-boton" type="button" data-piz-listo>Ya lo mandé</button>
                    <button class="piz-boton piz-boton--borde" type="button" data-piz-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="piz-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-piz-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="piz-pedido__botones">
                    <button class="piz-boton piz-boton--borde" type="button" data-piz-conservar>Conservar</button>
                    <button class="piz-boton" type="button" data-piz-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="piz-pedido__botones">
                <button class="piz-boton piz-boton--verde piz-pedido__enviar" type="button" disabled data-piz-enviar>
                    <svg class="piz-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="piz-boton piz-boton--borde" type="button" hidden data-piz-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
