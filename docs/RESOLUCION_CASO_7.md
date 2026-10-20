# Resolución del caso 7

**Asignatura:** Auditoría de Tecnología de la Información  
**Caso:** El expediente que nadie debía ver: auditoría de una plataforma pública antes de salir a producción  
**Estudiante:** Completar  
**Docente:** Mg. Nilton Luis Vicente Guerra

## 1. Propósito y alcance

Clasificar de manera argumentada las ocho evidencias del caso utilizando OWASP Top 10:2025 y reconocer la información adicional necesaria para verificar cada hallazgo. Se considera una plataforma institucional que administra solicitudes, documentos y estados de expedientes, con perfiles de ciudadano, analista y administrador.

Esta resolución analiza el escenario académico proporcionado. No afirma que se hayan ejecutado pruebas sobre la plataforma pública ni que los ocho problemas existan en el ZIP de otro sistema. Los resultados obtenidos al analizar una tienda u otro proyecto deben mantenerse separados de las evidencias E1–E8 de la guía.

No se asigna severidad numérica ni probabilidad sin conocer alcance, sensibilidad de datos, exposición, controles compensatorios y condiciones de explotación.

## 2. Matriz de clasificación inicial

| ID | Comportamiento inseguro | Categoría candidata OWASP 2025 | Impacto preliminar | Dato faltante principal |
|---|---|---|---|---|
| E1 | Cambiar el identificador permite consultar expedientes ajenos. | A01 · Control de acceso roto | Pérdida de confidencialidad de expedientes y datos personales. | Permisos y propiedad esperados; alcance de los objetos accesibles. |
| E2 | Panel administrativo expuesto con configuración por defecto sin endurecimiento. | A02 · Configuración de seguridad incorrecta | Aumento de superficie de ataque y posible exposición administrativa. | Configuración efectiva, accesibilidad, autenticación y valores inseguros concretos. |
| E3 | Componente crítico sin versión ni procedencia documentadas. | A03 · Fallos en la cadena de suministro de software | Dificultad para evaluar vulnerabilidades, procedencia y respuesta a incidentes. | Componente, versión, origen, inventario y proceso de incorporación. |
| E4 | Entrada del usuario se incorpora a una consulta sin separar datos e instrucciones. | A05 · Inyección | Posible alteración de consultas y acceso o modificación de datos. | Intérprete afectado y flujo de entrada; parametrización y permisos efectivos. |
| E5 | Diseño del flujo permite cambios sensibles sin una regla de doble validación. | A06 · Diseño inseguro | Alteración indebida del estado y pérdida de integridad del trámite. | Criticidad de la transición, política aprobada y controles compensatorios. |
| E6 | Recuperación de contraseña con verificación débil y predecible. | A07 · Fallos de autenticación | Posible apropiación de cuentas y operaciones con identidad ajena. | Token, entropía, vencimiento, intentos y vinculación con la cuenta. |
| E7 | Actualizaciones aceptadas sin verificar suficientemente integridad y procedencia. | A08 · Fallos de integridad del software o los datos | Instalación de software alterado o de origen no confiable. | Firmas, hashes de fuente confiable y comportamiento ante verificación fallida. |
| E8 | No hay registros ni alertas suficientes para reconstruir operaciones sensibles. | A09 · Fallos de registro de seguridad y alertas | Incidentes no detectados e insuficiente trazabilidad de acciones. | Eventos, campos, protección, conservación y reglas de alerta existentes. |

Las categorías coinciden con la orientación docente de la guía. Son clasificaciones iniciales sujetas a contraste y verificación, no conclusiones de explotación demostrada.

## 3. Desarrollo y justificación por evidencia

### E1. Consulta de expedientes ajenos

