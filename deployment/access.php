<?php
declare(strict_types=1);
function requireDeploymentAccess():void {
    if(getenv('AUDITORIA_ENV')!=='production')return;
    $user=getenv('AUDITORIA_USER')?:'';$password=getenv('AUDITORIA_PASSWORD')?:'';
    if($user===''||strlen($password)<16){http_response_code(503);echo json_encode(['detail'=>'Configura el acceso privado antes de usar el prototipo.']);exit;}
    if(!hash_equals($user,$_SERVER['PHP_AUTH_USER']??'')||!hash_equals($password,$_SERVER['PHP_AUTH_PW']??'')){
        header('WWW-Authenticate: Basic realm="Auditoria privada", charset="UTF-8"');
        http_response_code(401);echo json_encode(['detail'=>'Acceso privado requerido.']);exit;
    }
}
