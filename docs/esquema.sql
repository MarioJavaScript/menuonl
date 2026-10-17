-- =============================================================
-- MENUONL — Esquema de base de datos
-- Motor: MariaDB 10.6+ · InnoDB · utf8mb4_unicode_ci
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS menuonl
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE menuonl;

-- -------------------------------------------------------------
-- Usuarios del panel
-- -------------------------------------------------------------
CREATE TABLE usuarios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(120)    NOT NULL,
  email           VARCHAR(190)    NOT NULL,
  password_hash   VARCHAR(255)    NOT NULL,
  es_superadmin   TINYINT(1)      NOT NULL DEFAULT 0,
  activo          TINYINT(1)      NOT NULL DEFAULT 1,
  ultimo_acceso   DATETIME        NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Restaurantes
-- -------------------------------------------------------------
CREATE TABLE restaurantes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(150)    NOT NULL,
  slug              VARCHAR(80)     NOT NULL,
  descripcion       VARCHAR(500)    NULL,
  logo              VARCHAR(255)    NULL,
  portada           VARCHAR(255)    NULL,
  moneda            CHAR(3)         NOT NULL DEFAULT 'MXN',
  idioma_default    VARCHAR(5)      NOT NULL DEFAULT 'es',
  zona_horaria      VARCHAR(50)     NOT NULL DEFAULT 'America/Mexico_City',
  whatsapp_pedidos  VARCHAR(20)     NULL,
  pedidos_activos   TINYINT(1)      NOT NULL DEFAULT 0,
  mostrar_agotados  TINYINT(1)      NOT NULL DEFAULT 1,
  estado            ENUM('activo','suspendido') NOT NULL DEFAULT 'activo',
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at        DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_restaurantes_slug (slug),
  KEY ix_restaurantes_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Usuarios por restaurante y su rol
