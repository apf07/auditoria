import re
import unicodedata

ENGINE = 'reglas-2025-v1'
RULES = {
 'A01': r'otro ciudadano|otro usuario|sin permiso|cambiando el id|expediente.{0,30}ajeno',
 'A02': r'por defecto|no endurecid|configuracion insegura|cabecera',
 'A03': r'libreria|dependencia|inventario de componentes|cadena de suministro',
 'A04': r'cifrado|criptograf|\bmd5\b|\bsha1\b',
 'A05': r'inyeccion|datos e instrucciones|sin separacion|consulta sql',
 'A06': r'doble validacion|regla de negocio|politica de negocio',
 'A07': r'contrasena|autenticacion|inicio de sesion|recuperacion de cuenta',
 'A08': r'actualizacion|integridad|firma digital',
 'A09': r'registro|alerta|reconstruir|trazabilidad',
 'A10': r'excepcion|condicion excepcional|manejo de errores',
}


def classify(text: str, categories: list[dict]) -> dict:
    normalized = ''.join(c for c in unicodedata.normalize('NFD', text.lower()) if unicodedata.category(c) != 'Mn')
    candidates = []
    for category in categories:
        match = re.search(RULES[category['id']], normalized)
        if match:
            candidates.append({**category, 'regla': category['id'], 'motivo': f"La regla {category['id']} encontró la señal textual «{match.group()}». No confirma explotación."})
    return {
        'motor_version': ENGINE,
        'candidatas': candidates,
        'categoria_sugerida': candidates[0]['id'] if len(candidates) == 1 else None,
        'explicacion': ('Hipótesis única de estas reglas: requiere contraste humano.' if len(candidates) == 1 else 'Hay hipótesis alternativas; compara sus causas antes de elegir.' if candidates else 'Evidencia insuficiente para estas reglas. Solicita más datos o clasifica manualmente.'),
    }
