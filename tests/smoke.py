"""Flujo HTTP real con una ficha ficticia propia; no modifica las seis hipótesis."""
import csv
import io
import json
import http.cookiejar
import subprocess
import urllib.request
import urllib.error
import urllib.parse
import uuid
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
BASE='http://localhost:8080/AUDITORIA/'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
csrf=''
def request(route,data=None,**params):
    headers={}
    if data is not None:headers={'Content-Type':'application/json','X-CSRF-Token':csrf}
    req=urllib.request.Request(BASE+'api.php?'+urllib.parse.urlencode({'route':route,**params}),data=None if data is None else json.dumps(data).encode(),headers=headers)
    try:r=client.open(req,timeout=15)
    except urllib.error.HTTPError as e:r=e
    raw=r.read().decode('utf-8-sig')
    return r.status,raw if route=='export' else json.loads(raw)

marker='QA-'+uuid.uuid4().hex
finding=None
try:
    status,b=request('bootstrap');assert status==200,b
    csrf=b['csrf'];project=b['proyectos'][0]['id'];assert len(b['categorias'])==10
    status,initial=request('matrix',project=project);assert status==200
    fixture=dict(proyecto_id=project,titulo=marker,activo='Activo ficticio',perfil='Perfil de prueba',ubicacion='fixture.php:1',evidencia='Evidencia ficticia creada solo para probar el prototipo.',impacto='Impacto ficticio sin datos reales.',recomendacion='Recomendación ficticia de validación.',pasos='Ejecutar comprobación ficticia sin acceder a sistemas externos.',esperado='Resultado ficticio esperado.',referencia_asvs='Pendiente de contrastar.',referencia_wstg='Pendiente de contrastar.',prioridad='media',categoria_id='A01')
    real_csrf=csrf;csrf='invalid';assert request('finding',fixture)[0]==403;csrf=real_csrf
    status,created=request('finding',fixture);assert status==201,created;finding=created['id']
    payload=dict(hallazgo_id=finding,version_previa=0,autor='Autor QA',entorno='Entorno ficticio de prueba local',observado='Se documenta un resultado ficticio para verificar el guardado.',evidencia_prueba='=HYPERLINK("QA") Evidencia ficticia y anonimizada.',resultado='no_reproducido',categoria_id='A01',justificacion='Clasificación ficticia para verificar validación y persistencia.',contraste='Contraste ficticio entre autorización y autenticación.',impacto='Impacto ficticio de la prueba.',politica='No proporcionada, caso de prueba.',referencia_asvs='Referencia pendiente de contraste.',referencia_wstg='Prueba pendiente de contraste.',realizada=True)
    status,t=request('test',payload);assert status==201,t
    review=dict(prueba_id=t['id'],revisor='Autor QA',decision='confirmado',comentario='Revisión ficticia creada para comprobar las restricciones.',autoevaluacion=True)
    assert request('review',review)[0]==422
    review['decision']='descartado';review['autoevaluacion']=False;assert request('review',review)[0]==422
    review['autoevaluacion']=True;status,r=request('review',review);assert status==201,r
    status,exported=request('export',project=project,scope='reviewed');assert status==200 and marker in exported and "'=HYPERLINK" in exported
    assert request('test',payload)[0]==409
    payload['version_previa']=t['id'];payload['resultado']='inconcluso';status,t2=request('test',payload);assert status==201,t2
    row=next(r for r in request('matrix',project=project)[1] if r['id']==finding);assert row['estado']=='en_revision' and row['revision_id'] is None
    assert marker not in request('export',project=project,scope='reviewed')[1]
    assert request('review',review)[0]==409
    history=request('history',id=finding)[1];assert len(history['pruebas'])==2 and len(history['revisiones'])==1
    assert marker in request('export',project=project,scope='all')[1]
    for path in ['database/seed.json','database/schema.sql','app/db.php','scripts/setup.php','config.example.php']:
        try:r=client.open(BASE+path,timeout=10)
        except urllib.error.HTTPError as e:r=e
        assert r.status==403,(path,r.status)
    print('OK: MySQL, CSRF, evidencia manual, prueba, restricciones de revisión, versiones, historial, exportación y archivos privados.')
finally:
    if finding is not None:
        subprocess.run([r'C:\xampp\php\php.exe',str(ROOT/'tests/cleanup.php'),str(finding),marker],cwd=ROOT,check=True)
        print('Ficha ficticia propia eliminada; hipótesis y respuestas del usuario conservadas.')
