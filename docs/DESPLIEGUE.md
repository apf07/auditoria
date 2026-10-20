# Publicación del prototipo existente

El Dockerfile compila React para la raíz del dominio y conserva la API PHP, análisis ZIP, enlace y evidencia manual. XAMPP sigue funcionando en /AUDITORIA/. No incluye datos locales, documentos ni credenciales.

## Render

1. Subir este proyecto a un repositorio privado de GitHub.
2. Crear un Blueprint desde el repositorio usando render.yaml. Atención: declara servicio Starter y disco de 1 GB; revisar el coste y autorizarlo antes de confirmar.
3. Configurar AUDITORIA_USER y AUDITORIA_PASSWORD (mínimo 16 caracteres) como secretos del servicio.
4. Configurar AUDITORIA_ALLOWED_ORIGINS con el origen HTTPS exacto del servicio, por ejemplo https://auditoria-xxxx.onrender.com, sin barra final. Agregar dominios adicionales separados por comas si se utilizan.
5. El disco se monta en /var/data. La nube inicia con base vacía; los datos existentes en XAMPP no se suben automáticamente.
6. Comprobar /health.php, crear un sistema de prueba, analizar un ZIP autorizado y un enlace autorizado, justificar, revisar y exportar CSV. Reiniciar y comprobar persistencia.

La contraseña protege toda la API mediante HTTP Basic; no representa identidades individuales de estudiantes/docentes. Usar exclusivamente HTTPS. /health.php solo expone disponibilidad. El frontend estático es público, los registros requieren acceso privado.

## Clever Cloud

El contenedor escucha en 8080 (CC_DOCKER_EXPOSED_HTTP_PORT=8080). Ya admite MySQL externo mediante AUDITORIA_DB_DRIVER=mysql y MYSQL_ADDON_*. Seguir [CLEVER_CLOUD.md](CLEVER_CLOUD.md). No publicar allí con SQLite local esperando conservar datos: el disco Docker es efímero. No conectar SQLite sobre FS Bucket.

Referencias: https://render.com/docs/docker · https://render.com/docs/disks · https://www.clever.cloud/developers/doc/deploy/applications/docker/ · https://www.clever.cloud/developers/doc/deploy/storage

## Verificación local del contenedor

Requiere Docker instalado; en esta estación no está disponible. Construir con `docker build -t auditoria .`. Ejecutar publicando 8080 y montando un volumen en /var/data, proporcionando las variables anteriores sin guardar contraseñas en el repositorio. El despliegue no se considera verificado hasta completar construcción, salud y pruebas del flujo en el proveedor.
