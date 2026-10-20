<?php
// Prueba aislada: crea y elimina exclusivamente una base aleatoria propia.
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/database/connection.php';
$name='auditoria_test_'.bin2hex(random_bytes(8));
$admin=new PDO('mysql:host=127.0.0.1;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
try{
    putenv('AUDITORIA_ENV=test');putenv('AUDITORIA_DB_DRIVER=mysql');
    putenv('MYSQL_ADDON_HOST=127.0.0.1');putenv('MYSQL_ADDON_DB='.$name);putenv('MYSQL_ADDON_USER=root');putenv('MYSQL_ADDON_PASSWORD=');
    require dirname(__DIR__).'/deployment/init-db.php';
    $db=openDatabase();seedCatalog($db);
    if((int)$db->query('SELECT COUNT(*) FROM categorias_owasp WHERE categoria_cercana IS NOT NULL')->fetchColumn()!==10)throw new RuntimeException('Catálogo inválido');
    $db->beginTransaction();
    $db->exec("INSERT INTO sistemas(nombre) VALUES ('Sistema de prueba')");$system=(int)$db->lastInsertId();
    $q=$db->prepare('INSERT INTO evidencias(sistema_id,texto,activo,perfil) VALUES (?,?,?,?)');$q->execute([$system,'Acceso sin permiso a otro ciudadano.','Portal','Ciudadano']);$evidence=(int)$db->lastInsertId();
    $q=$db->prepare('INSERT INTO clasificaciones(evidencia_id,categoria_id,origen,motor_version,explicacion) VALUES (?, ?, ?, ?, ?)');$q->execute([$evidence,'A01','reglas','test-v1','Señal de acceso indebido.']);$classification=(int)$db->lastInsertId();
    $q=$db->prepare('INSERT INTO justificaciones(clasificacion_id,autor,texto,contraste,impacto,dato_faltante,politica_institucional,referencia_asvs,prueba_wstg) VALUES (?,?,?,?,?,?,?,?,?)');$q->execute([$classification,'Prueba','Justificación de prueba.','Contraste de permisos.','Exposición de datos.','Por verificar.','No proporcionada.','Por verificar.','Prueba manual.']);$justification=(int)$db->lastInsertId();
    $q=$db->prepare('INSERT INTO revisiones(justificacion_id,revisor,decision,observaciones) VALUES (?,?,?,?)');$q->execute([$justification,'Docente','observada','Completar comprobación.']);
    $db->commit();
    $rows=$db->query(file_get_contents(dirname(__DIR__).'/database/matrix.sql'))->fetchAll();
    if(count($rows)!==1||$rows[0]['categoria_id']!=='A01'||$rows[0]['decision']!=='observada'||$rows[0]['sistema_id']!==$system)throw new RuntimeException('Matriz inválida');
    $q=$db->prepare('UPDATE evidencias SET archived_at=? WHERE id=?');$q->execute([gmdate('Y-m-d\TH:i:s\Z'),$evidence]);
    if($db->query(file_get_contents(dirname(__DIR__).'/database/matrix.sql'))->fetchAll())throw new RuntimeException('Archivo inválido');
    try{$db->exec("INSERT INTO clasificaciones(evidencia_id,categoria_id,origen,motor_version,explicacion) VALUES (999999,'A01','manual','test','Prueba')");throw new RuntimeException('FK no aplicada');}catch(PDOException $expected){}
    echo "OK MySQL: esquema, catálogo repetible, relaciones, justificación, revisión, matriz y archivo.\n";
}finally{
    $admin->exec('DROP DATABASE `'.$name.'`');echo "Base temporal propia eliminada.\n";
}
