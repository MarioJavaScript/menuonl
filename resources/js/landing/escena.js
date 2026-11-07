/**
 * Escena 3D de la portada: un celular, un soporte con QR y tarjetas de platillos que se mueven con el scroll.
 *
 * Es solo decoración (el lienzo lleva aria-hidden): todos los textos de la página están en el HTML. Los textos que dependen de la
 * configuración llegan por atributos data-* del lienzo. Las imágenes se piden con new Image(), nunca con fetch, porque la CSP de la
 * portada tiene connect-src 'none'. Los platillos y precios son ilustrativos: no son los precios de los planes.
 */
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { encuadre, limitar } from './recorrido.js';

const FUENTE = "'Plus Jakarta Sans', system-ui, 'Segoe UI', Arial, sans-serif";

const suavizar = (t) => t * t * (3 - 2 * t);
const banda = (p, desde, hasta, fundido = 0.035) => limitar((p - desde) / fundido) * limitar((hasta - p) / fundido);
const V = (x, y, z) => new THREE.Vector3(x, y, z);

const CALIDADES = {
    alta: { densidadMaxima: 2, sombras: true, chispas: true, msPantalla: 33 },
    simple: { densidadMaxima: 1.5, sombras: false, chispas: true, msPantalla: 50 },
    minima: { densidadMaxima: 1, sombras: false, chispas: false, msPantalla: 80 },
};

const PLATILLOS = [
    ['taco', 'Tacos de asada', 'Orden de 4 con guacamole', 120],
    ['hotdog', 'Hot dog sonorense', 'Con frijoles y tocino', 65],
    ['burrito', 'Burrito percherón', 'Tortilla de harina gigante', 140],
    ['burger', 'Hamburguesa de la casa', 'Doble carne y queso', 150],
    ['drink', 'Agua de horchata', 'Vaso de 1 litro', 35],
    ['flan', 'Flan napolitano', 'Receta de la casa', 55],
];

/** Posición del celular y de la cámara a lo largo del recorrido. Coinciden con BANDAS de recorrido.js. */
const CUADROS_CLAVE = [
    { p: 0, celular: V(1.9, 0, 0), giroY: -0.5, giroX: 0.06, camara: V(0, 0.1, 6.6), mira: V(0.4, 0, 0) },
    { p: 0.16, celular: V(1.65, 0.1, 0.2), giroY: -0.25, giroX: 0.02, camara: V(0, 0.1, 6.4), mira: V(0.4, 0, 0) },
    { p: 0.27, celular: V(1.5, 0.05, 0.9), giroY: 0.42, giroX: -0.05, camara: V(3.6, 0.9, 6.4), mira: V(-0.9, -0.1, 0) },
    { p: 0.38, celular: V(1.45, 0.1, 0.9), giroY: 0.36, giroX: -0.03, camara: V(3.3, 0.8, 6.1), mira: V(-0.9, -0.05, 0) },
    { p: 0.47, celular: V(-1.1, 0, 0), giroY: 0.12, giroX: 0, camara: V(0, 0, 4.4), mira: V(0, 0, 0) },
    { p: 0.6, celular: V(-2.6, 0, 0), giroY: 0.3, giroX: 0, camara: V(0, 0.2, 7.2), mira: V(0, 0, 0) },
    { p: 0.66, celular: V(2.9, -0.1, -0.5), giroY: -0.45, giroX: 0.05, camara: V(0, 0.3, 7.6), mira: V(0, 0.2, 0) },
    { p: 0.82, celular: V(2.9, -0.1, -0.5), giroY: -0.3, giroX: 0.05, camara: V(0, 0.3, 7.6), mira: V(0, 0.2, 0) },
    { p: 1, celular: V(2.6, 0, 0), giroY: -0.5, giroX: 0.06, camara: V(0, 0.15, 7), mira: V(0.8, 0, 0) },
];

/** En móvil la posición en pantalla la da el encuadre: aquí solo se decide cómo se ve el celular, cerca del origen. */
const CUADROS_CLAVE_MOVIL = [
    { p: 0, celular: V(0, 0, 0), giroY: -0.25, giroX: 0.06, camara: V(0, 0, 7.4), mira: V(0, 0, 0) },
    { p: 0.16, celular: V(0, 0, 0), giroY: -0.1, giroX: 0.02, camara: V(0, 0, 7.2), mira: V(0, 0, 0) },
    { p: 0.27, celular: V(0.55, 0, 0.5), giroY: 0.3, giroX: -0.05, camara: V(1.2, 0.2, 7.6), mira: V(0, 0, 0) },
    { p: 0.38, celular: V(0.55, 0, 0.5), giroY: 0.25, giroX: -0.03, camara: V(1, 0.2, 7.4), mira: V(0, 0, 0) },
    { p: 0.47, celular: V(0, 0, 0), giroY: 0, giroX: 0, camara: V(0, 0, 6.8), mira: V(0, 0, 0) },
    { p: 0.6, celular: V(0, 0, 0), giroY: 0.15, giroX: 0, camara: V(0, 0, 7.4), mira: V(0, 0, 0) },
    { p: 0.66, celular: V(0, 0, 0), giroY: -0.3, giroX: 0.05, camara: V(0, 0, 7.4), mira: V(0, 0, 0) },
    { p: 0.82, celular: V(0, 0, 0), giroY: -0.2, giroX: 0.05, camara: V(0, 0, 7.4), mira: V(0, 0, 0) },
    { p: 1, celular: V(0, 0, 0), giroY: -0.3, giroX: 0.06, camara: V(0, 0, 7.4), mira: V(0, 0, 0) },
];

