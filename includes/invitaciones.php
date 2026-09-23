<?php
/**
 * Invitaciones. Al abrir un evento nuevo se puede invitar a los
 * asistentes de eventos anteriores: a cada uno le llega un correo con un
 * enlace único (confirmar.php). Al confirmar, queda inscrito en el evento
 * nuevo con su misma cédula, así que **su código QR de siempre le sirve**
 * — el QR lleva la cédula y el control de acceso lo lee dentro del evento
 * activo.
 *
 * Estados de una invitación: 'pendiente', 'confirmado' o 'rechazado'.
 */

/**
 * Personas de eventos anteriores que todavía no están inscritas en
 * $eventoId. Salen del directorio `personas` (sus datos más recientes),
 * así que siguen aquí aunque el evento en el que estuvieron se haya
 * borrado. No incluye a las personas borradas de la lista.
 */
function personasInvitables(mysqli $conn, $eventoId) {
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare(
        "SELECT p.*, COALESCE(e.nombre, p.ultimo_evento_nombre) AS evento_nombre, (e.id IS NULL) AS evento_borrado,
                (SELECT COUNT(*) FROM invitaciones i WHERE i.evento_id = ? AND i.cedula = p.cedula) AS invitada
         FROM personas p
         LEFT JOIN eventos e ON e.id = p.ultimo_evento_id
         WHERE NOT EXISTS (SELECT 1 FROM asistentes ac WHERE ac.evento_id = ? AND ac.cedula = p.cedula)
           AND NOT EXISTS (SELECT 1 FROM personas_excluidas x WHERE x.cedula = p.cedula)
         ORDER BY p.actualizado_en DESC"
    );
    $stmt->bind_param('ii', $eventoId, $eventoId);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function invitacionesDelEvento(mysqli $conn, $eventoId) {
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare(
        "SELECT i.*, u.nombre AS creado_por_nombre
         FROM invitaciones i
         LEFT JOIN usuarios u ON u.id = i.creado_por
         WHERE i.evento_id = ?
         ORDER BY FIELD(i.estado, 'confirmado', 'pendiente', 'rechazado'), i.nombre"
    );
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/** Cuántas invitaciones hay de cada estado y cuántas ya quedaron inscritas. */
function resumenInvitaciones(array $invitaciones) {
    $resumen = ['total' => count($invitaciones), 'pendiente' => 0, 'confirmado' => 0, 'rechazado' => 0, 'inscritos' => 0, 'por_inscribir' => 0,
        'email_link' => 0, 'public_link' => 0, 'abiertas' => 0, 'por_correo_inscritos' => 0];
    foreach ($invitaciones as $i) {
        $origen = ($i['origen'] ?? 'email_link') === 'public_link' ? 'public_link' : 'email_link';
        $resumen[$origen]++;
        if ($origen === 'email_link' && !empty($i['abierto_en'])) {
            $resumen['abiertas']++;
        }
        if ($origen === 'email_link' && (int) $i['inscrito'] === 1) {
            $resumen['por_correo_inscritos']++;
        }
        $resumen[$i['estado']] = ($resumen[$i['estado']] ?? 0) + 1;
        if ((int) $i['inscrito'] === 1) {
            $resumen['inscritos']++;
        } elseif ($i['estado'] === 'confirmado' && (string) $i['cedula'] !== '') {
            $resumen['por_inscribir']++;
        }
    }
    return $resumen;
}

function invitacionPorId(mysqli $conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM invitaciones WHERE id = ?");
    $id = (int) $id;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function invitacionPorToken(mysqli $conn, $token) {
    if (!preg_match('/^[a-f0-9]{32}$/', (string) $token)) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM invitaciones WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/** Enlace del correo con el que la persona confirma o rechaza. */
function urlConfirmacion(array $invitacion) {
    return urlInvitacion($invitacion);
}

/**
 * Crea la invitación de una persona para el evento (si ya existía, la
 * devuelve tal cual, para no perder su token ni su respuesta).
 */
function crearInvitacion(mysqli $conn, $eventoId, array $persona, $usuarioId) {
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("SELECT * FROM invitaciones WHERE evento_id = ? AND cedula = ?");
    $stmt->bind_param('is', $eventoId, $persona['cedula']);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existente) {
        return $existente;
    }

    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare(
        "INSERT INTO invitaciones (evento_id, cedula, nombre, tipo, tipo_otro, correo, telefono, empresa, direccion, token, creado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'isssssssssi',
        $eventoId, $persona['cedula'], $persona['nombre'], $persona['tipo'], $persona['tipo_otro'],
        $persona['correo'], $persona['telefono'], $persona['empresa'], $persona['direccion'], $token, $usuarioId
    );
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return invitacionPorId($conn, $id);
}

function marcarInvitacionEnviada(mysqli $conn, $id) {
    $id = (int) $id;
    $stmt = $conn->prepare("UPDATE invitaciones SET enviado_en = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/** Guarda la respuesta de la persona: 'confirmado' o 'rechazado'. */
function responderInvitacion(mysqli $conn, $id, $estado) {
    $id = (int) $id;
    $estado = $estado === 'confirmado' ? 'confirmado' : 'rechazado';
    $stmt = $conn->prepare("UPDATE invitaciones SET estado = ?, respondido_en = NOW() WHERE id = ?");
    $stmt->bind_param('si', $estado, $id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Inscribe en el evento a quien confirmó: crea el asistente con los
 * mismos datos y la misma cédula, así que su código QR sigue sirviendo.
 * Si ya estaba inscrito, solo lo marca. Devuelve true si quedó inscrito.
 */
function inscribirInvitacion(mysqli $conn, array $invitacion) {
    $eventoId = (int) $invitacion['evento_id'];
    $stmt = $conn->prepare("SELECT cedula FROM asistentes WHERE evento_id = ? AND cedula = ?");
    $stmt->bind_param('is', $eventoId, $invitacion['cedula']);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existe) {
        $stmt = $conn->prepare(
            "INSERT INTO asistentes (evento_id, cedula, nombre, tipo, tipo_otro, correo, telefono, empresa, direccion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issssssss',
            $eventoId, $invitacion['cedula'], $invitacion['nombre'], $invitacion['tipo'], $invitacion['tipo_otro'],
            $invitacion['correo'], $invitacion['telefono'], $invitacion['empresa'], $invitacion['direccion']
        );
        $stmt->execute();
        $stmt->close();
    }

    // Su QR de siempre queda registrado (o se crea si no lo tenía guardado).
    codigoQrPersona($conn, $invitacion['cedula'], $invitacion['nombre']);
    guardarPersona($conn, $invitacion, $eventoId);
    restaurarPersona($conn, $invitacion['cedula']);

    $id = (int) $invitacion['id'];
    $stmt = $conn->prepare("UPDATE invitaciones SET inscrito = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    return true;
}

/** Etiqueta del estado de una invitación, para mostrarla. */
function etiquetaInvitacion($estado) {
    $etiquetas = [
        'pendiente'  => 'Invitación pendiente',
        'confirmado' => 'Confirmado',
        'rechazado'  => 'No asistirá',
    ];
    return $etiquetas[$estado] ?? $estado;
}

/**
 * Borra a una persona de la lista de "invitar asistentes anteriores" y
 * elimina sus invitaciones sin responder del evento activo. Sus datos en
 * los eventos archivados (asistencia, entradas y salidas) se conservan, y
 * su QR sigue guardado: si se vuelve a registrar, recibe el mismo.
 */
function excluirPersona(mysqli $conn, $cedula, $eventoId, $usuarioId) {
    $stmt = $conn->prepare("INSERT IGNORE INTO personas_excluidas (cedula, excluido_por) VALUES (?, ?)");
    $stmt->bind_param('si', $cedula, $usuarioId);
    $stmt->execute();
    $stmt->close();

    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("DELETE FROM invitaciones WHERE evento_id = ? AND cedula = ? AND inscrito = 0");
    $stmt->bind_param('is', $eventoId, $cedula);
    $stmt->execute();
    $stmt->close();
}

/** Personas borradas de la lista, con sus datos más recientes. */
function personasExcluidas(mysqli $conn) {
    return $conn->query(
        "SELECT x.cedula, x.excluido_en, p.nombre, p.correo,
                COALESCE(e.nombre, p.ultimo_evento_nombre) AS evento_nombre, (e.id IS NULL) AS evento_borrado
         FROM personas_excluidas x
         JOIN personas p ON p.cedula = x.cedula
         LEFT JOIN eventos e ON e.id = p.ultimo_evento_id
         ORDER BY x.excluido_en DESC"
    )->fetch_all(MYSQLI_ASSOC);
}

/** Borra una invitación que todavía no se convirtió en inscripción. */
function eliminarInvitacion(mysqli $conn, $id, $eventoId) {
    $id = (int) $id;
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("DELETE FROM invitaciones WHERE id = ? AND evento_id = ? AND inscrito = 0");
    $stmt->bind_param('ii', $id, $eventoId);
    $stmt->execute();
    $borradas = $stmt->affected_rows;
    $stmt->close();
    return $borradas > 0;
}