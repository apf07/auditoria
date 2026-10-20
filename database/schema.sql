-- SQLite 3: esquema ejecutado por el backend. Todas las fechas son UTC.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS categorias_owasp (
    id TEXT PRIMARY KEY CHECK (id IN ('A01','A02','A03','A04','A05','A06','A07','A08','A09','A10')),
    version TEXT NOT NULL DEFAULT '2025',
    nombre TEXT NOT NULL,
    criterio TEXT NOT NULL,
    categoria_cercana TEXT NOT NULL REFERENCES categorias_owasp(id) DEFERRABLE INITIALLY DEFERRED,
    contraste TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE TABLE IF NOT EXISTS sistemas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL CHECK(length(trim(nombre)) BETWEEN 1 AND 200),
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE TABLE IF NOT EXISTS analisis_zip (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sistema_id INTEGER NOT NULL REFERENCES sistemas(id) ON DELETE RESTRICT,
    nombre_archivo TEXT NOT NULL,
    content_sha256 TEXT NULL,
    resultado TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE TABLE IF NOT EXISTS evidencias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sistema_id INTEGER NULL REFERENCES sistemas(id) ON DELETE RESTRICT,
    archived_at TEXT NULL,
    analisis_id INTEGER NULL REFERENCES analisis_zip(id) ON DELETE RESTRICT,
    ubicacion TEXT NULL,
    recomendacion TEXT NULL,
    texto TEXT NOT NULL CHECK(length(trim(texto)) BETWEEN 10 AND 10000),
    activo TEXT NOT NULL CHECK(length(trim(activo)) BETWEEN 1 AND 200),
    perfil TEXT NOT NULL CHECK(length(trim(perfil)) BETWEEN 1 AND 200),
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE TABLE IF NOT EXISTS clasificaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    evidencia_id INTEGER NOT NULL REFERENCES evidencias(id) ON DELETE RESTRICT,
    categoria_id TEXT NOT NULL REFERENCES categorias_owasp(id) ON DELETE RESTRICT,
    origen TEXT NOT NULL CHECK(origen IN ('reglas','manual')),
    motor_version TEXT NOT NULL,
    explicacion TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE TABLE IF NOT EXISTS justificaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    clasificacion_id INTEGER NOT NULL REFERENCES clasificaciones(id) ON DELETE RESTRICT,
    autor TEXT NOT NULL CHECK(length(trim(autor)) BETWEEN 1 AND 120),
    texto TEXT NOT NULL CHECK(length(trim(texto)) >= 10),
    contraste TEXT NOT NULL CHECK(length(trim(contraste)) >= 10),
    impacto TEXT NOT NULL CHECK(length(trim(impacto)) >= 3),
    dato_faltante TEXT NOT NULL,
    politica_institucional TEXT NOT NULL,
    referencia_asvs TEXT NOT NULL,
    prueba_wstg TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE TABLE IF NOT EXISTS revisiones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    justificacion_id INTEGER NOT NULL REFERENCES justificaciones(id) ON DELETE RESTRICT,
    revisor TEXT NOT NULL CHECK(length(trim(revisor)) BETWEEN 1 AND 120),
    decision TEXT NOT NULL CHECK(decision IN ('aprobada','observada')),
    autoevaluacion INTEGER NOT NULL DEFAULT 0 CHECK(autoevaluacion IN (0,1)),
    observaciones TEXT NOT NULL CHECK(length(trim(observaciones)) >= 5),
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE INDEX IF NOT EXISTS idx_clasificacion_evidencia ON clasificaciones(evidencia_id,id);
CREATE INDEX IF NOT EXISTS idx_justificacion_clasificacion ON justificaciones(clasificacion_id,id);
CREATE INDEX IF NOT EXISTS idx_revision_justificacion ON revisiones(justificacion_id,id);
-- Las nuevas versiones se insertan: no se sobrescriben decisiones previas.
CREATE TRIGGER IF NOT EXISTS evidencia_updated AFTER UPDATE ON evidencias
BEGIN
    UPDATE evidencias SET updated_at=strftime('%Y-%m-%dT%H:%M:%fZ','now') WHERE id=NEW.id;
END;
