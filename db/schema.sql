-- =====================================================================
-- Seguridad Minera — esquema PostgreSQL
-- Idempotente: se puede ejecutar en cada arranque (bin/migrate.php).
-- Marco: Ley 19.587, Ley 24.557, Dec. 249/07 (minería), Res. SRT 525/15
-- (denuncia), Res. SRT 3326/14 (RENAL). Ver docs/normativa.md
-- =====================================================================

-- ---------- Usuarios ----------
CREATE TABLE IF NOT EXISTS users (
  id            SERIAL PRIMARY KEY,
  email         TEXT NOT NULL UNIQUE,
  full_name     TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'SUPERVISOR'
                CHECK (role IN ('ADMIN','SUPERVISOR','LECTOR')),
  active        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT now()
);

-- ---------- Empresas (titular, contratistas) ----------
CREATE TABLE IF NOT EXISTS companies (
  id      SERIAL PRIMARY KEY,
  nombre  TEXT NOT NULL UNIQUE,
  tipo    TEXT NOT NULL DEFAULT 'CONTRATISTA'
          CHECK (tipo IN ('TITULAR','CONTRATISTA','SUBCONTRATISTA','PROVEEDOR','OTRO')),
  cuit    TEXT,
  art     TEXT,                       -- Aseguradora de Riesgos del Trabajo
  activo  BOOLEAN NOT NULL DEFAULT TRUE
);

