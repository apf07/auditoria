<?php
declare(strict_types=1);
require __DIR__.'/app/auth.php';startAuth();
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if(signedIn()){header('Location: ./');exit;}
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
$error='';$ready=true;$setup=false;$username='';
try{$db=database();$empty=(int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===0;$setup=$empty&&localSetupAllowed();if($empty&&!$setup){$error='Primero crea la cuenta en XAMPP e importa la base actualizada en el alojamiento.';$ready=false;}}
catch(Throwable $e){$error='Activa MySQL y ejecuta scripts/setup.php para preparar la base y el acceso.';$ready=false;}
if($_SERVER['REQUEST_METHOD']==='POST'&&$ready){
    $username=is_string($_POST['username']??null)?trim($_POST['username']):'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
    if(!checkFormCsrf()){$error='El formulario venció. Recarga la página y vuelve a intentar.';http_response_code(403);}
    elseif(!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/',$username)||strlen($password)>72){$error='Revisa el usuario y la contraseña. El usuario admite de 3 a 60 letras, números, puntos, guiones o guiones bajos.';http_response_code(422);}
    else{
        try{
            if($setup){
                $name=is_string($_POST['display_name']??null)?trim($_POST['display_name']):'';
                if(mb_strlen($name)<2||mb_strlen($name)>100||mb_strlen($password)<12||$password!==($_POST['confirm_password']??null)){$error='Escribe tu nombre, una contraseña de al menos 12 caracteres (máximo 72 bytes) y repítela exactamente.';http_response_code(422);}
                else{
                    $lock='audita-setup-'.substr(hash('sha256',settings()['database']),0,20);$q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Setup ocupado');
                    try{
                        if((int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()!==0){$error='La cuenta ya fue creada. Recarga para iniciar sesión.';http_response_code(409);}
                        else{$q=$db->prepare('INSERT INTO usuarios(username,display_name,password_hash) VALUES (?,?,?)');$q->execute([$username,$name,password_hash($password,PASSWORD_DEFAULT)]);sessionLogin(['id'=>$db->lastInsertId(),'username'=>$username,'display_name'=>$name]);header('Location: ./',true,303);exit;}
                    }finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
                }
            }else{
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
            }
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(503);$error='No se pudo completar el acceso. Revisa MySQL y vuelve a intentar.';}
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $setup?'Crear tu cuenta':'Iniciar sesión' ?> · Audita</title><link rel="icon" href="assets/mark.svg"><link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__.'/assets/style.css') ?>"></head><body class="login-page"><main class="login-layout"><section class="login-story"><a class="brand" href="login.php"><img src="assets/mark.svg" width="42" height="42" alt=""><span>audita<span class="brand-dot">.</span><small>EVIDENCIA ANTES QUE SUPOSICIONES</small></span></a><span class="eyebrow">TU ESPACIO PRIVADO DE AUDITORÍA</span><h1>El criterio es tuyo.<br>La evidencia, protegida.</h1><p>Un lugar para comprobar los problemas, fundamentar tus decisiones y conservar el recorrido de cada revisión.</p><div class="login-feature"><span>01</span><div><b>Evidencia con contexto</b><p>Problemas, código y pruebas en una misma ficha.</p></div></div><div class="login-feature"><span>02</span><div><b>Decisiones trazables</b><p>Justificaciones y revisiones que conservan su historial.</p></div></div><small>OWASP Top 10:2025 · Tienda Tecnológica</small></section><section class="login-card"><span class="login-lock">◎</span><span class="eyebrow"><?= $setup?'CONFIGURACIÓN INICIAL · SOLO EN TU EQUIPO':'ACCESO AL PROTOTIPO' ?></span><h2><?= $setup?'Crea tu cuenta de auditor':'Bienvenido de nuevo' ?></h2><p><?= $setup?'Elige tus credenciales. No hay una contraseña predeterminada.':'Inicia sesión para consultar evidencias, registrar pruebas y exportar la matriz.' ?></p><?php if($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?><?php if($ready): ?><form method="post" class="login-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><?php if($setup): ?><label class="field"><span>Tu nombre</span><input name="display_name" autocomplete="name" minlength="2" maxlength="100" required></label><?php endif; ?><label class="field"><span>Usuario</span><input name="username" value="<?= h($username) ?>" autocomplete="username" minlength="3" maxlength="60" pattern="[a-zA-Z0-9_.-]+" required autocapitalize="none" spellcheck="false"></label><label class="field"><span>Contraseña</span><input name="password" type="password" autocomplete="<?= $setup?'new-password':'current-password' ?>" <?= $setup?'minlength="12"':'' ?> maxlength="72" required></label><?php if($setup): ?><label class="field"><span>Repite la contraseña</span><input name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><p class="form-help">Usa una contraseña propia de 12 caracteres o más. Se guardará su hash, nunca el texto original.</p><?php endif; ?><button class="button primary" type="submit"><?= $setup?'Crear cuenta y entrar':'Entrar a mi auditoría' ?> →</button></form><?php endif; ?><div class="login-bottom">Acceso privado · Sesión con cierre automático por inactividad</div></section></main></body></html>
