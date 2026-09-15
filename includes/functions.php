<?php
/**
 * Funciones de ayuda para leer y escribir en la base de datos, y para
 * dar formato a los datos que se muestran en las páginas.
 */

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
    $res = $conn->query("SELECT valor FROM configuracion WHERE clave = 'nombre_evento'");
    $fila = $res ? $res->fetch_assoc() : null;
    return $fila ? $fila['valor'] : 'Evento SENA';
}

function actualizarNombreEvento(mysqli $conn, $nombre) {
    $stmt = $conn->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'nombre_evento'");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $stmt->close();
}

function soloDigitos($texto) {
    return preg_replace('/\D/', '', (string) $texto);
}

function buscarAsistente(mysqli $conn, $cedula) {
    $stmt = $conn->prepare("SELECT * FROM asistentes WHERE cedula = ?");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/**
 * Inserta un nuevo asistente. Devuelve un arreglo [ok(bool), mensaje(string)].
 */
function registrarAsistente(mysqli $conn, array $datos) {
    if (buscarAsistente($conn, $datos['cedula'])) {
        return [false, 'Ya existe un asistente registrado con esta cédula.'];
    }
    $stmt = $conn->prepare(
        "INSERT INTO asistentes (cedula, nombre, correo, telefono, empresa, direccion) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'ssssss',
        $datos['cedula'],
        $datos['nombre'],
        $datos['correo'],
        $datos['telefono'],
        $datos['empresa'],
        $datos['direccion']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? [true, ''] : [false, 'No se pudo guardar el registro. Intenta de nuevo.'];
}

function registrarMovimiento(mysqli $conn, $cedula, $tipo) {
    $stmt = $conn->prepare("INSERT INTO movimientos (cedula, tipo) VALUES (?, ?)");
    $stmt->bind_param('ss', $cedula, $tipo);
    $stmt->execute();
    $stmt->close();

    $estado = $tipo === 'entrada' ? 'dentro' : 'fuera';
    $stmt2 = $conn->prepare("UPDATE asistentes SET estado = ? WHERE cedula = ?");
    $stmt2->bind_param('ss', $estado, $cedula);
    $stmt2->execute();
    $stmt2->close();
}

function listarAsistentes(mysqli $conn, $busqueda = '') {
    $busqueda = trim((string) $busqueda);
    if ($busqueda !== '') {
        $like = '%' . $busqueda . '%';
        $stmt = $conn->prepare(
            "SELECT * FROM asistentes WHERE nombre LIKE ? OR cedula LIKE ? OR empresa LIKE ? ORDER BY registrado_en DESC"
        );
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query("SELECT * FROM asistentes ORDER BY registrado_en DESC");
    }
    $filas = [];
    while ($fila = $res->fetch_assoc()) {
        $filas[] = $fila;
    }
    return $filas;
}

/**
 * Asistentes que están "dentro" o "fuera" ahora mismo, con la fecha de su
 * último movimiento (si tiene) para mostrarla en las tablas de estado.
 */
function listarPorEstado(mysqli $conn, $estado) {
    $sql = "SELECT a.*, (
              SELECT m.fecha FROM movimientos m
              WHERE m.cedula = a.cedula
              ORDER BY m.fecha DESC LIMIT 1
            ) AS ultima_fecha
            FROM asistentes a
            WHERE a.estado = ?
            ORDER BY ultima_fecha IS NULL, ultima_fecha DESC, a.registrado_en DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $estado);
    $stmt->execute();
    $res = $stmt->get_result();
    $filas = [];
    while ($fila = $res->fetch_assoc()) {
        $filas[] = $fila;
    }
    $stmt->close();
    return $filas;
}

/**
 * Historial completo de entradas y salidas (para la página de
 * Historial), opcionalmente filtrado por nombre/cédula/empresa.
 */
function historialGeneral(mysqli $conn, $busqueda = '', $limite = 300) {
    $limite = (int) $limite;
    $busqueda = trim((string) $busqueda);
    if ($busqueda !== '') {
        $like = '%' . $busqueda . '%';
        $sql = "SELECT m.tipo, m.fecha, a.nombre, a.cedula, a.empresa
                FROM movimientos m
                JOIN asistentes a ON a.cedula = m.cedula
                WHERE a.nombre LIKE ? OR a.cedula LIKE ? OR a.empresa LIKE ?
                ORDER BY m.fecha DESC
                LIMIT $limite";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $sql = "SELECT m.tipo, m.fecha, a.nombre, a.cedula, a.empresa
                FROM movimientos m
                JOIN asistentes a ON a.cedula = m.cedula
                ORDER BY m.fecha DESC
                LIMIT $limite";
        $res = $conn->query($sql);
    }
    $filas = [];
    while ($fila = $res->fetch_assoc()) {
        $filas[] = $fila;
    }
    return $filas;
}

function contarEstados(mysqli $conn) {
    $dentro = (int) $conn->query("SELECT COUNT(*) c FROM asistentes WHERE estado = 'dentro'")->fetch_assoc()['c'];
    $total = (int) $conn->query("SELECT COUNT(*) c FROM asistentes")->fetch_assoc()['c'];
    return ['dentro' => $dentro, 'fuera' => $total - $dentro, 'total' => $total];
}

