<?php
/**
 * Funciones de ayuda para leer y escribir en la base de datos, y para
 * dar formato a los datos que se muestran en las páginas.
 *
 * Todo lo que tiene que ver con asistentes, entradas, salidas y avisos se
 * consulta y se guarda dentro del evento del contexto (ver
 * includes/eventos.php): normalmente el evento activo y, en las páginas
 * del administrador, el evento archivado que se esté consultando.
 */
require_once __DIR__ . '/eventos.php';
require_once __DIR__ . '/cronograma.php';

function h($valor) {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Versión de un archivo estático (assets/css/style.css, etc.) para usar
 * como ?v= en su URL. Al basarse en la fecha de modificación del archivo,
 * el navegador descarga la versión nueva automáticamente cada vez que se
 * edita el CSS o el JS, en vez de quedarse con una copia vieja en caché.
 */
function assetVersion($rutaRelativa) {
    $ruta = __DIR__ . '/../' . $rutaRelativa;
    return file_exists($ruta) ? filemtime($ruta) : time();
}

function nombreEvento(mysqli $conn) {
    $evento = eventoContextoFila($conn);
    return $evento ? $evento['nombre'] : 'Evento SENA';
}

/**
 * Fecha(s) y horario de ingreso del evento del contexto. Cualquier valor
 * puede estar vacío: sin fechas no se limita el día y sin horas no se
 * limita la hora. Si solo hay fecha de inicio, el evento dura ese día.
 */
function horarioEvento(mysqli $conn) {
    return horarioDeEvento(eventoContextoFila($conn));
}

function horarioConfigurado(array $horario) {
    return $horario['fecha_inicio'] !== '' || $horario['hora_inicio'] !== '' || $horario['hora_fin'] !== '';
}

/**
 * Dice si en este momento se pueden registrar entradas según la fecha y
 * el horario del evento. Devuelve ['abierto' => bool, 'motivo' => texto].
 */
function estadoHorario(array $horario, $ahora = null) {
    $ahora = $ahora ?? time();
    $hoy = date('Y-m-d', $ahora);
    $hora = date('H:i', $ahora);
    $motivo = '';
    if ($horario['fecha_inicio'] !== '' && $hoy < $horario['fecha_inicio']) {
        $motivo = 'el evento empieza el ' . fmtDia($horario['fecha_inicio']) . '.';
    } elseif ($horario['fecha_fin'] !== '' && $hoy > $horario['fecha_fin']) {
        $motivo = 'el evento terminó el ' . fmtDia($horario['fecha_fin']) . '.';
    } elseif ($horario['hora_inicio'] !== '' && $hora < $horario['hora_inicio']) {
        $motivo = 'el ingreso abre a las ' . fmtHora12($horario['hora_inicio']) . '.';
    } elseif ($horario['hora_fin'] !== '' && $hora > $horario['hora_fin']) {
        $motivo = 'el ingreso cerró a las ' . fmtHora12($horario['hora_fin']) . '.';
    }
    return ['abierto' => $motivo === '', 'motivo' => $motivo];
}

/**
 * Segundos que faltan para que hoy abra o cierre el ingreso según el
 * horario (el control de acceso se recarga justo en ese momento), o null
 * si hoy no queda ningún cambio pendiente.
 */
function segundosHastaCambioHorario(array $horario, $ahora = null) {
    $ahora = $ahora ?? time();
    $hoy = date('Y-m-d', $ahora);
    if ($horario['fecha_inicio'] !== '' && ($hoy < $horario['fecha_inicio'] || $hoy > $horario['fecha_fin'])) {
        return null;
    }
    $cambios = [];
    if ($horario['hora_inicio'] !== '') {
        $cambios[] = strtotime($hoy . ' ' . $horario['hora_inicio'] . ':00');
    }
    if ($horario['hora_fin'] !== '') {
        // A la hora de cierre exacta todavía está abierto; cierra al minuto siguiente.
        $cambios[] = strtotime($hoy . ' ' . $horario['hora_fin'] . ':00') + 60;
    }
    $pendientes = array_filter($cambios, function ($t) use ($ahora) { return $t > $ahora; });
    return $pendientes ? min($pendientes) - $ahora + 1 : null;
}

/** Fecha y horario en una sola línea, p. ej. "20 sep 2026 · 8:00 AM a 5:00 PM". */
function textoHorario(array $horario) {
    $partes = [];
    if ($horario['fecha_inicio'] !== '') {
        $partes[] = $horario['fecha_fin'] !== $horario['fecha_inicio']
            ? 'del ' . fmtDia($horario['fecha_inicio']) . ' al ' . fmtDia($horario['fecha_fin'])
            : fmtDia($horario['fecha_inicio']);
    }
    $desde = $horario['hora_inicio'];
    $hasta = $horario['hora_fin'];
    if ($desde !== '' && $hasta !== '') {
        $partes[] = fmtHora12($desde) . ' a ' . fmtHora12($hasta);
    } elseif ($desde !== '') {
        $partes[] = 'desde las ' . fmtHora12($desde);
    } elseif ($hasta !== '') {
        $partes[] = 'hasta las ' . fmtHora12($hasta);
    }
    return ucfirst(implode(' · ', $partes));
}

function soloDigitos($texto) {
    return preg_replace('/\D/', '', (string) $texto);
}

/** Tipos de asistente que se eligen al registrarse. */
const TIPOS_ASISTENTE = ['Aprendiz', 'Instructor', 'Funcionario', 'Visitante', 'Contratista', 'Otro'];

/** Texto del tipo de asistente para mostrar: si eligió "Otro", lo que escribió. */
function tipoAsistente($tipo, $tipoOtro = '') {
    $tipo = (string) $tipo;
    $tipoOtro = trim((string) $tipoOtro);
    if ($tipo === 'Otro' && $tipoOtro !== '') {
        return $tipoOtro;
    }
    return $tipo !== '' ? $tipo : '—';
}

/**
 * Lee y valida el formulario de registro de asistentes (el público de
 * autorregistro y el del panel). Devuelve [$valores, $errores].
 */
function validarRegistro(mysqli $conn, array $datos) {
    $valores = [
        'nombre'    => trim($datos['nombre'] ?? ''),
        'tipo'      => trim($datos['tipo'] ?? ''),
        'tipo_otro' => trim($datos['tipo_otro'] ?? ''),
        'cedula'    => soloDigitos($datos['cedula'] ?? ''),
        'telefono'  => soloDigitos($datos['telefono'] ?? ''),
        'correo'    => trim($datos['correo'] ?? ''),
        'empresa'   => trim($datos['empresa'] ?? ''),
        'direccion' => trim($datos['direccion'] ?? ''),
    ];
    if ($valores['tipo'] !== 'Otro') {
        $valores['tipo_otro'] = '';
    }

    $errores = [];
    if (mb_strlen($valores['nombre']) < 3) {
        $errores['nombre'] = 'Escribe el nombre completo.';
    }
    if (!in_array($valores['tipo'], TIPOS_ASISTENTE, true)) {
        $errores['tipo'] = 'Elige el tipo de asistente.';
    } elseif ($valores['tipo'] === 'Otro' && mb_strlen($valores['tipo_otro']) < 3) {
        $errores['tipo_otro'] = 'Escribe cuál.';
    }
    if (mb_strlen($valores['cedula']) < 5) {
        $errores['cedula'] = 'Escribe un número de cédula válido.';
    } elseif (buscarAsistente($conn, $valores['cedula'])) {
        $errores['cedula'] = 'Ya existe un asistente registrado con esta cédula.';
    }
    if (mb_strlen($valores['telefono']) < 7) {
        $errores['telefono'] = 'Escribe un número de teléfono válido.';
    }
    if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Escribe un correo electrónico válido.';
    }
    return [$valores, $errores];
}

