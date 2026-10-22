<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/db.php';
try{
    $c=settings();$server=database(true);$server->exec('CREATE DATABASE IF NOT EXISTS `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db=database();
    foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/schema.sql')) as $sql)if(trim($sql)!=='')$db->exec($sql);
    $seed=json_decode(file_get_contents(dirname(__DIR__).'/database/seed.json'),true,512,JSON_THROW_ON_ERROR);
    $db->beginTransaction();
    $q=$db->prepare('INSERT INTO categorias(id,nombre,criterio,cercana,contraste) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=VALUES(id)');
    foreach($seed['categorias'] as $category)$q->execute($category);
    $q=$db->prepare('SELECT id FROM proyectos WHERE sha256=? LIMIT 1');$q->execute([$seed['proyecto']['sha256']]);$project=$q->fetchColumn();
    if(!$project){
        $p=$seed['proyecto'];$q=$db->prepare('INSERT INTO proyectos(nombre,descripcion,alcance,fuente,sha256) VALUES (?,?,?,?,?)');$q->execute(array_values($p));$project=(int)$db->lastInsertId();
        foreach($seed['hallazgos'] as $finding){$finding=['proyecto_id'=>$project]+$finding;$cols=implode(',',array_keys($finding));$q=$db->prepare('INSERT INTO hallazgos('.$cols.') VALUES ('.implode(',',array_fill(0,count($finding),'?')).')');$q->execute(array_values($finding));}
    }
    $db->commit();echo "OK: base ".$c['database']." preparada. Seis hipótesis del ZIP; ninguna prueba ni revisión inventada.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'No se pudo preparar la base. Verifica MySQL y config.local.php. Código: '.$e->getCode()."\n");exit(1);}
