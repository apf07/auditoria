from contextlib import asynccontextmanager
from dataclasses import asdict
from pathlib import Path
from typing import Annotated, Literal
from urllib.parse import urlsplit

from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import Response, JSONResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, ConfigDict, StringConstraints
from starlette.middleware.trustedhost import TrustedHostMiddleware

from .classifier import classify, ENGINE
from .db import initialize, connection, categories, latest_matrix, ROOT
from .export import reviewed_csv
from .models import Evidencia

Short = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=200)]
Text = Annotated[str, StringConstraints(strip_whitespace=True, min_length=10, max_length=10000)]
Context = Annotated[str, StringConstraints(strip_whitespace=True, min_length=3, max_length=5000)]


class Input(BaseModel):
    model_config = ConfigDict(extra='forbid')


class EvidenceInput(Input):
    texto: Text
    activo: Short
    perfil: Short
    sistema_id: int | None = None


class ClassificationInput(Input):
    categoria_id: Annotated[str, StringConstraints(pattern=r'^A(?:0[1-9]|10)$')]


class JustificationInput(Input):
    autor: Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=120)]
    texto: Text
    contraste: Text
    impacto: Context
    dato_faltante: Context
    politica_institucional: Context
    referencia_asvs: Context
    prueba_wstg: Context


class ReviewInput(Input):
    revisor: Annotated[str, StringConstraints(strip_whitespace=True, min_length=1, max_length=120)]
    autoevaluacion: bool = False
    decision: Literal['aprobada', 'observada']
    observaciones: Annotated[str, StringConstraints(strip_whitespace=True, min_length=5, max_length=5000)]


@asynccontextmanager
async def lifespan(app):
    initialize()
    yield


app = FastAPI(title='Auditoría · Clasificación de evidencias', version='1.0.0', lifespan=lifespan)
app.add_middleware(TrustedHostMiddleware, allowed_hosts=['localhost', '127.0.0.1', 'testserver'])


@app.middleware('http')
async def local_request_guard(request: Request, call_next):
    if request.method in ('POST', 'PUT', 'PATCH', 'DELETE'):
        origin = request.headers.get('origin')
        if origin:
            parsed = urlsplit(origin)
            if parsed.hostname not in ('localhost', '127.0.0.1') or parsed.port not in (8000, 5173):
                return JSONResponse({'detail': 'Origen no autorizado.'}, status_code=403)
        if request.headers.get('content-type', '').split(';')[0] != 'application/json':
            return JSONResponse({'detail': 'Se requiere application/json.'}, status_code=415)
        if len(await request.body()) > 100000:
            return JSONResponse({'detail': 'Solicitud demasiado grande.'}, status_code=413)
    response = await call_next(request)
    response.headers['X-Content-Type-Options'] = 'nosniff'
    response.headers['Referrer-Policy'] = 'no-referrer'
    if request.url.path.startswith('/api'):
        response.headers['Cache-Control'] = 'no-store'
    return response


def required(db, table, identifier):
    # Los nombres de tabla son constantes internas, no entradas del usuario.
    row = db.execute(f'SELECT * FROM {table} WHERE id=?', (identifier,)).fetchone()
    if row is None:
        raise HTTPException(404, 'Registro no encontrado.')
    return dict(row)


@app.get('/api/health')
def health():
    return {'status': 'ok', 'mode': 'prototipo local sin autenticación'}


class SystemInput(Input):
    nombre: Short


class AssignSystemInput(Input):
    sistema_id: int


@app.get('/api/sistemas')
def list_systems():
    with connection() as db:
        return [dict(r) for r in db.execute('SELECT * FROM sistemas ORDER BY id DESC')]


@app.post('/api/sistemas', status_code=201)
def create_system(payload: SystemInput):
    with connection() as db:
        cursor=db.execute('INSERT INTO sistemas (nombre) VALUES (?)',(payload.nombre,))
        return required(db,'sistemas',cursor.lastrowid)


