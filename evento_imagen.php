<?php
/**
 * Sirve la imagen promocional de un evento (?id=ID). Es pública: la usan
 * la página de registro, la invitación y las pantallas de la sede.
 * &descarga=1 la baja como archivo (para imprimirla o ponerla en pantallas).
 * Los SVG se sirven con una política que no deja ejecutar nada.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$evento = eventoPorId($conn, $_GET['id'] ?? 0);
$ruta = $evento ? rutaImagenEvento($evento) : null;
if (!$ruta) {
    http_response_code(404);
    exit;
}

$extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
$tipos = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'];
$base = preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $evento['nombre'])));

header('Content-Type: ' . $tipos[$extension]);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: ' . (isset($_GET['descarga']) ? 'attachment' : 'inline') . '; filename="promocion_' . trim($base, '_') . '.' . $extension . '"');
readfile($ruta);
