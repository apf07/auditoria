export function toCSV(headers,rows){
 const cell=value=>{let text=String(value??'');if(/^[\s]*[=+@-]/.test(text))text="'"+text;return '"'+text.replace(/"/g,'""')+'"';};
 return '\ufeff'+[headers,...rows].map(row=>row.map(cell).join(';')).join('\r\n');
}
export function downloadCSV(filename,headers,rows){
 const url=URL.createObjectURL(new Blob([toCSV(headers,rows)],{type:'text/csv;charset=utf-8'}));
 const link=document.createElement('a');link.href=url;link.download=filename;link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
}

export function evaluationRows(scan,rows,catalog){
 const result=scan.resultado;
 const isUrl=result.origin==='url';
 const justified=rows.filter(r=>r.justificacion_id).length;
 const reviewed=rows.filter(r=>r.revision_id).length;
 const categoryIDs=[...new Set(result.findings.map(f=>f.category))];
 const contrasts=categoryIDs.map(id=>{const c=catalog.find(item=>item.id===id);return c?`${id}: ${c.criterio} Contraste con ${c.categoria_cercana}: ${c.contraste}`:id;}).join(' | ');
 return [
  ['Descripción del problema','Reducir errores de clasificación inicial de evidencias de seguridad web.','Se organiza la evidencia del sistema analizado y se conservan categorías candidatas; la revisión de reglas no demuestra por sí sola una vulnerabilidad.','Contexto'],
  ['Objetivo','Ingresar evidencia y obtener una categoría candidata con explicación auditable.',`${result.scanned} ${isUrl?'respuestas HTTP revisadas':'archivos revisados'}, ${result.skipped} omitidos y ${result.findings.length} señales potenciales. ${rows.length} evidencias activas asociadas a este análisis.`,result.capped?'Análisis parcial':'Análisis terminado'],
  ['RF01','Registrar evidencia textual.',`${rows.length} evidencias activas registradas con descripción y ubicación (${isUrl?'URL observada':'archivo y línea'}).`,rows.length?'Generado':'Sin señales activas'],
  ['RF02','Seleccionar categoría OWASP candidata.',categoryIDs.length?`Hipótesis detectadas: ${categoryIDs.join(', ')}. Pueden corregirse al justificar.`:'Sin hipótesis detectadas por estas reglas. Continuar con verificación manual.',categoryIDs.length?'Candidatas generadas':'Sin coincidencias'],
  ['RF03','Mostrar criterios de decisión y categorías cercanas.',contrasts||'Sin categorías que contrastar automáticamente. La selección manual permite consultar criterios.',categoryIDs.length?'Criterios disponibles':'Requiere más evidencia'],
  ['RF04','Registrar justificación del estudiante.',`${justified} de ${rows.length} evidencias activas con justificación guardada. Usar «Continuar» en la matriz para contrastar y fundamentar.`,justified===rows.length&&rows.length?'Registrado':'Pendiente de sustento humano'],
  ['RF05','Permitir revisión del auditor/docente.',`${reviewed} de ${rows.length} evidencias activas revisadas. ${rows.filter(r=>r.decision==='aprobada').length} fichas aprobadas; ${rows.filter(r=>r.decision==='observada').length} observadas. Revisión académica sin autenticación.`,reviewed===rows.length&&rows.length?'Revisado':'Pendiente de revisión humana'],
  ['RF06','Exportar matriz de clasificación.','«Exportar hallazgos» incluye señales pendientes, identificadas como tales. «Exportar revisadas» incluye únicamente fichas con revisión actual.','Exportación disponible'],
  ['Matriz normativa','OWASP Top 10:2025; ASVS; WSTG; política institucional.',`${rows.filter(r=>r.referencia_asvs).length} fichas con referencia ASVS; ${rows.filter(r=>r.prueba_wstg).length} con prueba WSTG; ${rows.filter(r=>r.politica_institucional).length} con contexto institucional declarado. Su registro no acredita cumplimiento.`, 'Verificación humana requerida'],
  ['Funcionamiento','Entrada → Análisis → Hipótesis → Contraste → Clasificación → Justificación → Revisión humana.','El análisis por enlace o ZIP aporta evidencia y categorías candidatas. El contraste, la justificación y la revisión se completan en la ficha.','Flujo disponible'],
  ['Riesgos','Automatización excesiva, falsos positivos/negativos, clasificación fuera de contexto, exposición de datos y dependencia de IA.',result.scope+' Esta versión usa reglas locales, sin IA.','Límites informados'],
  ['Conclusión esperada','El prototipo apoya la decisión; no reemplaza el juicio del auditor.','Validar causa, contexto y controles antes de confirmar cada posible vulnerabilidad. Cero coincidencias no significa sistema seguro.','Decisión humana'],
 ];
}