@app.get('/api/papelera')
def trash():
    with connection() as db:
        sql=(ROOT/'database'/'matrix.sql').read_text().replace('e.archived_at IS NULL','e.archived_at IS NOT NULL')
        return [dict(r) for r in db.execute(sql)]


@app.post('/api/evidencias/{evidence_id}/archivar')
def archive(evidence_id: int):
    with connection() as db:
        required(db,'evidencias',evidence_id)
        db.execute("UPDATE evidencias SET archived_at=strftime('%Y-%m-%dT%H:%M:%fZ','now') WHERE id=?",(evidence_id,))
        return required(db,'evidencias',evidence_id)


@app.post('/api/evidencias/{evidence_id}/restaurar')
def restore(evidence_id: int):
    with connection() as db:
        required(db,'evidencias',evidence_id)
        db.execute('UPDATE evidencias SET archived_at=NULL WHERE id=?',(evidence_id,))
        return required(db,'evidencias',evidence_id)


@app.post('/api/evidencias/{evidence_id}/sistema')
def assign_system(evidence_id: int,payload: AssignSystemInput):
    with connection() as db:
        required(db,'evidencias',evidence_id); required(db,'sistemas',payload.sistema_id)
        db.execute('UPDATE evidencias SET sistema_id=? WHERE id=?',(payload.sistema_id,evidence_id))
        return required(db,'evidencias',evidence_id)


@app.get('/api/sistemas/{system_id}/analisis')
def scans(system_id:int):
    import json
    with connection() as db:
        required(db,'sistemas',system_id)
        return [{**dict(r),'resultado':json.loads(r['resultado'])} for r in db.execute('SELECT * FROM analisis_zip WHERE sistema_id=? ORDER BY id DESC',(system_id,))]


@app.get('/api/categorias')
def list_categories():
    with connection() as db:
        return categories(db)


@app.post('/api/evidencias', status_code=201)
def create_evidence(payload: EvidenceInput):
    with connection() as db:
        if payload.sistema_id is not None:
            required(db, 'sistemas', payload.sistema_id)
        cursor = db.execute('INSERT INTO evidencias (texto,activo,perfil,sistema_id) VALUES (?,?,?,?)', (payload.texto, payload.activo, payload.perfil, payload.sistema_id))
        row = required(db, 'evidencias', cursor.lastrowid)
        return asdict(Evidencia(**row))


@app.get('/api/evidencias')
def list_evidence():
    with connection() as db:
        return latest_matrix(db)


@app.get('/api/evidencias/{evidence_id}/hipotesis')
def hypotheses(evidence_id: int):
    with connection() as db:
        evidence = required(db, 'evidencias', evidence_id)
        return classify(evidence['texto'], categories(db))


@app.post('/api/evidencias/{evidence_id}/clasificaciones', status_code=201)
def select_classification(evidence_id: int, payload: ClassificationInput):
    with connection() as db:
        evidence = required(db, 'evidencias', evidence_id)
        category = db.execute('SELECT * FROM categorias_owasp WHERE id=?', (payload.categoria_id,)).fetchone()
        if category is None:
            raise HTTPException(422, 'Categoría desconocida.')
        result = classify(evidence['texto'], categories(db))
        candidate = next((c for c in result['candidatas'] if c['id'] == payload.categoria_id), None)
        explanation = candidate['motivo'] if candidate else 'Categoría seleccionada manualmente, pendiente de sustento y contraste.'
        cursor = db.execute('INSERT INTO clasificaciones (evidencia_id,categoria_id,origen,motor_version,explicacion) VALUES (?,?,?,?,?)', (evidence_id, payload.categoria_id, 'reglas' if candidate else 'manual', ENGINE, explanation))
        return required(db, 'clasificaciones', cursor.lastrowid)