- **Activo:** datos y documentos del expediente.
- **Usuario:** ciudadano autenticado que solicita un expediente de otro titular.
- **Función:** consulta de `/api/expedientes/1042` mediante cambio del ID.
- **Comportamiento:** el servidor entrega un objeto sin restringir adecuadamente su acceso según la identidad y propiedad.
- **Clasificación y sustento:** A01. La evidencia apunta a autorización sobre objetos; un identificador conocido o modificado no debe sustituir la verificación del permiso.
- **Contraste con A07:** el ciudadano puede tener una identidad correctamente autenticada. El defecto observado es lo que esa identidad puede consultar, no necesariamente cómo se acreditó.
- **Verificación propuesta:** usar dos cuentas ficticias, documentar quién es titular de cada expediente y comparar las respuestas. Comprobar el control del lado servidor, incluso si la interfaz oculta enlaces.
- **Recomendación:** autorización por objeto y operación en cada petición; denegación por defecto. Cambiar IDs por valores impredecibles no reemplaza el control de acceso.

### E2. Configuración administrativa insegura

- **Activo:** panel y configuración del servidor.
- **Usuario:** administrador legítimo; posible acceso de un usuario no autorizado si el panel está alcanzable.
- **Función:** administración de la plataforma.
- **Comportamiento:** se mantiene una configuración por defecto sin endurecimiento.
- **Clasificación y sustento:** A02. El origen del hallazgo descrito es la configuración del entorno.
- **Contraste con A06:** A06 sería pertinente si falta un control en el diseño; aquí se señala un estado de configuración inseguro. Si además se prueba acceso sin permisos, registrar o justificar A01 como hallazgo relacionado.
- **Verificación propuesta:** comparar configuración efectiva con una línea base aprobada; revisar autenticación, accesibilidad, servicios innecesarios, valores por defecto y detalle de errores.
- **Recomendación:** aplicar endurecimiento y restringir la superficie administrativa según la necesidad operativa. La mera existencia de un panel no demuestra por sí misma una vulnerabilidad.

### E3. Dependencia sin trazabilidad

- **Activo:** librería crítica e inventario de componentes.
- **Usuario:** responsables de desarrollo, mantenimiento y gestión de dependencias.
- **Función:** selección e incorporación de software de terceros.
- **Comportamiento:** no se documentan versión y procedencia.
- **Clasificación y sustento:** A03. La carencia afecta la trazabilidad y gobernanza de la cadena de suministro.
- **Contraste con A08:** A08 se centra en aceptar software o datos sin garantías suficientes de integridad. E3 describe el desconocimiento del componente y su origen dentro del proceso de suministro. Puede existir relación, pero no debe suponerse que la librería está alterada.
- **Verificación propuesta:** identificar paquete, versión y repositorio; revisar inventario, manifiestos, archivos de bloqueo y autorización de proveedores. Consultar avisos de seguridad una vez identificada la versión real.
- **Recomendación:** mantener inventario o SBOM y un proceso de adquisición, actualización y seguimiento de dependencias.
- **Límite:** una falta documental no demuestra que el componente tenga una vulnerabilidad conocida o sea malicioso.

### E4. Entrada que puede alterar instrucciones

- **Activo:** buscador y datos accesibles mediante consultas.
- **Usuario:** quien utiliza el campo de búsqueda.
- **Función:** construcción y ejecución de consultas.
- **Comportamiento:** no se separan adecuadamente entradas y sintaxis de instrucciones.
- **Clasificación y sustento:** A05. La característica relevante es el cruce de la frontera entre datos e instrucciones.
- **Contraste con A06:** puede haber una debilidad general de diseño, pero A05 expresa el mecanismo descrito. No confundir este problema con A01: un fallo de permisos puede coexistir, sin ser la misma causa.
- **Verificación propuesta:** revisar el flujo hasta el intérprete y las consultas preparadas; realizar comprobaciones controladas en un entorno autorizado. Si el intérprete es SQL, precisar la inyección SQL; la descripción no obliga a asumir que toda consulta es SQL.
- **Recomendación:** parametrización y APIs seguras según el intérprete, además de permisos mínimos y validación contextual.

### E5. Ausencia de regla de doble validación

