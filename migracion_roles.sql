-- Migración: roles (administrador y portero).
-- Impórtala en phpMyAdmin (pestaña "Importar") sobre la base sena_evento
-- que ya tienes, después de migracion_codigos.sql. Se puede ejecutar más
-- de una vez sin problema.

-- Rol de cada cuenta:
--   admin   -> maneja el evento (fecha y horario), crea los códigos de
--              registro, ve estadísticas y exporta a Excel/PDF.
--   portero -> solo registra entradas y salidas en el control de acceso.
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS rol VARCHAR(10) NOT NULL DEFAULT 'portero' AFTER cedula;

-- Rol que tendrá la cuenta que se cree con cada código.
ALTER TABLE codigos_porteria
  ADD COLUMN IF NOT EXISTS rol VARCHAR(10) NOT NULL DEFAULT 'portero' AFTER cedula;

-- Si todavía no hay ningún administrador, la cuenta más antigua pasa a serlo.
UPDATE usuarios SET rol = 'admin'
WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM usuarios) AS primera)
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM usuarios WHERE rol = 'admin') AS admins);
