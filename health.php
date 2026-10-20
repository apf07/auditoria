<?php
header('Content-Type: application/json');
header('Cache-Control: no-store');
$dir=getenv('AUDITORIA_DATA_DIR')?:__DIR__.'/data';
$ready=extension_loaded('pdo_sqlite')&&extension_loaded('mbstring')&&extension_loaded('curl')&&is_dir($dir)&&is_writable($dir);
if(getenv('AUDITORIA_ENV')==='production')$ready=$ready&&(bool)getenv('AUDITORIA_USER')&&strlen(getenv('AUDITORIA_PASSWORD')?:'')>=16;
http_response_code($ready?200:503);
echo json_encode(['status'=>$ready?'ok':'not_ready']);