- **Activo:** integridad del estado de los expedientes.
- **Usuario:** perfil autorizado para modificar estados; confirmar cuál es en la implementación.
- **Función:** transición de estados sensibles del trámite.
- **Comportamiento:** el flujo no incorpora una regla de doble validación.
- **Clasificación y sustento:** A06, conforme al enfoque del caso: el control está ausente en el diseño del proceso.
- **Contraste con A01:** A01 aplicaría si existe una regla de autorización definida y el sistema permite incumplirla. Si la regla no fue diseñada, la causa principal se orienta a A06.
- **Verificación propuesta:** revisar modelo de estados, requisitos, política institucional, criticidad y segregación de funciones. Confirmar si un mismo actor puede iniciar y autorizar la transición.
- **Recomendación:** definir estados, transiciones y validaciones según el riesgo; incorporar aprobación independiente donde corresponda.
- **Límite:** la doble validación no es obligatoria para toda operación por el solo hecho de usar OWASP. Su necesidad debe sustentarse en la política y el riesgo de la transición concreta.

### E6. Recuperación de contraseña predecible

- **Activo:** identidad y control de cuentas.
- **Usuario:** solicitante de recuperación y titular de la cuenta.
- **Función:** restablecimiento de credenciales.
- **Comportamiento:** pruebas de identidad o elementos del flujo son débiles y predecibles.
- **Clasificación y sustento:** A07. La recuperación forma parte del control de identidad y de la autenticación.
- **Contraste con A01:** restablecer una cuenta sin acreditar suficientemente la identidad no equivale a carecer de permisos sobre un expediente después de autenticarse.
- **Verificación propuesta:** analizar generación del token, vínculo con la cuenta, vencimiento, reutilización y límites de intentos; comprobarlo con cuentas de prueba.
- **Recomendación:** tokens criptográficamente aleatorios, de un solo uso y con vencimiento; protección contra intentos y verificación proporcional al riesgo.

### E7. Actualización sin verificación de integridad

- **Activo:** software instalado y proceso de actualización.
- **Usuario:** responsable o servicio que despliega la actualización.
- **Función:** incorporación de una nueva versión ejecutable.
- **Comportamiento:** la instalación acepta el paquete sin verificar suficientemente integridad y procedencia.
- **Clasificación y sustento:** A08. La confianza en el artefacto aceptado constituye el problema central descrito.
- **Contraste con A03:** el proceso de suministro puede contribuir; E7 enfatiza la verificación en el punto de aceptación. Una categoría alternativa requiere explicar por qué la causa principal es otra.
- **Verificación propuesta:** comprobar firmas, claves confiables, origen de hashes y rechazo de paquetes alterados en pruebas.
- **Recomendación:** validar integridad y autenticidad antes de instalar y detener el proceso si falla la validación. Un hash descargado junto al paquete desde la misma fuente comprometida no aporta por sí solo autenticidad suficiente.

### E8. Trazabilidad y alertas insuficientes

- **Activo:** registros de seguridad y capacidad de detección e investigación.
- **Usuario:** ejecutor de la operación sensible y personal de auditoría/monitoreo.
- **Función:** registrar y alertar sobre acciones relevantes.
- **Comportamiento:** no se puede reconstruir quién hizo qué y cuándo.
- **Clasificación y sustento:** A09. La evidencia describe fallos de observabilidad de seguridad.
- **Contraste con A10:** A10 trata la respuesta segura a condiciones excepcionales. Un error puede manejarse correctamente y seguir sin generar registro o alerta adecuados; también puede registrarse y manejarse de forma insegura.
- **Verificación propuesta:** ejecutar una operación ficticia sensible y revisar identidad del actor, acción, fecha/hora, objeto, resultado, correlación, conservación y alertas. Diferenciar eventos rutinarios de los que ameritan alertar.
- **Recomendación:** definir eventos de auditoría, proteger su integridad y acceso, sincronizar tiempos y generar alertas accionables. Evitar registrar contraseñas, tokens o información personal innecesaria.

## 4. Sustentación ante el «Abogado del Diablo»

### Defensa 1: E1 como A01 frente a A07

**Objeción:** «Se accedió a datos de otra persona; entonces falló la autenticación».

