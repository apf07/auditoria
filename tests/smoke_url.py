"""Revisión URL con una página ficticia local; no consulta servicios externos."""
import json
import subprocess
import urllib.request
import urllib.parse
import urllib.error
import uuid
from pathlib import Path

root=Path(__file__).resolve().parents[1]
base='http://localhost:8080/AUDITORIA/'
marker='URL-TEST-'+uuid.uuid4().hex
fixture=root/(marker+'.html')
system_id=None

def call(path,data=None):
    req=urllib.request.Request(base+'api.php?path='+urllib.parse.quote(path),data=None if data is None else json.dumps(data).encode(),headers={} if data is None else {'Content-Type':'application/json'})
    try: response=urllib.request.urlopen(req,timeout=20)
    except urllib.error.HTTPError as error: response=error
    return response.code,json.load(response)

try:
    fixture.write_text('<!doctype html><html lang="es"><title>Prueba de auditoría URL</title><p>Datos ficticios</p></html>',encoding='utf-8')
    status,system=call('/sistemas',{'nombre':marker});assert status==201,system
    system_id=system['id']
    route=f'/sistemas/{system_id}/analizar-url'
    url=base+fixture.name
    assert call(route,{'url':url,'autorizacion':'0'})[0]==422
    assert call(route,{'url':'http://169.254.169.254/','autorizacion':'1'})[0]==422
    assert call(route,{'url':'file:///C:/Windows/win.ini','autorizacion':'1'})[0]==422
    status,result=call(route,{'url':url+'?token=no-guardar-este-parametro','autorizacion':'1'})
    assert status==201,result
    assert result['resultado']['origin']=='url'
    assert result['resultado']['http_status']==200
    assert result['resultado']['findings']
    assert 'no-guardar-este-parametro' not in json.dumps(result)
    status,rows=call('/evidencias');assert status==200
    associated=[r for r in rows if r['analisis_id']==result['id']]
    assert len(associated)==len(result['resultado']['findings'])
    assert all(r['ubicacion']==url for r in associated)
    assert call(route,{'url':base+marker+'-missing.html','autorizacion':'1'})[0]==422
    assert len(call(f'/sistemas/{system_id}/analisis')[1])==1
    print('OK: URL local, autorización, redes restringidas, protocolos, hallazgos, matriz y omisión de parámetros sensibles.')
finally:
    fixture.unlink(missing_ok=True)
    if system_id:
        php="""$d=new PDO('sqlite:data/auditoria.sqlite3');$d->exec('PRAGMA foreign_keys=ON');$q=$d->prepare('SELECT id FROM sistemas WHERE id=? AND nombre=?');$q->execute([(int)$argv[1],$argv[2]]);if($q->fetchColumn()){$d->beginTransaction();$id=(int)$argv[1];$d->exec("DELETE FROM clasificaciones WHERE evidencia_id IN(SELECT id FROM evidencias WHERE sistema_id=$id)");$d->exec("DELETE FROM evidencias WHERE sistema_id=$id");$d->exec("DELETE FROM analisis_zip WHERE sistema_id=$id");$d->exec("DELETE FROM sistemas WHERE id=$id");$d->commit();}"""
        subprocess.run([r'C:\xampp\php\php.exe','-r',php,str(system_id),marker],cwd=root,check=True)
        print('Página y datos temporales eliminados; datos del usuario conservados.')