/** En móvil, el soporte con QR va a un lado del celular para que los dos se vean completos. */
const SOPORTE_MOVIL = V(-0.85, -1.05, -0.4);
const SOPORTE_ESCRITORIO = V(0.5, -1.69, -1.8);
const ESCALA_SOPORTE_MOVIL = 0.95;

function interpolar(p, movil) {
    const cuadros = movil ? CUADROS_CLAVE_MOVIL : CUADROS_CLAVE;
    let i = 0;

    while (i < cuadros.length - 2 && p > cuadros[i + 1].p) {
        i += 1;
    }

    const a = cuadros[i];
    const b = cuadros[i + 1];
    const t = suavizar(limitar((p - a.p) / (b.p - a.p)));

    return {
        celular: a.celular.clone().lerp(b.celular, t),
        giroY: a.giroY + (b.giroY - a.giroY) * t,
        giroX: a.giroX + (b.giroX - a.giroX) * t,
        camara: a.camara.clone().lerp(b.camara, t),
        mira: a.mira.clone().lerp(b.mira, t),
    };
}

function rectanguloRedondo(x, X, Y, W, H, r) {
    x.beginPath();
    x.moveTo(X + r, Y);
    x.arcTo(X + W, Y, X + W, Y + H, r);
    x.arcTo(X + W, Y + H, X, Y + H, r);
    x.arcTo(X, Y + H, X, Y, r);
    x.arcTo(X, Y, X + W, Y, r);
    x.closePath();
}

function cargarImagen(src) {
    return new Promise((resolver) => {
        const imagen = new Image();
        imagen.onload = () => resolver(imagen);
        imagen.onerror = () => resolver(null);
        imagen.src = src;
    });
}

/**
 * Código QR decorativo: se dibuja al azar con semilla fija y no se puede escanear.
 */
function dibujarQr(simbolo, modulos = 29, px = 12) {
    const c = document.createElement('canvas');
    c.width = c.height = modulos * px + px * 4;
    const x = c.getContext('2d');
    let semilla = 7;
    const azar = () => (semilla = (semilla * 16807) % 2147483647) / 2147483647;

    x.fillStyle = '#fff';
    x.fillRect(0, 0, c.width, c.height);
    x.fillStyle = '#12355B';

    for (let i = 0; i < modulos; i += 1) {
        for (let j = 0; j < modulos; j += 1) {
            if (azar() < 0.48) {
                x.fillRect((i + 2) * px, (j + 2) * px, px, px);
            }
        }
    }

    [[0, 0], [modulos - 7, 0], [0, modulos - 7]].forEach(([a, b]) => {
        x.fillStyle = '#fff';
        x.fillRect((a + 1) * px, (b + 1) * px, 9 * px, 9 * px);
        x.fillStyle = '#12355B';
        x.fillRect((a + 2) * px, (b + 2) * px, 7 * px, 7 * px);
        x.fillStyle = '#fff';
        x.fillRect((a + 3) * px, (b + 3) * px, 5 * px, 5 * px);
        x.fillStyle = '#2B7FD6';
        x.fillRect((a + 4) * px, (b + 4) * px, 3 * px, 3 * px);
    });

    if (simbolo) {
        const k = c.width * 0.22;
        x.fillStyle = '#fff';
        x.fillRect((c.width - k) / 2 - 8, (c.width - k) / 2 - 8, k + 16, k + 16);
        x.drawImage(simbolo, (c.width - k) / 2, (c.width - k) / 2, k, k);
    }

    return c;
}

/**
 * Comida dibujada con formas simples.
 */
