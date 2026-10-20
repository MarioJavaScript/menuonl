/**
 * TypeScript interfaces matching the MENUONL Public Menu REST API schema.
 * Source: spec_miner_survey_0/report.md & PROJECT.md
 */

export interface PublicMenuApiResponse {
  data: RestaurantData;
}

export interface RestaurantData {
  id: number;
  nombre: string;
  slug: string;
  eslogan: string | null;
  descripcion: string | null;
  logo_url: string | null;
  portada_url: string | null;
  monograma: string;             // Uppercase 1-letter monogram
  tema: string;                  // 'calido' | 'editorial' | 'rustico' | 'clasico'
  color_acento: string | null;   // #RRGGBB
  color_acento_texto: string | null; // #ffffff or #17171e
  moneda: string;                // 'MXN', 'USD', 'EUR', etc.
  idioma: string;                // Resolved active language (e.g. 'es')
  idioma_default: string;        // Default fallback language (e.g. 'es')
  idiomas_disponibles: string[]; // ['es', 'en']
  zona_horaria: string;          // e.g. 'America/Mexico_City'
  abierto: boolean;              // Evaluated dynamically in real time
  pedidos_activos: boolean;      // Admin configuration flag
  puede_pedir: boolean;          // pedidos_activos && abierto && has(whatsapp)
  whatsapp_pedidos: string | null; // Raw configured phone
  whatsapp_url: string | null;   // https://wa.me/{clean_digits}
  mostrar_agotados: boolean;
  mesa: string | null;           // Sanitized table parameter if supplied
  horarios: HorarioItem[];
  contactos: ContactoItem[];
  categorias: CategoriaItem[];
  destacados: DishItem[];        // Featured products for quick access
}

export interface HorarioItem {
  id: number;
  dia_semana: number;            // 0=Domingo, 1=Lunes, ..., 6=Sábado
  dia_nombre: string;            // 'Domingo', 'Lunes', etc.
  abre: string | null;           // '10:00:00'
  cierra: string | null;         // '22:00:00'
  abre_formateado: string | null;// '10:00 a. m.'
  cierra_formateado: string | null; // '10:00 p. m.'
  cerrado: boolean;
}

export interface ContactoItem {
  id: number;
  tipo: string;                  // 'telefono' | 'whatsapp' | 'email' | 'direccion' | 'web' | etc.
  etiqueta: string | null;
  valor: string;
  url: string;                   // tel:..., https://wa.me/..., mailto:..., https://...
}

export interface CategoriaItem {
  id: number;
  orden: number;
  nombre: string;                // Localized
  descripcion: string | null;    // Localized
  platillos: DishItem[];
}

export interface DishItem {
  id: number;
  categoria_id: number;
  nombre: string;                // Localized
  descripcion: string | null;    // Localized
  precio: number;                // Numeric float e.g. 45.00
  precio_formateado: string;     // e.g. "$45.00"
  precio_oferta: number | null;
  precio_oferta_formateado: string | null;
  precio_vigente: number;        // Active payable price
  precio_vigente_formateado: string;
  tiene_oferta: boolean;
  imagen_url: string | null;     // Storage URL or null
  disponible: boolean;
  destacado: boolean;
  orden: number;
  etiquetas: DietaryTagItem[];
  variantes: DishVariantItem[];
}

export interface DietaryTagItem {
  id: number;
  codigo: string;                // 'vegano', 'picante', 'sin_gluten', etc.
  nombre: string;                // 'Vegano', 'Picante', 'Sin gluten'
  icono: string | null;          // 'leaf', 'flame', 'wheat-off', etc.
}

export interface DishVariantItem {
  id: number;
  producto_id: number;
  nombre: string;                // Localized (e.g. 'Mediana', 'Familiar')
  precio: number;                // Numeric float
  precio_formateado: string;     // e.g. "$75.00"
  disponible: boolean;
  orden: number;
}
