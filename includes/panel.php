<?php
/**
 * Arranque de las páginas del panel: conexión, funciones, sesión
 * obligatoria y evento del contexto (el activo). Sin sesión se vuelve a
 * index.php con el aviso "Es necesario iniciar sesión para acceder".
 * Las páginas que solo ve el administrador usan includes/panel_admin.php.
 *
 * Deja listas dos variables para la página:
 *   $eventoActual -> el evento activo (o null si no hay ninguno)
 *   $usuario      -> la cuenta con la sesión abierta
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
requerirSesion();
sincronizarSesion($conn);

$eventoActual = eventoActivo($conn);
fijarEventoContexto($eventoActual['id'] ?? 0);
$usuario = usuarioActual();
