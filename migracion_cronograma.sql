-- Migración: cronograma por día de cada evento. Impórtala en phpMyAdmin
-- sobre la base de datos del sistema. Se puede ejecutar más de una vez.

-- Cada fila es una actividad de un día del evento. `dia` es el número de
-- día (1 = primer día); `fecha` es la fecha de ese día, que se recalcula
-- si cambian las fechas del evento (queda vacía si el evento no tiene fechas).
CREATE TABLE IF NOT EXISTS cronograma (
  id INT NOT NULL AUTO_INCREMENT,
  evento_id INT NOT NULL,
  dia INT NOT NULL DEFAULT 1,
  fecha VARCHAR(10) NOT NULL DEFAULT '',
  titulo VARCHAR(150) NOT NULL,
  descripcion VARCHAR(255) NOT NULL DEFAULT '',
  hora_inicio VARCHAR(5) NOT NULL,           -- 'HH:MM'
  hora_fin VARCHAR(5) NOT NULL,
  ubicacion VARCHAR(150) NOT NULL DEFAULT '',
  responsable VARCHAR(150) NOT NULL DEFAULT '',
  orden INT NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY evento_dia (evento_id, dia, hora_inicio),
  CONSTRAINT cronograma_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cómo se arma el cronograma de un evento de varios días:
-- 'mismo' = el mismo horario para todos los días, 'por_dia' = uno distinto por día.
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS cronograma_modo VARCHAR(10) NOT NULL DEFAULT 'mismo';
