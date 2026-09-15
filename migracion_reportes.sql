-- Migración: Reportes, avisos por correo y horario del evento.
-- Impórtala en phpMyAdmin (pestaña "Importar") sobre la base sena_evento
-- que ya tienes. Se puede ejecutar más de una vez sin problema.

-- Registro de los correos de aviso enviados desde Reportes (por ejemplo,
-- a quien entró al evento y no registró su salida).
CREATE TABLE IF NOT EXISTS notificaciones (
  id INT NOT NULL AUTO_INCREMENT,
  cedula VARCHAR(20) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  dia DATE NOT NULL,                      -- día del reporte al que corresponde el aviso
  enviado TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = se envió, 0 = falló
  detalle VARCHAR(255) NOT NULL DEFAULT '',
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cedula (cedula),
  KEY dia (dia),
  CONSTRAINT notificaciones_ibfk_1 FOREIGN KEY (cedula) REFERENCES asistentes (cedula) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El tipo de aviso pasa de ENUM a texto para admitir el nuevo
-- 'fuera_de_horario' (entrada intentada fuera de la fecha u hora del evento).
ALTER TABLE avisos MODIFY tipo VARCHAR(40) NOT NULL;

-- La fecha y el horario del evento se guardan en `configuracion` con las
-- claves fecha_inicio, fecha_fin, hora_inicio y hora_fin (se crean solas
-- la primera vez que se guardan desde el Inicio del panel).
