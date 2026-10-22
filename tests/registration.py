"""Registro real con dos cuentas ficticias; comprueba aislamiento y elimina solo sus datos."""
import http.cookiejar,json,re,subprocess,urllib.request,urllib.error,urllib.parse,uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
BASE='http://localhost:8080/AUDITORIA/'
def browser():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(client,path,data=None):
    req=urllib.request.Request(BASE+path,data=None if data is None else urllib.parse.urlencode(data).encode())
    try:r=client.open(req,timeout=15)
    except urllib.error.HTTPError as e:r=e
    return r.status,r.read().decode('utf-8-sig')
def api(client,route,data=None,**params):
    headers={} if data is None else {'Content-Type':'application/json','X-CSRF-Token':params.pop('csrf')}
    req=urllib.request.Request(BASE+'api.php?'+urllib.parse.urlencode({'route':route,**params}),data=None if data is None else json.dumps(data).encode(),headers=headers)
    try:r=client.open(req,timeout=15)
    except urllib.error.HTTPError as e:r=e
    raw=r.read().decode('utf-8-sig');return r.status,raw if route=='export' else json.loads(raw)
def token(html):return re.search(r'name="csrf" value="([a-f0-9]+)"',html).group(1)
users=['QA-'+uuid.uuid4().hex for _ in range(2)]
password=uuid.uuid4().hex
finding=None
try:
    a,b=browser(),browser()
    _,html=get(a,'register.php');csrf=token(html)
    assert 'Iniciar sesión' in html and 'Registrarse' in html
    payload=dict(csrf=csrf,username=users[0],display_name='Cuenta temporal de prueba',password=password,confirm_password=password,rol='administrador')
    assert get(a,'register.php',{**payload,'csrf':'invalid'})[0]==403
    assert get(a,'register.php',{**payload,'confirm_password':'different'})[0]==422
    status,html=get(a,'register.php',payload);assert status==200 and 'logout-form' in html
    info=api(a,'bootstrap')[1];assert info['usuario']['rol']=='auditor';project=info['proyectos'][0]['id'];csrf_a=info['csrf']
    c=browser();_,html=get(c,'register.php');assert get(c,'register.php',{**payload,'csrf':token(html)})[0]==409
    _,html=get(b,'register.php');status,html=get(b,'register.php',{**payload,'csrf':token(html),'username':users[1],'rol':'docente'});assert status==200 and 'logout-form' in html
    info_b=api(b,'bootstrap')[1];assert info_b['usuario']['rol']=='auditor';csrf_b=info_b['csrf']
    rows=api(a,'matrix',project=project)[1];seed=next(r for r in rows if r['origen']=='revision_zip')
    test=dict(hallazgo_id=seed['id'],version_previa=seed['prueba_id'] or 0,autor='Otra identidad manipulada',entorno='Copia ficticia para prueba automatizada',observado='Resultado ficticio para verificar aislamiento entre usuarios.',evidencia_prueba='Evidencia ficticia sin datos reales.',resultado='reproducido',categoria_id='A01',justificacion='Justificación ficticia sobre permisos de acceso.',contraste='Contraste ficticio con autenticación.',impacto='Impacto ficticio de la comprobación.',politica='No proporcionada; prueba ficticia.',referencia_asvs='Referencia por contrastar.',referencia_wstg='Prueba por contrastar.',realizada=True)
    status,t=api(a,'test',test,csrf=csrf_a);assert status==201,t
    observed=next(r for r in api(a,'matrix',project=project)[1] if r['id']==seed['id']);assert observed['autor']=='Cuenta temporal de prueba'
    other=next(r for r in api(b,'matrix',project=project)[1] if r['id']==seed['id']);assert other['prueba_id'] is None
    assert api(b,'history',id=seed['id'])[1]['pruebas']==[]
    assert api(b,'review',dict(prueba_id=t['id'],revisor='Docente',decision='confirmado',comentario='Intento de revisión no autorizada de otra cuenta.',autoevaluacion=True),csrf=csrf_b)[0]==403
    fixture=dict(proyecto_id=project,titulo=users[0],activo='Activo ficticio',perfil='Perfil de prueba',ubicacion='fixture.php:1',evidencia='Evidencia privada creada para verificar aislamiento.',impacto='Impacto ficticio sin datos reales.',recomendacion='Recomendación ficticia de validación.',pasos='Ejecutar comprobación ficticia sin acceder a sistemas externos.',esperado='Resultado ficticio esperado.',referencia_asvs='Pendiente de contrastar.',referencia_wstg='Pendiente de contrastar.',prioridad='media',categoria_id='A01')
    status,f=api(a,'finding',fixture,csrf=csrf_a);assert status==201,f;finding=f['id']
    assert all(r['id']!=finding for r in api(b,'matrix',project=project)[1])
    assert api(b,'history',id=finding)[0]==403
    assert api(b,'test',{**test,'hallazgo_id':finding},csrf=csrf_b)[0]==403
    assert users[0] not in api(b,'export',project=project,scope='all')[1]
    print('OK: registro, contraseña, CSRF, duplicados, rol fijo auditor, firma autenticada, privacidad de matriz/historial/CSV y bloqueo de acceso cruzado.')
finally:
    if finding is not None:subprocess.run([r'C:\xampp\php\php.exe',str(ROOT/'tests/cleanup.php'),str(finding),users[0]],cwd=ROOT,check=True)
    for username in users:
        subprocess.run([r'C:\xampp\php\php.exe',str(ROOT/'tests/auth-fixture.php')],input=json.dumps({'action':'cleanup','username':username}),text=True,capture_output=True,cwd=ROOT,check=True)
