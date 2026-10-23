// DOM mínimo para probar pedido/controlador.js con Node (sin dependencias nuevas). Solo implementa lo que el controlador usa:
// selectores de atributo, clase, id y etiqueta (con descendientes por espacio), closest, dataset, eventos con burbujeo,
// <template>, replaceChildren, cloneNode y un localStorage de memoria.

const aKebab = (texto) => texto.replace(/[A-Z]/g, (letra) => `-${letra.toLowerCase()}`);

function pasos(selector) {
    return selector.trim().split(/\s+/).map((paso) => {
        const etiqueta = /^[a-z][a-z0-9]*/i.exec(paso)?.[0]?.toLowerCase() ?? null;
        const atributos = [...paso.matchAll(/\[([^\]=^]+)(\^?=)?(?:"([^"]*)")?\]/g)].map(([, nombre, operador, valor]) => [nombre, valor, operador === '^=']);
        const clases = [...paso.replace(/\[[^\]]*\]/g, '').matchAll(/\.([\w-]+)/g)].map(([, clase]) => clase);
        const id = /#([\w-]+)/.exec(paso.replace(/\[[^\]]*\]/g, ''))?.[1] ?? null;

        return { etiqueta, atributos, clases, id };
    });
}

function coincide(nodo, paso) {
    return (paso.etiqueta === null || nodo.tagName === paso.etiqueta)
        && paso.atributos.every(([nombre, valor, prefijo]) => nodo.attrs.has(nombre) && (valor === undefined || (prefijo ? nodo.attrs.get(nombre).startsWith(valor) : nodo.attrs.get(nombre) === valor)))
        && paso.clases.every((clase) => nodo.classList.contains(clase))
        && (paso.id === null || nodo.attrs.get('id') === paso.id);
}

export class Nodo {
    constructor(etiqueta, atributos = {}, hijos = []) {
        this.tagName = etiqueta.toLowerCase();
        this.attrs = new Map(Object.entries(atributos).map(([nombre, valor]) => [nombre, String(valor)]));
        this.children = [];
        this.parent = null;
        this.listeners = new Map();
        this.texto = '';
        this.value = this.attrs.get('value') ?? '';
        this.disabled = false;
        this.maxLength = -1;
        this.style = {};
        hijos.forEach((hijo) => this.append(hijo));

        const clases = new Set((this.attrs.get('class') ?? '').split(/\s+/).filter(Boolean));
        this.classList = {
            add: (...nombres) => nombres.forEach((nombre) => clases.add(nombre)),
            remove: (...nombres) => nombres.forEach((nombre) => clases.delete(nombre)),
            contains: (nombre) => clases.has(nombre),
        };
        this.dataset = new Proxy({}, {
            get: (_, propiedad) => this.attrs.get(`data-${aKebab(String(propiedad))}`),
            set: (_, propiedad, valor) => {
                this.attrs.set(`data-${aKebab(String(propiedad))}`, String(valor));

                return true;
            },
        });
    }

    get offsetParent() {
        for (let nodo = this; nodo; nodo = nodo.parent) {
            if (nodo.hidden) {
                return null;
            }
        }

        return {};
    }

    get hidden() {
        return this.attrs.has('hidden');
    }

    set hidden(valor) {
        if (valor) {
            this.attrs.set('hidden', '');
        } else {
            this.attrs.delete('hidden');
        }
    }

    get textContent() {
        return this.children.length > 0 ? this.children.map((hijo) => hijo.textContent).join('') : this.texto;
    }

    set textContent(valor) {
        this.children = [];
        this.texto = String(valor);
    }

    get content() {
        return { firstElementChild: this.children[0] };
    }

    get firstElementChild() {
        return this.children[0];
    }

    get open() {
        return this.attrs.has('open');
    }

    append(hijo) {
        hijo.parent = this;
        this.children.push(hijo);
    }

    replaceChildren(...hijos) {
        this.children = [];
        hijos.forEach((hijo) => this.append(hijo));
    }

    setAttribute(nombre, valor) {
        this.attrs.set(nombre, String(valor));
    }

    getAttribute(nombre) {
        return this.attrs.get(nombre) ?? null;
    }