function dibujarComida(x, tipo, cx, cy, tamano) {
    const E = (X, Y, rx, ry, color, r = 0) => {
        x.fillStyle = color;
        x.beginPath();
        x.ellipse(X, Y, rx, ry, r, 0, 7);
        x.fill();
    };
    const RR = (X, Y, W, H, r, color) => {
        x.fillStyle = color;
        rectanguloRedondo(x, X, Y, W, H, r);
        x.fill();
    };

    x.save();
    x.translate(cx, cy);
    x.scale(tamano / 100, tamano / 100);
    x.lineJoin = 'round';
    x.lineCap = 'round';
    E(0, 62, 70, 12, 'rgba(18,53,91,.12)');

    if (tipo === 'taco') {
        x.fillStyle = '#E9A83B';
        x.beginPath();
        x.arc(0, 30, 72, Math.PI, 0);
        x.closePath();
        x.fill();
        x.fillStyle = '#F6C85F';
        x.beginPath();
        x.arc(0, 30, 62, Math.PI, 0);
        x.closePath();
        x.fill();
        x.fillStyle = '#7A3E1D';
        x.beginPath();
        x.moveTo(-58, 22);
        for (let i = 0; i <= 12; i += 1) x.lineTo(-58 + i * 9.7, 10 - (i % 2) * 8);
        x.lineTo(58, 26);
        x.closePath();
        x.fill();
        x.fillStyle = '#5CB85C';
        x.beginPath();
        x.moveTo(-60, 16);
        for (let i = 0; i <= 10; i += 1) x.quadraticCurveTo(-54 + i * 12, -4, -48 + i * 12, 12);
        x.lineTo(62, 22);
        x.closePath();
        x.fill();
        [[-30, 4], [0, -2], [28, 4], [-12, 10], [14, 8]].forEach(([a, b]) => E(a, b, 6, 6, '#E5484D'));
        E(-22, -2, 7, 5, '#9BD36A');
        E(24, 0, 7, 5, '#9BD36A');
        x.fillStyle = '#E9A83B';
        x.fillRect(-72, 26, 144, 8);
    } else if (tipo === 'hotdog') {
        RR(-78, -6, 156, 46, 23, '#E4A65B');
        RR(-86, -14, 172, 26, 13, '#B4472A');
        x.strokeStyle = '#F7D23E';
        x.lineWidth = 6;
        x.beginPath();
        for (let i = 0; i <= 10; i += 1) x.lineTo(-66 + i * 13.2, -6 + (i % 2 ? -6 : 4));
        x.stroke();
        RR(-74, 8, 148, 34, 17, '#F0B96E');
        E(-30, 24, 10, 4, '#C98B44');
        E(10, 26, 10, 4, '#C98B44');
        E(44, 22, 9, 4, '#C98B44');
    } else if (tipo === 'burrito') {
        x.rotate(-0.35);
        RR(-80, -30, 150, 64, 32, '#EBCB8B');
        x.strokeStyle = '#D6AE62';
        x.lineWidth = 4;
        [-40, -5, 30].forEach((a) => {
            x.beginPath();
            x.moveTo(a, -28);
            x.quadraticCurveTo(a + 14, 0, a, 30);
            x.stroke();
        });
        E(72, 2, 26, 32, '#F3DCA8');
        E(72, 2, 19, 25, '#8A4A22');
        E(66, -8, 7, 6, '#5CB85C');
        E(78, 10, 6, 5, '#E5484D');
        E(70, 12, 6, 5, '#F6C85F');
    } else if (tipo === 'burger') {
        x.fillStyle = '#E7A647';
        x.beginPath();
        x.moveTo(-70, -6);
        x.quadraticCurveTo(-70, -62, 0, -62);
        x.quadraticCurveTo(70, -62, 70, -6);
        x.closePath();
        x.fill();
        [[-34, -36], [-8, -46], [18, -40], [40, -28], [-16, -24], [10, -22]].forEach(([a, b]) => E(a, b, 5, 3, '#FFF2D6', 0.3));
        x.fillStyle = '#5CB85C';
        x.beginPath();
        x.moveTo(-76, -6);
        for (let i = 0; i <= 8; i += 1) x.quadraticCurveTo(-70 + i * 19, 8, -62 + i * 19, -4);
        x.lineTo(76, 0);
        x.closePath();
        x.fill();
        x.fillStyle = '#F7C948';
        x.beginPath();
        x.moveTo(-72, 2);
        x.lineTo(72, 2);
        x.lineTo(40, 22);
        x.lineTo(10, 10);
        x.lineTo(-30, 24);
        x.closePath();
        x.fill();
        RR(-72, 6, 144, 24, 12, '#6B3419');
        RR(-68, 32, 136, 22, 11, '#E7A647');
    } else if (tipo === 'drink') {
        x.fillStyle = '#F3E9DC';
        x.beginPath();
        x.moveTo(-40, -50);
        x.lineTo(40, -50);
        x.lineTo(30, 60);
        x.lineTo(-30, 60);
        x.closePath();
        x.fill();
        x.fillStyle = '#E8D8C2';
        x.beginPath();
        x.moveTo(-36, -10);
        x.lineTo(36, -10);
        x.lineTo(30, 60);
        x.lineTo(-30, 60);
        x.closePath();
        x.fill();
        x.fillStyle = 'rgba(255,255,255,.6)';
        x.fillRect(-28, -44, 8, 96);
        RR(-46, -62, 92, 16, 8, '#7CC4F8');
        x.strokeStyle = '#2B7FD6';
        x.lineWidth = 8;
        x.beginPath();
        x.moveTo(8, -60);
        x.lineTo(22, -100);
        x.lineTo(44, -104);
        x.stroke();
        E(-10, 30, 4, 4, '#C9A77C');
        E(12, 16, 4, 4, '#C9A77C');
        E(4, 44, 4, 4, '#C9A77C');
    } else if (tipo === 'flan') {
        E(0, 46, 82, 18, '#FFFFFF');
        E(0, 44, 70, 13, '#EAF6FF');
        x.fillStyle = '#F1C56A';
        x.beginPath();
        x.moveTo(-58, 40);
        x.lineTo(-40, -24);
        x.lineTo(40, -24);
        x.lineTo(58, 40);
        x.closePath();
        x.fill();
        E(0, 40, 58, 10, '#F1C56A');
        E(0, -24, 40, 10, '#B8651B');
        x.fillStyle = '#B8651B';
        x.beginPath();
        x.moveTo(-40, -24);
        x.quadraticCurveTo(-46, 0, -38, 10);
        x.quadraticCurveTo(-32, -6, -26, -16);
        x.closePath();
        x.fill();
        x.beginPath();
        x.moveTo(30, -22);
        x.quadraticCurveTo(46, 4, 40, 14);
        x.quadraticCurveTo(34, 0, 22, -18);
        x.closePath();
        x.fill();
        E(-14, -27, 10, 3, 'rgba(255,255,255,.4)');
    }

    x.restore();
}

