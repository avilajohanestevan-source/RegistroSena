<?php
/**
 * Eventos. Cada evento es una unidad con sus propios asistentes, entradas
 * y salidas, avisos y estadísticas: al crear uno nuevo, el panel arranca
 * limpio y el historial anterior queda guardado bajo el evento archivado.
 * Solo puede haber un evento 'activo' a la vez.
 *
 * Todas las consultas del sistema se filtran por el "evento del
 * contexto": normalmente el activo y, en las páginas del administrador,
 * el que se esté consultando (?evento=ID). Si no hay evento activo, el
 * contexto queda en 0 y las consultas no devuelven nada.
 */

/** Fija el evento cuyas consultas se van a hacer en esta página. */
function fijarEventoContexto($id) {
    $GLOBALS['sena_evento_contexto'] = (int) $id;
    unset($GLOBALS['sena_evento_fila']);
}

function eventoContexto() {
    return (int) ($GLOBALS['sena_evento_contexto'] ?? 0);
}

/** Los datos del evento del contexto (nombre, fechas, horario, estado). */
function eventoContextoFila(mysqli $conn) {
    if (!isset($GLOBALS['sena_evento_fila'])) {
        $GLOBALS['sena_evento_fila'] = eventoPorId($conn, eventoContexto());
    }
    return $GLOBALS['sena_evento_fila'];
}

