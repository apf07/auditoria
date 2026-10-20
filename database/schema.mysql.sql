-- MySQL 8 / MariaDB. No borra ni reemplaza tablas existentes.
CREATE TABLE IF NOT EXISTS categorias_owasp (
 id VARCHAR(3) PRIMARY KEY,
 version VARCHAR(4) NOT NULL DEFAULT '2025',
 nombre VARCHAR(200) NOT NULL, criterio TEXT NOT NULL,
 categoria_cercana VARCHAR(3) NULL, contraste TEXT NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 updated_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
 FOREIGN KEY (categoria_cercana) REFERENCES categorias_owasp(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sistemas (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(200) NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS analisis_zip (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sistema_id INT NOT NULL,
 nombre_archivo TEXT NOT NULL, content_sha256 CHAR(64) NULL, resultado MEDIUMTEXT NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 FOREIGN KEY (sistema_id) REFERENCES sistemas(id),
 UNIQUE KEY idx_scan_fingerprint (sistema_id,content_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS evidencias (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, sistema_id INT NULL, archived_at VARCHAR(30) NULL,
 analisis_id INT NULL, ubicacion TEXT NULL, recomendacion TEXT NULL,
 texto TEXT NOT NULL, activo VARCHAR(200) NOT NULL, perfil VARCHAR(200) NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 updated_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
 FOREIGN KEY (sistema_id) REFERENCES sistemas(id), FOREIGN KEY (analisis_id) REFERENCES analisis_zip(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS clasificaciones (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, evidencia_id INT NOT NULL, categoria_id VARCHAR(3) NOT NULL,
 origen ENUM('reglas','manual') NOT NULL, motor_version VARCHAR(100) NOT NULL, explicacion TEXT NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 FOREIGN KEY (evidencia_id) REFERENCES evidencias(id), FOREIGN KEY (categoria_id) REFERENCES categorias_owasp(id),
 KEY idx_clasificacion_evidencia (evidencia_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS justificaciones (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, clasificacion_id INT NOT NULL, autor VARCHAR(120) NOT NULL,
 texto TEXT NOT NULL, contraste TEXT NOT NULL, impacto TEXT NOT NULL, dato_faltante TEXT NOT NULL,
 politica_institucional TEXT NOT NULL, referencia_asvs TEXT NOT NULL, prueba_wstg TEXT NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 FOREIGN KEY (clasificacion_id) REFERENCES clasificaciones(id),
 KEY idx_justificacion_clasificacion (clasificacion_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS revisiones (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, justificacion_id INT NOT NULL, revisor VARCHAR(120) NOT NULL,
 decision ENUM('aprobada','observada') NOT NULL, autoevaluacion TINYINT NOT NULL DEFAULT 0, observaciones TEXT NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 FOREIGN KEY (justificacion_id) REFERENCES justificaciones(id),
 KEY idx_revision_justificacion (justificacion_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
