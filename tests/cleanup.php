<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/db.php';
$id=(int)($argv[1]??0);$marker=$argv[2]??'';
if(!preg_match('/^QA-[a-f0-9]{32}$/',$marker))exit(2);
$db=database();$q=$db->prepare('SELECT id FROM hallazgos WHERE id=? AND titulo=? AND origen=?');$q->execute([$id,$marker,'manual']);
if(!$q->fetch())exit(3);
$db->beginTransaction();
$q=$db->prepare('DELETE FROM revisiones WHERE prueba_id IN (SELECT id FROM pruebas WHERE hallazgo_id=?)');$q->execute([$id]);
$q=$db->prepare('DELETE FROM pruebas WHERE hallazgo_id=?');$q->execute([$id]);
$q=$db->prepare('DELETE FROM hallazgos WHERE id=? AND titulo=?');$q->execute([$id,$marker]);
$db->commit();
