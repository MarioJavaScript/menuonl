// Pedido por WhatsApp de menu.publico (plantillas a-d y menú por defecto). Se carga solo cuando el restaurante puede recibir pedidos.
// La lógica vive en pedido/; aquí solo se migra el carrito antiguo y se inicia con la presentación del cajón de este menú.
import { iniciarPedido } from './pedido/controlador.js';
import { migrarCarritoAntiguo } from './pedido/migracion.js';
import { crearPresentacionHoja } from './pedido/presentacion-hoja.js';

const raiz = document.querySelector('[data-mo-pedido]');

if (raiz) {
    try {
        if (raiz.dataset.claveAntigua && raiz.dataset.clave) {
            migrarCarritoAntiguo(localStorage, raiz.dataset.claveAntigua, raiz.dataset.clave);
        }
    } catch {
        // localStorage puede lanzar al leer la propiedad (modo privado): el pedido funciona igual sin carrito guardado.
    }

    iniciarPedido('mo', 'menuonl_cart_m', {
        presentacion: crearPresentacionHoja,
        textoAgregado: (etiqueta) => `${etiqueta} agregado`,
    });
}
