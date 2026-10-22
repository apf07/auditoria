# Publicar en Render con MySQL de Clever Cloud

1. Activar Apache/MySQL y ejecutar `C:\xampp\php\php.exe scripts/setup.php` para agregar las tablas de acceso. No borra datos.
2. Abrir http://localhost:8080/AUDITORIA/. Iniciar sesión con una cuenta existente o usar Registrarse para crear una cuenta de auditor (12 caracteres como mínimo en la contraseña). No hay credenciales predeterminadas ni elección de permisos elevados desde el registro.
3. Exportar de nuevo `auditoria_guiada` desde phpMyAdmin: SQL con estructura y datos, incluidas `usuarios` y `login_intentos` además de las cinco tablas de auditoría. Guardar el SQL fuera del repositorio; contiene evidencia y hashes de acceso.
4. Importar en la base MySQL asignada por Clever Cloud. Si ya hay tablas con los mismos nombres, hacer copia de seguridad y revisar las opciones de importación antes de reemplazarlas.
5. Guardar el código en GitHub con git add, git commit y git push. No subir SQL exportado, config.local.php ni .env.
6. Crear o actualizar Web Service en Render, repositorio apf07/auditoria, rama main, entorno Docker. Se usa el Dockerfile de la raíz; no necesita comandos de build/start manuales ni disco persistente porque los registros están en MySQL.
7. Environment: definir únicamente DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD con los datos de Clever Cloud. También se aceptan los alias MYSQL_ADDON_*. No se requiere AUDITORIA_USER ni AUDITORIA_PASSWORD de la versión antigua. El usuario y hash del login están en la tabla usuarios. Si la base de la nube no tiene cuentas, se puede registrar una cuenta directamente en esa URL; la cuenta local no se transfiere automáticamente.
8. Health Check Path: /health.php. Revisar plan y coste antes de confirmar. El arranque comprueba conexión, crea tablas y migra las columnas faltantes sin borrar datos. Una base sin cuentas ya puede atender el registro. Se reutiliza una sola conexión PDO por petición para reducir el consumo del plan DEV de MySQL.
9. Abrir la URL HTTPS final. Iniciar sesión si importaste una cuenta o usar Registrarse para crear una cuenta nueva de auditor en la nube. Verificar matriz, registro de una prueba, revisión, CSV, cierre de sesión y rechazo de acceso anónimo. Las sesiones locales del contenedor se pierden al reiniciar, por lo que se pedirá login nuevamente. MySQL conserva los registros.

Cada cuenta tiene rol auditor por defecto. Solo el responsable del servidor puede asignar docente/administrador mediante el script CLI set-role.php; no hay selector público de permisos. Las pruebas y exportaciones están separadas por auditor y el servidor vincula las firmas con la identidad autenticada. La revisión del propio trabajo exige autoevaluación; el panel actual no incluye gestión docente de alumnos.

El despliegue actualizado aún debe verificarse en Render: este equipo no tiene Docker instalado. Las pruebas locales comprueban registro, autenticación, aislamiento y flujo; no prueban la red del proveedor.
