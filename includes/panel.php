<?php
/**
 * Arranque de las páginas del panel: conexión, funciones y sesión
 * obligatoria (sin sesión se vuelve a index.php para iniciarla). Las
 * páginas que solo ve el administrador usan includes/panel_admin.php.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
requerirSesion();
sincronizarSesion($conn);