**Respuesta:** no basta con observar la identidad del titular del dato. Se debe determinar si el solicitante inició sesión con una identidad válida y si la petición mantiene esa identidad. Si eso se verifica, pero se entrega un expediente sobre el que carece de permiso, falla la autorización sobre el objeto: A01. Para sostenerlo, presentar cuentas de prueba, propiedad de los expedientes, petición y respuesta anonimizadas. Si la identidad puede falsificarse o la sesión se establece indebidamente, investigar también A07.

### Defensa 2: E5 como A06 frente a A01

**Objeción:** «Cambiar un estado sin segunda aprobación es siempre un fallo de permisos».

**Respuesta:** primero se revisa si existe una regla aprobada de segunda validación. La guía plantea que no existe esa regla en la política de negocio, por lo que orienta el análisis a A06. Si se demuestra que el requisito sí existe y la implementación lo omite o permite eludirlo, A01 puede ser una alternativa justificada o un hallazgo relacionado. La necesidad de la doble validación debe vincularse con el riesgo y la política institucional; no se presenta como una obligación universal.

## 5. Respuestas a las preguntas críticas

### 5.1 ¿Qué evidencia permite distinguir A01 de A07 si el problema aparece durante el inicio de sesión?

La ubicación visual del problema no determina la categoría. Debe identificarse qué control falla:

- Para A07: evidencia de que se puede establecer o recuperar una identidad indebidamente, aceptar credenciales inválidas o utilizar una sesión insegura.
- Para A01: evidencia de una identidad correctamente establecida a la que se permite acceder a recursos o funciones fuera de sus permisos.

Recoger peticiones y respuestas, validación de credenciales, sesión anonimizada, identidad reconocida por el servidor y permisos esperados. Por ejemplo, entrar como administrador sin acreditar esa identidad apunta a A07; iniciar sesión correctamente como ciudadano y recibir funciones administrativas por falta de autorización apunta a A01. Pueden coexistir ambos defectos.

### 5.2 ¿Puede un hallazgo ser técnicamente correcto y estar mal clasificado?

Sí. La observación puede ser reproducible, pero la categoría puede atribuir una causa que la evidencia no demuestra. Clasificar E1 como autenticación cuando la identidad es válida confunde identidad con permisos. Una clasificación errónea puede dirigir la corrección al equipo o control equivocado y distorsionar prioridades e indicadores de auditoría. Debe justificarse la causa principal y registrar relaciones con categorías próximas cuando corresponda.

### 5.3 Si el escáner reporta cero vulnerabilidades, ¿qué seguirías auditando manualmente?

Continuaría revisando, según el alcance, las diez categorías, con especial atención a:

1. A01: permisos por rol, objeto y operación con distintas cuentas.
2. A06: reglas de negocio, transiciones de estados y segregación de funciones.
3. A07: recuperación de cuentas, sesiones y flujos de identidad.
4. A09: calidad y protección de registros, y funcionamiento real de alertas.
5. A03 y A08: inventario/procedencia de componentes y validación de actualizaciones.
6. A02, A04, A05 y A10: configuración efectiva, criptografía, fronteras de interpretación y manejo de errores que el escáner no haya cubierto.

Un resultado cero solo indica que no se detectaron problemas mediante las comprobaciones, credenciales, entradas y condiciones empleadas. No constituye una prueba de ausencia de vulnerabilidades ni una autorización automática para producción.

## 6. Resolución del conflicto con el jefe de proyecto

La afirmación «el escáner confirmó que todo está cubierto» no está sustentada sin conocer cobertura, configuración, autenticación, exclusiones, reglas y resultados. Se solicita ese alcance, se contrasta con los activos y requisitos institucionales y se ejecutan las verificaciones manuales necesarias. Las ocho evidencias se clasifican con argumentos, impacto preliminar y datos faltantes, conservando trazabilidad.

Con la información disponible no corresponde afirmar que todos los controles son eficaces ni aprobar el despliegue. El auditor debe emitir una conclusión limitada a lo verificado y señalar pendientes, riesgos y condiciones para una eventual decisión de salida a producción.

## 7. Matriz normativa aplicada

