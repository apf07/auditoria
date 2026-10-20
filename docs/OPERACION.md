# Operación del prototipo en XAMPP

Dirección: http://localhost:8080/AUDITORIA/ . Apache sirve React compilado y PHP ejecuta la API. Base local SQLite protegida del acceso web. La documentación de instalación está en README.md.

## Recorrido principal

1. Registrar un sistema y seleccionarlo.
2. Cargar código fuente ZIP o elegir evidencia manual.
3. Consultar la matriz y filtrar por categoría, texto o decisión de revisión.
4. Abrir «Continuar» para contrastar la categoría, justificar y documentar contexto normativo.
5. Registrar una decisión humana explícita. La interfaz no preselecciona aprobación.
6. Exportar las versiones revisadas del sistema seleccionado.

## Invariantes

- Una señal automática no equivale a una vulnerabilidad confirmada.
- El porcentaje del panel mide fichas revisadas, no el nivel de seguridad del sistema.
- La revisión se refiere a una justificación concreta; una corrección requiere revisión nueva.
- Un ZIP idéntico se reconoce por SHA-256 dentro del mismo sistema y recupera el análisis anterior sin duplicar evidencias. El archivo no se conserva.
- La papelera es reversible; los registros archivados se excluyen de la matriz activa y CSV. Restaurar es necesario antes de volver a clasificarlos o justificarlos.
- El historial conserva versiones declaradas, pero no autentica autor ni revisor y no constituye una bitácora inmutable frente al administrador de la base.

## Validación

`tests/smoke_projects.py`: ZIP, límites de cobertura, huella, deduplicación, asociación por sistema, papelera, bloqueo de cambios y restauración.

`tests/smoke_xampp.py`: flujo textual completo, CSV protegido contra fórmulas, rechazo de revisiones obsoletas y bloqueo del acceso web a archivos internos.

`run_tests.py`: contrato FastAPI alternativo con bases temporales; estado vacío, reglas ambiguas, claves foráneas, versionado y validaciones.

Los scripts de prueba de XAMPP generan registros propios identificables y los limpian al terminar. No borrar la base del usuario para reiniciar una prueba.

## Cambios de interfaz

Editar frontend/src y ejecutar `node scripts/build-xampp.mjs`. Publica index.html y assets en esta carpeta. Los archivos compilados llevan nombres con huella para evitar servir una versión anterior.

## Límites pendientes de producción

Antes de publicar: autenticación, permisos por rol/proyecto, límites por usuario, controles de retención/cifrado, almacenamiento fuera de htdocs y migraciones gestionadas. El análisis actual es estático por patrones, con cobertura parcial; no evalúa integralmente lógica de negocio, sesiones o acceso entre usuarios.
