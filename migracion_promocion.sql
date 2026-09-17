-- Migración: imagen promocional por evento e invitaciones por correo o por
-- enlace público. Impórtala en phpMyAdmin. Se puede ejecutar más de una vez.

-- Imagen promocional del evento (PNG, JPG o SVG en uploads/eventos, se
-- sirve con evento_imagen.php), su texto alternativo y dónde se muestra:
-- imagen_en_pagina = 1 -> correo y página de registro; 0 -> solo en el correo.
ALTER TABLE eventos
  ADD COLUMN IF NOT EXISTS imagen_promo VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS imagen_alt VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS imagen_en_pagina TINYINT(1) NOT NULL DEFAULT 1;

-- Invitaciones a personas nuevas (solo nombre y correo: la cédula llega
-- cuando se registran), de dónde vino cada una y cuándo abrió su enlace.
--   origen = 'email_link'  -> se le envió un correo con su enlace (token)
--   origen = 'public_link' -> se registró con el enlace público del evento
ALTER TABLE invitaciones
  MODIFY cedula VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS origen VARCHAR(12) NOT NULL DEFAULT 'email_link',
  ADD COLUMN IF NOT EXISTS abierto_en DATETIME NULL;

ALTER TABLE invitaciones ADD INDEX IF NOT EXISTS evento_correo (evento_id, correo);
