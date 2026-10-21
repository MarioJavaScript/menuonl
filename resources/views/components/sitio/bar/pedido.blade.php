@props(['restaurante', 'numero', 'idioma', 'mesa' => null])
@php
    /*
     * Cajón del pedido por WhatsApp (plantilla F). Todo lo que lee el script sale de estos atributos, escritos por el servidor:
     * número ya validado (8 a 20 dígitos), nombre del restaurante, moneda, idioma y mesa de ?mesa= ya filtrada.
     * Sin JavaScript no se dibuja nada útil: el dialog queda cerrado y el menú conserva su enlace a WhatsApp.
     */
@endphp
<div class="bar-pedido-raiz" data-bar-pedido
     data-numero="{{ $numero }}"
     data-restaurante="{{ $restaurante->nombre }}"
     data-moneda="{{ strtoupper($restaurante->moneda) }}"
     data-locale="{{ \App\Services\FormatoMoneda::locale($idioma, $restaurante->moneda) }}"
     data-mesa="{{ $mesa }}"
     data-clave="{{ 'menuonl_cart_f_'.$restaurante->slug }}">
    <button class="bar-barra" type="button" hidden data-bar-barra aria-haspopup="dialog" aria-expanded="false">
        <span class="bar-barra__cuenta" data-bar-cuenta>0</span>
        <span class="bar-barra__texto">Ver pedido</span>
        <span class="bar-barra__total" data-bar-barra-total></span>
    </button>

    <p class="bar-toast" role="status" aria-live="polite" hidden data-bar-toast></p>

    <dialog class="bar-pedido" data-bar-dialogo aria-labelledby="pedido-titulo">
        <div class="bar-pedido__cabecera">
            <h2 class="bar-pedido__titulo" id="pedido-titulo">Tu pedido</h2>
            <button class="bar-pedido__cerrar" type="button" data-bar-cerrar aria-label="Cerrar pedido">
                <svg class="bar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="bar-pedido__cuerpo">
            <p class="bar-pedido__vacio" data-bar-vacio>Tu pedido está vacío. Toca "Agregar" en un platillo.</p>
            <ul class="bar-pedido__lista" role="list" data-bar-lineas></ul>
            <template data-bar-plantilla-linea>
                <li class="bar-linea">
                    <div class="bar-linea__fila">
                        <p class="bar-linea__nombre" data-bar-nombre></p>
                        <p class="bar-linea__subtotal" data-bar-subtotal></p>
                    </div>
                    <div class="bar-linea__fila">
                        <input class="bar-campo__entrada bar-linea__nota" type="text" autocomplete="off" placeholder="Nota. Ej. sin azúcar" data-bar-nota>
                        <div class="bar-linea__cantidad">
                            <button class="bar-cantidad" type="button" data-bar-menos><span aria-hidden="true">−</span></button>
                            <p class="bar-linea__numero"><span class="bar-solo-lectores">Cantidad: </span><span data-bar-cantidad></span></p>
                            <button class="bar-cantidad" type="button" data-bar-mas-uno><span aria-hidden="true">+</span></button>
                        </div>
                    </div>
                </li>
            </template>

            <div class="bar-campo">
                <label class="bar-campo__etiqueta" for="pedido-mesa">Mesa</label>
                <input class="bar-campo__entrada" id="pedido-mesa" type="text" autocomplete="off" placeholder='Ej. 4, o escribe "Para llevar"' data-bar-mesa>
            </div>
            <div class="bar-campo">
                <label class="bar-campo__etiqueta" for="pedido-cocina">Nota para la cocina (opcional)</label>
                <textarea class="bar-campo__entrada bar-campo__area" id="pedido-cocina" rows="2" placeholder="Ej. Sin cubiertos" data-bar-cocina></textarea>
            </div>
        </div>

        <div class="bar-pedido__pie">
            <p class="bar-pedido__total"><span>Total</span> <span data-bar-total></span></p>
            <p class="bar-pedido__nota">El total es informativo. El restaurante confirma tu pedido por WhatsApp.</p>
            <p class="bar-aviso" role="status" hidden data-bar-aviso-envio></p>

            <div class="bar-aviso bar-aviso--exito" role="status" tabindex="-1" hidden data-bar-enviado>
                <p>Abrimos WhatsApp con tu pedido. Tócale enviar ahí para que nos llegue.</p>
                <div class="bar-pedido__botones">
                    <button class="bar-boton" type="button" data-bar-listo>Ya lo mandé</button>
                    <button class="bar-boton bar-boton--contorno" type="button" data-bar-aun-no>Todavía no</button>
                </div>
            </div>

            <div class="bar-aviso" role="alertdialog" aria-labelledby="pedido-vaciar" hidden data-bar-confirmar-vaciar>
                <p id="pedido-vaciar">¿Quitar todo de tu pedido?</p>
                <div class="bar-pedido__botones">
                    <button class="bar-boton bar-boton--contorno" type="button" data-bar-conservar>Conservar</button>
                    <button class="bar-boton" type="button" data-bar-confirmar>Vaciar pedido</button>
                </div>
            </div>

            <div class="bar-pedido__botones">
                <button class="bar-boton bar-pedido__enviar" type="button" disabled data-bar-enviar>
                    <svg class="bar-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/></svg>
                    <span>Enviar por WhatsApp</span>
                </button>
                <button class="bar-boton bar-boton--contorno" type="button" hidden data-bar-vaciar>Vaciar pedido</button>
            </div>
        </div>
    </dialog>
</div>