    hasAttribute(nombre) {
        return this.attrs.has(nombre);
    }

    removeAttribute(nombre) {
        this.attrs.delete(nombre);
    }

    focus() {
        globalThis.document.activeElement = this;
    }

    contains(otro) {
        for (let nodo = otro; nodo; nodo = nodo.parent) {
            if (nodo === this) {
                return true;
            }
        }

        return false;
    }

    cloneNode(profundo) {
        const copia = new Nodo(this.tagName, Object.fromEntries(this.attrs));
        copia.texto = this.texto;
        copia.value = this.value;
        copia.disabled = this.disabled;

        if (profundo) {
            this.children.forEach((hijo) => copia.append(hijo.cloneNode(true)));
        }

        return copia;
    }

    closest(selector) {
        const [paso] = pasos(selector);

        for (let nodo = this; nodo; nodo = nodo.parent) {
            if (nodo instanceof Nodo && coincide(nodo, paso)) {
                return nodo;
            }
        }

        return null;
    }

    querySelectorAll(selector) {
        const alternativas = selector.split(',').map(pasos);
        const acierta = (hijo) => alternativas.some((secuencia) => {
            if (!coincide(hijo, secuencia[secuencia.length - 1])) {
                return false;
            }

            let pendiente = secuencia.length - 2;

            for (let ancestro = hijo.parent; ancestro && pendiente >= 0; ancestro = ancestro.parent) {
                if (coincide(ancestro, secuencia[pendiente])) {
                    pendiente -= 1;
                }
            }

            return pendiente < 0;
        });
        const resultado = [];
        const visitar = (nodo) => {
            nodo.children.forEach((hijo) => {
                if (acierta(hijo)) {
                    resultado.push(hijo);
                }

                visitar(hijo);
            });
        };

        visitar(this);

        return resultado;
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null;
    }

    addEventListener(tipo, funcion) {
        this.listeners.set(tipo, [...(this.listeners.get(tipo) ?? []), funcion]);
    }

    dispatchEvent(evento) {
        evento.target ??= this;

        for (let nodo = this; nodo; nodo = nodo.parent) {
            (nodo.listeners.get(evento.type) ?? []).forEach((funcion) => funcion(evento));
        }

        (globalThis.document.listeners.get(evento.type) ?? []).forEach((funcion) => funcion(evento));
    }

    clic() {
        this.dispatchEvent({ type: 'click', target: this });
    }
}

/**
 * Prepara globalThis (document, window, localStorage, CSS) alrededor de `raiz` y devuelve utilidades para la prueba.
 *
 * @param {Nodo[]} cuerpo Nodos de primer nivel de la página (el contenedor del pedido, los botones "Agregar"...).
 */
export function montarPagina(cuerpo, { almacenamiento = {} } = {}) {
    const memoria = new Map(Object.entries(almacenamiento));
    const abiertas = [];
    const cuerpoNodo = new Nodo('body', {}, cuerpo);
    const documento = {
        listeners: new Map(),
        body: cuerpoNodo,
        activeElement: null,
        querySelector: (selector) => cuerpoNodo.querySelector(selector),
        querySelectorAll: (selector) => cuerpoNodo.querySelectorAll(selector),
        getElementById: (id) => cuerpoNodo.querySelector(`#${id}`),
        addEventListener: (tipo, funcion) => documento.listeners.set(tipo, [...(documento.listeners.get(tipo) ?? []), funcion]),
    };

    globalThis.document = documento;
    globalThis.window = { open: (...argumentos) => abiertas.push(argumentos), matchMedia: () => ({ matches: true }) };
    globalThis.CSS = { escape: (texto) => String(texto) };
    globalThis.localStorage = {
        getItem: (clave) => memoria.get(clave) ?? null,
        setItem: (clave, valor) => memoria.set(clave, String(valor)),
        removeItem: (clave) => memoria.delete(clave),
    };
    globalThis.setTimeout = () => 0;
    globalThis.clearTimeout = () => {};

    return { memoria, abiertas, documento, cuerpo: cuerpoNodo, almacen: globalThis.localStorage };
}
