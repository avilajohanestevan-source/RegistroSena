<?php
/**
 * Sesión del personal. Cada persona tiene su cuenta (cédula + contraseña)
 * con un rol y un permiso:
 *   - rol admin:   el administrador del evento. Crea y cierra eventos,
 *                  configura fecha y horario, crea los códigos de
 *                  registro, ve estadísticas, reportes y exportes.
 *   - rol portero: solo registra entradas y salidas en el control de acceso.
 *   - punto:       qué puede registrar — 'entrada', 'salida' o 'ambas'.
 *                  Lo asigna el administrador al crear el código, y la
 *                  pantalla de control muestra solo lo que le corresponde.
 * Mientras la sesión está abierta queda un "turno" registrado dentro del
 * evento activo, y cada entrada, salida o aviso queda a nombre de esa
 * persona.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('sena_porteria');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Permisos de registro de una cuenta. */
function puntosControl() {
    return [
        'ambas'   => 'Entrada y salida',
        'entrada' => 'Solo entrada',
        'salida'  => 'Solo salida',
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

/**
 * Página a la que se vuelve después de iniciar sesión cuando alguien
 * llegó por URL a una página del panel. Solo se aceptan páginas de este
 * mismo sistema, nunca direcciones de afuera.
 */
function destinoSeguro($destino) {
    $destino = (string) $destino;
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $destino) && strpos($destino, 'index.php') !== 0
        ? $destino
        : '';
}

/**
 * Para las páginas del panel: sin sesión se responde 401 (no autorizado)
 * con la alerta en pantalla, y de ahí se pasa al inicio de sesión. Si la
 * persona acababa de cerrar sesión, el mensaje se lo recuerda (lo marca
 * la cookie que deja salir.php).
 */
function requerirSesion() {
    if (usuarioActual()) {
        return;
    }
    $pagina = basename($_SERVER['PHP_SELF'] ?? '');
    $consulta = $_SERVER['QUERY_STRING'] ?? '';
    $destino = destinoSeguro($pagina . ($consulta !== '' ? '?' . $consulta : ''));
    $cerroSesion = isset($_COOKIE['sena_sesion_cerrada']);
    require __DIR__ . '/no_autorizado.php';
    exit;
}

/** Para las páginas del administrador: un portero vuelve al control de acceso con el aviso. */
function requerirAdmin() {
    requerirSesion();
    if (!esAdmin()) {
        header('Location: control.php?aviso=solo_admin');
        exit;
    }
}

/** A dónde va cada rol al iniciar sesión. */
function paginaInicioRol() {
    return esAdmin() ? 'estadisticas.php' : 'control.php';
}

/**
 * Vuelve a leer el nombre, el rol y el permiso de la cuenta en cada
 * página del panel, para que un cambio se note de inmediato. Si la cuenta
 * ya no existe, cierra la sesión.
 */
function sincronizarSesion(mysqli $conn) {
    $usuario = usuarioActual();
    if (!$usuario) {
        return;
    }
    $id = (int) $usuario['id'];
    $stmt = $conn->prepare("SELECT nombre, rol, punto FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php?aviso=login');
        exit;
    }
    $_SESSION['usuario']['nombre'] = $fila['nombre'];
    $_SESSION['usuario']['rol'] = $fila['rol'];
    $_SESSION['usuario']['punto'] = $fila['punto'];
}

/** ¿El permiso de la cuenta deja registrar este movimiento ('entrada' o 'salida')? */
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

function registrarUsuario(mysqli $conn, $nombre, $cedula, $contrasena, $rol = 'portero', $punto = 'ambas') {
    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, cedula, rol, punto, contrasena) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssss', $nombre, $cedula, $rol, $punto, $hash);
    $stmt->execute();
    $stmt->close();
    return buscarUsuario($conn, $cedula);
}

/** Cambia el rol y el permiso de una cuenta (lo hace el administrador). */
function actualizarUsuario(mysqli $conn, $id, $rol, $punto) {
    $id = (int) $id;
    $stmt = $conn->prepare("UPDATE usuarios SET rol = ?, punto = ? WHERE id = ?");
    $stmt->bind_param('ssi', $rol, $punto, $id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Abre el turno de la persona dentro del evento activo y deja la sesión
 * iniciada con el permiso que tiene su cuenta.
 */
function iniciarTurno(mysqli $conn, array $usuario, $eventoId) {
    $usuarioId = (int) $usuario['id'];
    $eventoId = (int) $eventoId;
    $punto = $usuario['punto'] ?? 'ambas';
    $stmt = $conn->prepare("INSERT INTO turnos (evento_id, usuario_id, punto) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $eventoId, $usuarioId, $punto);
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
