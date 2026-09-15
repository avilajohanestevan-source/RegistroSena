-- Migración: códigos de registro del personal de portería.
-- Impórtala en phpMyAdmin (pestaña "Importar") sobre la base sena_evento
-- que ya tienes, después de migracion_porteria.sql. Se puede ejecutar más
-- de una vez sin problema.

-- Cada código se crea en la pestaña "Portería" para una persona, se le
-- envía por correo y sirve para crear UNA sola cuenta en index.php.
CREATE TABLE IF NOT EXISTS codigos_porteria (
  id INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(16) NOT NULL,              -- 8 caracteres, se muestra como XXXX-XXXX
  nombre VARCHAR(150) NOT NULL,             -- para quién es el código
  correo VARCHAR(150) NOT NULL DEFAULT '',
  cedula VARCHAR(20) NOT NULL DEFAULT '',   -- si se llena, solo esa cédula puede usarlo
  creado_por INT NULL,                      -- portero que lo creó
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_en DATETIME NULL,                 -- último envío por correo
  usado_por INT NULL,                       -- cuenta que se creó con él
  usado_en DATETIME NULL,
  anulado TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY codigo (codigo),
  KEY creado_por (creado_por),
  KEY usado_por (usado_por),
  CONSTRAINT codigos_porteria_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT codigos_porteria_usuario FOREIGN KEY (usado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