function texturaDeTarjeta(platillo) {
    const c = document.createElement('canvas');
    c.width = 420;
    c.height = 520;
    const x = c.getContext('2d');

    x.shadowColor = 'rgba(43,127,214,.25)';
    x.shadowBlur = 30;
    x.fillStyle = '#fff';
    rectanguloRedondo(x, 20, 20, 380, 480, 40);
    x.fill();
    x.shadowBlur = 0;
    const g = x.createLinearGradient(0, 40, 0, 300);
    g.addColorStop(0, '#EAF6FF');
    g.addColorStop(1, '#CFE9FF');
    x.fillStyle = g;
    rectanguloRedondo(x, 44, 44, 332, 250, 30);
    x.fill();
    dibujarComida(x, platillo[0], 210, 175, 125);
    x.textAlign = 'center';
    x.fillStyle = '#12355B';
    x.font = `800 30px ${FUENTE}`;
    x.fillText(platillo[1], 210, 350);
    x.fillStyle = '#5B6B82';
    x.font = `500 22px ${FUENTE}`;
    x.fillText(platillo[2], 210, 390);
    x.fillStyle = '#2B7FD6';
    rectanguloRedondo(x, 140, 420, 140, 52, 26);
    x.fill();
    x.fillStyle = '#fff';
    x.font = `800 28px ${FUENTE}`;
    x.fillText(`$${platillo[3]}`, 210, 456);

    const textura = new THREE.CanvasTexture(c);
    textura.colorSpace = THREE.SRGBColorSpace;
    textura.anisotropy = 8;

    return textura;
}

/**
 * @param {{lienzo: HTMLCanvasElement, nivel: string, alPerderContexto: function(): void}} opciones
 * @return {Promise<{dibujar: function(number, number, ?object): void, cambiarNivel: function(string): void, redimensionar: function(): void, destruir: function(): void}>}
 */
