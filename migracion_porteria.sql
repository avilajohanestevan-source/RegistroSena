-- Migración: tipo de asistente, cuentas de portería y turnos.
-- Impórtala en phpMyAdmin (pestaña "Importar") sobre la base sena_evento
-- que ya tienes, después de migracion_reportes.sql. Se puede ejecutar más
-- de una vez sin problema.

-- Tipo de asistente elegido al registrarse (Aprendiz, Instructor,
-- Funcionario, Visitante, Contratista u Otro) y, si eligió "Otro", cuál.
ALTER TABLE asistentes
  ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) NOT NULL DEFAULT '' AFTER nombre,
  ADD COLUMN IF NOT EXISTS tipo_otro VARCHAR(60) NOT NULL DEFAULT '' AFTER tipo;

-- Personal de portería: inicia sesión en index.php con cédula y contraseña.
CREATE TABLE IF NOT EXISTS usuarios (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  cedula VARCHAR(20) NOT NULL,
  contrasena VARCHAR(255) NOT NULL,  -- hash de password_hash(), nunca la contraseña en texto
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cedula (cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turnos: desde que un portero inicia sesión hasta que la cierra, y en
-- qué punto de control estuvo.
CREATE TABLE IF NOT EXISTS turnos (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  punto VARCHAR(10) NOT NULL,        -- 'entrada', 'salida' o 'ambas'
  inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fin DATETIME NULL,
  PRIMARY KEY (id),
  KEY usuario_id (usuario_id),
  KEY inicio (inicio),
  CONSTRAINT turnos_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quién registró cada entrada/salida y cada aviso (queda NULL en los
-- registros anteriores a esta migración).
ALTER TABLE movimientos
  ADD COLUMN IF NOT EXISTS usuario_id INT NULL AFTER tipo,
  ADD INDEX IF NOT EXISTS usuario_id (usuario_id);
ALTER TABLE avisos
  ADD COLUMN IF NOT EXISTS usuario_id INT NULL AFTER mensaje;
