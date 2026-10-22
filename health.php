<?php
declare(strict_types=1);
header('Content-Type: application/json');header('Cache-Control: no-store');
$ready=false;
try{require __DIR__.'/app/db.php';$db=database();$db->query('SELECT id FROM hallazgos LIMIT 1');$db->query('SELECT id,rol FROM usuarios LIMIT 1');$ready=true;}catch(Throwable $e){}
http_response_code($ready?200:503);echo json_encode(['status'=>$ready?'ok':'not_ready']);
