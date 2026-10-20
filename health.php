<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
$ready=false;
try{
    require_once __DIR__.'/database/connection.php';
    $db=openDatabase();$db->query('SELECT id FROM categorias_owasp LIMIT 1');
    $ready=extension_loaded('mbstring')&&extension_loaded('curl');
}catch(Throwable $e){$ready=false;}
if(getenv('AUDITORIA_ENV')==='production')$ready=$ready&&(bool)getenv('AUDITORIA_USER')&&strlen(getenv('AUDITORIA_PASSWORD')?:'')>=16;
http_response_code($ready?200:503);
echo json_encode(['status'=>$ready?'ok':'not_ready']);
