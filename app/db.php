<?php
declare(strict_types=1);
function settings():array {
    $config=['host'=>'127.0.0.1','port'=>3306,'database'=>'auditoria_guiada','user'=>'root','password'=>''];
    if(is_file(dirname(__DIR__).'/config.local.php'))$config=array_replace($config,require dirname(__DIR__).'/config.local.php');
    $aliases=['host'=>['DB_HOST','MYSQL_ADDON_HOST'],'port'=>['DB_PORT','MYSQL_ADDON_PORT'],'database'=>['DB_NAME','MYSQL_ADDON_DB'],'user'=>['DB_USER','MYSQL_ADDON_USER'],'password'=>['DB_PASSWORD','MYSQL_ADDON_PASSWORD']];
    foreach($aliases as $key=>$names){array_unshift($names,'AUDITORIA_DB_'.strtoupper($key));$configured=false;foreach($names as $name){$value=getenv($name);if($value!==false){$config[$key]=$value;$configured=true;break;}}if(getenv('AUDITORIA_ENV')==='production'&&(!$configured||$config[$key]===''))throw new RuntimeException('Falta configuración de MySQL.');}
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$config['database']))throw new RuntimeException('Nombre de base inválido.');
    if(preg_match('/[;\s]/',$config['host'])||!ctype_digit((string)$config['port']))throw new RuntimeException('Configuración inválida.');
    return $config;
}
function database(bool $serverOnly=false):PDO {
    $c=settings();$dsn='mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4'.($serverOnly?'':';dbname='.$c['database']);
    $db=new PDO($dsn,$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false]);
    $db->exec("SET time_zone='+00:00'");return $db;
}
function matrixSql():string {
    return "SELECT h.*,c.nombre categoria_nombre,p.id prueba_id,p.autor,p.entorno,p.observado,p.evidencia_prueba,p.resultado,p.categoria_id categoria_final,p.justificacion,p.contraste,p.impacto impacto_verificado,p.politica,p.referencia_asvs asvs_verificado,p.referencia_wstg wstg_verificado,p.created_at fecha_prueba,r.id revision_id,r.revisor,r.decision,r.comentario,r.autoevaluacion,r.created_at fecha_revision,COALESCE(r.decision,IF(p.id IS NULL,'pendiente','en_revision')) estado FROM hallazgos h LEFT JOIN pruebas p ON p.id=(SELECT MAX(id) FROM pruebas WHERE hallazgo_id=h.id) LEFT JOIN revisiones r ON r.id=(SELECT MAX(id) FROM revisiones WHERE prueba_id=p.id) JOIN categorias c ON c.id=COALESCE(p.categoria_id,h.categoria_id) WHERE h.proyecto_id=? ORDER BY h.id";
}
