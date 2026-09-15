<?php
/**
 * Arranque de las páginas del panel de portería: conexión, funciones y
 * sesión obligatoria (sin sesión se vuelve a index.php para iniciarla).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
requerirSesion();
