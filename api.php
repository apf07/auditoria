<?php
declare(strict_types=1);
// Adaptador XAMPP del contrato REST de backend/main.py. Comparte esquema y base SQLite.
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $status,$value):void {http_response_code($status);echo json_encode($value,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function requiredRow(PDO $db,string $table,int $id):array {
    $q=$db->prepare("SELECT * FROM $table WHERE id=?");$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)reply(404,['detail'=>'Registro no encontrado.']);return $row;
}
function currentClassification(PDO $db,array $c):void {
    $e=requiredRow($db,'evidencias',(int)$c['evidencia_id']);if($e['archived_at']!==null)reply(409,['detail'=>'Restaura la evidencia antes de modificarla.']);
    $q=$db->prepare('SELECT MAX(id) FROM clasificaciones WHERE evidencia_id=?');$q->execute([$c['evidencia_id']]);
    if((int)$q->fetchColumn()!==(int)$c['id'])reply(409,['detail'=>'La clasificación fue reemplazada. Recarga la evidencia.']);
}
function replyScanError(string $tmp,Throwable $error):void {if(is_file($tmp))unlink($tmp);reply(422,['detail'=>$error instanceof InvalidArgumentException?$error->getMessage():'No se pudo leer el ZIP. Usa un ZIP sin contraseña y con código fuente.']);}
function hypotheses(string $text,array $catalog):array {
    $normalized=mb_strtolower($text);$normalized=strtr($normalized,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
    $rules=[
      'A01'=>'otro ciudadano|otro usuario|sin permiso|cambiando el id|expediente.{0,30}ajeno',
      'A02'=>'por defecto|no endurecid|configuracion insegura|cabecera',
      'A03'=>'libreria|dependencia|inventario de componentes|cadena de suministro',
      'A04'=>'cifrado|criptograf|\bmd5\b|\bsha1\b','A05'=>'inyeccion|datos e instrucciones|sin separacion|consulta sql',
      'A06'=>'doble validacion|regla de negocio|politica de negocio','A07'=>'contrasena|autenticacion|inicio de sesion|recuperacion de cuenta',
      'A08'=>'actualizacion|integridad|firma digital','A09'=>'registro|alerta|reconstruir|trazabilidad','A10'=>'excepcion|condicion excepcional|manejo de errores'
    ];$candidates=[];
    foreach($catalog as $c)if(preg_match('~'.$rules[$c['id']].'~u',$normalized,$m))$candidates[]=array_merge($c,['regla'=>$c['id'],'motivo'=>'La regla '.$c['id'].' encontró la señal textual «'.$m[0].'». No confirma explotación.']);
    return ['motor_version'=>'reglas-2025-v1','candidatas'=>$candidates,'categoria_sugerida'=>count($candidates)===1?$candidates[0]['id']:null,'explicacion'=>count($candidates)===1?'Hipótesis única de estas reglas: requiere contraste humano.':(count($candidates)?'Hay hipótesis alternativas; compara sus causas antes de elegir.':'Evidencia insuficiente para estas reglas. Solicita más datos o clasifica manualmente.')];
}
function fields(array $input,array $spec):array {
    if(array_diff(array_keys($input),array_keys($spec)))reply(422,['detail'=>'La solicitud contiene campos desconocidos.']);
    $result=[];
    foreach($spec as $key=>[$min,$max]){
        $value=$input[$key]??null;
        if(!is_string($value)||mb_strlen(trim($value))<$min||mb_strlen(trim($value))>$max)reply(422,['detail'=>"Revisa el campo $key: longitud permitida $min a $max caracteres."]);
        $result[$key]=trim($value);
    }return $result;
}
function insertRow(PDO $db,string $table,array $values):array {
    $columns=implode(',',array_keys($values));$parameters=implode(',',array_fill(0,count($values),'?'));
    $q=$db->prepare("INSERT INTO $table ($columns) VALUES ($parameters)");$q->execute(array_values($values));return requiredRow($db,$table,(int)$db->lastInsertId());
}
try {
    require_once __DIR__.'/deployment/access.php';
    requireDeploymentAccess();
    $path=$_GET['path']??'/health';$method=$_SERVER['REQUEST_METHOD'];
    if(!is_string($path))reply(400,['detail'=>'Ruta inválida.']);
    if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');reply(405,['detail'=>'Método no permitido.']);}
    if($method==='POST'){
        if(isset($_SERVER['HTTP_ORIGIN'])){
            $origin=parse_url($_SERVER['HTTP_ORIGIN']);
            $allowedOrigins=array_filter(array_map('trim',explode(',',getenv('AUDITORIA_ALLOWED_ORIGINS')?:'http://localhost:8080,http://127.0.0.1:8080')));
            if(!in_array($_SERVER['HTTP_ORIGIN'],$allowedOrigins,true))reply(403,['detail'=>'Origen no autorizado.']);
        }
        if(preg_match('~^/sistemas/[1-9][0-9]*/analizar-zip$~',$path)){
            if(($_SERVER['HTTP_X_AUDITORIA']??'')!=='1')reply(403,['detail'=>'Solicitud inválida. Usa el formulario del sistema.']);
            $input=[];
        }else{
        if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)reply(415,['detail'=>'Se requiere application/json.']);
        $raw=file_get_contents('php://input',false,null,0,100001);
        if(strlen($raw)>100000)reply(413,['detail'=>'Solicitud demasiado grande.']);
        try{$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){reply(400,['detail'=>'JSON inválido.']);}
        if(!is_array($input))reply(422,['detail'=>'Datos inválidos.']);
        }
    }
    require_once __DIR__.'/database/connection.php';
    $db=openDatabase();
    if(databaseDriver()==='sqlite'){
        $db->exec(file_get_contents(__DIR__.'/database/schema.sql'));
        require __DIR__.'/database/migrations.php';seedCatalog($db);
    }
    $catalog=$db->query('SELECT * FROM categorias_owasp ORDER BY id')->fetchAll();
    if($method==='GET' && $path==='/health')reply(200,['status'=>'ok','database'=>databaseDriver(),'mode'=>getenv('AUDITORIA_ENV')==='production'?'Prototipo privado':'Prototipo local']);
    if($method==='GET' && $path==='/categorias')reply(200,$catalog);
    if($method==='GET' && in_array($path,['/evidencias','/papelera','/exportar.csv'],true)){
        $matrixSql=file_get_contents(__DIR__.'/database/matrix.sql');
        if($path==='/papelera')$matrixSql=str_replace('e.archived_at IS NULL','e.archived_at IS NOT NULL',$matrixSql);
        $rows=$db->query($matrixSql)->fetchAll();
        if(in_array($path,['/evidencias','/papelera'],true))reply(200,$rows);
        if(isset($_GET['sistema_id'])){$filter=$_GET['sistema_id'];$rows=array_values(array_filter($rows,fn($row)=>$filter==='legacy'?$row['sistema_id']===null:(int)$row['sistema_id']===(int)$filter));}
        if(isset($_GET['analisis_id'])){$analysisFilter=(int)$_GET['analisis_id'];$rows=array_values(array_filter($rows,fn($row)=>(int)$row['analisis_id']===$analysisFilter));}
        if(($_GET['origen']??'')==='manual')$rows=array_values(array_filter($rows,fn($row)=>$row['analisis_id']===null));
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="matriz-revisada.csv"');
        $columns=['sistema','analisis_id','archivo_zip','ubicacion','recomendacion','evidencia_id','evidencia','activo','perfil','categoria_id','categoria','justificacion','contraste','impacto','dato_faltante','politica_institucional','referencia_asvs','prueba_wstg','autor','revisor','autoevaluacion','decision','observaciones','fecha_revision'];
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$columns,';','"','');
        foreach($rows as $row)if($row['revision_id']){ $values=[];foreach($columns as $key){$value=(string)($row[$key]??'');$values[]=preg_match('/^\s*[=+@-]/u',$value)?"'".$value:$value;}fputcsv($out,$values,';','"','');}fclose($out);exit;
    }
    if($method==='GET' && $path==='/sistemas')reply(200,$db->query('SELECT * FROM sistemas ORDER BY id DESC')->fetchAll());
    if($method==='POST' && $path==='/sistemas')reply(201,insertRow($db,'sistemas',fields($input,['nombre'=>[1,200]])));
    if($method==='POST' && preg_match('~^/evidencias/([1-9][0-9]*)/(archivar|restaurar|sistema)$~',$path,$m)){
        $e=requiredRow($db,'evidencias',(int)$m[1]);
        if($m[2]==='sistema'){
            if(!isset($input['sistema_id'])||!is_int($input['sistema_id']))reply(422,['detail'=>'Selecciona un sistema.']);
            requiredRow($db,'sistemas',$input['sistema_id']);$q=$db->prepare('UPDATE evidencias SET sistema_id=? WHERE id=?');$q->execute([$input['sistema_id'],$e['id']]);
        }else{
            $q=$db->prepare('UPDATE evidencias SET archived_at=? WHERE id=?');$q->execute([$m[2]==='archivar'?gmdate('Y-m-d\TH:i:s\Z'):null,$e['id']]);
        }
        reply(200,requiredRow($db,'evidencias',$e['id']));
    }
    if($method==='GET' && preg_match('~^/sistemas/([1-9][0-9]*)/analisis$~',$path,$m)){
        requiredRow($db,'sistemas',(int)$m[1]);$q=$db->prepare('SELECT * FROM analisis_zip WHERE sistema_id=? ORDER BY id DESC');$q->execute([(int)$m[1]]);$scans=$q->fetchAll();foreach($scans as &$scan)$scan['resultado']=json_decode($scan['resultado'],true);reply(200,$scans);
    }
    if($method==='POST' && preg_match('~^/sistemas/([1-9][0-9]*)/analizar-url$~',$path,$m)){
        $system=requiredRow($db,'sistemas',(int)$m[1]);
        $values=fields($input,['url'=>[8,2048],'autorizacion'=>[1,1]]);
        if($values['autorizacion']!=='1')reply(422,['detail'=>'Confirma que tienes autorización para revisar este sistema.']);
        require __DIR__.'/url_scanner.php';
        try{$result=scanPublicUrl($values['url']);}catch(InvalidArgumentException $e){reply(422,['detail'=>$e->getMessage()]);}
        $db->beginTransaction();
        $analysis=insertRow($db,'analisis_zip',['sistema_id'=>$system['id'],'nombre_archivo'=>$result['final_url'],'resultado'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);
        foreach($result['findings'] as $finding){
            $text=$finding['title'].'. URL: '.$finding['location'].'. Señal potencial de la respuesta web. Recomendación: '.$finding['recommendation'];
            $e=insertRow($db,'evidencias',['sistema_id'=>$system['id'],'analisis_id'=>$analysis['id'],'ubicacion'=>$finding['location'],'recomendacion'=>$finding['recommendation'],'texto'=>$text,'activo'=>mb_substr($finding['location'],0,200),'perfil'=>'Visitante sin iniciar sesión']);
            insertRow($db,'clasificaciones',['evidencia_id'=>$e['id'],'categoria_id'=>$finding['category'],'origen'=>'reglas','motor_version'=>'url-pasivo-v1','explicacion'=>'Observación de la respuesta web en '.$finding['location'].'. Pendiente de contraste humano.']);
        }
        $db->commit();reply(201,['id'=>$analysis['id'],'resultado'=>$result,'nombre_archivo'=>$result['final_url'],'duplicado'=>false]);
    }
    if($method==='POST' && preg_match('~^/sistemas/([1-9][0-9]*)/analizar-zip$~',$path,$m)){
        $system=requiredRow($db,'sistemas',(int)$m[1]);$upload=$_FILES['project']??null;
        if(!$upload||$upload['error']!==UPLOAD_ERR_OK)reply(422,['detail'=>'Selecciona un ZIP válido; revisa el límite de carga de PHP.']);
        if($upload['size']>20*1024*1024||strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION))!=='zip')reply(422,['detail'=>'El archivo debe ser .zip de hasta 20 MB.']);
        $fingerprint=hash_file('sha256',$upload['tmp_name']);
        $duplicate=$db->prepare('SELECT * FROM analisis_zip WHERE sistema_id=? AND content_sha256=?');$duplicate->execute([$system['id'],$fingerprint]);
        if($existing=$duplicate->fetch())reply(200,['id'=>$existing['id'],'resultado'=>json_decode($existing['resultado'],true),'nombre_archivo'=>$existing['nombre_archivo'],'duplicado'=>true,'content_sha256'=>$fingerprint]);
        require __DIR__.'/scanner.php';
        $tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'audit_'.bin2hex(random_bytes(16)).'.zip';
        if(!move_uploaded_file($upload['tmp_name'],$tmp))throw new RuntimeException('No se pudo recibir el ZIP.');
        try{$result=scanProjectZip($tmp);}catch(Throwable $e){replyScanError($tmp,$e);}finally{if(is_file($tmp))unlink($tmp);}
        $filename=mb_substr(basename(str_replace('\\','/',$upload['name'])),0,255);
        $db->beginTransaction();
        $analysis=insertRow($db,'analisis_zip',['sistema_id'=>$system['id'],'nombre_archivo'=>$filename,'content_sha256'=>$fingerprint,'resultado'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);
        foreach($result['findings'] as $finding){
            $text=$finding['title'].'. Ubicación: '.$finding['location'].'. Señal estática potencial; confirmar uso y controles. Recomendación: '.$finding['recommendation'];
            $e=insertRow($db,'evidencias',['sistema_id'=>$system['id'],'analisis_id'=>$analysis['id'],'ubicacion'=>$finding['location'],'recomendacion'=>$finding['recommendation'],'texto'=>$text,'activo'=>mb_substr($finding['location'],0,200),'perfil'=>'Por determinar en revisión']);
            insertRow($db,'clasificaciones',['evidencia_id'=>$e['id'],'categoria_id'=>$finding['category'],'origen'=>'reglas','motor_version'=>'zip-reglas-v1','explicacion'=>'Coincidencia estática en '.$finding['location'].'. Pendiente de contraste humano.']);
        }
        $db->commit();reply(201,['id'=>$analysis['id'],'resultado'=>$result,'nombre_archivo'=>$filename,'content_sha256'=>$fingerprint,'duplicado'=>false]);
    }
    if($method==='POST' && $path==='/evidencias'){
        $systemId=$input['sistema_id']??null;unset($input['sistema_id']);
        if($systemId!==null){if(!is_int($systemId))reply(422,['detail'=>'Sistema inválido.']);requiredRow($db,'sistemas',$systemId);}
        $values=fields($input,['texto'=>[10,10000],'activo'=>[1,200],'perfil'=>[1,200]]);reply(201,insertRow($db,'evidencias',['sistema_id'=>$systemId,...$values]));
    }
    if($method==='GET' && preg_match('~^/evidencias/([1-9][0-9]*)/hipotesis$~',$path,$m)){
        $e=requiredRow($db,'evidencias',(int)$m[1]);reply(200,hypotheses($e['texto'],$catalog));
    }
    if($method==='POST' && preg_match('~^/evidencias/([1-9][0-9]*)/clasificaciones$~',$path,$m)){
        $v=fields($input,['categoria_id'=>[3,3]]);$e=requiredRow($db,'evidencias',(int)$m[1]);
        if($e['archived_at']!==null)reply(409,['detail'=>'Restaura la evidencia antes de clasificarla.']);
        if(!in_array($v['categoria_id'],array_column($catalog,'id'),true))reply(422,['detail'=>'Categoría desconocida.']);
        $hyp=hypotheses($e['texto'],$catalog);$candidate=null;foreach($hyp['candidatas'] as $c)if($c['id']===$v['categoria_id'])$candidate=$c;
        reply(201,insertRow($db,'clasificaciones',['evidencia_id'=>$e['id'],'categoria_id'=>$v['categoria_id'],'origen'=>$candidate?'reglas':'manual','motor_version'=>'reglas-2025-v1','explicacion'=>$candidate['motivo']??'Categoría seleccionada manualmente, pendiente de sustento y contraste.']));
    }
    if($method==='POST' && preg_match('~^/clasificaciones/([1-9][0-9]*)/justificaciones$~',$path,$m)){
        $v=fields($input,['autor'=>[1,120],'texto'=>[10,10000],'contraste'=>[10,10000],'impacto'=>[3,5000],'dato_faltante'=>[3,5000],'politica_institucional'=>[3,5000],'referencia_asvs'=>[3,5000],'prueba_wstg'=>[3,5000]]);
        $c=requiredRow($db,'clasificaciones',(int)$m[1]);currentClassification($db,$c);
        reply(201,insertRow($db,'justificaciones',['clasificacion_id'=>$c['id'],...$v]));
    }
    if($method==='POST' && preg_match('~^/justificaciones/([1-9][0-9]*)/revisiones$~',$path,$m)){
        $selfReview=$input['autoevaluacion']??false;unset($input['autoevaluacion']);if(!is_bool($selfReview))reply(422,['detail'=>'Indicador de autoevaluación inválido.']);
        $v=fields($input,['revisor'=>[1,120],'decision'=>[8,9],'observaciones'=>[5,5000]]);
        if(!in_array($v['decision'],['aprobada','observada'],true))reply(422,['detail'=>'Decisión inválida.']);
        $j=requiredRow($db,'justificaciones',(int)$m[1]);$c=requiredRow($db,'clasificaciones',(int)$j['clasificacion_id']);currentClassification($db,$c);
        $q=$db->prepare('SELECT MAX(id) FROM justificaciones WHERE clasificacion_id=?');$q->execute([$c['id']]);
        if((int)$q->fetchColumn()!==(int)$j['id'])reply(409,['detail'=>'La justificación fue reemplazada. Revisa la versión actual.']);
        if(mb_strtolower($j['autor'])===mb_strtolower($v['revisor'])&&!$selfReview)reply(422,['detail'=>'Si eres autor y revisor, marca la opción de autoevaluación.']);
        reply(201,insertRow($db,'revisiones',['justificacion_id'=>$j['id'],'autoevaluacion'=>$selfReview?1:0,...$v]));
    }
    if($method==='GET' && preg_match('~^/evidencias/([1-9][0-9]*)/historial$~',$path,$m)){
        $e=requiredRow($db,'evidencias',(int)$m[1]);$data=[];
        foreach(['clasificaciones'=>'SELECT * FROM clasificaciones WHERE evidencia_id=? ORDER BY id','justificaciones'=>'SELECT j.* FROM justificaciones j JOIN clasificaciones c ON c.id=j.clasificacion_id WHERE c.evidencia_id=? ORDER BY j.id','revisiones'=>'SELECT r.* FROM revisiones r JOIN justificaciones j ON j.id=r.justificacion_id JOIN clasificaciones c ON c.id=j.clasificacion_id WHERE c.evidencia_id=? ORDER BY r.id'] as $key=>$sql){$q=$db->prepare($sql);$q->execute([$e['id']]);$data[$key]=$q->fetchAll();}reply(200,$data);
    }
    reply(404,['detail'=>'Ruta no encontrada.']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('AUDITORIA: '.$e->getMessage());reply(503,['detail'=>'No se pudo completar la operación. Revisa el servidor local.']);}
