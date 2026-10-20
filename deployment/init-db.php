<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/database/connection.php';
try{
    $db=openDatabase();
    if(databaseDriver()==='mysql'){
        foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/schema.mysql.sql')) as $statement)if(trim($statement)!=='')$db->exec($statement);
    }else{
        $db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
        require dirname(__DIR__).'/database/migrations.php';
    }
    seedCatalog($db);echo "Base de datos inicializada; registros existentes conservados.\n";
}catch(Throwable $e){fwrite(STDERR,"No se pudo inicializar la base de datos. Revisa la conexión y los permisos del servicio.\n");exit(1);}
