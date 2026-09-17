<?php
/**
 * Certificados de asistencia.
 *
 * Plantilla única (certificado_plantilla, id = 1) editable desde el panel:
 * texto por criterio, firmante, firma (imagen), logo, sello y color.
 *
 * Criterios de emisión:
 *   'completa' -> asistió a todos los días del evento.
 *   'parcial'  -> asistió a un mínimo de días, sin llegar a todos
 *                 (quien asistió a todos recibe el de asistencia completa).
 *   'charla'   -> estuvo presente durante una actividad del cronograma un
 *                 mínimo de minutos (certificado específico de esa charla).
 * Un día cuenta como asistido si la persona registró al menos una entrada
 * ese día. Los roles son los tipos de asistente (Aprendiz, Instructor...).
 *
 * Cada vez que se generan se crea un lote (certificado_lotes). Cada
 * certificado tiene un código único que se verifica en
 * certificado_verificar.php.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

const CRITERIOS_CERTIFICADO = [
    'completa' => 'Asistencia completa',
    'parcial'  => 'Asistencia parcial',
    'charla'   => 'Charla corta',
];

/** Cómo se muestran los tipos de asistente al elegir a quién se emite. */
const ROLES_CERTIFICADO = [
    'Aprendiz'    => 'Aprendices (estudiantes)',
    'Instructor'  => 'Instructores',
    'Funcionario' => 'Funcionarios',
    'Visitante'   => 'Visitantes (invitados)',
    'Contratista' => 'Contratistas (empresas)',
    'Otro'        => 'Otros',
];

const COLORES_CERTIFICADO = [
    '#39A900' => 'Verde institucional',
    '#007832' => 'Verde oscuro',
    '#00304D' => 'Azul oscuro',
    '#71277A' => 'Violeta',
];

const CARPETA_CERTIFICADOS = __DIR__ . '/../uploads/certificados';

/* ------------------------------------------------------------ plantilla */

function plantillaCertificado(mysqli $conn) {
    $fila = $conn->query("SELECT * FROM certificado_plantilla WHERE id = 1")->fetch_assoc();
    return $fila ?: [
        'titulo' => 'Certificado de asistencia', 'texto_completa' => '', 'texto_parcial' => '', 'texto_charla' => '',
        'pie' => '', 'firmante_nombre' => '', 'firmante_cargo' => 'Director Académico', 'firma_archivo' => '',
        'logo_archivo' => '', 'mostrar_sello' => 1, 'color' => '#39A900',
    ];
}

function guardarPlantillaCertificado(mysqli $conn, array $datos, $usuarioId) {
    $color = array_key_exists($datos['color'], COLORES_CERTIFICADO) ? $datos['color'] : '#39A900';
    $sello = !empty($datos['mostrar_sello']) ? 1 : 0;
    $usuarioId = (int) $usuarioId;
    $stmt = $conn->prepare(
        "INSERT INTO certificado_plantilla (id, titulo, texto_completa, texto_parcial, texto_charla, pie, firmante_nombre, firmante_cargo,
                                            firma_archivo, logo_archivo, mostrar_sello, color, actualizado_en, actualizado_por)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), texto_completa = VALUES(texto_completa), texto_parcial = VALUES(texto_parcial),
           texto_charla = VALUES(texto_charla), pie = VALUES(pie), firmante_nombre = VALUES(firmante_nombre),
           firmante_cargo = VALUES(firmante_cargo), firma_archivo = VALUES(firma_archivo), logo_archivo = VALUES(logo_archivo),
           mostrar_sello = VALUES(mostrar_sello), color = VALUES(color), actualizado_en = NOW(), actualizado_por = VALUES(actualizado_por)"
    );
    $stmt->bind_param(
        'sssssssssisi',
        $datos['titulo'], $datos['texto_completa'], $datos['texto_parcial'], $datos['texto_charla'], $datos['pie'],
        $datos['firmante_nombre'], $datos['firmante_cargo'], $datos['firma_archivo'], $datos['logo_archivo'],
        $sello, $color, $usuarioId
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Guarda una imagen subida (firma o logo) en uploads/certificados con un
 * nombre aleatorio. Solo PNG o JPG de hasta 2 MB.
 * Devuelve [nombre del archivo | null, mensaje de error].
 */
function guardarImagenCertificado(array $archivo, $prefijo) {
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, ''];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return [null, 'No se pudo subir el archivo. Intenta de nuevo.'];
    }
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return [null, 'La imagen pesa más de 2 MB.'];
    }
    $tipos = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!isset($tipos[$mime]) || !getimagesize($archivo['tmp_name'])) {
        return [null, 'La imagen debe ser PNG o JPG (para la firma, mejor PNG con fondo transparente).'];
    }
    prepararCarpetaCertificados();
    $nombre = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . $tipos[$mime];
    if (!move_uploaded_file($archivo['tmp_name'], CARPETA_CERTIFICADOS . '/' . $nombre)) {
        return [null, 'No se pudo guardar la imagen en el servidor.'];
    }
    return [$nombre, ''];
}

