<?php
/**
 * Cierra la sesión (y el turno) y vuelve a la página de inicio con el
 * mensaje "Sesión cerrada".
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

cerrarTurno($conn);
// Marca que la sesión se cerró, para que el aviso lo diga si la persona
// intenta volver por URL (la marca dura 10 minutos).
setcookie('sena_sesion_cerrada', '1', ['expires' => time() + 600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
header('Location: index.php?aviso=salida');
exit;
