<?php
/**
 * Sesión del personal de portería. Cada portero tiene su cuenta (cédula +
 * contraseña) y al entrar elige en qué punto de control está. Mientras
 * tenga la sesión abierta queda un "turno" registrado, y cada entrada,
 * salida o aviso que registre queda a su nombre.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('sena_porteria');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function puntosControl() {
    return [
        'ambas'   => 'Entrada y salida',
        'entrada' => 'Portería de entrada',
        'salida'  => 'Portería de salida',
    ];
}

/** Datos del portero con la sesión abierta (id, nombre, cedula, punto, turno_id) o null. */
function usuarioActual() {
    return $_SESSION['usuario'] ?? null;
}

/** Para las páginas del panel: sin sesión, de vuelta a la página de inicio. */
function requerirSesion() {
    if (!usuarioActual()) {
        header('Location: index.php');
        exit;
    }
}

/** ¿El punto de control de la sesión permite registrar este movimiento ('entrada' o 'salida')? */
function puedeRegistrar($tipo) {
    $usuario = usuarioActual();
    return $usuario && ($usuario['punto'] === 'ambas' || $usuario['punto'] === $tipo);
}

function buscarUsuario(mysqli $conn, $cedula) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE cedula = ?");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function registrarUsuario(mysqli $conn, $nombre, $cedula, $contrasena) {
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, cedula, contrasena) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $nombre, $cedula, $hash);
    $stmt->execute();
    $stmt->close();
    return buscarUsuario($conn, $cedula);
}

/** Abre un turno para el portero en el punto de control elegido y lo deja con la sesión iniciada. */
function iniciarTurno(mysqli $conn, array $usuario, $punto) {
    $usuarioId = (int) $usuario['id'];
    $stmt = $conn->prepare("INSERT INTO turnos (usuario_id, punto) VALUES (?, ?)");
    $stmt->bind_param('is', $usuarioId, $punto);
    $stmt->execute();
    $turnoId = $conn->insert_id;
    $stmt->close();

    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'       => $usuarioId,
        'nombre'   => $usuario['nombre'],
        'cedula'   => $usuario['cedula'],
        'punto'    => $punto,
        'turno_id' => $turnoId,
    ];
}

/** Cierra el turno abierto (guarda la hora de salida) y la sesión. */
function cerrarTurno(mysqli $conn) {
    $usuario = usuarioActual();
    if ($usuario) {
        $turnoId = (int) $usuario['turno_id'];
        $stmt = $conn->prepare("UPDATE turnos SET fin = NOW() WHERE id = ? AND fin IS NULL");
        $stmt->bind_param('i', $turnoId);
        $stmt->execute();
        $stmt->close();
    }
    $_SESSION = [];
    session_destroy();
}
