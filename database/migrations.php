<?php
// Migración no destructiva para bases anteriores a la organización por sistema.
$columns=$db->query('PRAGMA table_info(evidencias)')->fetchAll();
$names=array_column($columns,'name');
if(!in_array('sistema_id',$names,true))$db->exec('ALTER TABLE evidencias ADD COLUMN sistema_id INTEGER NULL REFERENCES sistemas(id) ON DELETE RESTRICT');
if(!in_array('archived_at',$names,true))$db->exec('ALTER TABLE evidencias ADD COLUMN archived_at TEXT NULL');

$scanColumns=array_column($db->query('PRAGMA table_info(analisis_zip)')->fetchAll(),'name');
if(!in_array('content_sha256',$scanColumns,true))$db->exec('ALTER TABLE analisis_zip ADD COLUMN content_sha256 TEXT NULL');
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_scan_fingerprint ON analisis_zip(sistema_id,content_sha256)');

$needsBackfill=!in_array('analisis_id',$names,true);
if($needsBackfill)$db->exec('ALTER TABLE evidencias ADD COLUMN analisis_id INTEGER NULL REFERENCES analisis_zip(id) ON DELETE RESTRICT');
if(!in_array('ubicacion',$names,true))$db->exec('ALTER TABLE evidencias ADD COLUMN ubicacion TEXT NULL');
if(!in_array('recomendacion',$names,true))$db->exec('ALTER TABLE evidencias ADD COLUMN recomendacion TEXT NULL');
if($needsBackfill){
    // Recuperar solo asociaciones inequívocas; no atribuir evidencias por semejanza.
    $matches=[];
    $lookup=$db->prepare("SELECT e.id FROM evidencias e WHERE e.sistema_id=? AND e.texto=? AND EXISTS(SELECT 1 FROM clasificaciones c WHERE c.evidencia_id=e.id AND c.motor_version='zip-reglas-v1')");
    foreach($db->query('SELECT id,sistema_id,resultado FROM analisis_zip')->fetchAll() as $analysis){
        $result=json_decode($analysis['resultado'],true);
        foreach($result['findings']??[] as $finding){
            $text=$finding['title'].'. Ubicación: '.$finding['location'].'. Señal estática potencial; confirmar uso y controles. Recomendación: '.$finding['recommendation'];
            $lookup->execute([$analysis['sistema_id'],$text]);
            foreach($lookup->fetchAll() as $e)$matches[$e['id']][$analysis['id']]=[$finding['location'],$finding['recommendation']];
        }
    }
    $update=$db->prepare('UPDATE evidencias SET analisis_id=?,ubicacion=?,recomendacion=? WHERE id=? AND analisis_id IS NULL');
    foreach($matches as $id=>$analyses)if(count($analyses)===1){$analysisId=array_key_first($analyses);[$location,$recommendation]=$analyses[$analysisId];$update->execute([$analysisId,$location,$recommendation,$id]);}
}

$reviewColumns=array_column($db->query('PRAGMA table_info(revisiones)')->fetchAll(),'name');
if(!in_array('autoevaluacion',$reviewColumns,true))$db->exec('ALTER TABLE revisiones ADD COLUMN autoevaluacion INTEGER NOT NULL DEFAULT 0');
