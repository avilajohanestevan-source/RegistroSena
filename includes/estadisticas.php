<?php
/**
 * Datos de asistencia para Estadísticas (gráficos) y para los exportes a
 * Excel y PDF (exportar.php). Todo se calcula sobre un rango de días
 * (desde–hasta, 'AAAA-MM-DD', ambos incluidos).
 */

/**
 * Rango que viene en la URL o, si no es válido, las fechas del evento
 * (o desde el primer movimiento registrado hasta hoy, si el evento no
 * tiene fechas). Devuelve [desde, hasta].
 */
function rangoEstadisticas(mysqli $conn, array $horario, $desde, $hasta) {
    if (!esFechaValida($desde) || !esFechaValida($hasta)) {
        if ($horario['fecha_inicio'] !== '') {
            $desde = $horario['fecha_inicio'];
            $hasta = $horario['fecha_fin'];
        } else {
            $primero = $conn->query("SELECT DATE(MIN(fecha)) AS dia FROM movimientos")->fetch_assoc()['dia'];
            $desde = $primero ?: date('Y-m-d');
            $hasta = date('Y-m-d');
        }
    }
    if ($hasta < $desde) {
        [$desde, $hasta] = [$hasta, $desde];
    }
    return [$desde, $hasta];
}

function textoRango($desde, $hasta) {
    return $desde === $hasta ? 'del ' . fmtDia($desde) : 'del ' . fmtDia($desde) . ' al ' . fmtDia($hasta);
}

