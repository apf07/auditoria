# Audita · Tienda Tecnológica

Prototipo PHP 8.2 + MySQL + JavaScript sin dependencias de frontend. Dirección local: http://localhost:8080/AUDITORIA/. Incluye login por sesión y protección de páginas, API y exportaciones. Despliegue: [DEPLOY.md](DEPLOY.md).

## Preparar

Activar Apache y MySQL en XAMPP. Desde esta carpeta ejecutar:

```powershell
C:\xampp\php\php.exe scripts/setup.php
```

Usa la base independiente `auditoria_guiada`. Si tu MySQL requiere contraseña u otra conexión, copiar `config.example.php` a `config.local.php` y ajustar los datos. No se borran ni importan otras bases. La inicialización es repetible y no reemplaza pruebas ya registradas. El acceso muestra Iniciar sesión y Registrarse, tanto en XAMPP como en producción. Cada cuenta nueva tiene rol auditor y un espacio de respuestas independiente; no existe contraseña predeterminada. El rol no se puede elegir desde el formulario.

## Usar

1. Vista general: seis hipótesis de revisión estática del ZIP, ninguna confirmada automáticamente.
2. Matriz: buscar por problema/archivo y filtrar por estado y OWASP; también registrar evidencias manuales.
3. Pruebas y revisión: leer evidencia y código, realizar la comprobación en una copia autorizada con datos ficticios, registrar resultado, justificación y contraste.
4. Revisión: otra persona puede revisar, o la misma persona puede declarar autoevaluación. Confirmar requiere prueba reproducida. Descartar requiere prueba no reproducida y fundamento. Los nombres no autentican identidades.
5. Informe: exportar CSV completo o únicamente fichas con revisión de la prueba actual. Los CSV neutralizan fórmulas y son UTF-8 para Excel.

Una prueba nueva exige otra revisión. El historial conserva las anteriores. Los borradores se conservan en memoria al cambiar de pestaña, ficha o sección durante la sesión; cerrar/recargar la página los pierde y muestra aviso. No se guardan borradores sensibles en almacenamiento persistente del navegador.

Este prototipo documenta una auditoría humana: no escanea ni ataca sistemas. Las seis fichas se sustentan en el ZIP identificado por SHA-256, no en comprobaciones del despliegue. ASVS, WSTG y políticas requieren contraste del auditor. Las seis hipótesis del caso son compartidas; pruebas, historial, hallazgos manuales y exportaciones quedan limitados a cada auditor. El servidor toma la firma de la cuenta autenticada, sin confiar en nombres manipulados en el formulario. Un auditor solo revisa sus propias pruebas declarando autoevaluación. Los permisos de docente/administrador se asignan exclusivamente desde la consola del responsable del servidor mediante `php scripts/set-role.php usuario docente` (o administrador); el panel actual está centrado en el espacio personal del auditor, no incluye un panel de gestión docente. La sesión expira tras 30 minutos de inactividad o 8 horas desde el acceso. Cinco contraseñas incorrectas bloquean temporalmente ese usuario durante el intervalo de 15 minutos. El registro limita a diez solicitudes válidas por hora desde una conexión de servidor; detrás de un proxy este límite puede ser compartido.

Al actualizar desde la versión de una sola cuenta, setup agrega columnas sin borrar respuestas. Solo atribuye pruebas antiguas si existía exactamente una cuenta; con varias cuentas anteriores, las pruebas sin propietario quedan conservadas en MySQL y requieren atribución revisada por el responsable del servidor antes de mostrarlas.

## Comprobar

```powershell
python tests/smoke.py
```

La prueba utiliza una cuenta y una ficha ficticias con marcador aleatorio y elimina únicamente sus propios datos al terminar. Comprueba login/logout, bloqueo de intentos, rechazo de consultas anónimas, flujo HTTP, base, CSRF, revisiones, versiones, CSV e historial.

`python tests/registration.py` comprueba registro, roles no elegibles, duplicados, firma autenticada y aislamiento entre dos cuentas ficticias.