def assert_current_classification(db, classification):
    latest = db.execute('SELECT MAX(id) FROM clasificaciones WHERE evidencia_id=?', (classification['evidencia_id'],)).fetchone()[0]
    if latest != classification['id']:
        raise HTTPException(409, 'La clasificación fue reemplazada. Recarga la evidencia.')


@app.post('/api/clasificaciones/{classification_id}/justificaciones', status_code=201)
def create_justification(classification_id: int, payload: JustificationInput):
    with connection() as db:
        classification = required(db, 'clasificaciones', classification_id)
        assert_current_classification(db, classification)
        values = payload.model_dump()
        columns = ','.join(values)
        placeholders = ','.join('?' for _ in values)
        cursor = db.execute(f'INSERT INTO justificaciones (clasificacion_id,{columns}) VALUES (?,{placeholders})', (classification_id, *values.values()))
        return required(db, 'justificaciones', cursor.lastrowid)


@app.post('/api/justificaciones/{justification_id}/revisiones', status_code=201)
def create_review(justification_id: int, payload: ReviewInput):
    with connection() as db:
        justification = required(db, 'justificaciones', justification_id)
        classification = required(db, 'clasificaciones', justification['clasificacion_id'])
        assert_current_classification(db, classification)
        latest = db.execute('SELECT MAX(id) FROM justificaciones WHERE clasificacion_id=?', (classification['id'],)).fetchone()[0]
        if latest != justification_id:
            raise HTTPException(409, 'La justificación fue reemplazada. Revisa la versión actual.')
        if justification['autor'].casefold() == payload.revisor.casefold() and not payload.autoevaluacion:
            raise HTTPException(422, 'Si eres autor y revisor, marca autoevaluación.')
        cursor = db.execute('INSERT INTO revisiones (justificacion_id,revisor,decision,observaciones,autoevaluacion) VALUES (?,?,?,?,?)', (justification_id, payload.revisor, payload.decision, payload.observaciones, int(payload.autoevaluacion)))
        return required(db, 'revisiones', cursor.lastrowid)


@app.get('/api/evidencias/{evidence_id}/historial')
def history(evidence_id: int):
    with connection() as db:
        required(db, 'evidencias', evidence_id)
        return {
            'clasificaciones': [dict(r) for r in db.execute('SELECT * FROM clasificaciones WHERE evidencia_id=? ORDER BY id', (evidence_id,))],
            'justificaciones': [dict(r) for r in db.execute('SELECT j.* FROM justificaciones j JOIN clasificaciones c ON c.id=j.clasificacion_id WHERE c.evidencia_id=? ORDER BY j.id', (evidence_id,))],
            'revisiones': [dict(r) for r in db.execute('SELECT r.* FROM revisiones r JOIN justificaciones j ON j.id=r.justificacion_id JOIN clasificaciones c ON c.id=j.clasificacion_id WHERE c.evidencia_id=? ORDER BY r.id', (evidence_id,))],
        }


@app.get('/api/exportar.csv')
def export_classifications(sistema_id: str | None = None, analisis_id: int | None = None, origen: str | None = None):
    with connection() as db:
        rows = latest_matrix(db)
        if sistema_id is not None:
            if sistema_id != 'legacy' and not sistema_id.isdigit():
                raise HTTPException(422, 'Sistema inválido.')
            rows = [r for r in rows if r['sistema_id'] is None] if sistema_id == 'legacy' else [r for r in rows if r['sistema_id'] == int(sistema_id)]
        if analisis_id is not None:
            rows = [r for r in rows if r['analisis_id'] == analisis_id]
        if origen == 'manual':
            rows = [r for r in rows if r['analisis_id'] is None]
        csv = reviewed_csv(rows)
    return Response(csv, media_type='text/csv; charset=utf-8', headers={'Content-Disposition': 'attachment; filename="matriz-revisada.csv"'})


dist = ROOT / 'frontend' / 'dist'
if dist.is_dir():
    app.mount('/', StaticFiles(directory=dist, html=True), name='frontend')
