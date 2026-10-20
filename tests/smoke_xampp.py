"""Prueba HTTP del adaptador XAMPP; elimina exclusivamente su evidencia temporal."""
import csv
import io
import json
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://localhost:8080/AUDITORIA/'
marker = 'PRUEBA-TEMPORAL-' + uuid.uuid4().hex
evidence_id = None


def request(path, data=None):
    url = BASE + 'api.php?path=' + urllib.parse.quote(path)
    req = urllib.request.Request(url, data=None if data is None else json.dumps(data).encode(), headers={} if data is None else {'Content-Type': 'application/json'})
    try:
        response = urllib.request.urlopen(req, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    raw = response.read().decode('utf-8-sig')
    return response.status, raw if path.endswith('.csv') else json.loads(raw)


try:
    assert request('/health')[0] == 200
    status, evidence = request('/evidencias', {'texto': marker + ': otro ciudadano accede sin permiso.', 'activo': 'Prueba ficticia', 'perfil': 'Ciudadano'})
    assert status == 201, evidence
    evidence_id = evidence['id']
    assert request(f'/evidencias/{evidence_id}/hipotesis')[1]['categoria_sugerida'] == 'A01'
    status, classification = request(f'/evidencias/{evidence_id}/clasificaciones', {'categoria_id': 'A01'})
    assert status == 201, classification
    payload = {'autor': 'Estudiante de prueba', 'texto': '=HYPERLINK("prueba") La evidencia indica acceso indebido a otro objeto.', 'contraste': 'A07 establece identidad y A01 trata permisos sobre objetos.', 'impacto': 'Exposición ficticia de datos.', 'dato_faltante': 'Confirmar permisos.', 'politica_institucional': 'No proporcionada; solicitar regla de acceso.', 'referencia_asvs': 'Revisar autorización en ASVS.', 'prueba_wstg': 'Comparar permisos con cuentas ficticias.'}
    status, justification = request(f"/clasificaciones/{classification['id']}/justificaciones", payload)
    assert status == 201, justification
    status, review = request(f"/justificaciones/{justification['id']}/revisiones", {'revisor': 'Docente de prueba', 'decision': 'observada', 'observaciones': 'Completar prueba del permiso.'})
    assert status == 201, review
    exported = request('/exportar.csv')[1]
    assert "'=HYPERLINK" in exported
    assert marker in exported
    status, updated = request(f"/clasificaciones/{classification['id']}/justificaciones", {**payload, 'texto': 'Versión corregida y pendiente de una nueva revisión.'})
    assert status == 201, updated
    matrix = request('/evidencias')[1]
    current = next(r for r in matrix if r['evidencia_id'] == evidence_id)
    assert current['revision_id'] is None
    assert marker not in request('/exportar.csv')[1]
    assert request(f"/justificaciones/{justification['id']}/revisiones", {'revisor': 'Docente de prueba', 'decision': 'aprobada', 'observaciones': 'Obsoleta.'})[0] == 409
    status, self_review = request(f"/justificaciones/{updated['id']}/revisiones", {'revisor':'Estudiante de prueba','decision':'observada','observaciones':'Autoevaluación explícita de prueba.','autoevaluacion':True})
    assert status == 201 and self_review['autoevaluacion'] == 1, self_review
    for url in ['data/auditoria.sqlite3', 'database/schema.sql', 'backend/categories.json']:
        try:
            urllib.request.urlopen(BASE+url)
            raise AssertionError('Archivo interno expuesto: '+url)
        except urllib.error.HTTPError as error:
            assert error.code == 403, (url, error.code)
    print('OK XAMPP: entrada, hipótesis, categoría, justificación, revisión, CSV, versiones y protección de archivos internos.')
finally:
    if evidence_id is not None:
        php = '''$d=new PDO('sqlite:data/auditoria.sqlite3'); $d->exec('PRAGMA foreign_keys=ON'); $q=$d->prepare('SELECT id FROM evidencias WHERE id=? AND texto LIKE ?'); $q->execute([(int)$argv[1],$argv[2].'%']); if($q->fetchColumn()){$d->beginTransaction();$id=(int)$argv[1];$d->exec("DELETE FROM revisiones WHERE justificacion_id IN (SELECT j.id FROM justificaciones j JOIN clasificaciones c ON c.id=j.clasificacion_id WHERE c.evidencia_id=$id)");$d->exec("DELETE FROM justificaciones WHERE clasificacion_id IN (SELECT id FROM clasificaciones WHERE evidencia_id=$id)");$d->exec("DELETE FROM clasificaciones WHERE evidencia_id=$id");$d->exec("DELETE FROM evidencias WHERE id=$id");$d->commit();}'''
        subprocess.run([r'C:\xampp\php\php.exe', '-r', php, str(evidence_id), marker], cwd=ROOT, check=True)
        print('Registro de prueba eliminado; sin cambios en las demás evidencias.')