function buscarAsistente(mysqli $conn, $cedula) {
    $evento = eventoContexto();
    $stmt = $conn->prepare("SELECT * FROM asistentes WHERE evento_id = ? AND cedula = ?");
    $stmt->bind_param('is', $evento, $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/**
 * Inserta un nuevo asistente en el evento del contexto.
 * Devuelve [ok(bool), mensaje(string), qrReutilizado(bool)].
 */
function registrarAsistente(mysqli $conn, array $datos) {
    $evento = eventoContexto();
    if ($evento <= 0) {
        return [false, 'No hay ningún evento activo en este momento.'];
    }
    if (buscarAsistente($conn, $datos['cedula'])) {
        return [false, 'Ya existe un asistente registrado con esta cédula.'];
    }
    $stmt = $conn->prepare(
        "INSERT INTO asistentes (evento_id, cedula, nombre, tipo, tipo_otro, correo, telefono, empresa, direccion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'issssssss',
        $evento,
        $datos['cedula'],
        $datos['nombre'],
        $datos['tipo'],
        $datos['tipo_otro'],
        $datos['correo'],
        $datos['telefono'],
        $datos['empresa'],
        $datos['direccion']
    );
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return [false, 'No se pudo guardar el registro. Intenta de nuevo.', false];
    }
    // Si ya había venido a otro evento, se le entrega su mismo QR.
    [, $reutilizado] = codigoQrPersona($conn, $datos['cedula'], $datos['nombre']);
    guardarPersona($conn, $datos, $evento);
    restaurarPersona($conn, $datos['cedula']);
    return [true, '', $reutilizado];
}

/** $usuarioId: el portero que registra el movimiento (null si no hay sesión). */
function registrarMovimiento(mysqli $conn, $cedula, $tipo, $usuarioId = null) {
    $evento = eventoContexto();
    $stmt = $conn->prepare("INSERT INTO movimientos (evento_id, cedula, tipo, usuario_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('issi', $evento, $cedula, $tipo, $usuarioId);
    $stmt->execute();
    $stmt->close();

    $estado = $tipo === 'entrada' ? 'dentro' : 'fuera';
    $stmt2 = $conn->prepare("UPDATE asistentes SET estado = ? WHERE evento_id = ? AND cedula = ?");
    $stmt2->bind_param('sis', $estado, $evento, $cedula);
    $stmt2->execute();
    $stmt2->close();
}

function listarAsistentes(mysqli $conn, $busqueda = '') {
    $evento = eventoContexto();
    $busqueda = trim((string) $busqueda);
    if ($busqueda !== '') {
        $like = '%' . $busqueda . '%';
        $stmt = $conn->prepare(
            "SELECT * FROM asistentes
             WHERE evento_id = ?
               AND (nombre LIKE ? OR cedula LIKE ? OR empresa LIKE ? OR tipo LIKE ? OR tipo_otro LIKE ?)
             ORDER BY registrado_en DESC"
        );
        $stmt->bind_param('isssss', $evento, $like, $like, $like, $like, $like);
    } else {
        $stmt = $conn->prepare("SELECT * FROM asistentes WHERE evento_id = ? ORDER BY registrado_en DESC");
        $stmt->bind_param('i', $evento);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Asistentes que están "dentro" o "fuera" ahora mismo, con la fecha de su
 * último movimiento (si tiene) para mostrarla en las tablas de estado.
 */
function listarPorEstado(mysqli $conn, $estado) {
    $evento = eventoContexto();
    $stmt = $conn->prepare(
        "SELECT a.*, (
              SELECT m.fecha FROM movimientos m
              WHERE m.evento_id = a.evento_id AND m.cedula = a.cedula
              ORDER BY m.fecha DESC LIMIT 1
            ) AS ultima_fecha
         FROM asistentes a
         WHERE a.evento_id = ? AND a.estado = ?
         ORDER BY ultima_fecha IS NULL, ultima_fecha DESC, a.registrado_en DESC"
    );
    $stmt->bind_param('is', $evento, $estado);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Historial completo de entradas y salidas del evento, opcionalmente
 * filtrado por nombre/cédula/empresa. Incluye el tipo de asistente y el
 * portero que registró cada movimiento.
 */
function historialGeneral(mysqli $conn, $busqueda = '', $limite = 300) {
    $evento = eventoContexto();
    $limite = (int) $limite;
    $busqueda = trim((string) $busqueda);
    $select = "SELECT m.tipo, m.fecha, a.nombre, a.cedula, a.empresa,
                      a.tipo AS tipo_asistente, a.tipo_otro, u.nombre AS portero
               FROM movimientos m
               JOIN asistentes a ON a.evento_id = m.evento_id AND a.cedula = m.cedula
               LEFT JOIN usuarios u ON u.id = m.usuario_id
               WHERE m.evento_id = ?";
    if ($busqueda !== '') {
        $like = '%' . $busqueda . '%';
        $stmt = $conn->prepare(
            "$select AND (a.nombre LIKE ? OR a.cedula LIKE ? OR a.empresa LIKE ?)
             ORDER BY m.fecha DESC LIMIT $limite"
        );
        $stmt->bind_param('isss', $evento, $like, $like, $like);
    } else {
        $stmt = $conn->prepare("$select ORDER BY m.fecha DESC LIMIT $limite");
        $stmt->bind_param('i', $evento);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function contarEstados(mysqli $conn) {
    $evento = eventoContexto();
    $stmt = $conn->prepare(
        "SELECT SUM(estado = 'dentro') AS dentro, COUNT(*) AS total FROM asistentes WHERE evento_id = ?"
    );
    $stmt->bind_param('i', $evento);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $dentro = (int) ($fila['dentro'] ?? 0);
    $total = (int) ($fila['total'] ?? 0);
    return ['dentro' => $dentro, 'fuera' => $total - $dentro, 'total' => $total];
}

function ultimoMovimiento(mysqli $conn, $cedula) {
    $evento = eventoContexto();
    $stmt = $conn->prepare(
        "SELECT tipo, fecha FROM movimientos WHERE evento_id = ? AND cedula = ? ORDER BY fecha DESC, id DESC LIMIT 1"
    );
    $stmt->bind_param('is', $evento, $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function historialMovimientos(mysqli $conn, $cedula) {
    $evento = eventoContexto();
    $stmt = $conn->prepare(
        "SELECT tipo, fecha FROM movimientos WHERE evento_id = ? AND cedula = ? ORDER BY fecha DESC"
    );
    $stmt->bind_param('is', $evento, $cedula);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

/**
 * Intenta registrar una entrada o una salida, validando el estado actual
 * del asistente:
 *  - No deja dar ENTRADA fuera de la fecha u horario del evento.
 *  - No deja dar ENTRADA si ya está dentro (sin salida todavía) desde hoy.
 *  - No deja dar SALIDA si nunca entró, o si ya se le había dado salida.
 * Cuando bloquea la acción, además deja un registro en la tabla `avisos`.
 * $usuarioId es el portero con la sesión abierta: queda guardado en el
 * movimiento o en el aviso.
 *
 * Devuelve un arreglo:
 *   ok        -> true si el movimiento quedó registrado
 *   nivel     -> 'success' | 'warning' | 'error'
 *   mensaje   -> texto para mostrar al operador
 *   asistente -> los datos actuales del asistente (o null si no existe)
 */
function intentarMovimiento(mysqli $conn, $cedula, $tipoSolicitado, $usuarioId = null) {
    $asistente = buscarAsistente($conn, $cedula);
    if (!$asistente) {
        return [
            'ok' => false,
            'nivel' => 'error',
            'mensaje' => 'No se encontró ningún registro con la cédula ' . $cedula . ' en este evento.',
            'asistente' => null,
        ];
    }

    $ultimo = ultimoMovimiento($conn, $cedula);

    if ($tipoSolicitado === 'entrada') {
        // Fuera de la fecha o del horario del evento no se registran
        // entradas (las salidas sí, para no dejar a nadie "adentro").
        $ingreso = estadoHorario(horarioEvento($conn));
        if (!$ingreso['abierto']) {
            $mensaje = 'No se registró la entrada de ' . $asistente['nombre'] . ': ' . $ingreso['motivo'];
            registrarAviso($conn, $cedula, 'fuera_de_horario', $mensaje, $usuarioId);
            return ['ok' => false, 'nivel' => 'warning', 'mensaje' => $mensaje, 'asistente' => $asistente];
        }
        // Si quedó "dentro" desde un día anterior es porque se fue sin
        // registrar salida: se le deja entrar de nuevo (ese día aparece en
        // Reportes como "entró y no registró salida").
        $dentroHoy = $asistente['estado'] === 'dentro' && $ultimo
            && date('Y-m-d', strtotime($ultimo['fecha'])) === date('Y-m-d');
        if ($dentroHoy) {
            $hora = $ultimo ? fmtFecha($ultimo['fecha']) : 'una hora anterior';
            $mensaje = $asistente['nombre'] . ' ya registró su entrada a las ' . $hora . ' y todavía no se le ha dado salida.';
            registrarAviso($conn, $cedula, 'entrada_duplicada', $mensaje, $usuarioId);
            return ['ok' => false, 'nivel' => 'warning', 'mensaje' => $mensaje, 'asistente' => $asistente];
        }
        registrarMovimiento($conn, $cedula, 'entrada', $usuarioId);
        return [
            'ok' => true,
            'nivel' => 'success',
            'mensaje' => 'Entrada registrada para ' . $asistente['nombre'] . '.',
            'asistente' => buscarAsistente($conn, $cedula),
        ];
    }

    if ($tipoSolicitado === 'salida') {
        if ($asistente['estado'] === 'fuera') {
            if (!$ultimo) {
                $mensaje = $asistente['nombre'] . ' nunca ha registrado su entrada — no se le puede dar salida.';
                registrarAviso($conn, $cedula, 'salida_sin_entrada', $mensaje, $usuarioId);
            } else {
                $mensaje = 'Ya se registró la salida de ' . $asistente['nombre'] . ' a las ' . fmtFecha($ultimo['fecha']) . '.';
                registrarAviso($conn, $cedula, 'salida_duplicada', $mensaje, $usuarioId);
            }
            return ['ok' => false, 'nivel' => 'warning', 'mensaje' => $mensaje, 'asistente' => $asistente];
        }
        registrarMovimiento($conn, $cedula, 'salida', $usuarioId);
        return [
            'ok' => true,
            'nivel' => 'success',
            'mensaje' => 'Salida registrada para ' . $asistente['nombre'] . '.',
            'asistente' => buscarAsistente($conn, $cedula),
        ];
    }

    return ['ok' => false, 'nivel' => 'error', 'mensaje' => 'Acción no reconocida.', 'asistente' => $asistente];
}

/** Lo que un portero registró hoy (lo más reciente primero), para su lista en el control de acceso. */
function movimientosDePortero(mysqli $conn, $usuarioId, $limite = 15) {
    $evento = eventoContexto();
    $limite = (int) $limite;
    $desde = date('Y-m-d') . ' 00:00:00';
    $stmt = $conn->prepare(
        "SELECT m.tipo, m.fecha, a.nombre, a.cedula
         FROM movimientos m
         JOIN asistentes a ON a.evento_id = m.evento_id AND a.cedula = m.cedula
         WHERE m.evento_id = ? AND m.usuario_id = ? AND m.fecha >= ?
         ORDER BY m.fecha DESC, m.id DESC
         LIMIT $limite"
    );
    $stmt->bind_param('iis', $evento, $usuarioId, $desde);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function registrarAviso(mysqli $conn, $cedula, $tipo, $mensaje, $usuarioId = null) {
    $evento = eventoContexto();
    $stmt = $conn->prepare("INSERT INTO avisos (evento_id, cedula, tipo, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('isssi', $evento, $cedula, $tipo, $mensaje, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

function listarAvisos(mysqli $conn, $limite = 15) {
    $evento = eventoContexto();
    $limite = (int) $limite;
    $stmt = $conn->prepare(
        "SELECT av.id, av.cedula, av.tipo, av.mensaje, av.fecha, a.nombre, u.nombre AS portero
         FROM avisos av
         LEFT JOIN asistentes a ON a.evento_id = av.evento_id AND a.cedula = av.cedula
         LEFT JOIN usuarios u ON u.id = av.usuario_id
         WHERE av.evento_id = ?
         ORDER BY av.fecha DESC
         LIMIT $limite"
    );
    $stmt->bind_param('i', $evento);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function etiquetaAviso($tipo) {
    $etiquetas = [
        'entrada_duplicada'  => 'Entrada repetida',
        'salida_duplicada'   => 'Salida repetida',
        'salida_sin_entrada' => 'Salida sin entrada',
        'fuera_de_horario'   => 'Fuera de horario',
    ];
    return $etiquetas[$tipo] ?? $tipo;
}

function mesCorto($ts) {
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return $meses[(int) date('n', $ts) - 1];
}

/** Hora legible de 12 horas: '21:30' → '9:30 PM'. */
function fmtHora12($hora) {
    $hora = trim((string) $hora);
    if ($hora === '') {
        return '';
    }
    $ts = strtotime('2000-01-01 ' . $hora);
    return $ts ? date('g:i A', $ts) : $hora;
}

function fmtFecha($fecha) {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    return date('d', $ts) . ' ' . mesCorto($ts) . ', ' . date('g:i A', $ts);
}

/** Solo el día, p. ej. "20 sep 2026". */
function fmtDia($fecha) {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    return date('j', $ts) . ' ' . mesCorto($ts) . ' ' . date('Y', $ts);
}

function textoQR($cedula, $nombre) {
    return 'SENA-EVT|' . $cedula . '|' . $nombre;
}

/**
 * El código QR es de la persona, no del evento: se crea en su primer
 * registro y se guarda en `codigos_qr`. Si vuelve en otro evento se le
 * entrega el mismo. Devuelve [texto del QR, true si ya lo tenía].
 */
function codigoQrPersona(mysqli $conn, $cedula, $nombre) {
    $stmt = $conn->prepare("SELECT codigo FROM codigos_qr WHERE cedula = ?");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($fila) {
        $stmt = $conn->prepare("UPDATE codigos_qr SET reutilizado_en = NOW() WHERE cedula = ?");
        $stmt->bind_param('s', $cedula);
        $stmt->execute();
        $stmt->close();
        return [$fila['codigo'], true];
    }

    $codigo = textoQR($cedula, $nombre);
    $stmt = $conn->prepare("INSERT IGNORE INTO codigos_qr (cedula, codigo) VALUES (?, ?)");
    $stmt->bind_param('ss', $cedula, $codigo);
    $stmt->execute();
    $stmt->close();
    return [$codigo, false];
}

/** Texto del QR guardado para una cédula (sin marcarlo como reutilizado). */
function qrGuardado(mysqli $conn, $cedula, $nombre) {
    $stmt = $conn->prepare("SELECT codigo FROM codigos_qr WHERE cedula = ?");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ? $fila['codigo'] : textoQR($cedula, $nombre);
}

/**
 * Guarda (o actualiza) a la persona en el directorio `personas`, que no
 * depende de ningún evento: aunque se borre el evento, la persona sigue
 * disponible para invitarla a los siguientes.
 */
function guardarPersona(mysqli $conn, array $datos, $eventoId) {
    $eventoId = (int) $eventoId;
    $evento = eventoPorId($conn, $eventoId);
    $nombreEvento = $evento['nombre'] ?? '';
    $stmt = $conn->prepare(
        "INSERT INTO personas (cedula, nombre, tipo, tipo_otro, correo, telefono, empresa, direccion, ultimo_evento_id, ultimo_evento_nombre)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), tipo = VALUES(tipo), tipo_otro = VALUES(tipo_otro),
           correo = VALUES(correo), telefono = VALUES(telefono), empresa = VALUES(empresa), direccion = VALUES(direccion),
           ultimo_evento_id = VALUES(ultimo_evento_id), ultimo_evento_nombre = VALUES(ultimo_evento_nombre), actualizado_en = NOW()"
    );
    $stmt->bind_param(
        'ssssssssis',
        $datos['cedula'], $datos['nombre'], $datos['tipo'], $datos['tipo_otro'], $datos['correo'],
        $datos['telefono'], $datos['empresa'], $datos['direccion'], $eventoId, $nombreEvento
    );
    $stmt->execute();
    $stmt->close();
}

/** Quita a alguien de la lista de personas quitadas (p. ej. porque volvió a registrarse). */
function restaurarPersona(mysqli $conn, $cedula) {
    $stmt = $conn->prepare("DELETE FROM personas_excluidas WHERE cedula = ?");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $stmt->close();
}

/** URL completa de un archivo del sistema, con la misma dirección por la que se entró. */
function urlDelSistema($archivo) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $proto . '://' . $host . $dir . '/' . $archivo;
}

function urlAutorregistro() {
    return urlDelSistema('ingreso.php');
}

function esFechaValida($texto) {
    return is_string($texto)
        && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $p)
        && checkdate((int) $p[2], (int) $p[3], (int) $p[1]);
}

function esHoraValida($texto) {
    return is_string($texto) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $texto);
}

/* ---------- Reportes (reportes.php) ----------
   Todas estas consultas reciben el día como 'AAAA-MM-DD' y miran solo
   lo que pasó entre las 00:00 y las 23:59 de ese día, dentro del evento
   del contexto. */

/**
 * Día que muestra Reportes si no se elige otro: hoy, o el día del evento
 * más cercano si hoy está por fuera de sus fechas.
 */
function diaReportePorDefecto(array $horario) {
    $hoy = date('Y-m-d');
    if ($horario['fecha_inicio'] !== '' && $hoy < $horario['fecha_inicio']) return $horario['fecha_inicio'];
    if ($horario['fecha_fin'] !== '' && $hoy > $horario['fecha_fin']) return $horario['fecha_fin'];
    return $hoy;
}

/** Ejecuta una consulta del evento con dos parámetros: inicio y fin del día. */
function filasDelDia(mysqli $conn, $sql, $dia) {
    $evento = eventoContexto();
    $desde = $dia . ' 00:00:00';
    $hasta = $dia . ' 23:59:59';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $evento, $desde, $hasta);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function movimientosDelDia(mysqli $conn, $dia) {
    return filasDelDia($conn,
        "SELECT m.tipo, m.fecha, a.nombre, a.cedula, a.correo, a.telefono, a.empresa,
                a.tipo AS tipo_asistente, a.tipo_otro, u.nombre AS portero
         FROM movimientos m
         JOIN asistentes a ON a.evento_id = m.evento_id AND a.cedula = m.cedula
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.evento_id = ? AND m.fecha BETWEEN ? AND ?
         ORDER BY m.fecha, m.id", $dia);
}

/**
 * Personas cuyo ÚLTIMO movimiento de ese día fue una entrada: entraron y
 * no registraron salida. Incluye quién registró esa entrada y cuándo se
 * les envió el último aviso por correo después de ella (null si todavía
 * no se les ha enviado).
 */
function sinSalidaDelDia(mysqli $conn, $dia) {
    return filasDelDia($conn,
        "SELECT a.cedula, a.nombre, a.tipo, a.tipo_otro, a.correo, a.telefono, a.empresa,
                m.fecha AS hora_entrada, u.nombre AS portero,
                (SELECT MAX(n.fecha) FROM notificaciones n
                 WHERE n.evento_id = m.evento_id AND n.cedula = a.cedula AND n.enviado = 1 AND n.fecha >= m.fecha) AS ultimo_aviso
         FROM movimientos m
         JOIN asistentes a ON a.evento_id = m.evento_id AND a.cedula = m.cedula
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.evento_id = ? AND m.tipo = 'entrada'
           AND m.id = (SELECT m2.id FROM movimientos m2
                       WHERE m2.evento_id = m.evento_id AND m2.cedula = m.cedula AND m2.fecha BETWEEN ? AND ?
                       ORDER BY m2.fecha DESC, m2.id DESC LIMIT 1)
         ORDER BY m.fecha", $dia);
}

function avisosDelDia(mysqli $conn, $dia) {
    return filasDelDia($conn,
        "SELECT av.tipo, av.mensaje, av.fecha, av.cedula, a.nombre, u.nombre AS portero
         FROM avisos av
         LEFT JOIN asistentes a ON a.evento_id = av.evento_id AND a.cedula = av.cedula
         LEFT JOIN usuarios u ON u.id = av.usuario_id
         WHERE av.evento_id = ? AND av.fecha BETWEEN ? AND ?
         ORDER BY av.fecha DESC, av.id DESC", $dia);
}

/**
 * Turnos de portería de ese día (un turno sin cerrar cuenta solo para el
 * día en que empezó), con cuántos movimientos registró el portero
 * durante el turno.
 */
function turnosDelDia(mysqli $conn, $dia) {
    return filasDelDia($conn,
        "SELECT t.punto, t.inicio, t.fin, u.nombre, u.cedula,
                (SELECT COUNT(*) FROM movimientos m
                 WHERE m.evento_id = t.evento_id AND m.usuario_id = t.usuario_id AND m.fecha >= t.inicio
                   AND m.fecha <= COALESCE(t.fin, CONCAT(DATE(t.inicio), ' 23:59:59'))) AS movimientos
         FROM turnos t
         JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.evento_id = ? AND COALESCE(t.fin, t.inicio) >= ? AND t.inicio <= ?
         ORDER BY t.inicio", $dia);
}

/** Correos de aviso enviados sobre ese día del reporte (no el día en que se enviaron). */
function notificacionesDelDia(mysqli $conn, $dia) {
    $evento = eventoContexto();
    $stmt = $conn->prepare(
        "SELECT n.correo, n.asunto, n.enviado, n.detalle, n.fecha, n.cedula, a.nombre
         FROM notificaciones n
         LEFT JOIN asistentes a ON a.evento_id = n.evento_id AND a.cedula = n.cedula
         WHERE n.evento_id = ? AND n.dia = ?
         ORDER BY n.fecha DESC, n.id DESC"
    );
    $stmt->bind_param('is', $evento, $dia);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function registrarNotificacion(mysqli $conn, $cedula, $correo, $asunto, $dia, $enviado, $detalle) {
    $evento = eventoContexto();
    $asunto = mb_substr($asunto, 0, 200);
    $detalle = mb_substr($detalle, 0, 255);
    $enviado = $enviado ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO notificaciones (evento_id, cedula, correo, asunto, dia, enviado, detalle)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issssis', $evento, $cedula, $correo, $asunto, $dia, $enviado, $detalle);
    $stmt->execute();
    $stmt->close();
}
