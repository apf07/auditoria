# Publicar en Render con MySQL de Clever Cloud

1. Activar Apache/MySQL y ejecutar `C:\xampp\php\php.exe scripts/setup.php` para agregar las tablas de acceso. No borra datos.
2. Abrir http://localhost:8080/AUDITORIA/. Si no existe cuenta, aparece el formulario inicial. Crear usuario y contraseña propios (12 caracteres como mínimo). No hay credenciales predeterminadas. La creación inicial solo está disponible desde el equipo local y está desactivada en producción.
3. Exportar de nuevo `auditoria_guiada` desde phpMyAdmin: SQL con estructura y datos, incluidas `usuarios` y `login_intentos` además de las cinco tablas de auditoría. Guardar el SQL fuera del repositorio; contiene evidencia y hashes de acceso.
4. Importar en la base MySQL asignada por Clever Cloud. Si ya hay tablas con los mismos nombres, hacer copia de seguridad y revisar las opciones de importación antes de reemplazarlas.
5. Guardar el código en GitHub con git add, git commit y git push. No subir SQL exportado, config.local.php ni .env.
6. Crear o actualizar Web Service en Render, repositorio apf07/auditoria, rama main, entorno Docker. Se usa el Dockerfile de la raíz; no necesita comandos de build/start manuales ni disco persistente porque los registros están en MySQL.
7. Environment: definir únicamente DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD con los datos de Clever Cloud. También se aceptan los alias MYSQL_ADDON_*. No se requiere AUDITORIA_USER ni AUDITORIA_PASSWORD de la versión antigua. El usuario y hash del login están en la tabla usuarios.
8. Health Check Path: /health.php. Revisar plan y coste antes de confirmar. El arranque comprueba conexión y crea tablas faltantes sin borrar datos; si no hay usuario importado, health devuelve 503 y el login de producción no permite crear una cuenta públicamente.
9. Abrir la URL HTTPS final e iniciar sesión con la cuenta creada localmente. Verificar matriz, registro de una prueba, revisión, CSV, cierre de sesión y rechazo de acceso anónimo. Las sesiones locales del contenedor se pierden al reiniciar, por lo que se pedirá login nuevamente. MySQL conserva los registros.

El primer despliegue aún debe verificarse en Render: este equipo no tiene Docker instalado. Las pruebas locales comprueban autenticación y flujo; no prueban la red del proveedor. Las referencias de autor/revisor siguen siendo firmas académicas: un único login administrativo no autentica dos personas diferentes ni aplica una separación de funciones.
