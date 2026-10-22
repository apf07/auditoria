<?php
declare(strict_types=1);
require __DIR__.'/app/auth.php';startAuth();
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if(signedIn()){header('Location: ./');exit;}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
$error='';$ready=true;$username='';$displayName='';$accessMode='register';
try{$db=database();$db->query('SELECT id,rol FROM usuarios LIMIT 1');}catch(Throwable $e){$error='No se pudo conectar. Si acabas de actualizar, ejecuta scripts/setup.php con MySQL activo.';$ready=false;}
if($_SERVER['REQUEST_METHOD']==='POST'&&$ready){
    $username=is_string($_POST['username']??null)?trim($_POST['username']):'';
    $displayName=is_string($_POST['display_name']??null)?trim($_POST['display_name']):'';
    $password=is_string($_POST['password']??null)?$_POST['password']:'';
    $confirm=is_string($_POST['confirm_password']??null)?$_POST['confirm_password']:'';
    if(!checkFormCsrf()){$error='El formulario venció. Recarga la página y vuelve a intentar.';http_response_code(403);}
    elseif(!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/',$username)||mb_strlen($displayName)<2||mb_strlen($displayName)>100){$error='Escribe tu nombre y un usuario de 3 a 60 letras, números, puntos o guiones.';http_response_code(422);}
    elseif(mb_strlen($password)<12||strlen($password)>72||$password!==$confirm){$error='Usa una contraseña de al menos 12 caracteres (máximo 72 bytes) y repítela exactamente.';http_response_code(422);}
    else{
        try{
            $key=hash('sha256','registro|'.($_SERVER['REMOTE_ADDR']??'unknown'));$db->beginTransaction();
            $q=$db->prepare('INSERT INTO login_intentos(clave,fallos,inicio) VALUES (?,0,?) ON DUPLICATE KEY UPDATE clave=VALUES(clave)');$q->execute([$key,time()]);
            $q=$db->prepare('SELECT * FROM login_intentos WHERE clave=? FOR UPDATE');$q->execute([$key]);$attempt=$q->fetch();
            if(time()-(int)$attempt['inicio']>=3600){$attempt['fallos']=0;$q=$db->prepare('UPDATE login_intentos SET fallos=0,inicio=? WHERE clave=?');$q->execute([time(),$key]);}
            if((int)$attempt['fallos']>=10){$db->commit();http_response_code(429);header('Retry-After: '.max(1,3600-(time()-(int)$attempt['inicio'])));$error='Se alcanzó el límite de registros desde esta conexión. Intenta más tarde.';}
            else{
                $q=$db->prepare('UPDATE login_intentos SET fallos=fallos+1 WHERE clave=?');$q->execute([$key]);
                $q=$db->prepare('SELECT id FROM usuarios WHERE username=?');$q->execute([$username]);
                if($q->fetch()){$db->commit();http_response_code(409);$error='Ese usuario ya está registrado. Elige otro o inicia sesión con tu cuenta existente.';}
                else{
                    // El formulario nunca decide el rol, aunque se envíe uno manipulado.
                    $q=$db->prepare("INSERT INTO usuarios(username,display_name,password_hash,rol) VALUES (?,?,?,'auditor')");$q->execute([$username,$displayName,password_hash($password,PASSWORD_DEFAULT)]);
                    $id=(int)$db->lastInsertId();$db->commit();sessionLogin(['id'=>$id,'username'=>$username,'display_name'=>$displayName,'rol'=>'auditor']);header('Location: ./',true,303);exit;
                }
            }
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(503);$error='No se pudo crear la cuenta. Intenta de nuevo cuando MySQL esté disponible.';}
    }
}
require __DIR__.'/app/access-view.php';
