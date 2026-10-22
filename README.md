# Audita · Tienda Tecnológica

Prototipo PHP 8.2 + MySQL + JavaScript sin dependencias de frontend. Dirección local: http://localhost:8080/AUDITORIA/. Incluye login por sesión y protección de páginas, API y exportaciones. Despliegue: [DEPLOY.md](DEPLOY.md).

## Preparar

Activar Apache y MySQL en XAMPP. Desde esta carpeta ejecutar:

```powershell
C:\xampp\php\php.exe scripts/setup.php
```

Usa la base independiente `auditoria_guiada`. Si tu MySQL requiere contraseña u otra conexión, copiar `config.example.php` a `config.local.php` y ajustar los datos. No se borran ni importan otras bases. La inicialización es repetible y no reemplaza pruebas ya registradas. Al abrir el sitio por primera vez desde tu equipo, crea tu propia cuenta; no existe contraseña predeterminada. En producción no se permite crear cuentas desde la web: exporta e importa la base después de crear tu cuenta local.

## Usar

1. Vista general: seis hipótesis de revisión estática del ZIP, ninguna confirmada automáticamente.
2. Matriz: buscar por problema/archivo y filtrar por estado y OWASP; también registrar evidencias manuales.
3. Pruebas y revisión: leer evidencia y código, realizar la comprobación en una copia autorizada con datos ficticios, registrar resultado, justificación y contraste.
4. Revisión: otra persona puede revisar, o la misma persona puede declarar autoevaluación. Confirmar requiere prueba reproducida. Descartar requiere prueba no reproducida y fundamento. Los nombres no autentican identidades.
5. Informe: exportar CSV completo o únicamente fichas con revisión de la prueba actual. Los CSV neutralizan fórmulas y son UTF-8 para Excel.

Una prueba nueva exige otra revisión. El historial conserva las anteriores. Los borradores se conservan en memoria al cambiar de pestaña, ficha o sección durante la sesión; cerrar/recargar la página los pierde y muestra aviso. No se guardan borradores sensibles en almacenamiento persistente del navegador.

Este prototipo documenta una auditoría humana: no escanea ni ataca sistemas. Las seis fichas se sustentan en el ZIP identificado por SHA-256, no en comprobaciones del despliegue. ASVS, WSTG y políticas requieren contraste del auditor. El acceso administrativo está autenticado; los nombres escritos de autor/revisor siguen siendo registros académicos y no verifican identidades independientes. La sesión expira tras 30 minutos de inactividad o 8 horas desde el acceso. Cinco contraseñas incorrectas bloquean temporalmente ese usuario durante el intervalo de 15 minutos.

## Comprobar

```powershell
python tests/smoke.py
```

La prueba utiliza una cuenta y una ficha ficticias con marcador aleatorio y elimina únicamente sus propios datos al terminar. Comprueba login/logout, bloqueo de intentos, rechazo de consultas anónimas, flujo HTTP, base, CSRF, revisiones, versiones, CSV e historial.
