<?php
declare(strict_types=1);
require __DIR__.'/app/auth.php';startAuth();header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
if(!checkFormCsrf()){http_response_code(403);exit('Formulario vencido. Recarga la página.');}
destroyAuth();header('Location: login.php',true,303);exit;
