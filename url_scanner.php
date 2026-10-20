<?php
declare(strict_types=1);

function publicScanTarget(string $url):array {
    if(strlen($url)>2048||!filter_var($url,FILTER_VALIDATE_URL)||preg_match('/[\x00-\x20\\\\]/',$url))throw new InvalidArgumentException('Ingresa una URL HTTP o HTTPS válida.');
    $p=parse_url($url);$scheme=strtolower($p['scheme']??'');$host=strtolower($p['host']??'');
    if(!in_array($scheme,['http','https'],true)||isset($p['user'])||isset($p['pass']))throw new InvalidArgumentException('Usa un enlace HTTP/HTTPS sin usuario ni contraseña incluidos.');
    $port=$p['port']??($scheme==='https'?443:80);$local=in_array($host,['localhost','127.0.0.1'],true);
    if(!in_array($port,$local?[80,443,8080]:[80,443],true))throw new InvalidArgumentException('Se permiten puertos públicos 80/443 y localhost:8080 para pruebas locales.');
    if($local)$ip='127.0.0.1';
    else{
        $ips=filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)?[$host]:gethostbynamel($host);
        if(!$ips)throw new InvalidArgumentException('No se pudo resolver el dominio. Usa un enlace público accesible.');
        foreach($ips as $address)if(!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new InvalidArgumentException('El destino pertenece a una red privada o reservada. Usa un dominio público o localhost.');
        $ip=$ips[0];
    }
    return [$scheme,$host,$port,$ip];
}
function reportUrl(string $url):string {
    $p=parse_url($url);return ($p['scheme']??'https').'://'.($p['host']??'').(isset($p['port'])?':'.$p['port']:'').($p['path']??'/');
}
function redirectUrl(string $base,string $location):string {
    if(preg_match('~^https?://~i',$location))return $location;
    $p=parse_url($base);$origin=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
    if(str_starts_with($location,'//'))return $p['scheme'].':'.$location;
    if(preg_match('~^[a-z][a-z0-9+.-]*:~i',$location))throw new InvalidArgumentException('La redirección utiliza un protocolo no permitido.');
    if(str_starts_with($location,'/'))return $origin.$location;
    if(str_starts_with($location,'?'))return $origin.($p['path']??'/').$location;
    $folder=preg_replace('~[^/]*$~','',$p['path']??'/');
    return $origin.$folder.$location;
}
function scanPublicUrl(string $url):array {
    $requested=reportUrl($url);$checked=[];$started=microtime(true);$headerLimit=65536;
    for($hop=0;$hop<4;$hop++){
        [$scheme,$host,$port,$ip]=publicScanTarget($url);
        if(microtime(true)-$started>24)throw new InvalidArgumentException('La consulta tardó demasiado. Intenta nuevamente cuando el sistema esté disponible.');
        $headers=[];$body='';$headerSize=0;$overflow=false;
        $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'AuditoriaAcademica/2.0 (revision pasiva)',CURLOPT_RESOLVE=>["$host:$port:$ip"],CURLOPT_PROXY=>'',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers,&$headerSize,&$overflow,$headerLimit){$headerSize+=strlen($line);if($headerSize>$headerLimit){$overflow=true;return 0;}if(preg_match('~^HTTP/~',$line))$headers=[];$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))][]=trim($parts[1]);return strlen($line);},
            CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$body,&$overflow){if(strlen($body)+strlen($chunk)>2*1024*1024){$overflow=true;return 0;}$body.=$chunk;return strlen($chunk);}
        ]);
        if(!ini_get('curl.cainfo')){
            foreach([getenv('AUDITORIA_CA_BUNDLE')?:'', 'C:/xampp/apache/bin/curl-ca-bundle.crt','C:/xampp/php/extras/ssl/cacert.pem',__DIR__.'/.deps/certifi/cacert.pem'] as $bundle){
                if($bundle!==''&&is_file($bundle)){curl_setopt($curl,CURLOPT_CAINFO,$bundle);break;}
            }
        }
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_errno($curl);curl_close($curl);
        if($overflow)throw new InvalidArgumentException('La respuesta supera los límites de 2 MB de contenido o 64 KB de cabeceras.');
        if($ok===false)throw new InvalidArgumentException($error===60?'No se pudo validar el certificado HTTPS. Revisa el certificado del destino y los certificados de confianza de XAMPP.':'No se pudo conectar al sistema. Comprueba que esté disponible; algunos alojamientos tardan en iniciar.');
        $checked[]=['url'=>reportUrl($url),'status'=>$status];
        if(in_array($status,[301,302,303,307,308],true)){
            if(empty($headers['location'][0])||$hop===3)throw new InvalidArgumentException('Demasiadas redirecciones o destino final no disponible. Ingresa la URL final.');
            $url=redirectUrl($url,$headers['location'][0]);continue;
        }
        if($status<200||$status>=300)throw new InvalidArgumentException("El sistema respondió HTTP $status. No se generaron hallazgos sobre una página de error.");
        if(!str_contains(strtolower(implode(';',$headers['content-type']??[])),'text/html'))throw new InvalidArgumentException('La URL debe devolver una página HTML. No es una carga ZIP ni un documento.');
        break;
    }
    $location=reportUrl($url);$findings=[];
    $add=function($category,$title,$recommendation)use(&$findings,$location){$findings[]=compact('category','title','location','recommendation');};
    if($scheme==='http')$add('A04','Página recibida sin cifrado HTTPS','Comprobar que producción fuerce HTTPS. En localhost HTTP puede ser aceptable para desarrollo.');
    if(empty($headers['content-security-policy']))$add('A02','Política CSP no observada en esta respuesta','Evaluar una Content-Security-Policy adaptada al contenido. Su ausencia por sí sola no demuestra XSS.');
    if(strtolower(trim($headers['x-content-type-options'][0]??''))!=='nosniff')$add('A02','Protección nosniff no observada','Evaluar X-Content-Type-Options: nosniff para evitar interpretación incorrecta del tipo de contenido.');
    $csp=implode(';',$headers['content-security-policy']??[]);
    if(empty($headers['x-frame-options'])&&!preg_match('/(?:^|;)\s*frame-ancestors\s+/i',$csp))$add('A02','Restricción de inclusión en marcos no observada','Evaluar frame-ancestors en CSP o X-Frame-Options según las necesidades de integración; no confirma clickjacking.');
    if($scheme==='https'&&empty($headers['strict-transport-security']))$add('A02','HSTS no observado en la respuesta HTTPS','Evaluar Strict-Transport-Security cuando el dominio y su despliegue estén preparados para exigir HTTPS.');
    $cookieGaps=[];
    foreach($headers['set-cookie']??[] as $cookie){
        if(!preg_match('/;\s*httponly\b/i',$cookie))$cookieGaps['HttpOnly']=true;
        if(!preg_match('/;\s*samesite\s*=/i',$cookie))$cookieGaps['SameSite']=true;
        if($scheme==='https'&&!preg_match('/;\s*secure\b/i',$cookie))$cookieGaps['Secure']=true;
    }
    if($cookieGaps)$add('A02','Atributos de cookies por revisar','Una o más cookies no declaran '.implode(', ',array_keys($cookieGaps)).'. Determinar cuáles son cookies de sesión y qué atributos necesitan; no se guardan sus valores.');
    if(preg_match('/(?:Fatal error|Warning):.{0,180}(?: on line | in \/| in [A-Z]:)/is',$body))$add('A02','Posible detalle interno de PHP en el HTML','Confirmar si el patrón corresponde a un error real; ocultar detalles técnicos al usuario y registrarlos de forma interna.');
    return ['origin'=>'url','source'=>$requested,'final_url'=>$location,'http_status'=>$status,'responses'=>$checked,'checks'=>['HTTP/HTTPS','CSP','nosniff','Restricción de marcos','HSTS en HTTPS','Atributos de cookies recibidas','Señales de errores PHP'],'findings'=>$findings,'scanned'=>count($checked),'skipped'=>0,'entries'=>count($checked),'capped'=>false,'scope'=>'Revisión pasiva de la respuesta HTML final y hasta tres redirecciones. No inicia sesión, envía formularios ni ejecuta ataques. No evalúa permisos entre usuarios, SQL injection, código fuente o reglas de negocio. Las cabeceras ausentes son señales de endurecimiento, no vulnerabilidades confirmadas. Sin hallazgos no implica seguridad. No se guardan HTML, valores de cookies ni parámetros de consulta.'];
}
