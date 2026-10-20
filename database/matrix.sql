SELECT e.id evidencia_id,e.texto evidencia,e.activo,e.perfil,e.sistema_id,e.archived_at,e.analisis_id,e.ubicacion,e.recomendacion,s.nombre sistema,az.nombre_archivo archivo_zip,
        c.id clasificacion_id,c.categoria_id,cat.nombre categoria,c.explicacion,
        j.id justificacion_id,j.autor,j.texto justificacion,j.contraste,j.impacto,
        j.dato_faltante,j.politica_institucional,j.referencia_asvs,j.prueba_wstg,
        r.id revision_id,r.revisor,r.decision,r.autoevaluacion,r.observaciones,r.created_at fecha_revision,
        e.created_at fecha_evidencia
      FROM evidencias e
      LEFT JOIN sistemas s ON s.id=e.sistema_id
      LEFT JOIN analisis_zip az ON az.id=e.analisis_id
      LEFT JOIN clasificaciones c ON c.id=(SELECT MAX(id) FROM clasificaciones WHERE evidencia_id=e.id)
      LEFT JOIN categorias_owasp cat ON cat.id=c.categoria_id
      LEFT JOIN justificaciones j ON j.id=(SELECT MAX(id) FROM justificaciones WHERE clasificacion_id=c.id)
      LEFT JOIN revisiones r ON r.id=(SELECT MAX(id) FROM revisiones WHERE justificacion_id=j.id)
      WHERE e.archived_at IS NULL
      ORDER BY e.id DESC;