/** Ejecuta una consulta con dos parámetros: inicio del primer día y fin del último. */
function filasEnRango(mysqli $conn, $sql, $desde, $hasta) {
    $inicio = $desde . ' 00:00:00';
    $fin = $hasta . ' 23:59:59';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $inicio, $fin);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function movimientosEnRango(mysqli $conn, $desde, $hasta) {
    return filasEnRango($conn,
        "SELECT m.tipo, m.fecha, a.nombre, a.cedula, a.empresa,
                a.tipo AS tipo_asistente, a.tipo_otro, u.nombre AS portero
         FROM movimientos m
         JOIN asistentes a ON a.cedula = m.cedula
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.fecha BETWEEN ? AND ?
         ORDER BY m.fecha, m.id", $desde, $hasta);
}

/** Intentos de entrada o salida que el control no dejó registrar. */
function avisosEnRango(mysqli $conn, $desde, $hasta) {
    return filasEnRango($conn,
        "SELECT av.tipo, av.mensaje, av.fecha, av.cedula, a.nombre, u.nombre AS portero
         FROM avisos av
         LEFT JOIN asistentes a ON a.cedula = av.cedula
         LEFT JOIN usuarios u ON u.id = av.usuario_id
         WHERE av.fecha BETWEEN ? AND ?
         ORDER BY av.fecha, av.id", $desde, $hasta);
}

/**
 * Cada asistente registrado (la lista de invitados) con lo que hizo en el
 * rango: sus movimientos en orden, cuántas veces entró y salió, primera
 * entrada, última salida, tiempo total adentro (cada entrada con su
 * salida del mismo día) y si su último movimiento fue una entrada sin
 * salida.
 * $movimientos viene de movimientosEnRango().
 */
function asistenciaPorPersona(mysqli $conn, array $movimientos) {
    $personas = [];
    foreach ($conn->query("SELECT * FROM asistentes ORDER BY nombre")->fetch_all(MYSQLI_ASSOC) as $a) {
        $personas[$a['cedula']] = $a + [
            'movimientos'     => [],
            'entradas'        => 0,
            'salidas'         => 0,
            'primera_entrada' => null,
            'ultima_salida'   => null,
            'segundos_dentro' => 0,
            'sin_salida'      => false,
        ];
    }

    $entradaAbierta = [];
    foreach ($movimientos as $m) {
        $cedula = $m['cedula'];
        if (!isset($personas[$cedula])) {
            continue;
        }
        $personas[$cedula]['movimientos'][] = $m;
        if ($m['tipo'] === 'entrada') {
            $personas[$cedula]['entradas']++;
            if ($personas[$cedula]['primera_entrada'] === null) {
                $personas[$cedula]['primera_entrada'] = $m['fecha'];
            }
            $entradaAbierta[$cedula] = $m['fecha'];
        } else {
            $personas[$cedula]['salidas']++;
            $personas[$cedula]['ultima_salida'] = $m['fecha'];
            // El tiempo adentro solo cuenta si la salida es del mismo día que
            // la entrada: si alguien se fue sin registrar salida y la
            // registró otro día, esas horas no son reales.
            if (isset($entradaAbierta[$cedula]) && substr($entradaAbierta[$cedula], 0, 10) === substr($m['fecha'], 0, 10)) {
                $personas[$cedula]['segundos_dentro'] += strtotime($m['fecha']) - strtotime($entradaAbierta[$cedula]);
            }
            unset($entradaAbierta[$cedula]);
        }
        $personas[$cedula]['sin_salida'] = $m['tipo'] === 'entrada';
    }
    return $personas;
}

function resumenAsistencia(array $personas, array $avisos) {
    $asistieron = array_filter($personas, function ($p) { return $p['entradas'] > 0; });
    $conTiempo = array_filter($asistieron, function ($p) { return $p['segundos_dentro'] > 0; });
    $sinSalida = count(array_filter($asistieron, function ($p) { return $p['sin_salida']; }));
    return [
        'registrados'       => count($personas),
        'asistieron'        => count($asistieron),
        'no_asistieron'     => count($personas) - count($asistieron),
        'reingresaron'      => count(array_filter($asistieron, function ($p) { return $p['entradas'] > 1; })),
        'con_salida'        => count($asistieron) - $sinSalida,
        'sin_salida'        => $sinSalida,
        'entradas'          => array_sum(array_column($personas, 'entradas')),
        'salidas'           => array_sum(array_column($personas, 'salidas')),
        'intentos_fallidos' => count($avisos),
        'promedio_dentro'   => $conTiempo
            ? (int) round(array_sum(array_column($conTiempo, 'segundos_dentro')) / count($conTiempo))
            : 0,
    ];
}

/** Registrados y asistentes por tipo (en el orden de TIPOS_ASISTENTE, más "Sin tipo" para los registros viejos). */
function porTipoAsistente(array $personas) {
    $tipos = [];
    foreach (array_merge(TIPOS_ASISTENTE, ['Sin tipo']) as $tipo) {
        $tipos[$tipo] = ['categoria' => $tipo, 'registrados' => 0, 'asistieron' => 0];
    }
    foreach ($personas as $p) {
        $tipo = isset($tipos[$p['tipo']]) ? $p['tipo'] : 'Sin tipo';
        $tipos[$tipo]['registrados']++;
        if ($p['entradas'] > 0) {
            $tipos[$tipo]['asistieron']++;
        }
    }
    return array_values(array_filter($tipos, function ($t) { return $t['registrados'] > 0; }));
}

/** "2 h 15 min", "45 min" o "—". */
function fmtDuracion($segundos) {
    if ($segundos <= 0) {
        return '—';
    }
    $minutos = max(1, (int) round($segundos / 60));
    if ($minutos < 60) {
        return $minutos . ' min';
    }
    return intdiv($minutos, 60) . ' h' . ($minutos % 60 ? ' ' . ($minutos % 60) . ' min' : '');
}

/** Hora de un movimiento; con la fecha si el rango tiene varios días. */
function horaMovimiento($fecha, $conFecha) {
    return date($conFecha ? 'd/m H:i' : 'H:i', strtotime($fecha));
}

/** Entradas y salidas de una persona en orden: [['tipo', 'letra' => 'E'|'S', 'hora']]. */
function secuenciaMovimientos(array $movimientos, $conFecha) {
    return array_map(function ($m) use ($conFecha) {
        return [
            'tipo'  => $m['tipo'],
            'letra' => $m['tipo'] === 'entrada' ? 'E' : 'S',
            'hora'  => horaMovimiento($m['fecha'], $conFecha),
        ];
    }, $movimientos);
}

/** "E 08:10 · S 10:00 · E 10:30" */
function secuenciaTexto(array $movimientos, $conFecha) {
    return implode(' · ', array_map(function ($s) {
        return $s['letra'] . ' ' . $s['hora'];
    }, secuenciaMovimientos($movimientos, $conFecha)));
}

/** Datos de cada gráfico de estadisticas.php (ver assets/js/estadisticas.js). */
function datosGraficos(array $personas, array $avisos, array $movimientos, array $resumen, $desde, $hasta) {
    $comoFilas = function (array $conteos) {
        $filas = [];
        foreach ($conteos as $categoria => $valor) {
            $filas[] = ['categoria' => (string) $categoria, 'valor' => $valor];
        }
        return $filas;
    };

    // Entradas y salidas por hora del día, de la primera a la última hora con movimientos.
    $porHora = [];
    foreach ($movimientos as $m) {
        $hora = (int) date('G', strtotime($m['fecha']));
        $porHora[$hora][$m['tipo']] = ($porHora[$hora][$m['tipo']] ?? 0) + 1;
    }
    $horas = [];
    if ($porHora) {
        for ($hora = min(array_keys($porHora)); $hora <= max(array_keys($porHora)); $hora++) {
            $horas[] = [
                'categoria' => sprintf('%02d:00', $hora),
                'entradas'  => $porHora[$hora]['entrada'] ?? 0,
                'salidas'   => $porHora[$hora]['salida'] ?? 0,
            ];
        }
    }

    // Por día, solo si el rango tiene varios días (hasta 62, para que se pueda leer).
    $dias = [];
    if ($desde !== $hasta) {
        $porDia = [];
        foreach ($movimientos as $m) {
            $dia = substr($m['fecha'], 0, 10);
            $porDia[$dia][$m['tipo']] = ($porDia[$dia][$m['tipo']] ?? 0) + 1;
        }
        for ($t = strtotime($desde), $n = 0; $t <= strtotime($hasta) && $n < 62; $t = strtotime('+1 day', $t), $n++) {
            $dia = date('Y-m-d', $t);
            $dias[] = [
                'categoria' => date('j', $t) . ' ' . mesCorto($t),
                'entradas'  => $porDia[$dia]['entrada'] ?? 0,
                'salidas'   => $porDia[$dia]['salida'] ?? 0,
            ];
        }
    }

    // Cuántas veces entró cada persona que asistió (para ver los reingresos).
    $veces = ['1 vez' => 0, '2 veces' => 0, '3 o más' => 0];
    foreach ($personas as $p) {
        if ($p['entradas'] === 1) {
            $veces['1 vez']++;
        } elseif ($p['entradas'] === 2) {
            $veces['2 veces']++;
        } elseif ($p['entradas'] > 2) {
            $veces['3 o más']++;
        }
    }

    $fallidos = [];
    foreach ($avisos as $av) {
        $motivo = etiquetaAviso($av['tipo']);
        $fallidos[$motivo] = ($fallidos[$motivo] ?? 0) + 1;
    }
    arsort($fallidos);

    $porteros = [];
    foreach ($movimientos as $m) {
        $nombre = $m['portero'] ?? 'Sin portero (antes de las cuentas)';
        $porteros[$nombre] = ($porteros[$nombre] ?? 0) + 1;
    }
    arsort($porteros);

    return [
        'asistencia' => [
            ['categoria' => 'Asistieron', 'valor' => $resumen['asistieron']],
            ['categoria' => 'No asistieron', 'valor' => $resumen['no_asistieron']],
        ],
        'salida' => [
            ['categoria' => 'Registraron salida', 'valor' => $resumen['con_salida']],
            ['categoria' => 'Sin salida', 'valor' => $resumen['sin_salida']],
        ],
        'tipos'    => porTipoAsistente($personas),
        'horas'    => $horas,
        'dias'     => $dias,
        'veces'    => $comoFilas($veces),
        'fallidos' => $comoFilas($fallidos),
        'porteros' => $comoFilas($porteros),
    ];
}
