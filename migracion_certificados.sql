-- Migración: certificados de asistencia. Impórtala en phpMyAdmin sobre la
-- base de datos del sistema. Se puede ejecutar más de una vez.

-- Plantilla única del certificado (una sola fila, id = 1), editable desde
-- el panel: textos por criterio, firmante, firma, logo, sello y color.
-- En los textos se pueden usar {nombre}, {cedula}, {evento}, {fechas},
-- {dias_asistidos}, {total_dias}, {tipo}, {charla} y {charla_horario}.
CREATE TABLE IF NOT EXISTS certificado_plantilla (
  id TINYINT NOT NULL,
  titulo VARCHAR(120) NOT NULL DEFAULT 'Certificado de asistencia',
  texto_completa TEXT NOT NULL,
  texto_parcial TEXT NOT NULL,
  texto_charla TEXT NOT NULL,
  pie VARCHAR(255) NOT NULL DEFAULT '',
  firmante_nombre VARCHAR(150) NOT NULL DEFAULT '',
  firmante_cargo VARCHAR(150) NOT NULL DEFAULT 'Director Académico',
  firma_archivo VARCHAR(255) NOT NULL DEFAULT '',   -- vacío = firma de ejemplo con el nombre
  logo_archivo VARCHAR(255) NOT NULL DEFAULT '',    -- vacío = logo del SENA
  mostrar_sello TINYINT(1) NOT NULL DEFAULT 1,
  color VARCHAR(7) NOT NULL DEFAULT '#39A900',
  actualizado_en DATETIME NULL,
  actualizado_por INT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantilla inicial: firma de ejemplo con el nombre del primer administrador.
INSERT IGNORE INTO certificado_plantilla (id, texto_completa, texto_parcial, texto_charla, firmante_nombre)
SELECT 1,
  'Se certifica que {nombre}, identificado(a) con cédula {cedula}, asistió al evento {evento} {fechas} y participó en las actividades programadas.\n\nEste certificado se expide en reconocimiento a las competencias adquiridas durante el evento.',
  'Se certifica que {nombre}, identificado(a) con cédula {cedula}, asistió a {dias_asistidos} de los {total_dias} días del evento {evento} {fechas} y participó en las actividades programadas en esas jornadas.\n\nEste certificado se expide en reconocimiento a las competencias adquiridas durante el evento.',
  'Se certifica que {nombre}, identificado(a) con cédula {cedula}, asistió a la charla «{charla}» ({charla_horario}), realizada en el marco del evento {evento}.',
  COALESCE((SELECT nombre FROM usuarios WHERE rol = 'admin' ORDER BY id LIMIT 1), 'Administrador del sistema');

-- Cada vez que se pulsa "Generar certificados" se crea un lote.
CREATE TABLE IF NOT EXISTS certificado_lotes (
  id INT NOT NULL AUTO_INCREMENT,
  evento_id INT NOT NULL,
  criterio VARCHAR(10) NOT NULL,              -- completa | parcial | charla
  detalle VARCHAR(255) NOT NULL DEFAULT '',   -- p. ej. "mínimo 3 días" o el nombre de la charla
  roles VARCHAR(255) NOT NULL DEFAULT '',     -- tipos de asistente incluidos (vacío = todos)
  total INT NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creado_por INT NULL,
  PRIMARY KEY (id),
  KEY evento (evento_id),
  CONSTRAINT certificado_lotes_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un certificado por persona, evento y criterio (clave: 'completa',
-- 'parcial' o 'charla:<id de la actividad>'). Guarda los datos de la persona
-- al emitirlo y un código para verificarlo.
CREATE TABLE IF NOT EXISTS certificados (
  id INT NOT NULL AUTO_INCREMENT,
  lote_id INT NOT NULL,
  evento_id INT NOT NULL,
  cedula VARCHAR(20) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT '',
  tipo_otro VARCHAR(60) NOT NULL DEFAULT '',
  correo VARCHAR(150) NOT NULL DEFAULT '',
  criterio VARCHAR(10) NOT NULL,
  clave VARCHAR(40) NOT NULL,
  detalle VARCHAR(255) NOT NULL DEFAULT '',     -- charla: título de la actividad
  horario VARCHAR(120) NOT NULL DEFAULT '',     -- charla: día y horas de la actividad
  dias_asistidos INT NOT NULL DEFAULT 0,
  total_dias INT NOT NULL DEFAULT 0,
  codigo VARCHAR(12) NOT NULL,
  emitido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY codigo (codigo),
  UNIQUE KEY evento_clave_cedula (evento_id, clave, cedula),
  KEY lote (lote_id),
  CONSTRAINT certificados_lote FOREIGN KEY (lote_id) REFERENCES certificado_lotes (id) ON DELETE CASCADE,
  CONSTRAINT certificados_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
