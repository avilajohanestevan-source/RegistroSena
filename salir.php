<?php
/**
 * Cierra la sesión del portero (y su turno) y vuelve a la página de inicio.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

cerrarTurno($conn);
header('Location: index.php');
exit;