function ultimoMovimiento(mysqli $conn, $cedula) {
    $stmt = $conn->prepare("SELECT tipo, fecha FROM movimientos WHERE cedula = ? ORDER BY fecha DESC LIMIT 1");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function historialMovimientos(mysqli $conn, $cedula) {
    $stmt = $conn->prepare("SELECT tipo, fecha FROM movimientos WHERE cedula = ? ORDER BY fecha DESC");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $res = $stmt->get_result();
    $filas = [];
    while ($fila = $res->fetch_assoc()) {
        $filas[] = $fila;
    }
    $stmt->close();
    return $filas;
}

/**
 * Intenta registrar una entrada o una salida, validando el estado actual
 * del asistente:
 *  - No deja dar ENTRADA si ya está dentro (sin salida todavía).
 *  - No deja dar SALIDA si nunca entró, o si ya se le había dado salida.
 * Cuando bloquea la acción, además deja un registro en la tabla `avisos`.
 *
 * Devuelve un arreglo:
 *   ok        -> true si el movimiento quedó registrado
 *   nivel     -> 'success' | 'warning' | 'error'
 *   mensaje   -> texto para mostrar al operador
 *   asistente -> los datos actuales del asistente (o null si no existe)
 */
function intentarMovimiento(mysqli $conn, $cedula, $tipoSolicitado) {
    $asistente = buscarAsistente($conn, $cedula);
    if (!$asistente) {
        return [
            'ok' => false,
            'nivel' => 'error',
            'mensaje' => 'No se encontró ningún registro con la cédula ' . $cedula . '.',
            'asistente' => null,
        ];
    }

    $ultimo = ultimoMovimiento($conn, $cedula);

    if ($tipoSolicitado === 'entrada') {
        if ($asistente['estado'] === 'dentro') {
            $hora = $ultimo ? fmtFecha($ultimo['fecha']) : 'una hora anterior';
            $mensaje = $asistente['nombre'] . ' ya registró su entrada a las ' . $hora . ' y todavía no se le ha dado salida.';
            registrarAviso($conn, $cedula, 'entrada_duplicada', $mensaje);
            return ['ok' => false, 'nivel' => 'warning', 'mensaje' => $mensaje, 'asistente' => $asistente];
        }
        registrarMovimiento($conn, $cedula, 'entrada');
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
                registrarAviso($conn, $cedula, 'salida_sin_entrada', $mensaje);
            } else {
                $mensaje = 'Ya se registró la salida de ' . $asistente['nombre'] . ' a las ' . fmtFecha($ultimo['fecha']) . '.';
                registrarAviso($conn, $cedula, 'salida_duplicada', $mensaje);
            }
            return ['ok' => false, 'nivel' => 'warning', 'mensaje' => $mensaje, 'asistente' => $asistente];
        }
        registrarMovimiento($conn, $cedula, 'salida');
        return [
            'ok' => true,
            'nivel' => 'success',
            'mensaje' => 'Salida registrada para ' . $asistente['nombre'] . '.',
            'asistente' => buscarAsistente($conn, $cedula),
        ];
    }

    return ['ok' => false, 'nivel' => 'error', 'mensaje' => 'Acción no reconocida.', 'asistente' => $asistente];
}

function registrarAviso(mysqli $conn, $cedula, $tipo, $mensaje) {
    $stmt = $conn->prepare("INSERT INTO avisos (cedula, tipo, mensaje) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $cedula, $tipo, $mensaje);
    $stmt->execute();
    $stmt->close();
}

function listarAvisos(mysqli $conn, $limite = 15) {
    $limite = (int) $limite;
    $sql = "SELECT av.id, av.cedula, av.tipo, av.mensaje, av.fecha, a.nombre
            FROM avisos av
            LEFT JOIN asistentes a ON a.cedula = av.cedula
            ORDER BY av.fecha DESC
            LIMIT $limite";
    $res = $conn->query($sql);
    $filas = [];
    while ($fila = $res->fetch_assoc()) {
        $filas[] = $fila;
    }
    return $filas;
}

function etiquetaAviso($tipo) {
    $etiquetas = [
        'entrada_duplicada'  => 'Entrada repetida',
        'salida_duplicada'   => 'Salida repetida',
        'salida_sin_entrada' => 'Salida sin entrada',
    ];
    return $etiquetas[$tipo] ?? $tipo;
}

function feedActividad(mysqli $conn, $limite = 10) {
    $limite = (int) $limite;
    $sql = "SELECT m.tipo, m.fecha, a.nombre, a.cedula
            FROM movimientos m
            JOIN asistentes a ON a.cedula = m.cedula
            ORDER BY m.fecha DESC
            LIMIT $limite";
    $res = $conn->query($sql);
    $filas = [];
    while ($fila = $res->fetch_assoc()) {
        $filas[] = $fila;
    }
    return $filas;
}

function fmtFecha($fecha) {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return date('d', $ts) . ' ' . $meses[(int) date('n', $ts) - 1] . ', ' . date('H:i', $ts);
}

function textoQR($cedula, $nombre) {
    return 'SENA-EVT|' . $cedula . '|' . $nombre;
}

function urlAutorregistro() {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $proto . '://' . $host . $dir . '/ingreso.php';
}
