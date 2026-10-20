# Desplegar PHP + MySQL en Clever Cloud

El código PHP admite ahora MySQL. Por defecto XAMPP conserva SQLite. La base MySQL antigua `auditoria_caso7` (analyses/evidences/evidence_events) no corresponde al esquema actual y no se importa automáticamente. El nuevo servicio comienza sin evidencias y carga solo el catálogo OWASP.

## 1. Subir los cambios

Desde PowerShell en la carpeta del proyecto:

```powershell
git add .
git commit -m "Agregar MySQL y despliegue Clever Cloud"
git push origin main
```

## 2. Crear MySQL

En Clever Cloud Console, crear un add-on MySQL, elegir región y revisar coste y límites antes de confirmar. No crear manualmente un nombre de base diferente: usar el que asigna Clever Cloud.

## 3. Crear la aplicación

Crear aplicación desde GitHub y seleccionar `apf07/auditoria`, rama main. Elegir **Docker**: el Dockerfile ya incluye PHP con Apache y compila React. No seleccionar Static ni Python. Vincular el add-on MySQL a la aplicación y comprobar que sus variables MYSQL_ADDON_* estén disponibles. No se requiere disco persistente para los datos: se guardan en MySQL externo. Elegir una instancia de ejecución y de construcción con memoria suficiente, revisar costes antes de desplegar.

## 4. Variables de aplicación

| Variable | Valor |
|---|---|
| AUDITORIA_DB_DRIVER | mysql |
| AUDITORIA_ENV | production |
| AUDITORIA_USER | Usuario privado elegido por ti |
| AUDITORIA_PASSWORD | Contraseña nueva, mínimo 16 caracteres |
| AUDITORIA_ALLOWED_ORIGINS | Origen HTTPS exacto de la aplicación, sin barra final |
| CC_DOCKER_EXPOSED_HTTP_PORT | 8080 |
| PORT | 8080 |

Variables aportadas por el add-on: MYSQL_ADDON_HOST, MYSQL_ADDON_PORT, MYSQL_ADDON_DB, MYSQL_ADDON_USER y MYSQL_ADDON_PASSWORD. Si no aparecen al vincularlo, copiarlas desde el add-on al entorno de la aplicación, nunca al código ni al repositorio. MYSQL_SSL_CA permite configurar una CA de MySQL verificando su certificado si el proveedor suministra ese mecanismo.

Usar el dominio que muestra Clever Cloud en su configuración de dominios para AUDITORIA_ALLOWED_ORIGINS; ejemplo ilustrativo `https://app-xxxx.cleverapps.io`. Si se añade un dominio propio, agregar su origen separado por coma. Guardar variables y desplegar/reiniciar cuando se modifiquen.

## 5. Comprobar

El arranque crea siete tablas y las diez categorías sin borrar tablas existentes. Si no logra conectar, se detiene; revisar variables, disponibilidad y permisos de MySQL. /health.php debe devolver `{"status":"ok"}` y comprueba acceso a la tabla de categorías. La API solicita las credenciales privadas; se trata de acceso compartido para el prototipo, no roles individuales autenticados.

Crear un sistema de prueba, analizar ZIP y enlace autorizados, justificar, revisar y exportar CSV. Reiniciar la aplicación y comprobar que permanecen los registros. No afirmar despliegue completo hasta verificar el flujo en la URL HTTPS final. El frontend estático es público; los registros requieren contraseña.

Para MySQL local o un alojamiento PHP sin Docker, ejecutar primero `php deployment/init-db.php` con las variables de conexión disponibles para ese proceso. No usar el esquema SQLite en MySQL.

Referencias oficiales: https://www.clever.cloud/developers/doc/deploy/databases/mysql · https://www.clever.cloud/developers/doc/deploy/applications/docker/
