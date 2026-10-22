<?php
declare(strict_types=1);
require __DIR__.'/app/auth.php';startAuth();
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if(signedIn()){header('Location: ./');exit;}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
$error='';$ready=true;$setup=false;$username='';
try{$db=database();$db->query('SELECT id,rol FROM usuarios LIMIT 1');}
catch(Throwable $e){$error='No se pudo conectar. Si acabas de actualizar, ejecuta scripts/setup.php con MySQL activo.';$ready=false;}
if($_SERVER['REQUEST_METHOD']==='POST'&&$ready){
    $username=is_string($_POST['username']??null)?trim($_POST['username']):'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
    if(!checkFormCsrf()){$error='El formulario venció. Recarga la página y vuelve a intentar.';http_response_code(403);}
    elseif(!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/',$username)||strlen($password)>72){$error='Revisa el usuario y la contraseña. El usuario admite de 3 a 60 letras, números, puntos, guiones o guiones bajos.';http_response_code(422);}
    else{
        try{
                // Límite por usuario persistido en MySQL: cambiar de cookie/IP no lo evita.
                $key=hash('sha256',mb_strtolower($username));$db->beginTransaction();
                $q=$db->prepare('INSERT INTO login_intentos(clave,fallos,inicio) VALUES (?,0,?) ON DUPLICATE KEY UPDATE clave=VALUES(clave)');$q->execute([$key,time()]);
                $q=$db->prepare('SELECT * FROM login_intentos WHERE clave=? FOR UPDATE');$q->execute([$key]);$attempt=$q->fetch();
                if(time()-(int)$attempt['inicio']>=900){$attempt['fallos']=0;$q=$db->prepare('UPDATE login_intentos SET fallos=0,inicio=? WHERE clave=?');$q->execute([time(),$key]);}
                if((int)$attempt['fallos']>=5){$db->commit();http_response_code(429);header('Retry-After: '.max(1,900-(time()-(int)$attempt['inicio'])));$error='Demasiados intentos. Espera hasta 15 minutos antes de volver a intentar.';}
                else{
                    $q=$db->prepare('SELECT * FROM usuarios WHERE username=? AND activo=1 LIMIT 1');$q->execute([$username]);$user=$q->fetch();
                    $hash=$user?$user['password_hash']:'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
                    if(password_verify($password,$hash)&&$user){$q=$db->prepare('DELETE FROM login_intentos WHERE clave=?');$q->execute([$key]);$db->commit();sessionLogin($user);header('Location: ./',true,303);exit;}
                    $q=$db->prepare('UPDATE login_intentos SET fallos=fallos+1 WHERE clave=?');$q->execute([$key]);$db->commit();http_response_code(401);$error='Usuario o contraseña incorrectos.';
                }
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(503);$error='No se pudo completar el acceso. Revisa MySQL y vuelve a intentar.';}
    }
}
$accessMode='login';$displayName='';
require __DIR__.'/app/access-view.php';
