<?php
/**
 * Configuración de conexión a la base de datos.
 *
 * Para pruebas locales (XAMPP / WAMP / MAMP / Laragon) estos valores por
 * defecto normalmente funcionan sin tocar nada: solo hay que crear la base
 * importando database.sql.
 *
 * Cuando esto se suba a un servidor en la nube, solo hay que cambiar estas
 * cuatro constantes por los datos que te entregue el proveedor de hosting.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sena_evento');

// Zona horaria usada para las fechas de registro y de entrada/salida.
date_default_timezone_set('America/Bogota');

/**
 * Envío de correo (tarjeta con QR al registrarse), por SMTP.
 *
 * La función mail() de PHP no funciona en XAMPP porque necesita un
 * servidor de correo instalado en Windows — por eso el envío se hace por
 * SMTP: PHP se conecta directamente a un servidor de correo real (Gmail
 * en este caso), tal como lo haría cualquier programa de correo. Esto
 * funciona igual en localhost que cuando el proyecto se suba a un
 * servidor en la nube — no hay que cambiar nada de esta parte.
 *
 * Para usarlo con una cuenta de Gmail (recomendado si no tienes un
 * correo institucional con SMTP propio):
 *   1. Entra a https://myaccount.google.com/security con la cuenta de
 *      Gmail que va a enviar los correos.
 *   2. Activa "Verificación en dos pasos" (es obligatorio para el
 *      siguiente paso, aunque no la uses para nada más).
 *   3. Busca "Contraseñas de aplicaciones" (o entra directo a
 *      https://myaccount.google.com/apppasswords), crea una nueva y
 *      ponle un nombre como "Evento SENA". Google te da un código de 16
 *      letras — esa es la contraseña que va abajo en SMTP_PASS (NO la
 *      contraseña normal de tu Gmail).
 *   4. Reemplaza SMTP_USER por el correo de Gmail completo, y SMTP_PASS
 *      por el código de 16 letras (puedes dejarlo con o sin espacios).
 *
 * Mientras SMTP_USER esté vacío, el sistema simplemente no intenta
 * enviar correos (el registro sigue funcionando normal, solo que sin ese
 * paso adicional) — así puedes seguir probando antes de configurar Gmail.
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'avilajohanestevan@gmail.com');   // Ej: 'eventos.sena@gmail.com'
define('SMTP_PASS', 'rbsx vktd jecr zkpg');   // La contraseña de aplicación de 16 letras (no la del correo)
define('SMTP_FROM_NAME', 'Control de ingreso SENA');
define('EMAIL_HABILITADO', SMTP_USER !== '' && SMTP_PASS !== '');