| Referencia | Uso en la resolución | Evidencia esperada |
|---|---|---|
| OWASP Top 10:2025 | Clasificación de la causa o familia principal de riesgo. | Categoría candidata, criterio y contraste. |
| ASVS | Complementar la verificación de controles de autorización, autenticación, consultas, configuración y registros. | Versión y requisito/sección seleccionado, control esperado y resultado. |
| WSTG | Organizar las comprobaciones técnicas autorizadas. | Versión, prueba aplicable, precondiciones, pasos y resultados esperados/observados. |
| Política institucional | Determinar permisos, sensibilidad, doble aprobación, conservación de logs y aceptación de riesgos. | Documento, versión, sección y aprobación institucional. |

Los identificadores concretos de ASVS/WSTG deben seleccionarse de la edición utilizada antes de ejecutar las pruebas. No se inventan identificadores ni resultados de cumplimiento. La guía no adjunta una política institucional; se registra como información pendiente.

## 8. Producto esperado: aplicación al prototipo desarrollado

| Elemento | Resolución / implementación |
|---|---|
| Problema | Reducir errores de clasificación inicial mediante evidencia y contraste documentados. |
| Objetivo | Registrar evidencias y obtener categorías candidatas con motivos y límites explícitos. |
| RF01 | Registro textual con activo/perfil. El ZIP aporta señales adicionales con archivo y línea. |
| RF02 | Motor de reglas y selección manual de categoría OWASP 2025. |
| RF03 | Consulta de criterio, categoría cercana y contraste. |
| RF04 | Registro de justificación, impacto, datos faltantes y referencias contextuales. |
| RF05 | Revisión con observaciones y decisión. Si autor y revisor son la misma persona, registro explícito como autoevaluación. |
| RF06 | CSV de hallazgos potenciales y exportación separada de versiones revisadas; los estados quedan identificados. |
| Funcionamiento | Entrada → análisis → hipótesis → contraste → clasificación → justificación → revisión humana. |
| Riesgos y mitigación | Reglas explicables y revisiones para evitar automatización excesiva; datos ficticios; ZIP no ejecutado; separación de señales y decisiones; ausencia de dependencia de IA. |
| Conclusión | La herramienta apoya la decisión y organiza la evidencia; no sustituye al auditor. |

El análisis ZIP es complementario: no debe afirmarse que puede detectar automáticamente E1–E8 ni toda la cobertura OWASP. La revisión es una simulación sin autenticación de docentes. La autoevaluación no debe presentarse como una aprobación externa independiente.

## 9. Demostración sugerida para sustentar la práctica

1. Registrar un sistema académico llamado «Caso 7 - Plataforma de expedientes».
2. Ingresar E1–E8 como evidencias manuales del caso, conservando su identificador en el texto para diferenciarlo del ID interno.
3. Consultar las hipótesis del motor y seleccionar la categoría argumentada; corregir propuestas que no correspondan.
4. Usar el apartado 3 para elaborar la justificación, el contraste, el impacto y el dato faltante de cada ficha.
5. Registrar ASVS/WSTG como plan de verificación pendiente si no se han ejecutado pruebas; indicar que la política institucional no fue proporcionada.
6. Enviar a revisión y completar observaciones reales del docente o marcar autoevaluación si corresponde.
7. Exportar la matriz revisada como producto académico.

El ZIP de una tienda tecnológica puede servir para demostrar la función complementaria de análisis, pero sus hallazgos no reemplazan la resolución de las evidencias del caso de expedientes.

## 10. Conclusión

Las evidencias se orientan a A01, A02, A03, A05, A06, A07, A08 y A09, respectivamente. La clasificación debe sostenerse en el control que falla y contrastarse con categorías cercanas. ASVS, WSTG y la política institucional permiten pasar de una hipótesis a verificaciones contextualizadas. Un escáner no demuestra cobertura total y el prototipo no elimina la necesidad del juicio profesional.

## Fuentes

- Guía proporcionada: Caso_7_Auditoría_2026B.pdf, páginas 1–2.
- OWASP Top 10:2025: https://top10.owasp.org/2025/
- ASVS: https://owasp.org/www-project-application-security-verification-standard/
- WSTG: https://owasp.org/www-project-web-security-testing-guide/
