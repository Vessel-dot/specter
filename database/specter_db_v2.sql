-- ════════════════════════════════════════════════════════════════
-- Sistema Specter — v2 Schema Migration
-- Ejecutar DESPUÉS de specter_db.sql
-- Motor: PostgreSQL 17
-- ════════════════════════════════════════════════════════════════

-- ── 1. Agregar saldo al jugador (si no existe) ────────────────
ALTER TABLE jugador
  ADD COLUMN IF NOT EXISTS saldo_specter NUMERIC(10,2) NOT NULL DEFAULT 0.00;

-- ── 2. tarjeta_pago ──────────────────────────────────────────
-- Almacena tarjetas guardadas. NUNCA el número completo.
CREATE TABLE IF NOT EXISTS tarjeta_pago (
  id_tarjeta    SERIAL       PRIMARY KEY,
  id_jugador    INT          NOT NULL,
  ultimos4      CHAR(4)      NOT NULL,          -- últimos 4 dígitos
  marca         VARCHAR(20)  NOT NULL,           -- VISA, MASTERCARD, AMEX
  nombre_titular VARCHAR(100) NOT NULL,
  mes_exp       CHAR(2)      NOT NULL,           -- MM
  anio_exp      CHAR(4)      NOT NULL,           -- YYYY
  es_principal  BOOLEAN      NOT NULL DEFAULT FALSE,
  fecha_guardada TIMESTAMP   NOT NULL DEFAULT NOW(),
  FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE
);

-- ── 3. movimiento_saldo ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS movimiento_saldo (
  id_movimiento SERIAL        PRIMARY KEY,
  id_jugador    INT           NOT NULL,
  tipo          VARCHAR(20)   NOT NULL CHECK (tipo IN ('recarga','gasto','canje','devolucion')),
  monto         NUMERIC(10,2) NOT NULL,          -- positivo siempre; el signo lo da el tipo
  descripcion   VARCHAR(255)  NOT NULL,
  id_compra     INT,                             -- FK opcional a compra
  id_canje      INT,                             -- FK opcional a codigo_canje
  fecha         TIMESTAMP     NOT NULL DEFAULT NOW(),
  FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE
);

-- ── 4. tarjeta_specter_producto ───────────────────────────────
-- Catálogo de denominaciones (tipo Steam Gift Card)
CREATE TABLE IF NOT EXISTS tarjeta_specter_producto (
  id_producto   SERIAL        PRIMARY KEY,
  denominacion  NUMERIC(10,2) NOT NULL UNIQUE,   -- 100, 250, 500
  nombre        VARCHAR(100)  NOT NULL,
  descripcion   TEXT,
  imagen_url    VARCHAR(255),
  activo        BOOLEAN       NOT NULL DEFAULT TRUE
);

-- ── 5. codigo_canje ───────────────────────────────────────────
-- Códigos generados al comprar una tarjeta Specter
CREATE TABLE IF NOT EXISTS codigo_canje (
  id_codigo     SERIAL       PRIMARY KEY,
  codigo        VARCHAR(20)  NOT NULL UNIQUE,    -- formato XXXX-XXXX-XXXX
  valor         NUMERIC(10,2) NOT NULL,
  estado        VARCHAR(20)  NOT NULL DEFAULT 'activo'
                             CHECK (estado IN ('activo','canjeado','expirado')),
  id_jugador_comprador INT   NOT NULL,           -- quién lo compró
  id_jugador_canje     INT,                      -- quién lo canjeó (NULL hasta canje)
  id_producto   INT          NOT NULL,
  fecha_creacion TIMESTAMP   NOT NULL DEFAULT NOW(),
  fecha_uso      TIMESTAMP,
  fecha_expiracion TIMESTAMP NOT NULL DEFAULT (NOW() + INTERVAL '2 years'),
  FOREIGN KEY (id_jugador_comprador) REFERENCES jugador(id_jugador),
  FOREIGN KEY (id_jugador_canje)     REFERENCES jugador(id_jugador),
  FOREIGN KEY (id_producto)          REFERENCES tarjeta_specter_producto(id_producto)
);

-- ── 6. compra: agregar método de pago ────────────────────────
ALTER TABLE compra
  ADD COLUMN IF NOT EXISTS metodo_pago     VARCHAR(20) DEFAULT 'tarjeta'
                                           CHECK (metodo_pago IN ('tarjeta','saldo','mixto')),
  ADD COLUMN IF NOT EXISTS monto_saldo     NUMERIC(10,2) DEFAULT 0,  -- cuánto se pagó con saldo
  ADD COLUMN IF NOT EXISTS monto_tarjeta   NUMERIC(10,2) DEFAULT 0,  -- cuánto se pagó con tarjeta
  ADD COLUMN IF NOT EXISTS id_tarjeta_pago INT REFERENCES tarjeta_pago(id_tarjeta);

-- ── 7. FK movimiento_saldo → compra / codigo_canje ───────────
ALTER TABLE movimiento_saldo
  ADD CONSTRAINT fk_mov_compra  FOREIGN KEY (id_compra) REFERENCES compra(id_compra),
  ADD CONSTRAINT fk_mov_canje   FOREIGN KEY (id_canje)  REFERENCES codigo_canje(id_codigo);

-- ── 8. Datos iniciales — denominaciones ──────────────────────
INSERT INTO tarjeta_specter_producto (denominacion, nombre, descripcion) VALUES
  (100.00, 'Tarjeta Specter $100', 'Agrega $100 de saldo a tu cartera Specter'),
  (250.00, 'Tarjeta Specter $250', 'Agrega $250 de saldo a tu cartera Specter'),
  (500.00, 'Tarjeta Specter $500', 'Agrega $500 de saldo a tu cartera Specter')
ON CONFLICT (denominacion) DO NOTHING;

-- ── 9. Índices de rendimiento ─────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_movimiento_jugador ON movimiento_saldo(id_jugador);
CREATE INDEX IF NOT EXISTS idx_codigo_estado      ON codigo_canje(estado);
CREATE INDEX IF NOT EXISTS idx_tarjeta_jugador    ON tarjeta_pago(id_jugador);