export async function crearEscena({ lienzo, nivel, alPerderContexto }) {
    const datos = lienzo.dataset;

    await Promise.all(['500', '600', '700', '800'].map((peso) => document.fonts.load(`${peso} 26px 'Plus Jakarta Sans'`))).catch(() => {});

    const [logoBlanco, simbolo] = await Promise.all([cargarImagen(datos.imagenLogo), cargarImagen(datos.imagenSimbolo)]);
    const qr = dibujarQr(simbolo);

    const render = new THREE.WebGLRenderer({ canvas: lienzo, antialias: nivel === 'alta', alpha: true });
    const anchoDeVista = () => lienzo.parentElement.clientWidth;
    const altoDeVista = () => lienzo.parentElement.clientHeight;

    render.setSize(anchoDeVista(), altoDeVista(), false);
    render.shadowMap.type = THREE.PCFShadowMap;
    render.outputColorSpace = THREE.SRGBColorSpace;
    render.toneMapping = THREE.ACESFilmicToneMapping;
    render.toneMappingExposure = 1.05;
    lienzo.addEventListener('webglcontextlost', alPerderContexto, { once: true });

    const escena = new THREE.Scene();
    const camara = new THREE.PerspectiveCamera(38, anchoDeVista() / altoDeVista(), 0.1, 100);

    escena.add(new THREE.HemisphereLight(0xffffff, 0xcfe8ff, 1.6));
    const sol = new THREE.DirectionalLight(0xffffff, 2.2);
    sol.position.set(3, 6, 5);
    sol.shadow.mapSize.set(1024, 1024);
    Object.assign(sol.shadow.camera, { left: -6, right: 6, top: 6, bottom: -6 });
    sol.shadow.radius = 6;
    escena.add(sol);
    const contraluz = new THREE.DirectionalLight(0x7cc4f8, 1.4);
    contraluz.position.set(-4, 2, -3);
    escena.add(contraluz);
    const piso = new THREE.Mesh(new THREE.PlaneGeometry(40, 40), new THREE.ShadowMaterial({ opacity: 0.12 }));
    piso.rotation.x = -Math.PI / 2;
    piso.position.y = -1.75;
    piso.receiveShadow = true;
    escena.add(piso);

    /* Celular */
    const celular = new THREE.Group();
    escena.add(celular);
    const ANCHO = 1.05;
    const ALTO = 2.15;
    const GROSOR = 0.12;
    const cuerpo = new THREE.Mesh(new RoundedBoxGeometry(ANCHO, ALTO, GROSOR, 8, 0.14), new THREE.MeshStandardMaterial({ color: 0xf4f9ff, roughness: 0.25, metalness: 0.15 }));
    cuerpo.castShadow = true;
    celular.add(cuerpo);
    celular.add(new THREE.Mesh(new RoundedBoxGeometry(ANCHO * 0.96, ALTO * 0.975, GROSOR * 1.02, 8, 0.12), new THREE.MeshStandardMaterial({ color: 0x12355b, roughness: 0.4 })));

    const lienzoPantalla = document.createElement('canvas');
    lienzoPantalla.width = 540;
    lienzoPantalla.height = 1120;
    const x = lienzoPantalla.getContext('2d');
    const texturaPantalla = new THREE.CanvasTexture(lienzoPantalla);
    texturaPantalla.colorSpace = THREE.SRGBColorSpace;
    texturaPantalla.anisotropy = 8;
    const pantalla = new THREE.Mesh(new THREE.PlaneGeometry(ANCHO * 0.9, ALTO * 0.935), new THREE.MeshBasicMaterial({ map: texturaPantalla, toneMapped: false }));
    pantalla.position.z = GROSOR / 2 + 0.002;
    celular.add(pantalla);

    function dibujarPantalla(enCamara, t, avanceEscaneo, destello) {
        const W = 540;
        const H = 1120;

        x.clearRect(0, 0, W, H);

        if (enCamara) {
            const g = x.createLinearGradient(0, 0, 0, H);
            g.addColorStop(0, '#2b3442');
            g.addColorStop(1, '#151b24');
            x.fillStyle = g;
            x.fillRect(0, 0, W, H);
            x.fillStyle = '#3a4352';
            x.fillRect(0, H * 0.72, W, H * 0.28);
            const s = 300;
            const qx = (W - s) / 2;
            const qy = H * 0.33;
            x.fillStyle = '#fff';
            rectanguloRedondo(x, qx - 26, qy - 26, s + 52, s + 120, 22);
            x.fill();
            x.drawImage(qr, qx, qy, s, s);
            x.fillStyle = '#12355B';
            x.font = `700 26px ${FUENTE}`;
            x.textAlign = 'center';
            x.fillText('Escanea el menú', W / 2, qy + s + 60);
            x.strokeStyle = '#7CC4F8';
            x.lineWidth = 8;
            x.lineCap = 'round';
            const b = 60;
            const m = 50;
            const X0 = qx - m;
            const Y0 = qy - m;
            const X1 = qx + s + m;
            const Y1 = qy + s + m + 40;
            [[X0, Y0, 1, 1], [X1, Y0, -1, 1], [X0, Y1, 1, -1], [X1, Y1, -1, -1]].forEach(([a, c, dx, dy]) => {
                x.beginPath();
                x.moveTo(a, c + b * dy);
                x.lineTo(a, c);
                x.lineTo(a + b * dx, c);
                x.stroke();
            });
            const ly = Y0 + (Y1 - Y0) * ((Math.sin(t * 4) + 1) / 2);
            const lg = x.createLinearGradient(0, ly - 40, 0, ly + 4);
            lg.addColorStop(0, 'rgba(124,196,248,0)');
            lg.addColorStop(1, 'rgba(124,196,248,.7)');
            x.fillStyle = lg;
            x.fillRect(X0, ly - 40, X1 - X0, 44);
            x.fillStyle = '#7CC4F8';
            x.fillRect(X0, ly, X1 - X0, 4);
            x.fillStyle = 'rgba(0,0,0,.45)';
            rectanguloRedondo(x, 60, 80, W - 120, 70, 35);
            x.fill();
            x.fillStyle = '#fff';
            x.font = `600 24px ${FUENTE}`;
            x.fillText(avanceEscaneo < 1 ? 'Apunta al código QR' : '¡Menú encontrado!', W / 2, 124);
            x.fillStyle = 'rgba(255,255,255,.2)';
            rectanguloRedondo(x, 120, H - 120, W - 240, 10, 5);
            x.fill();
            x.fillStyle = '#7CC4F8';
            rectanguloRedondo(x, 120, H - 120, Math.max(10, (W - 240) * limitar(avanceEscaneo)), 10, 5);
            x.fill();
        } else {
            x.fillStyle = '#F5FAFF';
            x.fillRect(0, 0, W, H);
            const hg = x.createLinearGradient(0, 0, W, 260);
            hg.addColorStop(0, '#7CC4F8');
            hg.addColorStop(1, '#2B7FD6');
            x.fillStyle = hg;
            rectanguloRedondo(x, 0, -40, W, 300, 40);
            x.fill();
            x.textAlign = 'left';
            if (logoBlanco) {
                x.drawImage(logoBlanco, 36, 54, 153, 32);
            }
            x.fillStyle = '#fff';
            x.font = `800 40px ${FUENTE}`;
            x.fillText('Taquería El Güero', 36, 150);
            x.font = `500 22px ${FUENTE}`;
            x.globalAlpha = 0.9;
            x.fillText('Hermosillo, Sonora', 36, 190);
            x.globalAlpha = 1;
            x.font = `600 22px ${FUENTE}`;
            ['Todo', 'Tacos', 'Bebidas', 'Postres'].forEach((categoria, i) => {
                const w = x.measureText(categoria).width + 44;
                const X = 36 + [0, 104, 222, 368][i];
                x.fillStyle = i === 0 ? '#12355B' : '#fff';
                rectanguloRedondo(x, X, 290, w, 52, 26);
                x.fill();
                x.fillStyle = i === 0 ? '#fff' : '#12355B';
                x.fillText(categoria, X + 22, 324);
            });
            const desplazamiento = (t * 28) % (PLATILLOS.length * 150);
            x.save();
            x.beginPath();
            x.rect(0, 370, W, H - 370);
            x.clip();
            for (let k = 0; k < PLATILLOS.length * 2; k += 1) {
                const platillo = PLATILLOS[k % PLATILLOS.length];
                const Y = 380 + k * 150 - desplazamiento;
                if (Y >= 250 && Y <= H) {
                    x.fillStyle = '#fff';
                    rectanguloRedondo(x, 28, Y, W - 56, 130, 26);
                    x.fill();
                    x.fillStyle = '#EAF6FF';
                    rectanguloRedondo(x, 44, Y + 15, 100, 100, 22);
                    x.fill();
                    dibujarComida(x, platillo[0], 94, Y + 62, 36);
                    x.textAlign = 'left';
                    x.fillStyle = '#12355B';
                    x.font = `700 24px ${FUENTE}`;
                    x.fillText(platillo[1], 166, Y + 55);
                    x.fillStyle = '#5B6B82';
                    x.font = `500 19px ${FUENTE}`;
                    x.fillText(platillo[2], 166, Y + 88);
                    x.fillStyle = '#2B7FD6';
                    x.font = `800 24px ${FUENTE}`;
                    x.textAlign = 'right';
                    x.fillText(`$${platillo[3]}`, W - 50, Y + 112);
                    x.textAlign = 'left';
                }
            }
            x.restore();
            x.fillStyle = '#12355B';
            rectanguloRedondo(x, 40, H - 130, W - 80, 84, 42);
            x.fill();
            x.fillStyle = '#fff';
            x.font = `700 26px ${FUENTE}`;
            x.textAlign = 'center';
            x.fillText('Ver mi pedido · 2', W / 2, H - 77);
            x.textAlign = 'left';
        }

        x.fillStyle = '#fff';
        x.font = `700 20px ${FUENTE}`;
        x.textAlign = 'left';
        x.fillText('9:41', 40, 44);
        [0, 1, 2, 3].forEach((i) => x.fillRect(W - 96 + i * 11, 32 - i * 5, 7, 8 + i * 5));
        x.fillStyle = '#12355B';
        rectanguloRedondo(x, W / 2 - 70, 18, 140, 38, 19);
        x.fill();

        if (destello > 0) {
            x.fillStyle = `rgba(255,255,255,${destello})`;
            x.fillRect(0, 0, W, H);
        }

        texturaPantalla.needsUpdate = true;
    }

    /* Soporte con QR */
    const soporte = new THREE.Group();
    escena.add(soporte);
    const acrilico = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.1, transparent: true, opacity: 0.6 });
    const base = new THREE.Mesh(new RoundedBoxGeometry(1.5, 0.12, 0.7, 4, 0.05), new THREE.MeshStandardMaterial({ color: 0x12355b, roughness: 0.4 }));
    base.castShadow = true;
    soporte.add(base);

    const lienzoTarjeta = document.createElement('canvas');
    lienzoTarjeta.width = 600;
    lienzoTarjeta.height = 840;
    const xt = lienzoTarjeta.getContext('2d');
    xt.fillStyle = '#fff';
    xt.fillRect(0, 0, 600, 840);
    const gt = xt.createLinearGradient(0, 0, 600, 0);
    gt.addColorStop(0, '#7CC4F8');
    gt.addColorStop(1, '#2B7FD6');
    xt.fillStyle = gt;
    xt.fillRect(0, 0, 600, 150);
    if (logoBlanco) {
        xt.drawImage(logoBlanco, 150, 45, 300, 63);
    }
    xt.textAlign = 'center';
    xt.drawImage(qr, 90, 190, 420, 420);
    xt.fillStyle = '#12355B';
    xt.font = `800 40px ${FUENTE}`;
    xt.fillText('Escanea el menú', 300, 690);
    xt.fillStyle = '#5B6B82';
    xt.font = `500 28px ${FUENTE}`;
    xt.fillText(datos.textoSoporte ?? '', 300, 750);

    const texturaTarjeta = new THREE.CanvasTexture(lienzoTarjeta);
    texturaTarjeta.colorSpace = THREE.SRGBColorSpace;
    const letrero = new THREE.Mesh(new RoundedBoxGeometry(1.2, 1.68, 0.05, 4, 0.04), [acrilico, acrilico, acrilico, acrilico, new THREE.MeshStandardMaterial({ map: texturaTarjeta, roughness: 0.35 }), acrilico]);
    letrero.position.set(0, 0.92, 0);
    letrero.rotation.x = -0.12;
    letrero.castShadow = true;
    soporte.add(letrero);
    soporte.position.copy(SOPORTE_ESCRITORIO);
    soporte.rotation.y = 0.35;
    soporte.scale.setScalar(0);

    /* Tarjetas de platillos */
    const tarjetas = PLATILLOS.map((platillo, i) => {
        const malla = new THREE.Mesh(new THREE.PlaneGeometry(1.05, 1.3), new THREE.MeshBasicMaterial({ map: texturaDeTarjeta(platillo), transparent: true, toneMapped: false, side: THREE.DoubleSide }));
        malla.userData = { angulo: (i / PLATILLOS.length) * Math.PI * 2, altura: i % 2 ? 0.35 : -0.35 };
        malla.scale.setScalar(0);
        escena.add(malla);

        return malla;
    });

    /* Chispas */
    const geometriaChispas = new THREE.BufferGeometry();
    const posiciones = new Float32Array(160 * 3);
    for (let i = 0; i < 160; i += 1) {
        // Giran alrededor del eje vertical: con un radio limitado, ninguna pasa pegada a la cámara y se ve como un cuadro enorme.
        const anguloChispa = Math.random() * Math.PI * 2;
        const radioChispa = Math.sqrt(Math.random()) * 5.5;

        posiciones.set([Math.cos(anguloChispa) * radioChispa, (Math.random() - 0.5) * 8, Math.sin(anguloChispa) * radioChispa], i * 3);
    }
    geometriaChispas.setAttribute('position', new THREE.BufferAttribute(posiciones, 3));
    const chispas = new THREE.Points(geometriaChispas, new THREE.PointsMaterial({ color: 0x7cc4f8, size: 0.05, transparent: true, opacity: 0.8 }));
    escena.add(chispas);

    /* Anillos: solo si el lienzo trae el atributo data-anillos */
    const anillos = 'anillos' in datos
        ? [0, 1, 2].map(() => {
            const anillo = new THREE.Mesh(new THREE.RingGeometry(0.5, 0.53, 64), new THREE.MeshBasicMaterial({ color: 0x2b7fd6, transparent: true, opacity: 0, side: THREE.DoubleSide }));
            escena.add(anillo);

            return anillo;
        })
        : [];

    let calidad = CALIDADES[nivel];
    let ultimaPantalla = -Infinity;
    let ultimoReloj = -1;
    let ultimoEstado = '';

    function aplicarCalidad(nuevoNivel) {
        calidad = CALIDADES[nuevoNivel];
        render.setPixelRatio(Math.min(devicePixelRatio, calidad.densidadMaxima));
        render.setSize(anchoDeVista(), altoDeVista(), false);
        render.shadowMap.enabled = calidad.sombras;
        sol.castShadow = calidad.sombras;
        piso.visible = calidad.sombras;
        chispas.visible = calidad.chispas;
        escena.traverse((objeto) => {
            if (objeto.material) {
                (Array.isArray(objeto.material) ? objeto.material : [objeto.material]).forEach((material) => {
                    material.needsUpdate = true;
                });
            }
        });
    }

    aplicarCalidad(nivel);

    const cajaVisible = new THREE.Box3();
    const esquina = V(0, 0, 0);

    function sumarACaja(x, y, z, semiX, semiY, semiZ) {
        cajaVisible.expandByPoint(esquina.set(x - semiX, y - semiY, z - semiZ));
        cajaVisible.expandByPoint(esquina.set(x + semiX, y + semiY, z + semiZ));
    }

    /**
     * Centra lo que está visible en el área libre. La caja no depende del reloj, para que el encuadre no tiemble con el movimiento.
     */
    function encuadrar(area, escalaSoporte, aparicion) {
        cajaVisible.makeEmpty();
        sumarACaja(celular.position.x, 0, celular.position.z, 0.62, 1.16, 0.3);

        if (escalaSoporte > 0) {
            sumarACaja(soporte.position.x, soporte.position.y + 0.85 * escalaSoporte, soporte.position.z, 0.75 * escalaSoporte, 0.95 * escalaSoporte, 0.5 * escalaSoporte);
        }

        if (aparicion > 0) {
            sumarACaja(celular.position.x, 0, 0, 1.4 * aparicion, 1.15 * aparicion, 0.72 * aparicion);
        }

        const ancho = anchoDeVista();
        const alto = altoDeVista();
        let izq = Infinity;
        let der = -Infinity;
        let sup = Infinity;
        let inf = -Infinity;

        camara.clearViewOffset();
        camara.updateMatrixWorld();

        for (const x of [cajaVisible.min.x, cajaVisible.max.x]) {
            for (const y of [cajaVisible.min.y, cajaVisible.max.y]) {
                for (const z of [cajaVisible.min.z, cajaVisible.max.z]) {
                    esquina.set(x, y, z).project(camara);
                    const X = ((esquina.x + 1) / 2) * ancho;
                    const Y = ((1 - esquina.y) / 2) * alto;

                    izq = Math.min(izq, X);
                    der = Math.max(der, X);
                    sup = Math.min(sup, Y);
                    inf = Math.max(inf, Y);
                }
            }
        }

        const ajuste = encuadre({ izq, der, sup, inf }, area);

        camara.setViewOffset(ancho * ajuste.escala, alto * ajuste.escala, -ajuste.x, -ajuste.y, ancho, alto);
    }

    return {
        dibujar(p, t, area = null) {
            const movil = area !== null;
            const pose = interpolar(p, movil);

            celular.position.copy(pose.celular);
            celular.position.y += Math.sin(t * 1.2) * 0.06;
            celular.rotation.set(pose.giroX + Math.sin(t * 0.8) * 0.03, pose.giroY + Math.sin(t * 0.6) * 0.05, Math.sin(t * 0.7) * 0.02);
            camara.position.copy(pose.camara);
            camara.lookAt(pose.mira);

            const entradaSoporte = suavizar(banda(p, 0.19, 0.45, 0.05));
            const escalaSoporte = entradaSoporte * (movil ? ESCALA_SOPORTE_MOVIL : 1.25);

            soporte.scale.setScalar(escalaSoporte);

            if (movil) {
                soporte.position.lerpVectors(pose.celular, SOPORTE_MOVIL, entradaSoporte);
            } else {
                soporte.position.copy(SOPORTE_ESCRITORIO);
            }

            piso.visible = calidad.sombras && !movil;

            const avanceEscaneo = limitar((p - 0.25) / 0.12);
            const enCamara = p > 0.21 && avanceEscaneo < 1;
            const destello = limitar(1 - Math.abs(p - 0.37) / 0.02) * 0.9;
            const estado = `${enCamara}|${avanceEscaneo.toFixed(3)}|${destello.toFixed(2)}`;
            const celularEnVista = celular.position.y > -3;
            const momento = performance.now();

            if (celularEnVista && (t !== ultimoReloj || estado !== ultimoEstado) && momento - ultimaPantalla >= calidad.msPantalla) {
                dibujarPantalla(enCamara, t, avanceEscaneo, destello);
                ultimaPantalla = momento;
                ultimoReloj = t;
                ultimoEstado = estado;
            }

            const aparicion = suavizar(limitar((p - 0.44) / 0.06)) * (1 - suavizar(limitar((p - 0.6) / 0.05)));
            tarjetas.forEach((malla, i) => {
                malla.visible = aparicion > 0;

                if (malla.visible) {
                    const angulo = malla.userData.angulo + t * 0.25 + p * 2.2;
                    const radio = (1.25 + aparicion * 0.35) * (movil ? aparicion : 1);
                    malla.position.set(
                        celular.position.x + Math.cos(angulo) * radio * (movil ? 0.62 : 0.9),
                        movil ? (malla.userData.altura * 1.2 + Math.sin(t * 1.3 + i) * 0.12) * aparicion : malla.userData.altura * 1.4 + Math.sin(t * 1.3 + i) * 0.12,
                        Math.sin(angulo) * radio * 0.9,
                    );
                    malla.lookAt(camara.position);
                    malla.scale.setScalar(aparicion * (0.9 + 0.1 * Math.sin(t * 2 + i)) * (movil ? 0.72 : 1));
                }
            });

            const intensidadAnillos = banda(p, 0.88, 1.2, 0.04);
            anillos.forEach((anillo, i) => {
                const fase = (t * 0.7 + i / 3) % 1;
                anillo.position.copy(celular.position).add(V(0, 0, 0.12));
                anillo.rotation.copy(celular.rotation);
                anillo.scale.setScalar(1 + fase * 1.6);
                anillo.material.opacity = intensidadAnillos * (1 - fase) * 0.6;
            });

            chispas.rotation.y = t * 0.03;
            chispas.position.y = Math.sin(t * 0.4) * 0.1;

            if (movil) {
                encuadrar(area, escalaSoporte, aparicion);
            } else if (camara.view?.enabled) {
                camara.clearViewOffset();
            }

            render.render(escena, camara);
        },

        cambiarNivel: aplicarCalidad,

        redimensionar() {
            camara.aspect = anchoDeVista() / altoDeVista();
            camara.updateProjectionMatrix();
            render.setSize(anchoDeVista(), altoDeVista(), false);
        },

        destruir() {
            lienzo.removeEventListener('webglcontextlost', alPerderContexto);
            escena.traverse((objeto) => {
                objeto.geometry?.dispose();
                (Array.isArray(objeto.material) ? objeto.material : [objeto.material]).forEach((material) => {
                    material?.map?.dispose();
                    material?.dispose();
                });
            });
            render.dispose();
            render.forceContextLoss();
        },
    };
}