/** Crea la carpeta de imágenes y la cierra al acceso directo por la web. */
function prepararCarpetaCertificados() {
    if (!is_dir(CARPETA_CERTIFICADOS)) {
        mkdir(CARPETA_CERTIFICADOS, 0755, true);
    }
    $htaccess = dirname(CARPETA_CERTIFICADOS) . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "# Firmas y logos de los certificados: solo los usa el sistema.\nRequire all denied\n");
    }
}

function borrarImagenCertificado($nombre) {
    $ruta = CARPETA_CERTIFICADOS . '/' . basename((string) $nombre);
    if ($nombre !== '' && is_file($ruta)) {
        unlink($ruta);
    }
}

/** La imagen como data URI (para el PDF y para mostrarla en el panel). */
function imagenCertificadoDataUri($nombre) {
    $ruta = CARPETA_CERTIFICADOS . '/' . basename((string) $nombre);
    if ($nombre === '' || !is_file($ruta)) {
        return null;
    }
    $mime = strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($ruta));
}

/* ----------------------------------------------------------- asistencia */

/** Fechas del evento en texto: "el 16 de septiembre de 2026" o "del 16 al 18 de ...". */
function textoFechasCertificado(array $evento) {
    $horario = horarioDeEvento($evento);
    if ($horario['fecha_inicio'] === '') {
        return '';
    }
    if ($horario['fecha_fin'] === $horario['fecha_inicio']) {
        return 'realizado el ' . fechaLarga($horario['fecha_inicio']);
    }
    return 'realizado del ' . fechaLarga($horario['fecha_inicio']) . ' al ' . fechaLarga($horario['fecha_fin']);
}

/**
 * Asistentes del evento con los días en que registraron entrada (dentro de
 * las fechas del evento) y todos sus movimientos, para evaluar criterios.
 */
function asistenciaParaCertificados(mysqli $conn, array $evento) {
    $eventoId = (int) $evento['id'];
    $dias = diasDelEvento($evento);
    $fechasEvento = array_filter(array_column($dias, 'fecha'));

    $stmt = $conn->prepare("SELECT * FROM asistentes WHERE evento_id = ? ORDER BY nombre");
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $personas = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
        $personas[$a['cedula']] = $a + ['fechas' => [], 'movimientos' => []];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT cedula, tipo, fecha FROM movimientos WHERE evento_id = ? ORDER BY fecha, id");
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $m) {
        if (!isset($personas[$m['cedula']])) {
            continue;
        }
        $personas[$m['cedula']]['movimientos'][] = $m;
        $fecha = substr($m['fecha'], 0, 10);
        if ($m['tipo'] === 'entrada' && (!$fechasEvento || in_array($fecha, $fechasEvento, true))) {
            $personas[$m['cedula']]['fechas'][$fecha] = true;
        }
    }
    $stmt->close();

    $total = count($dias);
    foreach ($personas as &$p) {
        // Sin fechas definidas el evento es de un día: basta con haber entrado.
        $p['dias_asistidos'] = min($total, count($p['fechas']));
        $p['total_dias'] = $total;
    }
    return $personas;
}

/**
 * Minutos que la persona estuvo adentro durante [inicio, fin] de un día.
 * Una entrada sin salida cuenta hasta el final del día; una salida sin
 * entrada, desde el comienzo del día.
 */
