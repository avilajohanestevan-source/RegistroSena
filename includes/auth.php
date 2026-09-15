<?php
/**
 * Sesión del personal. Cada persona tiene su cuenta (cédula + contraseña)
 * con un rol:
 *   - admin:   el administrador del evento. Configura fecha y horario,
 *              crea los códigos de registro, ve estadísticas y exporta.
 *   - portero: solo registra entradas y salidas en el control de acceso.
 * Al entrar se elige el punto de control. Mientras la sesión está
 * abierta queda un "turno" registrado, y cada entrada, salida o aviso que
 * se registre queda a nombre de esa persona.
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

function rolesUsuario() {
    return [
        'portero' => 'Portero',
        'admin'   => 'Administrador',
    ];
}

/** Datos de la cuenta con la sesión abierta (id, nombre, cedula, rol, punto, turno_id) o null. */
function usuarioActual() {
    return $_SESSION['usuario'] ?? null;
}

function esAdmin() {
    $usuario = usuarioActual();
    return $usuario && ($usuario['rol'] ?? '') === 'admin';
}

/** Para las páginas del panel: sin sesión, de vuelta a la página de inicio. */
function requerirSesion() {
    if (!usuarioActual()) {
        header('Location: index.php');
        exit;
    }
}

/** Para las páginas del administrador: un portero vuelve al control de acceso. */
function requerirAdmin() {
    requerirSesion();
    if (!esAdmin()) {
        header('Location: control.php');
        exit;
    }
}

/** A dónde va cada rol al iniciar sesión. */
function paginaInicioRol() {
    return esAdmin() ? 'estadisticas.php' : 'control.php';
}

/**
 * Vuelve a leer el nombre y el rol de la cuenta en cada página del panel,
 * para que un cambio de rol se note de inmediato. Si la cuenta ya no
 * existe, cierra la sesión.
 */
function sincronizarSesion(mysqli $conn) {
    $usuario = usuarioActual();
    if (!$usuario) {
        return;
    }
    $id = (int) $usuario['id'];
    $stmt = $conn->prepare("SELECT nombre, rol FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }
    $_SESSION['usuario']['nombre'] = $fila['nombre'];
    $_SESSION['usuario']['rol'] = $fila['rol'];
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

function registrarUsuario(mysqli $conn, $nombre, $cedula, $contrasena, $rol = 'portero') {
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, cedula, rol, contrasena) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $nombre, $cedula, $rol, $hash);
    $stmt->execute();
    $stmt->close();
    return buscarUsuario($conn, $cedula);
}

/** Abre un turno en el punto de control elegido y deja la sesión iniciada. */
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
        'rol'      => $usuario['rol'] ?? 'portero',
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
