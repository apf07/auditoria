<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/db.php';
[$script,$username,$role]=array_pad($argv,3,'');
if(!in_array($role,['auditor','docente','administrador'],true)||!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/',$username)){fwrite(STDERR,"Uso: php scripts/set-role.php usuario auditor|docente|administrador\n");exit(1);}
$db=database();$q=$db->prepare('SELECT id FROM usuarios WHERE username=?');$q->execute([$username]);$id=$q->fetchColumn();
if(!$id){fwrite(STDERR,"Usuario no encontrado.\n");exit(1);}
$q=$db->prepare('UPDATE usuarios SET rol=? WHERE id=?');$q->execute([$role,$id]);echo "Rol actualizado por el responsable del servidor.\n";
