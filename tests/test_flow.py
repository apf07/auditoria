import csv
import io
import os
import sqlite3
import tempfile
import unittest
from pathlib import Path

from fastapi.testclient import TestClient
from backend.main import app
from backend.db import connection


class WorkflowTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.previous = os.environ.get('AUDITORIA_DB')
        os.environ['AUDITORIA_DB'] = str(Path(self.temp.name) / 'test.sqlite3')
        self.client = TestClient(app)
        self.client.__enter__()

    def tearDown(self):
        self.client.__exit__(None, None, None)
        if self.previous is None:
            os.environ.pop('AUDITORIA_DB', None)
        else:
            os.environ['AUDITORIA_DB'] = self.previous
        self.temp.cleanup()

    def post(self, route, payload):
        result = self.client.post('/api' + route, json=payload)
        self.assertEqual(result.status_code, 201, result.text)
        return result.json()

    def evidence(self, text='Un ciudadano cambia el ID y consulta datos de otro ciudadano.'):
        return self.post('/evidencias', {'texto': text, 'activo': 'Expedientes', 'perfil': 'Ciudadano'})

    def test_empty_and_hypotheses(self):
        self.assertEqual(self.client.get('/api/evidencias').json(), [])
        self.assertEqual(len(self.client.get('/api/categorias').json()), 10)
        e = self.evidence()
        h = self.client.get(f"/api/evidencias/{e['id']}/hipotesis").json()
        self.assertEqual(h['categoria_sugerida'], 'A01')
        self.assertTrue(h['candidatas'][0]['criterio'])
        self.assertEqual(h['candidatas'][0]['categoria_cercana'], 'A07')
        other = self.evidence('Información insuficiente para establecer una causa concreta.')
        self.assertEqual(self.client.get(f"/api/evidencias/{other['id']}/hipotesis").json()['candidatas'], [])
        ambiguous = self.evidence('Otro ciudadano recupera una contraseña sin permiso.')
        h = self.client.get(f"/api/evidencias/{ambiguous['id']}/hipotesis").json()
        self.assertGreater(len(h['candidatas']), 1)
        self.assertIsNone(h['categoria_sugerida'])

    def test_review_export_and_versioning(self):
        e = self.evidence()
        c = self.post(f"/evidencias/{e['id']}/clasificaciones", {'categoria_id': 'A01'})
        payload = {'autor': 'Estudiante', 'texto': '=HYPERLINK("ejemplo") Se permite acceder a un objeto ajeno.', 'contraste': 'A07 valida identidad, pero esta prueba trata permisos.', 'impacto': 'Posible exposición de expedientes ficticios.', 'dato_faltante': 'Confirmar control del endpoint.', 'politica_institucional': 'No proporcionada; solicitar política de acceso.', 'referencia_asvs': 'ASVS: autorización sobre objetos, pendiente de contrastar versión.', 'prueba_wstg': 'WSTG: probar acceso a objeto ajeno con cuentas ficticias.'}
        j = self.post(f"/clasificaciones/{c['id']}/justificaciones", payload)
        before = self.client.get('/api/exportar.csv').text
        self.assertEqual(len(list(csv.reader(io.StringIO(before.lstrip('\ufeff')), delimiter=';'))), 1)
        self.assertEqual(self.client.post(f"/api/justificaciones/{j['id']}/revisiones", json={'revisor': 'Estudiante', 'decision': 'aprobada', 'observaciones': 'Validación de prueba.'}).status_code, 422)
        self.post(f"/justificaciones/{j['id']}/revisiones", {'revisor': 'Docente', 'decision': 'observada', 'observaciones': 'Solicitar evidencia adicional del permiso.'})
        exported = self.client.get('/api/exportar.csv')
        self.assertIn("'=HYPERLINK", exported.text)
        self.assertIn('observada', exported.text)
        j2 = self.post(f"/clasificaciones/{c['id']}/justificaciones", {**payload, 'texto': 'Nueva versión de la justificación con datos adicionales.'})
        rows = self.client.get('/api/evidencias').json()
        self.assertIsNone(rows[0]['revision_id'])
        self.assertEqual(len(list(csv.reader(io.StringIO(self.client.get('/api/exportar.csv').text.lstrip('\ufeff')), delimiter=';'))), 1)
        self.assertEqual(self.client.post(f"/api/justificaciones/{j['id']}/revisiones", json={'revisor': 'Docente', 'decision': 'aprobada', 'observaciones': 'Versión obsoleta.'}).status_code, 409)
        self.post(f"/justificaciones/{j2['id']}/revisiones", {'revisor': 'Docente', 'decision': 'aprobada', 'observaciones': 'Contraste de clasificación revisado.'})
        history = self.client.get(f"/api/evidencias/{e['id']}/historial").json()
        self.assertEqual(len(history['justificaciones']), 2)
        self.assertEqual(len(history['revisiones']), 2)
        self.post(f"/justificaciones/{j2['id']}/revisiones", {'revisor': 'Estudiante', 'decision': 'observada', 'observaciones': 'Autoevaluación de la justificación.', 'autoevaluacion': True})
        self.assertEqual(self.client.get('/api/evidencias').json()[0]['autoevaluacion'], 1)
        c2 = self.post(f"/evidencias/{e['id']}/clasificaciones", {'categoria_id': 'A07'})
        self.assertNotEqual(c2['id'], c['id'])
        self.assertEqual(self.client.post(f"/api/clasificaciones/{c['id']}/justificaciones", json=payload).status_code, 409)

    def test_validation_fk_and_origin(self):
        self.assertEqual(self.client.post('/api/evidencias', json={'texto': ' ', 'activo': ' ', 'perfil': ' '}).status_code, 422)
        self.assertEqual(self.client.get('/api/evidencias/999/hipotesis').status_code, 404)
        e = self.evidence()
        self.assertEqual(self.client.post(f"/api/evidencias/{e['id']}/clasificaciones", json={'categoria_id': 'A99'}).status_code, 422)
        self.assertEqual(self.client.post('/api/evidencias', json={'texto': 'Datos de prueba suficientemente largos', 'activo': 'Prueba', 'perfil': 'Prueba'}, headers={'Origin': 'https://example.com'}).status_code, 403)
        with connection() as db:
            with self.assertRaises(sqlite3.IntegrityError):
                db.execute("INSERT INTO clasificaciones (evidencia_id,categoria_id,origen,motor_version,explicacion) VALUES (999,'A01','manual','test','Prueba')")
