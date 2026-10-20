<?php
declare(strict_types=1);
// Análisis estático acotado: nunca extrae ni ejecuta archivos del proyecto.
function scanProjectZip(string $path):array {
    $zip=new PharData($path);
    if(!$zip->isFileFormat(Phar::ZIP))throw new InvalidArgumentException('Selecciona un archivo ZIP válido.');
    $rules=[
        ['A05','Posible inyección SQL','~(?:mysqli_query|->query|->exec)\s*\([^;\n]*\$_(?:GET|POST|REQUEST)\s*\[~i','Separar datos e instrucciones mediante parámetros enlazados.'],
        ['A05','Salida directa de entrada HTTP','~\b(?:echo|print)\s+\$_(?:GET|POST|REQUEST)\s*\[~i','Verificar contexto y codificar la salida para prevenir XSS.'],
        ['A05','Entrada HTTP en intérprete','~\b(?:system|exec|shell_exec|passthru|eval)\s*\([^;\n]*\$_(?:GET|POST|REQUEST)\s*\[~i','Evitar interpretar entradas del usuario como instrucciones.'],
        ['A04','Uso de hash antiguo','~\b(?:md5|sha1)\s*\(~i','Confirmar finalidad. Si protege contraseñas, usar password_hash/password_verify.'],
        ['A02','Errores visibles habilitados','~ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*(?:[\'"](?:1|on)[\'"]|1|true)~i','Deshabilitar errores visibles en producción y mantener registros internos.'],
        ['A02','Verificación TLS deshabilitada','~CURLOPT_SSL_VERIFYPEER\s*(?:=>|,)\s*(?:false|0)\b~i','Verificar certificados TLS en las conexiones salientes.'],
        ['A07','Generador predecible en flujo sensible','~(?:token|password|reset|contrase).*\b(?:rand|mt_rand|uniqid)\s*\(~i','Usar aleatoriedad criptográfica y verificar vencimiento de tokens.'],
        ['A05','Inserción dinámica en HTML','~\.innerHTML\s*=.*(?:location\.|document\.URL|\.value)~i','Verificar procedencia y usar textContent cuando corresponda.'],
    ];
    $findings=[];$scanned=0;$skipped=0;$total=0;$entries=0;$capped=false;
    foreach(new RecursiveIteratorIterator($zip) as $file){
        if(++$entries>5000)throw new InvalidArgumentException('El ZIP supera 5000 entradas. Excluye dependencias y archivos generados.');
        $full=str_replace('\\','/',$file->getPathname());
        $prefix='phar://'.str_replace('\\','/',$path).'/';$name=substr($full,strlen($prefix));
        if($file->isLink()||preg_match('~(^|/)\.\.(/|$)|^/|^[A-Za-z]:~',$name))throw new InvalidArgumentException('El ZIP contiene rutas no permitidas.');
        $size=$file->getSize();$total+=$size;
        if($total>50*1024*1024)throw new InvalidArgumentException('El ZIP supera 50 MB descomprimidos.');
        if(preg_match('~(^|/)(vendor|node_modules|\.git|dist|build)/~i',$name)||$size>512*1024||!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['php','phtml','js','ts','jsx','tsx'],true)){$skipped++;continue;}
        if(++$scanned>500)throw new InvalidArgumentException('El ZIP supera 500 archivos de código. Reduce su contenido.');
        foreach(preg_split('/\r\n|\n|\r/',$file->getContent()) as $line=>$text){
            if(preg_match('~^\s*(?://|#|\*|<!--)~',$text))continue;
            foreach($rules as [$category,$title,$pattern,$recommendation]){
                if(preg_match($pattern,$text)){
                    $findings[]=['category'=>$category,'title'=>$title,'location'=>$name.':'.($line+1),'recommendation'=>$recommendation];
                    if(count($findings)>=100){$capped=true;break 3;}
                }
            }
        }
    }
    return ['findings'=>$findings,'scanned'=>$scanned,'skipped'=>$skipped,'entries'=>$entries,'capped'=>$capped,'scope'=>'Reglas estáticas de PHP y algunas señales de JavaScript; no siguen el flujo de datos ni prueban permisos o lógica de negocio. Cada coincidencia es potencial. Sin coincidencias no certifica seguridad. No se conservan fragmentos de código ni el ZIP.'];
}
