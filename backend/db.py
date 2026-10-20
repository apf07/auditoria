import os
import sqlite3
from contextlib import contextmanager
from pathlib import Path
from .catalog import CATALOG

ROOT = Path(__file__).resolve().parents[1]


def database_path() -> Path:
    return Path(os.environ.get('AUDITORIA_DB', ROOT / 'data' / 'auditoria.sqlite3'))


@contextmanager
def connection():
    path = database_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    db = sqlite3.connect(path, timeout=10)
    db.row_factory = sqlite3.Row
    db.execute('PRAGMA foreign_keys = ON')
    try:
        with db:
            yield db
    finally:
        db.close()


def initialize():
    with connection() as db:
        db.executescript((ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8'))
        names = {r['name'] for r in db.execute('PRAGMA table_info(evidencias)')}
        if 'sistema_id' not in names:
            db.execute('ALTER TABLE evidencias ADD COLUMN sistema_id INTEGER NULL REFERENCES sistemas(id) ON DELETE RESTRICT')
        if 'archived_at' not in names:
            db.execute('ALTER TABLE evidencias ADD COLUMN archived_at TEXT NULL')
        for field, definition in [('analisis_id','INTEGER NULL REFERENCES analisis_zip(id) ON DELETE RESTRICT'),('ubicacion','TEXT NULL'),('recomendacion','TEXT NULL')]:
            if field not in names:
                db.execute(f'ALTER TABLE evidencias ADD COLUMN {field} {definition}')
        review_columns = {r['name'] for r in db.execute('PRAGMA table_info(revisiones)')}
        if 'autoevaluacion' not in review_columns:
            db.execute('ALTER TABLE revisiones ADD COLUMN autoevaluacion INTEGER NOT NULL DEFAULT 0')
        scan_columns = {r['name'] for r in db.execute('PRAGMA table_info(analisis_zip)')}
        if 'content_sha256' not in scan_columns:
            db.execute('ALTER TABLE analisis_zip ADD COLUMN content_sha256 TEXT NULL')
        db.execute('CREATE UNIQUE INDEX IF NOT EXISTS idx_scan_fingerprint ON analisis_zip(sistema_id,content_sha256)')
        db.executemany('INSERT OR IGNORE INTO categorias_owasp (id,nombre,criterio,categoria_cercana,contraste) VALUES (?,?,?,?,?)', CATALOG)


def categories(db):
    return [dict(r) for r in db.execute('SELECT * FROM categorias_owasp ORDER BY id')]


def latest_matrix(db):
    # La revisión se enlaza a una justificación concreta. Una versión nueva invalida
    # el estado aprobado anterior para fines de exportación, sin borrar el historial.
    rows = db.execute((ROOT / 'database' / 'matrix.sql').read_text(encoding='utf-8')).fetchall()
    return [dict(row) for row in rows]
