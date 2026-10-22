<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
function httpsRequest():bool {
    return (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(getenv('AUDITORIA_TRUST_PROXY')==='1'&&strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
}
function startAuth():void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('audita_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/').'/', 'httponly'=>true,'samesite'=>'Lax','secure'=>httpsRequest()]);
    session_start();
    if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function signedIn():bool {
    startAuth();
    if(!isset($_SESSION['user_id']))return false;
    if(time()-(int)($_SESSION['last_seen']??0)>1800||time()-(int)($_SESSION['login_at']??0)>28800){unset($_SESSION['user_id'],$_SESSION['username']);return false;}
    $_SESSION['last_seen']=time();return true;
}
function requireLogin(bool $json=false):void {
    if(signedIn())return;
    if($json){http_response_code(401);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Tu sesión terminó. Inicia sesión de nuevo antes de consultar o guardar información.']);exit;}
    header('Location: login.php');exit;
}
function localSetupAllowed():bool {
    return getenv('AUDITORIA_ENV')!=='production'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
}
function checkFormCsrf():bool {return isset($_POST['csrf'])&&is_string($_POST['csrf'])&&hash_equals($_SESSION['csrf'],$_POST['csrf']);}
function sessionLogin(array $user):void {
    session_regenerate_id(true);$_SESSION=['user_id'=>(int)$user['id'],'username'=>$user['username'],'display_name'=>$user['display_name'],'login_at'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
}
function destroyAuth():void {
    $_SESSION=[];$p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$p['path'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']);session_destroy();
}
