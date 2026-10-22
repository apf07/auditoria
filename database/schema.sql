CREATE TABLE IF NOT EXISTS proyectos (
 id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(160) NOT NULL, descripcion TEXT NOT NULL,
 alcance TEXT NOT NULL, fuente VARCHAR(200) NOT NULL, sha256 CHAR(64) NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS categorias (
 id CHAR(3) PRIMARY KEY, nombre VARCHAR(160) NOT NULL, criterio TEXT NOT NULL,
 cercana CHAR(3) NOT NULL, contraste TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hallazgos (
 id INT AUTO_INCREMENT PRIMARY KEY, proyecto_id INT NOT NULL, codigo VARCHAR(30) NOT NULL,
 titulo VARCHAR(200) NOT NULL, prioridad ENUM('alta','media','baja') NOT NULL,
 categoria_id CHAR(3) NOT NULL, activo VARCHAR(200) NOT NULL, perfil VARCHAR(160) NOT NULL,
 ubicacion TEXT NOT NULL, evidencia TEXT NOT NULL, codigo_fuente TEXT NOT NULL,
 impacto TEXT NOT NULL, recomendacion TEXT NOT NULL, pasos TEXT NOT NULL,
 esperado TEXT NOT NULL, referencia_asvs TEXT NOT NULL, referencia_wstg TEXT NOT NULL,
 origen ENUM('revision_zip','manual') NOT NULL, created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 UNIQUE KEY uq_hallazgo (proyecto_id,codigo),
 FOREIGN KEY (proyecto_id) REFERENCES proyectos(id), FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pruebas (
 id INT AUTO_INCREMENT PRIMARY KEY, hallazgo_id INT NOT NULL, autor VARCHAR(120) NOT NULL,
 entorno VARCHAR(200) NOT NULL, observado TEXT NOT NULL, evidencia_prueba TEXT NOT NULL,
 resultado ENUM('reproducido','no_reproducido','inconcluso') NOT NULL,
 categoria_id CHAR(3) NOT NULL, justificacion TEXT NOT NULL, contraste TEXT NOT NULL,
 impacto TEXT NOT NULL, politica TEXT NOT NULL, referencia_asvs TEXT NOT NULL, referencia_wstg TEXT NOT NULL,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 FOREIGN KEY (hallazgo_id) REFERENCES hallazgos(id), FOREIGN KEY (categoria_id) REFERENCES categorias(id),
 KEY idx_prueba (hallazgo_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS revisiones (
 id INT AUTO_INCREMENT PRIMARY KEY, prueba_id INT NOT NULL, revisor VARCHAR(120) NOT NULL,
 decision ENUM('confirmado','descartado','pendiente') NOT NULL, comentario TEXT NOT NULL,
 autoevaluacion TINYINT NOT NULL DEFAULT 0,
 created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
 FOREIGN KEY (prueba_id) REFERENCES pruebas(id), KEY idx_revision (prueba_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
