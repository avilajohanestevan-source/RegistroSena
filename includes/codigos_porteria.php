<?php
/**
 * Códigos de registro del personal de portería (tabla codigos_porteria).
 * Cada código se crea en la pestaña Portería para una persona, se le
 * envía por correo y sirve para crear UNA sola cuenta en index.php (si se
 * le asignó una cédula, solo con esa cédula). Mientras no exista ningún
 * portero, la primera cuenta se crea con el código inicial de config.php
 * (CODIGO_REGISTRO_PORTERIA).
 */

/** Deja un código en mayúsculas y sin guiones ni espacios ("k7m2-p9qx" → "K7M2P9QX"). */
function normalizarCodigo($texto) {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $texto));
}

/** Código para mostrar, en dos grupos de cuatro: "K7M2-P9QX". */
function formatoCodigo($codigo) {
    return strlen($codigo) === 8 ? substr($codigo, 0, 4) . '-' . substr($codigo, 4) : $codigo;
}

/** Código nuevo de 8 caracteres, sin los que se confunden al copiarlos (0/O, 1/I/L). */
function generarCodigo() {
    $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $codigo = '';
    for ($i = 0; $i < 8; $i++) {
        $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $codigo;
}

/** Enlace que abre el registro de portería con el código ya escrito. */
function urlRegistroPorteria(array $codigo) {
    return urlDelSistema('index.php') . '?modo=registro&codigo=' . formatoCodigo($codigo['codigo']);
}

function hayPorteros(mysqli $conn) {
    return (int) $conn->query("SELECT COUNT(*) c FROM usuarios")->fetch_assoc()['c'] > 0;
}

function buscarCodigoPorteria(mysqli $conn, $codigo) {
    $stmt = $conn->prepare("SELECT * FROM codigos_porteria WHERE codigo = ?");
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function codigoPorId(mysqli $conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM codigos_porteria WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/** 'disponible', 'usado' o 'anulado'. */
function estadoCodigo(array $codigo) {
    if ((int) $codigo['anulado'] === 1) return 'anulado';
    if ($codigo['usado_en'] !== null) return 'usado';
    return 'disponible';
}

/**
 * Revisa el código con el que alguien se está registrando (ya
 * normalizado) para la cédula que escribió. Devuelve [fila del código o
 * null, mensaje de error o '']. La fila es null sin error solo cuando se
 * usó el código inicial de config.php para crear la primera cuenta.
 */
function validarCodigoPorteria(mysqli $conn, $codigo, $cedula) {
    if ($codigo === '') {
        return [null, 'Escribe el código de registro que te enviaron.'];
    }
    if (!hayPorteros($conn) && hash_equals(normalizarCodigo(CODIGO_REGISTRO_PORTERIA), $codigo)) {
        return [null, ''];
    }
    $fila = buscarCodigoPorteria($conn, $codigo);
    if (!$fila || estadoCodigo($fila) === 'anulado') {
        return [null, 'El código no es válido. Pídele uno nuevo al coordinador del evento.'];
    }
    if (estadoCodigo($fila) === 'usado') {
        return [null, 'Este código ya se usó. Cada código sirve para crear una sola cuenta.'];
    }
    if ($fila['cedula'] !== '' && $fila['cedula'] !== $cedula) {
        return [null, 'Este código fue asignado a otra cédula.'];
    }
    return [$fila, ''];
}

function crearCodigoPorteria(mysqli $conn, $nombre, $correo, $cedula, $creadoPor) {
    do {
        $codigo = generarCodigo();
    } while (buscarCodigoPorteria($conn, $codigo));

    $stmt = $conn->prepare(
        "INSERT INTO codigos_porteria (codigo, nombre, correo, cedula, creado_por) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssssi', $codigo, $nombre, $correo, $cedula, $creadoPor);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return codigoPorId($conn, $id);
}

/**
 * Marca el código como usado por la cuenta nueva. Devuelve false si
 * mientras tanto ya lo había usado otra persona o se anuló.
 */
function marcarCodigoUsado(mysqli $conn, $codigoId, $usuarioId) {
    $stmt = $conn->prepare(
        "UPDATE codigos_porteria SET usado_por = ?, usado_en = NOW() WHERE id = ? AND usado_en IS NULL AND anulado = 0"
    );
    $stmt->bind_param('ii', $usuarioId, $codigoId);
    $stmt->execute();
    $ok = $stmt->affected_rows === 1;
    $stmt->close();
    return $ok;
}

function marcarCodigoEnviado(mysqli $conn, $codigoId) {
    $stmt = $conn->prepare("UPDATE codigos_porteria SET enviado_en = NOW() WHERE id = ?");
    $stmt->bind_param('i', $codigoId);
    $stmt->execute();
    $stmt->close();
}

/** Solo se pueden anular códigos que todavía no se han usado. */
function anularCodigoPorteria(mysqli $conn, $codigoId) {
    $stmt = $conn->prepare("UPDATE codigos_porteria SET anulado = 1 WHERE id = ? AND usado_en IS NULL");
    $stmt->bind_param('i', $codigoId);
    $stmt->execute();
    $stmt->close();
}

function listarCodigosPorteria(mysqli $conn) {
    return $conn->query(
        "SELECT c.*, u.nombre AS usado_por_nombre, cr.nombre AS creado_por_nombre
         FROM codigos_porteria c
         LEFT JOIN usuarios u ON u.id = c.usado_por
         LEFT JOIN usuarios cr ON cr.id = c.creado_por
         ORDER BY c.creado_en DESC, c.id DESC"
    )->fetch_all(MYSQLI_ASSOC);
}

function listarPorteros(mysqli $conn) {
    return $conn->query(
        "SELECT u.id, u.nombre, u.cedula, u.creado_en,
                (SELECT MAX(t.inicio) FROM turnos t WHERE t.usuario_id = u.id) AS ultimo_turno,
                (SELECT COUNT(*) FROM turnos t WHERE t.usuario_id = u.id) AS turnos
         FROM usuarios u
         ORDER BY u.nombre"
    )->fetch_all(MYSQLI_ASSOC);
}