-- -------------------------------------------------------------
CREATE TABLE restaurante_usuario (
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  usuario_id      BIGINT UNSIGNED NOT NULL,
  rol             ENUM('propietario','editor') NOT NULL DEFAULT 'editor',
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (restaurante_id, usuario_id),
  KEY ix_ru_usuario (usuario_id),
  CONSTRAINT fk_ru_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ru_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios (id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Invitaciones
-- -------------------------------------------------------------
CREATE TABLE invitaciones (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  email           VARCHAR(190)    NOT NULL,
  rol             ENUM('propietario','editor') NOT NULL DEFAULT 'editor',
  token           CHAR(64)        NOT NULL,
  invitado_por    BIGINT UNSIGNED NULL,
  expira_en       DATETIME        NOT NULL,
  aceptada_en     DATETIME        NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitaciones_token (token),
  KEY ix_invitaciones_email (email),
  CONSTRAINT fk_inv_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_invitado_por FOREIGN KEY (invitado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Idiomas activos por restaurante
-- -------------------------------------------------------------
CREATE TABLE restaurante_idiomas (
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  idioma          VARCHAR(5)      NOT NULL,
  orden           SMALLINT        NOT NULL DEFAULT 0,
  PRIMARY KEY (restaurante_id, idioma),
  CONSTRAINT fk_ri_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Contactos
-- -------------------------------------------------------------
CREATE TABLE contactos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  tipo            ENUM('telefono','whatsapp','email','direccion','instagram','facebook','tiktok','web','otro') NOT NULL,
  etiqueta        VARCHAR(80)     NULL,
  valor           VARCHAR(255)    NOT NULL,
  orden           INT             NOT NULL DEFAULT 0,
  visible         TINYINT(1)      NOT NULL DEFAULT 1,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_contactos_rest_orden (restaurante_id, orden),
  CONSTRAINT fk_contactos_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Horarios (varias filas por día = varios turnos)
-- -------------------------------------------------------------
CREATE TABLE horarios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  dia_semana      TINYINT UNSIGNED NOT NULL COMMENT '0=domingo ... 6=sábado',
  abre            TIME            NULL,
  cierra          TIME            NULL,
  cerrado         TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_horarios_rest_dia (restaurante_id, dia_semana),
  CONSTRAINT fk_horarios_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE,
  CONSTRAINT ck_horarios_dia CHECK (dia_semana BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Categorías
-- -------------------------------------------------------------
CREATE TABLE categorias (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  orden           INT             NOT NULL DEFAULT 0,
  visible         TINYINT(1)      NOT NULL DEFAULT 1,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME        NULL,
  PRIMARY KEY (id),
  KEY ix_categorias_rest_orden (restaurante_id, orden),
  CONSTRAINT fk_categorias_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categoria_traducciones (
  categoria_id    BIGINT UNSIGNED NOT NULL,
  idioma          VARCHAR(5)      NOT NULL,
  nombre          VARCHAR(120)    NOT NULL,
  descripcion     TEXT            NULL,
  PRIMARY KEY (categoria_id, idioma),
  CONSTRAINT fk_ct_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Productos (platillos)
-- -------------------------------------------------------------
CREATE TABLE productos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  categoria_id    BIGINT UNSIGNED NOT NULL,
  precio          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  precio_oferta   DECIMAL(10,2)   NULL,
  imagen          VARCHAR(255)    NULL,
  disponible      TINYINT(1)      NOT NULL DEFAULT 1,
  visible         TINYINT(1)      NOT NULL DEFAULT 1,
  destacado       TINYINT(1)      NOT NULL DEFAULT 0,
  orden           INT             NOT NULL DEFAULT 0,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME        NULL,
  PRIMARY KEY (id),
  KEY ix_productos_rest_orden (restaurante_id, orden),
  KEY ix_productos_categoria (categoria_id, orden),
  CONSTRAINT fk_productos_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE,
  CONSTRAINT fk_productos_categoria   FOREIGN KEY (categoria_id)   REFERENCES categorias (id)   ON DELETE CASCADE,
  CONSTRAINT ck_productos_precio CHECK (precio >= 0),
  CONSTRAINT ck_productos_oferta CHECK (precio_oferta IS NULL OR precio_oferta >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE producto_traducciones (
  producto_id     BIGINT UNSIGNED NOT NULL,
  idioma          VARCHAR(5)      NOT NULL,
  nombre          VARCHAR(150)    NOT NULL,
  descripcion     TEXT            NULL,
  PRIMARY KEY (producto_id, idioma),
  CONSTRAINT fk_pt_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Variantes (tamaños, presentaciones)
-- -------------------------------------------------------------
CREATE TABLE producto_variantes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id     BIGINT UNSIGNED NOT NULL,
  precio          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  disponible      TINYINT(1)      NOT NULL DEFAULT 1,
  orden           INT             NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_variantes_producto (producto_id, orden),
  CONSTRAINT fk_variantes_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
  CONSTRAINT ck_variantes_precio CHECK (precio >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE variante_traducciones (
  variante_id     BIGINT UNSIGNED NOT NULL,
  idioma          VARCHAR(5)      NOT NULL,
  nombre          VARCHAR(80)     NOT NULL,
  PRIMARY KEY (variante_id, idioma),
  CONSTRAINT fk_vt_variante FOREIGN KEY (variante_id) REFERENCES producto_variantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Etiquetas (catálogo global) y asignación a productos
-- -------------------------------------------------------------
CREATE TABLE etiquetas (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo          VARCHAR(40)     NOT NULL,
  icono           VARCHAR(40)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_etiquetas_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE producto_etiqueta (
  producto_id     BIGINT UNSIGNED NOT NULL,
  etiqueta_id     BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (producto_id, etiqueta_id),
  KEY ix_pe_etiqueta (etiqueta_id),
  CONSTRAINT fk_pe_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Códigos QR
-- -------------------------------------------------------------
CREATE TABLE codigos_qr (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  nombre          VARCHAR(80)     NOT NULL,
  mesa            VARCHAR(20)     NULL,
  tipo            VARCHAR(3)      NOT NULL DEFAULT 'qr',
  codigo          VARCHAR(16)     NOT NULL,
  activo          TINYINT(1)      NOT NULL DEFAULT 1,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_qr_codigo (codigo),
  CONSTRAINT ck_codigos_qr_tipo CHECK (tipo IN ('qr', 'nfc')),
  KEY ix_qr_restaurante (restaurante_id),
  CONSTRAINT fk_qr_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Historial de cambios (auditoría)
-- -------------------------------------------------------------
CREATE TABLE historial_cambios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id  BIGINT UNSIGNED NOT NULL,
  usuario_id      BIGINT UNSIGNED NULL,
  entidad         VARCHAR(50)     NOT NULL,
  entidad_id      BIGINT UNSIGNED NOT NULL,
  accion          ENUM('crear','actualizar','eliminar') NOT NULL,
  cambios         JSON            NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_hist_rest_fecha (restaurante_id, created_at),
  KEY ix_hist_entidad (entidad, entidad_id),
  CONSTRAINT fk_hist_restaurante FOREIGN KEY (restaurante_id) REFERENCES restaurantes (id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- Datos iniciales: etiquetas
-- -------------------------------------------------------------
INSERT INTO etiquetas (codigo, icono) VALUES
  ('vegano',       'leaf'),
  ('vegetariano',  'carrot'),
  ('picante',      'flame'),
  ('sin_gluten',   'wheat-off'),
  ('sin_lactosa',  'milk-off'),
  ('nuevo',        'sparkles'),
  ('recomendado',  'star');
