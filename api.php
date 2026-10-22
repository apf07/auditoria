<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/app/auth.php';requireLogin(true);
function respond(int $status,$data):never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function textField(array $in,string $key,int $min=1,int $max=10000):string {
    $s=$in[$key]??null;if(!is_string($s)||mb_strlen(trim($s))<$min||mb_strlen(trim($s))>$max)respond(422,['error'=>"Revisa el campo $key: entre $min y $max caracteres."]);return trim($s);
}
function record(PDO $db,string $table,int $id):array {$q=$db->prepare("SELECT * FROM $table WHERE id=?");$q->execute([$id]);$r=$q->fetch();if(!$r)respond(404,['error'=>'Registro no encontrado.']);return $r;}
function addRecord(PDO $db,string $table,array $data):int {$q=$db->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')');$q->execute(array_values($data));return (int)$db->lastInsertId();}
function categoryExists(PDO $db,string $category):void {$q=$db->prepare('SELECT id FROM categorias WHERE id=?');$q->execute([$category]);if(!$q->fetch())respond(422,['error'=>'Categoría OWASP inválida.']);}
function currentTest(PDO $db,int $finding):int {$q=$db->prepare('SELECT MAX(id) FROM pruebas WHERE hallazgo_id=?');$q->execute([$finding]);return (int)$q->fetchColumn();}
require_once __DIR__.'/app/db.php';
try{
    $db=database();$route=$_GET['route']??'bootstrap';$method=$_SERVER['REQUEST_METHOD'];
    if(!is_string($route))respond(400,['error'=>'Ruta inválida.']);
    if(!in_array($method,['GET','POST'],true))respond(405,['error'=>'Método no permitido.']);
    if($method==='POST'){
        if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))respond(403,['error'=>'Sesión vencida. Recarga la página antes de guardar.']);
        if(isset($_SERVER['HTTP_ORIGIN'])){$origin=parse_url($_SERVER['HTTP_ORIGIN']);$host=parse_url('http://'.($_SERVER['HTTP_HOST']??''));if(($origin['host']??'')!==($host['host']??'')||($origin['port']??(($origin['scheme']??'')==='https'?443:80))!==($host['port']??(httpsRequest()?443:80)))respond(403,['error'=>'Origen no permitido.']);}
        if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)respond(415,['error'=>'Se requiere JSON.']);
        $raw=file_get_contents('php://input',false,null,0,100001);if(strlen($raw)>100000)respond(413,['error'=>'Formulario demasiado grande.']);
        try{$in=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){respond(400,['error'=>'JSON inválido.']);}
        if(!is_array($in))respond(422,['error'=>'Formulario inválido.']);
    }
    if($method==='GET'&&$route==='bootstrap')respond(200,['csrf'=>$_SESSION['csrf'],'proyectos'=>$db->query('SELECT * FROM proyectos ORDER BY id')->fetchAll(),'categorias'=>$db->query('SELECT * FROM categorias ORDER BY id')->fetchAll()]);
    if($method==='GET'&&in_array($route,['matrix','export'],true)){
        $project=filter_var($_GET['project']??null,FILTER_VALIDATE_INT);if(!$project||$project<1)respond(422,['error'=>'Selecciona un proyecto.']);
        record($db,'proyectos',$project);$q=$db->prepare(matrixSql());$q->execute([$project]);$rows=$q->fetchAll();
        if($route==='matrix')respond(200,$rows);
        $scope=$_GET['scope']??'reviewed';if(!in_array($scope,['reviewed','all'],true))respond(422,['error'=>'Exportación inválida.']);
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="matriz-auditoria-'.$scope.'.csv"');
        $columns=['codigo','titulo','origen','ubicacion','evidencia','prioridad','categoria_id','categoria_final','categoria_nombre','estado','autor','entorno','resultado','observado','evidencia_prueba','justificacion','contraste','impacto_verificado','politica','asvs_verificado','wstg_verificado','recomendacion','revisor','decision','comentario','autoevaluacion','fecha_prueba','fecha_revision'];
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$columns,';','"','');
        foreach($rows as $r){if($scope==='reviewed'&&!$r['revision_id'])continue;$values=[];foreach($columns as $key){$value=(string)($r[$key]??'');$values[]=preg_match('/^\s*[=+@-]/u',$value)?"'".$value:$value;}fputcsv($out,$values,';','"','');}exit;
    }
    if($method==='GET'&&$route==='history'){
        $id=(int)($_GET['id']??0);record($db,'hallazgos',$id);$q=$db->prepare('SELECT * FROM pruebas WHERE hallazgo_id=? ORDER BY id DESC');$q->execute([$id]);$tests=$q->fetchAll();$q=$db->prepare('SELECT r.* FROM revisiones r JOIN pruebas p ON p.id=r.prueba_id WHERE p.hallazgo_id=? ORDER BY r.id DESC');$q->execute([$id]);respond(200,['pruebas'=>$tests,'revisiones'=>$q->fetchAll()]);
    }
    if($method==='POST'&&$route==='finding'){
        $project=$in['proyecto_id']??null;if(!is_int($project))respond(422,['error'=>'Proyecto inválido.']);record($db,'proyectos',$project);
        $data=['proyecto_id'=>$project,'codigo'=>'M-'.strtoupper(bin2hex(random_bytes(4)))];
        foreach(['titulo'=>[10,200],'activo'=>[2,200],'perfil'=>[2,160],'ubicacion'=>[3,2000],'evidencia'=>[20,10000],'impacto'=>[10,3000],'recomendacion'=>[10,3000],'pasos'=>[20,5000],'esperado'=>[10,3000],'referencia_asvs'=>[3,1500],'referencia_wstg'=>[3,1500]] as $key=>[$min,$max])$data[$key]=textField($in,$key,$min,$max);
        $data['prioridad']=textField($in,'prioridad');if(!in_array($data['prioridad'],['alta','media','baja'],true))respond(422,['error'=>'Prioridad inválida.']);
        $data['categoria_id']=textField($in,'categoria_id');categoryExists($db,$data['categoria_id']);$data['codigo_fuente']='';$data['origen']='manual';respond(201,['id'=>addRecord($db,'hallazgos',$data)]);
    }
    if($method==='POST'&&$route==='test'){
        $id=$in['hallazgo_id']??null;if(!is_int($id))respond(422,['error'=>'Hallazgo inválido.']);record($db,'hallazgos',$id);
        $data=['hallazgo_id'=>$id];
        foreach(['autor'=>[2,120],'entorno'=>[5,200],'observado'=>[20,10000],'evidencia_prueba'=>[15,10000],'justificacion'=>[20,5000],'contraste'=>[15,3000],'impacto'=>[10,3000],'politica'=>[5,2000],'referencia_asvs'=>[5,1500],'referencia_wstg'=>[5,1500]] as $key=>[$min,$max])$data[$key]=textField($in,$key,$min,$max);
        $data['categoria_id']=textField($in,'categoria_id');categoryExists($db,$data['categoria_id']);$data['resultado']=textField($in,'resultado');
        if(!in_array($data['resultado'],['reproducido','no_reproducido','inconcluso'],true))respond(422,['error'=>'Resultado de prueba inválido.']);
        if(($in['realizada']??false)!==true)respond(422,['error'=>'Confirma que realizaste la comprobación descrita.']);
        $expected=$in['version_previa']??null;if(!is_int($expected))respond(422,['error'=>'Recarga la ficha.']);
        $db->beginTransaction();$q=$db->prepare('SELECT id FROM hallazgos WHERE id=? FOR UPDATE');$q->execute([$id]);
        if(currentTest($db,$id)!==$expected){$db->rollBack();respond(409,['error'=>'Otra prueba fue guardada. Recarga antes de continuar.']);}
        $new=addRecord($db,'pruebas',$data);$db->commit();respond(201,['id'=>$new]);
    }
    if($method==='POST'&&$route==='review'){
        $id=$in['prueba_id']??null;if(!is_int($id))respond(422,['error'=>'Prueba inválida.']);$test=record($db,'pruebas',$id);
        $data=['prueba_id'=>$id,'revisor'=>textField($in,'revisor',2,120),'decision'=>textField($in,'decision'),'comentario'=>textField($in,'comentario',20,5000),'autoevaluacion'=>($in['autoevaluacion']??false)===true?1:0];
        if(!in_array($data['decision'],['confirmado','descartado','pendiente'],true))respond(422,['error'=>'Decisión inválida.']);
        if($data['decision']==='confirmado'&&$test['resultado']!=='reproducido')respond(422,['error'=>'Para confirmar, la prueba debe haber reproducido el problema.']);
        if($data['decision']==='descartado'&&$test['resultado']!=='no_reproducido')respond(422,['error'=>'Para descartar, registra una prueba no reproducida y justifica los controles que lo impiden.']);
        if(mb_strtolower(trim($data['revisor']))===mb_strtolower(trim($test['autor']))&&!$data['autoevaluacion'])respond(422,['error'=>'Si tú también eres el revisor, marca autoevaluación.']);
        $db->beginTransaction();$q=$db->prepare('SELECT id FROM hallazgos WHERE id=? FOR UPDATE');$q->execute([$test['hallazgo_id']]);
        if(currentTest($db,(int)$test['hallazgo_id'])!==$id){$db->rollBack();respond(409,['error'=>'Esta prueba ya fue reemplazada. Revisa la versión actual.']);}
        $new=addRecord($db,'revisiones',$data);$db->commit();respond(201,['id'=>$new]);
    }
    respond(404,['error'=>'Ruta no encontrada.']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Auditoria guiada: '.$e->getCode());respond(503,['error'=>'No se pudo acceder a la base. Activa MySQL y ejecuta C:\\xampp\\php\\php.exe scripts/setup.php en la carpeta AUDITORIA.']);}