function minutosPresente(array $movimientos, $fecha, $inicio, $fin) {
    $desde = strtotime($fecha . ' ' . $inicio);
    $hasta = strtotime($fecha . ' ' . $fin);
    $tramos = [];
    $abierta = null;
    foreach ($movimientos as $m) {
        if (substr($m['fecha'], 0, 10) !== $fecha) {
            continue;
        }
        $t = strtotime($m['fecha']);
        if ($m['tipo'] === 'entrada') {
            $abierta = $abierta ?? $t;
        } else {
            $tramos[] = [$abierta ?? strtotime($fecha . ' 00:00:00'), $t];
            $abierta = null;
        }
    }
    if ($abierta !== null) {
        $tramos[] = [$abierta, strtotime($fecha . ' 23:59:59')];
    }
    $segundos = 0;
    foreach ($tramos as [$a, $b]) {
        $segundos += max(0, min($b, $hasta) - max($a, $desde));
    }
    return (int) floor($segundos / 60);
}

function actividadCronograma(mysqli $conn, $eventoId, $id) {
    $id = (int) $id;
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("SELECT * FROM cronograma WHERE id = ? AND evento_id = ?");
    $stmt->bind_param('ii', $id, $eventoId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function duracionActividad(array $actividad) {
    return (int) ((strtotime('2000-01-01 ' . $actividad['hora_fin']) - strtotime('2000-01-01 ' . $actividad['hora_inicio'])) / 60);
}

/** Opciones del filtro, limpias: criterio, roles, mínimo de días, actividad y minutos. */
function opcionesCertificado(array $entrada, array $evento, mysqli $conn) {
    $total = count(diasDelEvento($evento));
    $criterio = array_key_exists($entrada['criterio'] ?? '', CRITERIOS_CERTIFICADO) ? $entrada['criterio'] : 'completa';
    $roles = array_values(array_intersect(array_keys(ROLES_CERTIFICADO), (array) ($entrada['roles'] ?? array_keys(ROLES_CERTIFICADO))));
    $minimoDias = max(1, min(max(1, $total - 1), (int) ($entrada['minimo_dias'] ?? max(1, $total - 1))));
    $actividad = !empty($entrada['actividad']) ? actividadCronograma($conn, $evento['id'], $entrada['actividad']) : null;
    $duracion = $actividad ? duracionActividad($actividad) : 0;
    $minimoMinutos = isset($entrada['minimo_minutos']) && $entrada['minimo_minutos'] !== ''
        ? max(1, (int) $entrada['minimo_minutos'])
        : max(1, (int) ceil($duracion / 2));
    return [
        'criterio'       => $criterio,
        'roles'          => $roles,
        'minimo_dias'    => $minimoDias,
        'actividad'      => $actividad,
        'minimo_minutos' => $actividad ? min($minimoMinutos, max(1, $duracion)) : $minimoMinutos,
        'total_dias'     => $total,
    ];
}

/** Clave única del certificado según el criterio. */
function claveCertificado(array $opciones) {
    return $opciones['criterio'] === 'charla' ? 'charla:' . (int) ($opciones['actividad']['id'] ?? 0) : $opciones['criterio'];
}

/** Texto corto del criterio del lote: "Asistencia parcial · mínimo 2 de 3 días". */
function detalleCriterio(array $opciones) {
    switch ($opciones['criterio']) {
        case 'parcial':
            return 'mínimo ' . $opciones['minimo_dias'] . ' de ' . $opciones['total_dias'] . ' días';
        case 'charla':
            return $opciones['actividad'] ? $opciones['actividad']['titulo'] . ' · mínimo ' . $opciones['minimo_minutos'] . ' min' : '';
        default:
            return $opciones['total_dias'] > 1 ? 'los ' . $opciones['total_dias'] . ' días' : 'el día del evento';
    }
}

/** "Día 2 · jue 17 sep, 9:00 AM a 11:00 AM" */
function horarioActividadCertificado(array $evento, array $actividad) {
    $dias = diasDelEvento($evento);
    $d = $dias[(int) $actividad['dia']] ?? null;
    $cuando = $d && $d['fecha'] !== '' ? fechaLarga($d['fecha']) . ', ' : '';
    return $cuando . fmtHora12($actividad['hora_inicio']) . ' a ' . fmtHora12($actividad['hora_fin']);
}

/**
 * Registrados del evento (de los roles elegidos) con si cumplen el
 * criterio, por qué, y si ya tienen ese certificado.
 */
function candidatosCertificado(mysqli $conn, array $evento, array $opciones) {
    $personas = asistenciaParaCertificados($conn, $evento);
    $clave = claveCertificado($opciones);
    $eventoId = (int) $evento['id'];
    $stmt = $conn->prepare("SELECT cedula, id FROM certificados WHERE evento_id = ? AND clave = ?");
    $stmt->bind_param('is', $eventoId, $clave);
    $stmt->execute();
    $emitidos = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id', 'cedula');
    $stmt->close();

    $actividad = $opciones['actividad'];
    $diaActividad = $actividad ? (diasDelEvento($evento)[(int) $actividad['dia']] ?? null) : null;

    $candidatos = [];
    foreach ($personas as $p) {
        if ($opciones['roles'] && !in_array($p['tipo'], $opciones['roles'], true)) {
            continue;
        }
        $p['minutos'] = null;
        switch ($opciones['criterio']) {
            case 'completa':
                $cumple = $p['dias_asistidos'] >= $p['total_dias'];
                $detalle = $p['dias_asistidos'] . ' de ' . $p['total_dias'] . ($p['total_dias'] === 1 ? ' día' : ' días');
                break;
            case 'parcial':
                $cumple = $p['dias_asistidos'] >= $opciones['minimo_dias'] && $p['dias_asistidos'] < $p['total_dias'];
                $detalle = $p['dias_asistidos'] . ' de ' . $p['total_dias'] . ' días'
                    . ($p['dias_asistidos'] >= $p['total_dias'] && $p['total_dias'] > 0 ? ' (le corresponde el de asistencia completa)' : '');
                break;
            default:
                $fecha = $diaActividad && $diaActividad['fecha'] !== '' ? $diaActividad['fecha'] : null;
                if (!$fecha) {
                    // Evento sin fechas: se usa el día en que la persona entró.
                    $fechas = array_keys($p['fechas']);
                    $fecha = $fechas[0] ?? null;
                }
                $p['minutos'] = $actividad && $fecha ? minutosPresente($p['movimientos'], $fecha, $actividad['hora_inicio'], $actividad['hora_fin']) : 0;
                $cumple = $actividad && $p['minutos'] >= $opciones['minimo_minutos'];
                $detalle = $actividad ? $p['minutos'] . ' de ' . duracionActividad($actividad) . ' min en la charla' : 'Elige una charla';
        }
        $p['cumple'] = (bool) $cumple;
        $p['detalle_asistencia'] = $detalle;
        $p['certificado_id'] = $emitidos[$p['cedula']] ?? null;
        unset($p['movimientos']);
        $candidatos[] = $p;
    }
    usort($candidatos, function ($a, $b) {
        return [$b['cumple'], $a['nombre']] <=> [$a['cumple'], $b['nombre']];
    });
    return $candidatos;
}

/* -------------------------------------------------------------- emisión */

function codigoCertificado() {
    return strtoupper(bin2hex(random_bytes(5)));
}

function formatoCodigoCertificado($codigo) {
    return substr($codigo, 0, 5) . '-' . substr($codigo, 5);
}

/**
 * Crea el lote con los certificados de las cédulas elegidas que cumplen el
 * criterio y todavía no lo tienen. Devuelve [id del lote | 0, emitidos, omitidos].
 */
function emitirCertificados(mysqli $conn, array $evento, array $opciones, array $cedulas, $usuarioId) {
    $elegidas = array_flip(array_map('strval', $cedulas));
    $porEmitir = array_filter(candidatosCertificado($conn, $evento, $opciones), function ($c) use ($elegidas) {
        return $c['cumple'] && !$c['certificado_id'] && isset($elegidas[$c['cedula']]);
    });
    $omitidos = count($elegidas) - count($porEmitir);
    if (!$porEmitir) {
        return [0, 0, $omitidos];
    }

    $eventoId = (int) $evento['id'];
    $criterio = $opciones['criterio'];
    $detalleLote = detalleCriterio($opciones);
    $roles = count($opciones['roles']) === count(ROLES_CERTIFICADO) ? '' : implode(', ', $opciones['roles']);
    $clave = claveCertificado($opciones);
    $actividad = $opciones['actividad'];
    $detalle = $actividad ? $actividad['titulo'] : '';
    $horario = $actividad ? horarioActividadCertificado($evento, $actividad) : '';
    $usuarioId = (int) $usuarioId;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO certificado_lotes (evento_id, criterio, detalle, roles, total, creado_por) VALUES (?, ?, ?, ?, 0, ?)");
        $stmt->bind_param('isssi', $eventoId, $criterio, $detalleLote, $roles, $usuarioId);
        $stmt->execute();
        $loteId = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO certificados (lote_id, evento_id, cedula, nombre, tipo, tipo_otro, correo, criterio, clave, detalle, horario, dias_asistidos, total_dias, codigo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $emitidos = 0;
        foreach ($porEmitir as $c) {
            $codigo = codigoCertificado();
            $stmt->bind_param(
                'iisssssssssiis',
                $loteId, $eventoId, $c['cedula'], $c['nombre'], $c['tipo'], $c['tipo_otro'], $c['correo'],
                $criterio, $clave, $detalle, $horario, $c['dias_asistidos'], $c['total_dias'], $codigo
            );
            $stmt->execute();
            $emitidos += $stmt->affected_rows;
        }
        $stmt->close();

        $stmt = $conn->prepare("UPDATE certificado_lotes SET total = ? WHERE id = ?");
        $stmt->bind_param('ii', $emitidos, $loteId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return [$loteId, $emitidos, $omitidos];
    } catch (Throwable $e) {
        $conn->rollback();
        return [0, 0, count($elegidas)];
    }
}

function lotesCertificados(mysqli $conn) {
    return $conn->query(
        "SELECT l.*, e.nombre AS evento_nombre, u.nombre AS creado_por_nombre,
                (SELECT COUNT(*) FROM certificados c WHERE c.lote_id = l.id) AS certificados,
                (SELECT COUNT(*) FROM certificados c WHERE c.lote_id = l.id AND c.enviado_en IS NOT NULL) AS enviados,
                (SELECT COUNT(*) FROM certificados c WHERE c.lote_id = l.id AND c.correo <> '') AS con_correo
         FROM certificado_lotes l
         JOIN eventos e ON e.id = l.evento_id
         LEFT JOIN usuarios u ON u.id = l.creado_por
         ORDER BY l.id DESC"
    )->fetch_all(MYSQLI_ASSOC);
}

function loteCertificados(mysqli $conn, $id) {
    foreach (lotesCertificados($conn) as $lote) {
        if ((int) $lote['id'] === (int) $id) {
            return $lote;
        }
    }
    return null;
}

function certificadosDelLote(mysqli $conn, $loteId) {
    $loteId = (int) $loteId;
    $stmt = $conn->prepare("SELECT * FROM certificados WHERE lote_id = ? ORDER BY nombre");
    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function certificadoPorId(mysqli $conn, $id) {
    $id = (int) $id;
    $stmt = $conn->prepare("SELECT * FROM certificados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function certificadoPorCodigo(mysqli $conn, $codigo) {
    $codigo = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', (string) $codigo));
    if (strlen($codigo) !== 10) {
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT c.*, e.nombre AS evento_nombre FROM certificados c JOIN eventos e ON e.id = c.evento_id WHERE c.codigo = ?"
    );
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function marcarCertificadoEnviado(mysqli $conn, $id) {
    $id = (int) $id;
    $stmt = $conn->prepare("UPDATE certificados SET enviado_en = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/* ------------------------------------------------------------------ PDF */

/** El texto del certificado con los datos de la persona. */
function textoCertificado(array $plantilla, array $certificado, array $evento) {
    $base = $plantilla['texto_' . $certificado['criterio']] ?? $plantilla['texto_completa'];
    return strtr($base, [
        '{nombre}'         => $certificado['nombre'],
        '[Nombre completo]' => $certificado['nombre'],
        '{cedula}'         => $certificado['cedula'],
        '[Número]'         => $certificado['cedula'],
        '{evento}'         => $evento['nombre'],
        '{fechas}'         => textoFechasCertificado($evento),
        '{dias_asistidos}' => (string) $certificado['dias_asistidos'],
        '{total_dias}'     => (string) $certificado['total_dias'],
        '{tipo}'           => tipoAsistente($certificado['tipo'], $certificado['tipo_otro']),
        '{charla}'         => $certificado['detalle'],
        '{charla_horario}' => $certificado['horario'],
    ]);
}

function urlVerificacionCertificado($codigo) {
    return urlDelSistema('certificado_verificar.php') . '?c=' . $codigo;
}

/** HTML de una página de certificado (carta horizontal) para Dompdf. */
function htmlCertificado(array $plantilla, array $certificado, array $evento) {
    $color = array_key_exists($plantilla['color'], COLORES_CERTIFICADO) ? $plantilla['color'] : '#39A900';
    $logo = imagenCertificadoDataUri($plantilla['logo_archivo'])
        ?? 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../img/sena-logo-verde.png'));
    $marca = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../img/sena-logo-verde.png'));
    $firma = imagenCertificadoDataUri($plantilla['firma_archivo']);
    $firmante = trim($plantilla['firmante_nombre']) !== '' ? $plantilla['firmante_nombre'] : 'Director Académico';

    $parrafos = '';
    foreach (preg_split('/\R{2,}/u', trim(textoCertificado($plantilla, $certificado, $evento))) as $parrafo) {
        $parrafos .= '<p>' . nl2br(h($parrafo)) . '</p>';
    }
    // Resalta el nombre y la cédula dentro del texto.
    $parrafos = str_replace(h($certificado['nombre']), '<strong>' . h($certificado['nombre']) . '</strong>', $parrafos);

    $criterio = CRITERIOS_CERTIFICADO[$certificado['criterio']] ?? '';
    if ($certificado['criterio'] === 'charla' && $certificado['detalle'] !== '') {
        $criterio .= ' · ' . $certificado['detalle'];
    }
    $firmaHtml = $firma
        ? '<img class="firma-img" src="' . $firma . '" alt="Firma">'
        : '<div class="firma-texto">' . h($firmante) . '</div>';
    $sello = !empty($plantilla['mostrar_sello'])
        ? '<div class="sello"><div class="sello-in"><img src="' . $marca . '" alt=""><div class="sello-txt">SENA</div><div class="sello-sub">Sello institucional</div></div></div>'
        : '';
    $emitido = !empty($certificado['emitido_en']) ? $certificado['emitido_en'] : date('Y-m-d');

    return '
    <div class="cert">
      <div class="marco" style="border-color:' . $color . ';"></div>
      <div class="marco-in"></div>
      <img class="marca" src="' . $marca . '" alt="">
      <div class="franja" style="background:' . $color . ';"></div>

      <img class="logo" src="' . $logo . '" alt="Logo">
      <div class="entidad">Servicio Nacional de Aprendizaje<br><strong>SENA</strong></div>

      <div class="centro">
        <div class="titulo">' . h(mb_strtoupper($plantilla['titulo'])) . '</div>
        <div class="criterio" style="color:' . $color . ';">' . h($criterio) . '</div>
        <div class="otorga">Se otorga a</div>
        <div class="nombre">' . h($certificado['nombre']) . '</div>
        <div class="linea" style="background:' . $color . ';"></div>
        <div class="cedula">C.C. ' . h($certificado['cedula']) . '</div>
        <div class="evento">' . h($evento['nombre']) . '</div>
        <div class="texto">' . $parrafos . '</div>
      </div>

      <div class="firma">
        ' . $firmaHtml . '
        <div class="firma-linea"></div>
        <div class="firmante">' . h($firmante) . '</div>
        <div class="cargo">' . h($plantilla['firmante_cargo']) . '</div>
        <div class="cargo">Firma electrónica</div>
      </div>
      ' . $sello . '

      <div class="pie">
        ' . ($plantilla['pie'] !== '' ? h($plantilla['pie']) . ' · ' : '') . 'Expedido el ' . h(fechaLarga(substr($emitido, 0, 10))) . '
        · ' . ($certificado['codigo'] === 'MUESTRA000'
            ? '<strong>VISTA PREVIA · el código de verificación se asigna al emitir</strong>'
            : 'Código de verificación: <strong>' . h(formatoCodigoCertificado($certificado['codigo'])) . '</strong> · ' . h(urlVerificacionCertificado($certificado['codigo']))) . '
      </div>
    </div>';
}

function documentoCertificados(array $paginas) {
    return '<html><head><meta charset="UTF-8"><style>
        @page { margin: 0; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1B1B1B; }
        .cert { position: relative; width: 1056px; height: 815px; page-break-after: always; overflow: hidden; }
        .cert:last-child { page-break-after: auto; }
        .marco { position: absolute; top: 22px; left: 22px; right: 22px; bottom: 22px; border: 8px solid #39A900; }
        .marco-in { position: absolute; top: 40px; left: 40px; right: 40px; bottom: 40px; border: 1px solid #00304D; }
        .marca { position: absolute; left: 318px; top: 180px; width: 420px; opacity: 0.05; }
        .franja { position: absolute; top: 40px; left: 40px; width: 14px; bottom: 40px; }
        .logo { position: absolute; top: 70px; left: 92px; width: 78px; height: 78px; }
        .entidad { position: absolute; top: 88px; right: 92px; text-align: right; font-size: 12px; color: #00304D; line-height: 1.5; }
        .entidad strong { font-size: 18px; letter-spacing: 3px; }
        .centro { position: absolute; top: 150px; left: 130px; right: 130px; text-align: center; }
        .titulo { font-size: 30px; font-weight: bold; letter-spacing: 3px; color: #00304D; }
        .criterio { font-size: 13px; font-weight: bold; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .otorga { font-size: 13px; color: #5B6660; margin-top: 22px; }
        .nombre { font-family: "DejaVu Serif", serif; font-size: 34px; font-weight: bold; color: #00304D; margin-top: 6px; line-height: 1.15; }
        .linea { width: 320px; height: 3px; margin: 10px auto 6px; }
        .cedula { font-size: 13px; color: #4D4D4D; }
        .evento { font-size: 17px; font-weight: bold; color: #007832; margin-top: 12px; }
        .texto { font-size: 13.5px; line-height: 1.55; color: #333333; margin-top: 8px; }
        .texto p { margin: 6px 0; }
        .firma { position: absolute; left: 330px; right: 330px; bottom: 96px; text-align: center; }
        .firma-img { max-width: 220px; max-height: 70px; }
        .firma-texto { font-family: "DejaVu Serif", serif; font-style: italic; font-size: 26px; color: #00304D; }
        .firma-linea { border-top: 1.5px solid #1B1B1B; margin: 4px 30px 6px; }
        .firmante { font-size: 13px; font-weight: bold; }
        .cargo { font-size: 11px; color: #5B6660; }
        .sello { position: absolute; right: 92px; bottom: 92px; width: 118px; height: 118px; border: 3px solid #007832; border-radius: 59px; }
        .sello-in { position: absolute; top: 7px; left: 7px; width: 98px; height: 98px; border: 1px dashed #007832; border-radius: 49px; text-align: center; }
        .sello-in img { width: 38px; height: 38px; margin-top: 12px; }
        .sello-txt { font-size: 14px; font-weight: bold; color: #007832; letter-spacing: 2px; }
        .sello-sub { font-size: 6px; color: #007832; text-transform: uppercase; }
        .pie { position: absolute; left: 70px; right: 70px; bottom: 52px; text-align: center; font-size: 9px; color: #5B6660; }
    </style></head><body>' . implode('', $paginas) . '</body></html>';
}

/** Bytes del PDF de uno o varios certificados. $items: [[certificado, evento], ...]. */
function pdfCertificados(array $plantilla, array $items) {
    $opciones = new Options();
    $opciones->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($opciones);
    $paginas = array_map(function ($item) use ($plantilla) { return htmlCertificado($plantilla, $item[0], $item[1]); }, $items);
    $dompdf->loadHtml(documentoCertificados($paginas), 'UTF-8');
    $dompdf->setPaper('letter', 'landscape');
    $dompdf->render();
    return $dompdf->output();
}

function nombreArchivoCertificado(array $certificado) {
    $base = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $certificado['nombre']));
    return 'certificado_' . trim(preg_replace('/[^a-z0-9]+/', '_', $base), '_') . '_' . $certificado['cedula'] . '.pdf';
}

/** Un certificado de muestra (vista previa sin emitir). */
function certificadoDeMuestra(array $opciones, array $evento, ?array $persona = null) {
    $actividad = $opciones['actividad'];
    return [
        'id' => 0, 'cedula' => $persona['cedula'] ?? '1000000000', 'nombre' => $persona['nombre'] ?? 'Nombre Completo del Asistente',
        'tipo' => $persona['tipo'] ?? 'Aprendiz', 'tipo_otro' => $persona['tipo_otro'] ?? '', 'correo' => $persona['correo'] ?? '',
        'criterio' => $opciones['criterio'], 'detalle' => $actividad['titulo'] ?? 'Nombre de la charla',
        'horario' => $actividad ? horarioActividadCertificado($evento, $actividad) : '16 de septiembre de 2026, 10:00 AM a 11:00 AM', 'dias_asistidos' => $persona['dias_asistidos'] ?? $opciones['minimo_dias'],
        'total_dias' => $opciones['total_dias'], 'codigo' => 'MUESTRA000', 'emitido_en' => date('Y-m-d H:i:s'),
    ];
}
