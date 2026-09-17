<?php
/**
 * Pulso del evento activo (JSON) para la actualización en tiempo real:
 * cuántos están adentro, afuera y registrados, y el id del último
 * movimiento. El control de acceso y las estadísticas lo consultan cada
 * pocos segundos para mostrar los números al día y avisar cuando otra
 * persona registra una entrada o una salida (ver assets/js/app.js).
 */
require_once __DIR__ . '/includes/panel.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$evento = eventoContexto();
$conteo = contarEstados($conn);

$ultimo = 0;
$avisos = 0;
if ($evento > 0) {
    $stmt = $conn->prepare(
        "SELECT (SELECT COALESCE(MAX(id), 0) FROM movimientos WHERE evento_id = ?) AS ultimo,
                (SELECT COUNT(*) FROM avisos WHERE evento_id = ?) AS avisos"
    );
    $stmt->bind_param('ii', $evento, $evento);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $ultimo = (int) $fila['ultimo'];
    $avisos = (int) $fila['avisos'];
}

echo json_encode([
    'evento'  => $evento,
    'dentro'  => $conteo['dentro'],
    'fuera'   => $conteo['fuera'],
    'total'   => $conteo['total'],
    'ultimo'  => $ultimo,
    'avisos'  => $avisos,
], JSON_UNESCAPED_UNICODE);
