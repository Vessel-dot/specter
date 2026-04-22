-- ════════════════════════════════════════════════════════════
-- Sistema Specter — Base de Datos
-- Motor: PostgreSQL 17 | API: PHP PDO (pgsql)
-- Ejecutar en pgAdmin 4: File > Open > specter_db.sql
-- ════════════════════════════════════════════════════════════

-- 1. Crear la base de datos (ejecutar conectado a postgres)
-- CREATE DATABASE specter_db WITH ENCODING='UTF8';
-- \c specter_db;

-- ── jugador ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jugador (
  id_jugador     SERIAL       PRIMARY KEY,
  nombre         VARCHAR(100) NOT NULL,
  correo         VARCHAR(150) NOT NULL UNIQUE,
  contrasena     VARCHAR(255) NOT NULL,
  rol            VARCHAR(20)  NOT NULL DEFAULT 'jugador'
                              CHECK (rol IN ('jugador', 'moderador')),
  avatar_url     VARCHAR(255),
  tema_visual    VARCHAR(20)  NOT NULL DEFAULT 'dark',
  fecha_registro TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ── videojuego ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS videojuego (
  id_videojuego SERIAL        PRIMARY KEY,
  titulo        VARCHAR(200)  NOT NULL,
  genero        VARCHAR(50),
  anio          INT,
  calificacion  NUMERIC(3,1),
  precio        NUMERIC(10,2) NOT NULL DEFAULT 9.99,
  descripcion   TEXT,
  steam_id      INT
);

-- ── biblioteca (M:N jugador ↔ videojuego) ───────────────────
CREATE TABLE IF NOT EXISTS biblioteca (
  id_biblioteca  SERIAL      PRIMARY KEY,
  id_jugador     INT         NOT NULL,
  id_videojuego  INT         NOT NULL,
  estado         VARCHAR(20) NOT NULL DEFAULT 'Pendiente'
                             CHECK (estado IN ('Jugando','Completado','Pendiente')),
  progreso       INT         NOT NULL DEFAULT 0
                             CHECK (progreso BETWEEN 0 AND 100),
  fecha_agregado TIMESTAMP   NOT NULL DEFAULT NOW(),
  FOREIGN KEY (id_jugador)    REFERENCES jugador(id_jugador)       ON DELETE CASCADE,
  FOREIGN KEY (id_videojuego) REFERENCES videojuego(id_videojuego) ON DELETE CASCADE,
  UNIQUE (id_jugador, id_videojuego)
);

-- ── resena ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS resena (
  id_resena         SERIAL      PRIMARY KEY,
  id_jugador        INT         NOT NULL,
  id_videojuego     INT         NOT NULL,
  calificacion      INT         NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
  texto             TEXT        NOT NULL,
  fecha             TIMESTAMP   NOT NULL DEFAULT NOW(),
  estado_moderacion VARCHAR(20) NOT NULL DEFAULT 'LIMPIO'
                                CHECK (estado_moderacion IN ('LIMPIO','EN_REVISION','BLOQUEADO')),
  FOREIGN KEY (id_jugador)    REFERENCES jugador(id_jugador)       ON DELETE CASCADE,
  FOREIGN KEY (id_videojuego) REFERENCES videojuego(id_videojuego) ON DELETE CASCADE
);

-- ── reputacion_usuario ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS reputacion_usuario (
  id_reputacion      SERIAL       PRIMARY KEY,
  id_jugador         INT          NOT NULL UNIQUE,
  indice_confianza   NUMERIC(3,1) NOT NULL DEFAULT 5.0,
  resenas_aprobadas  INT          NOT NULL DEFAULT 0,
  resenas_bloqueadas INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE
);

-- ── carrito ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carrito (
  id_carrito     SERIAL    PRIMARY KEY,
  id_jugador     INT       NOT NULL UNIQUE,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW(),
  FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE
);

-- ── carrito_item (M:N carrito ↔ videojuego) ─────────────────
CREATE TABLE IF NOT EXISTS carrito_item (
  id_item       SERIAL        PRIMARY KEY,
  id_carrito    INT           NOT NULL,
  id_videojuego INT           NOT NULL,
  precio_item   NUMERIC(10,2) NOT NULL,
  es_bundle     BOOLEAN       NOT NULL DEFAULT FALSE,
  es_regalo     BOOLEAN       NOT NULL DEFAULT FALSE,
  FOREIGN KEY (id_carrito)    REFERENCES carrito(id_carrito)         ON DELETE CASCADE,
  FOREIGN KEY (id_videojuego) REFERENCES videojuego(id_videojuego)   ON DELETE CASCADE
);

-- ── compra (TicketTemporal CU-04) ────────────────────────────
CREATE TABLE IF NOT EXISTS compra (
  id_compra    SERIAL        PRIMARY KEY,
  id_jugador   INT           NOT NULL,
  total        NUMERIC(10,2) NOT NULL,
  descuento    NUMERIC(10,2) NOT NULL DEFAULT 0,
  fecha_compra TIMESTAMP     NOT NULL DEFAULT NOW(),
  FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE
);

-- ── compra_detalle (M:N compra ↔ videojuego) ────────────────
CREATE TABLE IF NOT EXISTS compra_detalle (
  id_detalle      SERIAL        PRIMARY KEY,
  id_compra       INT           NOT NULL,
  id_videojuego   INT           NOT NULL,
  precio_unitario NUMERIC(10,2) NOT NULL,
  es_regalo       BOOLEAN       NOT NULL DEFAULT FALSE,
  FOREIGN KEY (id_compra)     REFERENCES compra(id_compra)            ON DELETE CASCADE,
  FOREIGN KEY (id_videojuego) REFERENCES videojuego(id_videojuego)    ON DELETE CASCADE
);

-- ── actividad ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS actividad (
  id_actividad SERIAL       PRIMARY KEY,
  id_jugador   INT          NOT NULL,
  texto        VARCHAR(255) NOT NULL,
  tipo         VARCHAR(20)  CHECK (tipo IN ('resena','logro','compra','progreso','sistema')),
  fecha        TIMESTAMP    NOT NULL DEFAULT NOW(),
  FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE
);

-- ════════════════════════════════════════════════════════════
-- DATOS DE PRUEBA
-- ════════════════════════════════════════════════════════════

-- Moderador de prueba (password: specter123)
INSERT INTO jugador (nombre, correo, contrasena, rol)
VALUES ('Admin Specter', 'admin@specter.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moderador')
ON CONFLICT (correo) DO NOTHING;

-- Jugador de prueba (password: specter123)
INSERT INTO jugador (nombre, correo, contrasena, rol)
VALUES ('Jorge García', 'jorge@specter.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jugador')
ON CONFLICT (correo) DO NOTHING;

-- Carrito y reputación para el jugador de prueba
INSERT INTO carrito (id_jugador)
SELECT id_jugador FROM jugador WHERE correo = 'jorge@specter.com'
ON CONFLICT (id_jugador) DO NOTHING;

INSERT INTO reputacion_usuario (id_jugador)
SELECT id_jugador FROM jugador WHERE correo = 'jorge@specter.com'
ON CONFLICT (id_jugador) DO NOTHING;

-- Videojuegos de muestra
INSERT INTO videojuego (titulo, genero, anio, calificacion, precio, descripcion, steam_id) VALUES
('Hollow Knight',        'Acción',     2017, 9.4,  9.99,  'Explora un vasto reino subterráneo de bichos y héroes.',                367520),
('Celeste',              'Plataforma', 2018, 9.7,  9.99,  'Ayuda a Madeline a sobrevivir su viaje hacia la Montaña Celeste.',      504230),
('Hades II',             'RPG',        2024, 9.8,  24.99, 'La secuela del aclamado roguelite. Domina los poderes del Olimpo.',      1145360),
('Disco Elysium',        'RPG',        2019, 9.6,  14.99, 'Una odisea de detective en mundo abierto de escala épica.',             632470),
('Stardew Valley',       'Simulación', 2016, 9.3,  9.99,  'Hereda la granja de tu abuelo y construye una nueva vida.',             413150),
('Elden Ring',           'RPG',        2022, 9.8,  44.99, 'Mundo oscuro de fantasía creado por Miyazaki y George R.R. Martin.',    1245620),
('Cyberpunk 2077',       'RPG',        2020, 9.1,  39.99, 'RPG de mundo abierto en Night City. Encarna a V, un mercenario.',       1091500),
('NieR: Automata',       'Acción',     2017, 9.6,  19.99, 'Androides luchan contra máquinas en un futuro postapocalíptico.',       524220),
('The Witcher 3',        'RPG',        2015, 9.9,  19.99, 'Conviértete en Geralt de Rivia, cazador de monstruos profesional.',     292030),
('Portal 2',             'Plataforma', 2011, 9.8,  9.99,  'Resuelve acertijos físicos ingeniosos con la pistola de portales.',     620),
('Dead Cells',           'Acción',     2018, 9.0,  19.99, 'Roguelite de castlevania-like en un castillo en constante cambio.',     588650),
('Slay the Spire',       'Estrategia', 2019, 9.2,  14.99, 'Fusión de construcción de mazos con el rogue-like definitivo.',         646570)
ON CONFLICT DO NOTHING;
