"""ZIP, asociación y papelera en XAMPP. Solo limpia su sistema temporal."""
import io
import json
import subprocess
import urllib.error
import urllib.request
import urllib.parse
import uuid
import zipfile
from pathlib import Path

root=Path(__file__).resolve().parents[1]
base='http://localhost:8080/AUDITORIA/api.php?path='
marker='PRUEBA-ZIP-'+uuid.uuid4().hex
system_id=None

def call(path,data=None):
    req=urllib.request.Request(base+urllib.parse.quote(path),data=None if data is None else json.dumps(data).encode(),headers={} if data is None else {'Content-Type':'application/json'})
    with urllib.request.urlopen(req,timeout=15) as response:
        return json.load(response)

try:
    system=call('/sistemas',{'nombre':marker});system_id=system['id']
    source=io.BytesIO()
    with zipfile.ZipFile(source,'w',zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('demo/login.php','<?php\necho $_GET["name"];\nmd5($password);\n')
        archive.writestr('vendor/omit.php','<?php echo $_GET["ignored"];')
    boundary=uuid.uuid4().hex
    body=f'--{boundary}\r\nContent-Disposition: form-data; name="project"; filename="demo.zip"\r\nContent-Type: application/zip\r\n\r\n'.encode()+source.getvalue()+f'\r\n--{boundary}--\r\n'.encode()
    request=urllib.request.Request(base+urllib.parse.quote(f'/sistemas/{system_id}/analizar-zip'),data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-Auditoria':'1'})
    result=json.load(urllib.request.urlopen(request,timeout=15))
    assert len(result['resultado']['findings'])==2,result
    assert result['resultado']['skipped']==1
    rows=[r for r in call('/evidencias') if r['sistema_id']==system_id]
    assert len(rows)==2 and all(r['sistema']==marker for r in rows)
    assert all(r['analisis_id']==result['id'] for r in rows)
    assert all(r['archivo_zip']=='demo.zip' and r['ubicacion'] and r['recomendacion'] for r in rows)
    repeated=json.load(urllib.request.urlopen(request,timeout=15))
    assert repeated['duplicado'] is True
    assert repeated['id']==result['id']
    assert len([r for r in call('/evidencias') if r['sistema_id']==system_id])==2

    assert any('demo/login.php:2' in r['evidencia'] for r in rows)
    identifier=rows[0]['evidencia_id']
    call(f'/evidencias/{identifier}/archivar',{})
    assert not any(r['evidencia_id']==identifier for r in call('/evidencias'))
    assert any(r['evidencia_id']==identifier for r in call('/papelera'))
    try:
        call(f'/evidencias/{identifier}/clasificaciones',{'categoria_id':'A01'})
        raise AssertionError('No debe clasificar una evidencia archivada')
    except urllib.error.HTTPError as error:
        assert error.code==409

    call(f'/evidencias/{identifier}/restaurar',{})
    assert any(r['evidencia_id']==identifier for r in call('/evidencias'))
    assert len(call(f'/sistemas/{system_id}/analisis'))==1
    print('OK: sistema, ZIP, huella y deduplicación, archivo/línea, matriz, bloqueo de cambios en papelera y restauración.')
finally:
    if system_id:
        php="""$d=new PDO('sqlite:data/auditoria.sqlite3');$d->exec('PRAGMA foreign_keys=ON');$q=$d->prepare('SELECT id FROM sistemas WHERE id=? AND nombre=?');$q->execute([(int)$argv[1],$argv[2]]);if($q->fetchColumn()){$d->beginTransaction();$id=(int)$argv[1];$d->exec("DELETE FROM revisiones WHERE justificacion_id IN(SELECT j.id FROM justificaciones j JOIN clasificaciones c ON c.id=j.clasificacion_id JOIN evidencias e ON e.id=c.evidencia_id WHERE e.sistema_id=$id)");$d->exec("DELETE FROM justificaciones WHERE clasificacion_id IN(SELECT c.id FROM clasificaciones c JOIN evidencias e ON e.id=c.evidencia_id WHERE e.sistema_id=$id)");$d->exec("DELETE FROM clasificaciones WHERE evidencia_id IN(SELECT id FROM evidencias WHERE sistema_id=$id)");$d->exec("DELETE FROM evidencias WHERE sistema_id=$id");$d->exec("DELETE FROM analisis_zip WHERE sistema_id=$id");$d->exec("DELETE FROM sistemas WHERE id=$id");$d->commit();}"""
        subprocess.run([r'C:\xampp\php\php.exe','-r',php,str(system_id),marker],cwd=root,check=True)
        print('Sistema y evidencias de prueba eliminados; datos del usuario conservados.')
