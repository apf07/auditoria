<?php
declare(strict_types=1);
function databaseDriver():string {
    $driver=getenv('AUDITORIA_DB_DRIVER')?:'sqlite';
    if(!in_array($driver,['sqlite','mysql'],true))throw new RuntimeException('Motor de base de datos inválido.');
    return $driver;
}
function openDatabase():PDO {
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>false];
    if(databaseDriver()==='mysql'){
        $values=[];
        foreach(['HOST','DB','USER','PASSWORD'] as $key){$value=getenv('MYSQL_ADDON_'.$key);if($value===false||($value===''&&($key!=='PASSWORD'||getenv('AUDITORIA_ENV')==='production')))throw new RuntimeException('Falta configuración MySQL.');$values[$key]=$value;}
        $port=getenv('MYSQL_ADDON_PORT')?:'3306';
        if(!ctype_digit($port)||preg_match('/[;\x00-\x20]/',$values['HOST'].$values['DB']))throw new RuntimeException('Configuración MySQL inválida.');
        $options[PDO::ATTR_EMULATE_PREPARES]=false;
        if($ca=getenv('MYSQL_SSL_CA')){$options[PDO::MYSQL_ATTR_SSL_CA]=$ca;$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;}
        $db=new PDO('mysql:host='.$values['HOST'].';port='.$port.';dbname='.$values['DB'].';charset=utf8mb4',$values['USER'],$values['PASSWORD'],$options);
        $db->exec("SET time_zone = '+00:00'");return $db;
    }
    $dir=getenv('AUDITORIA_DATA_DIR')?:dirname(__DIR__).'/data';if(!is_dir($dir))mkdir($dir,0700,true);
    $db=new PDO('sqlite:'.$dir.'/auditoria.sqlite3',null,null,$options);
    $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');return $db;
}
function seedCatalog(PDO $db):void {
    $seed=json_decode(file_get_contents(dirname(__DIR__).'/backend/categories.json'),true,512,JSON_THROW_ON_ERROR);
    $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $db->beginTransaction();
    try{
        if($mysql){
            // MySQL no permite diferir FK: insertar todas las categorías antes de vincularlas.
            $q=$db->prepare('INSERT INTO categorias_owasp (id,nombre,criterio,categoria_cercana,contraste) VALUES (?,?,?,NULL,?) ON DUPLICATE KEY UPDATE id=VALUES(id)');
            foreach($seed as $c)$q->execute([$c[0],$c[1],$c[2],$c[4]]);
            $q=$db->prepare('UPDATE categorias_owasp SET categoria_cercana=? WHERE id=? AND categoria_cercana IS NULL');
            foreach($seed as $c)$q->execute([$c[3],$c[0]]);
        }else{
            $q=$db->prepare('INSERT OR IGNORE INTO categorias_owasp (id,nombre,criterio,categoria_cercana,contraste) VALUES (?,?,?,?,?)');
            foreach($seed as $c)$q->execute($c);
        }
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