function eventoActivo(mysqli $conn) {
    $fila = $conn->query("SELECT * FROM eventos WHERE estado = 'activo' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    return $fila ?: null;
}

function eventoPorId(mysqli $conn, $id) {
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/** ¿El evento ya terminó según su fecha y hora de cierre? */
function eventoTerminado(array $evento) {
    if ($evento['estado'] !== 'activo') {
        return true;
    }
    if ($evento['fecha_fin'] === '' && $evento['fecha_inicio'] === '') {
        return false; // sin fechas, el evento no caduca solo
    }
    $ultimoDia = $evento['fecha_fin'] !== '' ? $evento['fecha_fin'] : $evento['fecha_inicio'];
    $hoy = date('Y-m-d');
    if ($hoy < $ultimoDia) {
        return false;
    }
    if ($hoy > $ultimoDia) {
        return true;
    }
    return $evento['hora_fin'] !== '' && date('H:i') > $evento['hora_fin'];
}

/**
 * Eventos con sus métricas clave (lo esencial que se muestra del
 * archivo): registrados, cuántos asistieron, movimientos e
 * irregularidades. El activo va de primero.
 */
function listarEventos(mysqli $conn) {
    return $conn->query(
        "SELECT e.*,
                (SELECT COUNT(*) FROM asistentes a WHERE a.evento_id = e.id) AS registrados,
                (SELECT COUNT(DISTINCT m.cedula) FROM movimientos m WHERE m.evento_id = e.id AND m.tipo = 'entrada') AS asistieron,
                (SELECT COUNT(*) FROM movimientos m WHERE m.evento_id = e.id) AS movimientos,
                (SELECT COUNT(*) FROM avisos av WHERE av.evento_id = e.id) AS irregularidades,
                u.nombre AS creado_por_nombre, c.nombre AS cerrado_por_nombre
         FROM eventos e
         LEFT JOIN usuarios u ON u.id = e.creado_por
         LEFT JOIN usuarios c ON c.id = e.cerrado_por
         ORDER BY (e.estado = 'activo') DESC, e.id DESC"
    )->fetch_all(MYSQLI_ASSOC);
}

/** Las métricas clave de un evento (para la vista resumida del archivo). */
function metricasEvento(mysqli $conn, $id) {
    $id = (int) $id;
    $stmt = $conn->prepare(
        "SELECT (SELECT COUNT(*) FROM asistentes a WHERE a.evento_id = ?) AS registrados,
                (SELECT COUNT(DISTINCT m.cedula) FROM movimientos m WHERE m.evento_id = ? AND m.tipo = 'entrada') AS asistieron,
                (SELECT COUNT(*) FROM movimientos m WHERE m.evento_id = ? AND m.tipo = 'entrada') AS entradas,
                (SELECT COUNT(*) FROM movimientos m WHERE m.evento_id = ? AND m.tipo = 'salida') AS salidas,
                (SELECT COUNT(*) FROM avisos av WHERE av.evento_id = ?) AS irregularidades,
                (SELECT COUNT(*) FROM notificaciones n WHERE n.evento_id = ? AND n.enviado = 1) AS avisos_enviados,
                (SELECT COUNT(DISTINCT t.usuario_id) FROM turnos t WHERE t.evento_id = ?) AS porteros"
    );
    $stmt->bind_param('iiiiiii', $id, $id, $id, $id, $id, $id, $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila;
}

/**
 * Crea un evento y lo deja activo. Solo se puede si no hay otro activo
 * (primero hay que cerrar el que esté abierto).
 */
function crearEvento(mysqli $conn, array $datos, $usuarioId) {
    if (eventoActivo($conn)) {
        return [0, 'Ya hay un evento activo. Ciérralo antes de crear uno nuevo.'];
    }
    $stmt = $conn->prepare(
        "INSERT INTO eventos (nombre, fecha_inicio, fecha_fin, hora_inicio, hora_fin, cronograma_modo, estado, creado_por)
         VALUES (?, ?, ?, ?, ?, ?, 'activo', ?)"
    );
    $modo = in_array($datos['cronograma_modo'] ?? '', CRONOGRAMA_MODOS, true) ? $datos['cronograma_modo'] : 'mismo';
    $stmt->bind_param(
        'ssssssi',
        $datos['nombre'], $datos['fecha_inicio'], $datos['fecha_fin'],
        $datos['hora_inicio'], $datos['hora_fin'], $modo, $usuarioId
    );
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return [$id, ''];
}

function actualizarEvento(mysqli $conn, $id, array $datos) {
    $id = (int) $id;
    $stmt = $conn->prepare(
        "UPDATE eventos SET nombre = ?, fecha_inicio = ?, fecha_fin = ?, hora_inicio = ?, hora_fin = ?, cronograma_modo = ? WHERE id = ?"
    );
    $modo = in_array($datos['cronograma_modo'] ?? '', CRONOGRAMA_MODOS, true) ? $datos['cronograma_modo'] : 'mismo';
    $stmt->bind_param(
        'ssssssi',
        $datos['nombre'], $datos['fecha_inicio'], $datos['fecha_fin'],
        $datos['hora_inicio'], $datos['hora_fin'], $modo, $id
    );
    $stmt->execute();
    $stmt->close();
    // Si cambiaron las fechas, el cronograma se ajusta a los días nuevos.
    sincronizarCronograma($conn, $id);
}

/** Cierra el evento: su historial queda archivado y el panel deja de mostrarlo como activo. */
function cerrarEvento(mysqli $conn, $id, $usuarioId) {
    $id = (int) $id;
    $usuarioId = (int) $usuarioId;
    $stmt = $conn->prepare(
        "UPDATE eventos SET estado = 'archivado', cerrado_en = NOW(), cerrado_por = ? WHERE id = ? AND estado = 'activo'"
    );
    $stmt->bind_param('ii', $usuarioId, $id);
    $stmt->execute();
    $ok = $stmt->affected_rows === 1;
    $stmt->close();
    return $ok;
}

/** Fecha y horario del evento con la forma que usan estadoHorario() y textoHorario(). */
function horarioDeEvento($evento) {
    if (!$evento) {
        return ['fecha_inicio' => '', 'fecha_fin' => '', 'hora_inicio' => '', 'hora_fin' => ''];
    }
    $horario = [
        'fecha_inicio' => trim((string) $evento['fecha_inicio']),
        'fecha_fin'    => trim((string) $evento['fecha_fin']),
        'hora_inicio'  => substr(trim((string) $evento['hora_inicio']), 0, 5),
        'hora_fin'     => substr(trim((string) $evento['hora_fin']), 0, 5),
    ];
    if ($horario['fecha_fin'] === '') {
        $horario['fecha_fin'] = $horario['fecha_inicio'];
    }
    return $horario;
}

/**
 * Evento que se está consultando en una página del administrador: el de
 * ?evento=ID si existe, o el activo. Fija el contexto de las consultas y
 * devuelve la fila del evento (o null si no hay ninguno).
 */
function fijarEventoConsulta(mysqli $conn, $id = null) {
    $evento = $id ? eventoPorId($conn, $id) : null;
    if (!$evento) {
        $evento = eventoActivo($conn);
    }
    fijarEventoContexto($evento['id'] ?? 0);
    return $evento;
}

/**
 * Borra un evento archivado con todo su historial (asistentes del evento,
 * entradas y salidas, avisos, turnos e invitaciones). Las PERSONAS no se
 * pierden: antes de borrar se asegura que todas estén en el directorio
 * `personas` y su QR sigue en `codigos_qr`, así que se pueden invitar a
 * los próximos eventos. El evento activo no se puede borrar (hay que
 * cerrarlo primero). Devuelve [ok, cuántas personas conserva].
 */
function eliminarEvento(mysqli $conn, $id) {
    $evento = eventoPorId($conn, $id);
    if (!$evento || $evento['estado'] === 'activo') {
        return [false, 0];
    }
    $id = (int) $evento['id'];

    $conn->begin_transaction();
    try {
        // Nadie se queda por fuera del directorio ni sin su QR.
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO personas (cedula, nombre, tipo, tipo_otro, correo, telefono, empresa, direccion, ultimo_evento_id, ultimo_evento_nombre, actualizado_en)
             SELECT a.cedula, a.nombre, a.tipo, a.tipo_otro, a.correo, a.telefono, a.empresa, a.direccion, a.evento_id, ?, a.registrado_en
             FROM asistentes a WHERE a.evento_id = ?"
        );
        $stmt->bind_param('si', $evento['nombre'], $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO codigos_qr (cedula, codigo, creado_en)
             SELECT a.cedula, CONCAT('SENA-EVT|', a.cedula, '|', a.nombre), a.registrado_en
             FROM asistentes a WHERE a.evento_id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM asistentes WHERE evento_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $personas = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // El historial del evento.
        foreach (['movimientos', 'notificaciones', 'avisos', 'turnos', 'invitaciones', 'asistentes'] as $tabla) {
            $stmt = $conn->prepare("DELETE FROM $tabla WHERE evento_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ? AND estado <> 'activo'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return [true, $personas];
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, 0];
    }
}