-- ---------- Catálogos propios del sistema original ----------
CREATE TABLE IF NOT EXISTS categories (
  id    SERIAL PRIMARY KEY,
  code  TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS severities (
  id    SERIAL PRIMARY KEY,
  code  TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL,
  orden INT  NOT NULL DEFAULT 0
);

-- ---------- Catálogos normativos (OIT 1962 / tablas RENAL a nivel grupo) ----------
-- kind: FORMA | AGENTE | NATURALEZA | ZONA | RIESGO_CRITICO
CREATE TABLE IF NOT EXISTS catalogs (
  kind  TEXT NOT NULL,
  code  TEXT NOT NULL,
  label TEXT NOT NULL,
  sort  INT  NOT NULL DEFAULT 0,
  PRIMARY KEY (kind, code)
);

-- ---------- Incidentes / accidentes ----------
CREATE TABLE IF NOT EXISTS incidents (
  id              SERIAL PRIMARY KEY,
  title           TEXT NOT NULL,
  description     TEXT,
  category_id     INT NOT NULL REFERENCES categories(id),
  severity_id     INT NOT NULL REFERENCES severities(id),
  company_id      INT REFERENCES companies(id),       -- empresa empleadora del afectado / principal
  event_datetime  TIMESTAMP NOT NULL,
  lat             DOUBLE PRECISION NOT NULL,
  lng             DOUBLE PRECISION NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT now(),
  updated_at      TIMESTAMP NOT NULL DEFAULT now(),
  created_by      INT REFERENCES users(id)
);

-- Columnas agregadas en la versión normativa (ALTER idempotente)
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS faena   TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS sector  TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS turno   TEXT;   -- DIA | TARDE | NOCHE | ROTATIVO
-- Tipo de contingencia (Ley 24.557 art. 6 y Res. SRT 525/15)
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS tipo_contingencia TEXT NOT NULL DEFAULT 'INCIDENTE';
-- Consecuencia real
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS consecuencia TEXT NOT NULL DEFAULT 'SIN_LESION';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS dias_perdidos INT NOT NULL DEFAULT 0;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS fecha_inicio_baja DATE;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS fecha_alta DATE;
-- Tipificación (tablas RENAL / OIT)
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS forma_code      TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS agente_code     TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS naturaleza_code TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS zona_code       TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS riesgo_critico_code TEXT;
-- Severidad potencial (matriz 5x5 probabilidad x consecuencia)
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS pot_probabilidad  SMALLINT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS pot_consecuencia  SMALLINT;
-- Denuncia a la ART (Res. SRT 525/15: 48 h) y autoridad minera
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS art_denunciado     BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS art_nombre         TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS art_nro_siniestro  TEXT;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS art_fecha_denuncia TIMESTAMP;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS autoridad_notificada BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS autoridad_fecha      TIMESTAMP;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS estado TEXT NOT NULL DEFAULT 'ABIERTO';

DO $$ BEGIN
  ALTER TABLE incidents ADD CONSTRAINT ck_tipo_contingencia CHECK (tipo_contingencia IN
    ('ACCIDENTE_TRABAJO','IN_ITINERE','ENFERMEDAD_PROFESIONAL','INCIDENTE','DANO_MATERIAL','AMBIENTAL','OTRO'));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
  ALTER TABLE incidents ADD CONSTRAINT ck_consecuencia CHECK (consecuencia IN
    ('SIN_LESION','PRIMEROS_AUXILIOS','TRATAMIENTO_MEDICO','TRABAJO_RESTRINGIDO','CON_BAJA','INCAPACIDAD_PERMANENTE','FATAL'));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
  ALTER TABLE incidents ADD CONSTRAINT ck_estado CHECK (estado IN
    ('ABIERTO','EN_INVESTIGACION','ACCIONES_PENDIENTES','CERRADO'));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
  ALTER TABLE incidents ADD CONSTRAINT ck_pot CHECK (
    (pot_probabilidad IS NULL OR pot_probabilidad BETWEEN 1 AND 5) AND
    (pot_consecuencia IS NULL OR pot_consecuencia BETWEEN 1 AND 5));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
  ALTER TABLE incidents ADD CONSTRAINT ck_dias CHECK (dias_perdidos >= 0);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

CREATE INDEX IF NOT EXISTS ix_incidents_dt      ON incidents(event_datetime);
CREATE INDEX IF NOT EXISTS ix_incidents_company ON incidents(company_id);

-- ---------- Empresas y personas involucradas ----------
CREATE TABLE IF NOT EXISTS incident_companies (
  id          SERIAL PRIMARY KEY,
  incident_id INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
  company_id  INT NOT NULL REFERENCES companies(id),
  role        TEXT NOT NULL DEFAULT 'RESPONSABLE'
);

CREATE TABLE IF NOT EXISTS persons (
  id         SERIAL PRIMARY KEY,
  full_name  TEXT NOT NULL,
  company_id INT REFERENCES companies(id),
  puesto     TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_persons_name_company
  ON persons (lower(full_name), COALESCE(company_id, 0));

CREATE TABLE IF NOT EXISTS incident_persons (
  id          SERIAL PRIMARY KEY,
  incident_id INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
  person_id   INT NOT NULL REFERENCES persons(id),
  role        TEXT
);

-- ---------- Fotos (en la base: el disco de Railway es efímero) ----------
CREATE TABLE IF NOT EXISTS incident_photos (
  id          SERIAL PRIMARY KEY,
  incident_id INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
  filename    TEXT NOT NULL,
  mime        TEXT NOT NULL,
  data        BYTEA NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_photos_incident ON incident_photos(incident_id);

-- ---------- Investigación (Dec. 249/07 art. 13 o; modelo de causalidad Bird/ILCI) ----------
CREATE TABLE IF NOT EXISTS incident_investigations (
  incident_id          INT PRIMARY KEY REFERENCES incidents(id) ON DELETE CASCADE,
  metodo               TEXT NOT NULL DEFAULT 'ARBOL_CAUSAS',  -- ARBOL_CAUSAS | CINCO_PORQUES | ICAM | TAPROOT | OTRO
  equipo               TEXT,      -- integrantes (incluye Comité de HyS, Dec. 249/07 art. 26 g)
  hechos               TEXT,      -- relato objetivo / lista de hechos (árbol de causas)
  actos_subestandar    TEXT,      -- causas inmediatas: actos
  condiciones_subestandar TEXT,   -- causas inmediatas: condiciones
  factores_personales  TEXT,      -- causas básicas: factores personales
  factores_trabajo     TEXT,      -- causas básicas: factores del trabajo
  falta_control        TEXT,      -- fallas del sistema de gestión
  porques              JSONB NOT NULL DEFAULT '[]'::jsonb,  -- cadena de "¿por qué?"
  conclusiones         TEXT,
  fecha_inicio         DATE,
  fecha_cierre         DATE,
  updated_at           TIMESTAMP NOT NULL DEFAULT now()
);

-- ---------- Acciones correctivas / preventivas ----------
CREATE TABLE IF NOT EXISTS incident_actions (
  id                 SERIAL PRIMARY KEY,
  incident_id        INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
  descripcion        TEXT NOT NULL,
  tipo               TEXT NOT NULL DEFAULT 'CORRECTIVA'
                     CHECK (tipo IN ('CORRECTIVA','PREVENTIVA','MEJORA')),
  jerarquia          TEXT NOT NULL DEFAULT 'ADMINISTRATIVO'
                     CHECK (jerarquia IN ('ELIMINACION','SUSTITUCION','INGENIERIA','ADMINISTRATIVO','EPP')),
  responsable        TEXT,
  fecha_compromiso   DATE,
  estado             TEXT NOT NULL DEFAULT 'PENDIENTE'
                     CHECK (estado IN ('PENDIENTE','EN_CURSO','CUMPLIDA','VERIFICADA','ANULADA')),
  fecha_cumplimiento DATE,
  verificacion       TEXT,       -- verificación de eficacia
  created_at         TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_actions_incident ON incident_actions(incident_id);

-- ---------- Exposición: horas-hombre y dotación por empresa y mes ----------
CREATE TABLE IF NOT EXISTS work_hours (
  id          SERIAL PRIMARY KEY,
  company_id  INT NOT NULL REFERENCES companies(id),
  periodo     DATE NOT NULL,                 -- primer día del mes
  hht         NUMERIC(14,2) NOT NULL CHECK (hht >= 0),   -- horas-hombre trabajadas
  dotacion    NUMERIC(10,2) NOT NULL CHECK (dotacion >= 0), -- trabajadores promedio del mes
  UNIQUE (company_id, periodo),
  CHECK (periodo = date_trunc('month', periodo)::date)
);
