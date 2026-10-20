import csv
import io

EXPORT_COLUMNS = ['sistema','analisis_id','archivo_zip','ubicacion','recomendacion','evidencia_id','evidencia','activo','perfil','categoria_id','categoria','justificacion','contraste','impacto','dato_faltante','politica_institucional','referencia_asvs','prueba_wstg','autor','revisor','autoevaluacion','decision','observaciones','fecha_revision']


def safe_cell(value):
    text = '' if value is None else str(value)
    # Impedir interpretación de fórmulas al abrir el CSV en Excel.
    return "'" + text if text.lstrip().startswith(('=', '+', '-', '@')) else text


def reviewed_csv(rows: list[dict]) -> str:
    output = io.StringIO(newline='')
    writer = csv.writer(output, delimiter=';', quoting=csv.QUOTE_ALL, lineterminator='\r\n')
    writer.writerow(EXPORT_COLUMNS)
    for row in rows:
        if row.get('revision_id') and row.get('decision') in ('aprobada', 'observada'):
            writer.writerow([safe_cell(row.get(key)) for key in EXPORT_COLUMNS])
    return '\ufeff' + output.getvalue()
