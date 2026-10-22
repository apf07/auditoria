<?php
declare(strict_types=1);
// Agrega control de identidad sin borrar ni reescribir respuestas anteriores.
$cols=$db->query('SHOW COLUMNS FROM usuarios')->fetchAll();
if(!in_array('rol',array_column($cols,'Field'),true))$db->exec("ALTER TABLE usuarios ADD COLUMN rol ENUM('auditor','docente','administrador') NOT NULL DEFAULT 'auditor'");
$added=[];
foreach(['hallazgos','pruebas','revisiones'] as $table){
    $cols=$db->query('SHOW COLUMNS FROM '.$table)->fetchAll();
    if(!in_array('usuario_id',array_column($cols,'Field'),true)){
        $db->exec('ALTER TABLE '.$table.' ADD COLUMN usuario_id INT NULL, ADD CONSTRAINT fk_'.$table.'_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)');$added[]=$table;
    }
}
// Solo se puede atribuir una versión antigua si existía una única cuenta.
// Con varias cuentas, dejar los registros sin atribuir para revisión administrativa.
if($added&&(int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===1){
    $owner=(int)$db->query('SELECT id FROM usuarios LIMIT 1')->fetchColumn();
    if(in_array('pruebas',$added,true))$db->exec('UPDATE pruebas SET usuario_id='.$owner.' WHERE usuario_id IS NULL');
    if(in_array('hallazgos',$added,true))$db->exec("UPDATE hallazgos SET usuario_id=$owner WHERE usuario_id IS NULL AND origen='manual'");
}
