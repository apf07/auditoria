<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/db.php';
$seed=json_decode(file_get_contents(dirname(__DIR__).'/database/seed.json'),true,512,JSON_THROW_ON_ERROR);
$db=database();$q=$db->prepare('SELECT id FROM proyectos WHERE sha256=?');$q->execute([$seed['proyecto']['sha256']]);$project=$q->fetchColumn();
if(!$project){fwrite(STDERR,"Proyecto del ZIP no encontrado.\n");exit(1);}
$db->beginTransaction();
try{
 $q=$db->prepare("UPDATE hallazgos SET titulo=?,evidencia=?,impacto=?,recomendacion=?,pasos=?,esperado=? WHERE proyecto_id=? AND codigo=? AND origen='revision_zip'");
 foreach($seed['hallazgos'] as $f)$q->execute([$f['titulo'],$f['evidencia'],$f['impacto'],$f['recomendacion'],$f['pasos'],$f['esperado'],$project,$f['codigo']]);
 $db->commit();echo "Explicaciones actualizadas. Pruebas y revisiones humanas conservadas.\n";
}catch(Throwable $e){$db->rollBack();throw $e;}
