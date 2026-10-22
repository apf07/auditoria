<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/db.php';
$in=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$username=$in['username']??'';
if(!preg_match('/^QA-[a-f0-9]{32}$/',$username))exit(2);
$db=database();
if(($in['action']??'')==='create'){
    $q=$db->prepare('INSERT INTO usuarios(username,display_name,password_hash) VALUES (?,?,?)');$q->execute([$username,'Cuenta temporal de prueba',password_hash($in['password'],PASSWORD_DEFAULT)]);
    echo "OK";
}elseif(($in['action']??'')==='cleanup'){
    $q=$db->prepare('DELETE FROM usuarios WHERE username=? AND display_name=?');$q->execute([$username,'Cuenta temporal de prueba']);
    $q=$db->prepare('DELETE FROM login_intentos WHERE clave=?');$q->execute([hash('sha256',mb_strtolower($username))]);
}
