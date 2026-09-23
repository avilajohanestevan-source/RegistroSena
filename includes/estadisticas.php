<?php
/**
 * Datos de asistencia para Estadísticas (gráficos), el Historial por
 * invitado y los exportes a Excel y PDF (exportar.php). Todo se calcula
 * sobre un rango de días (desde–hasta, 'AAAA-MM-DD', ambos incluidos).
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

/** Nombre base de los archivos exportados, p. ej. "asistencia_2026-09-01_a_2026-09-15". */
function nombreArchivoExporte($desde, $hasta) {
    return 'asistencia_' . $desde . ($desde !== $hasta ? '_a_' . $hasta : '');
}

/** Ejecuta una consulta con dos parámetros: inicio del primer día y fin del último. */
function filasEnRango(mysqli $conn, $sql, $desde, $hasta) {
    $inicio = $desde . ' 00:00:00';
    $fin = $hasta . ' 23:59:59';
    $evento = eventoContexto();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $evento, $inicio, $fin);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function movimientosEnRango(mysqli $conn, $desde, $hasta) {
    return filasEnRango($conn,
        "SELECT m.tipo, m.fecha, a.nombre, a.cedula, a.empresa, a.estado,
                a.tipo AS tipo_asistente, a.tipo_otro, u.nombre AS portero
         FROM movimientos m
         JOIN asistentes a ON a.evento_id = m.evento_id AND a.cedula = m.cedula
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.evento_id = ? AND m.fecha BETWEEN ? AND ?
         ORDER BY m.fecha, m.id", $desde, $hasta);
}

/** Intentos de entrada o salida que el control no dejó registrar. */
function avisosEnRango(mysqli $conn, $desde, $hasta) {
    return filasEnRango($conn,
        "SELECT av.tipo, av.mensaje, av.fecha, av.cedula, a.nombre, u.nombre AS portero
         FROM avisos av
         LEFT JOIN asistentes a ON a.evento_id = av.evento_id AND a.cedula = av.cedula
         LEFT JOIN usuarios u ON u.id = av.usuario_id
         WHERE av.evento_id = ? AND av.fecha BETWEEN ? AND ?
         ORDER BY av.fecha, av.id", $desde, $hasta);
}

/**
 * Junta las entradas y salidas de una persona (en orden) en "visitas":
 * cada entrada con la salida que le sigue ese mismo día. Devuelve
 * [['entrada' => movimiento|null, 'salida' => movimiento|null,
 *   'segundos' => duración o null]]. Una entrada sin salida (se fue sin
 * registrarla, o sigue adentro) queda con salida null; una salida sin
 * entrada antes, con entrada null.
 */
function paresMovimientos(array $movimientos) {
    $pares = [];
    $abierta = null;
    foreach ($movimientos as $m) {
        if ($m['tipo'] === 'entrada') {
            if ($abierta !== null) {
                $pares[] = ['entrada' => $abierta, 'salida' => null, 'segundos' => null];
            }
            $abierta = $m;
            continue;
        }
        if ($abierta !== null && substr($abierta['fecha'], 0, 10) === substr($m['fecha'], 0, 10)) {
            $pares[] = ['entrada' => $abierta, 'salida' => $m, 'segundos' => strtotime($m['fecha']) - strtotime($abierta['fecha'])];
        } else {
            if ($abierta !== null) {
                $pares[] = ['entrada' => $abierta, 'salida' => null, 'segundos' => null];
            }
            $pares[] = ['entrada' => null, 'salida' => $m, 'segundos' => null];
        }
        $abierta = null;
    }
    if ($abierta !== null) {
        $pares[] = ['entrada' => $abierta, 'salida' => null, 'segundos' => null];
    }
    return $pares;
}

/**
 * Cada asistente registrado (la lista de invitados) con lo que hizo en el
 * rango: sus movimientos en orden, sus visitas (paresMovimientos), cuántas
 * veces entró y salió, primera entrada, última salida, tiempo total
 * adentro (la suma de sus visitas) y si su último movimiento fue una
 * entrada sin salida. $movimientos viene de movimientosEnRango().
 */
function asistenciaPorPersona(mysqli $conn, array $movimientos) {
    $personas = [];
    $stmt = $conn->prepare("SELECT * FROM asistentes WHERE evento_id = ? ORDER BY nombre");
    $eventoId = eventoContexto();
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $registrados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($registrados as $a) {
        $personas[$a['cedula']] = $a + ['movimientos' => []];
    }
    foreach ($movimientos as $m) {
        if (isset($personas[$m['cedula']])) {
            $personas[$m['cedula']]['movimientos'][] = $m;
        }
    }

    $hoy = date('Y-m-d');
    foreach ($personas as $cedula => $p) {
        $entradas = array_values(array_filter($p['movimientos'], function ($m) { return $m['tipo'] === 'entrada'; }));
        $salidas = array_values(array_filter($p['movimientos'], function ($m) { return $m['tipo'] === 'salida'; }));
        $pares = paresMovimientos($p['movimientos']);
        $ultimo = $p['movimientos'] ? $p['movimientos'][count($p['movimientos']) - 1] : null;
        $sinSalida = $ultimo !== null && $ultimo['tipo'] === 'entrada';
        $personas[$cedula] += [
            'pares'           => $pares,
            'entradas'        => count($entradas),
            'salidas'         => count($salidas),
            'primera_entrada' => $entradas ? $entradas[0]['fecha'] : null,
            'ultima_salida'   => $salidas ? $salidas[count($salidas) - 1]['fecha'] : null,
            'segundos_dentro' => array_sum(array_column($pares, 'segundos')),
            'sin_salida'      => $sinSalida,
            'sigue_adentro'   => $sinSalida && substr($ultimo['fecha'], 0, 10) === $hoy && $p['estado'] === 'dentro',
        ];
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
    return date($conFecha ? 'd/m g:i A' : 'g:i A', strtotime($fecha));
}

/** Visitas en texto, una por línea: "07:00 → 08:00 (1 h)", "08:40 → sin salida". */
function visitasTexto(array $pares, $conFecha, $separador = "\n") {
    return implode($separador, array_map(function ($p) use ($conFecha) {
        $entrada = $p['entrada'] ? horaMovimiento($p['entrada']['fecha'], $conFecha) : 'sin entrada';
        $salida = $p['salida'] ? horaMovimiento($p['salida']['fecha'], $conFecha) : 'sin salida';
        return $entrada . ' → ' . $salida . ($p['segundos'] > 0 ? ' (' . fmtDuracion($p['segundos']) . ')' : '');
    }, $pares));
}

/**
 * Visitas de una persona como lista ordenada: una línea por visita
 * (Entrada → Salida · duración) y, entre una visita y otra del mismo día,
 * cuánto duró el descanso. $sigueAdentro marca la última visita abierta
 * como "Sigue adentro" en vez de "Sin salida".
 */
function htmlVisitas(array $pares, $conFecha, $sigueAdentro = false) {
    $registro = function ($m) {
        return $m['portero'] ? ' title="Registró: ' . h($m['portero']) . '"' : '';
    };
    $html = '<ol class="visitas' . ($conFecha ? ' con-fecha' : '') . '">';
    $anterior = null;
    $ultima = count($pares) - 1;
    foreach ($pares as $i => $p) {
        if ($anterior && $anterior['salida'] && $p['entrada']
            && substr($anterior['salida']['fecha'], 0, 10) === substr($p['entrada']['fecha'], 0, 10)) {
            $pausa = strtotime($p['entrada']['fecha']) - strtotime($anterior['salida']['fecha']);
            $html .= '<li class="descanso">Descanso de ' . h(fmtDuracion($pausa)) . '</li>';
        }
        $entrada = $p['entrada']
            ? '<span class="mov mov-e"' . $registro($p['entrada']) . '>Entrada ' . h(horaMovimiento($p['entrada']['fecha'], $conFecha)) . '</span>'
            : '<span class="mov mov-falta">Sin entrada</span>';
        if ($p['salida']) {
            $salida = '<span class="mov mov-s"' . $registro($p['salida']) . '>Salida ' . h(horaMovimiento($p['salida']['fecha'], $conFecha)) . '</span>';
        } elseif ($i === $ultima && $sigueAdentro) {
            $salida = '<span class="mov mov-adentro">Sigue adentro</span>';
        } else {
            $salida = '<span class="mov mov-falta">Sin salida</span>';
        }
        $html .= '<li class="visita">' . $entrada . '<span class="visita-flecha" aria-hidden="true">→</span>' . $salida
            . '<span class="visita-duracion">' . ($p['segundos'] > 0 ? h(fmtDuracion($p['segundos'])) : '') . '</span></li>';
        $anterior = $p;
    }
    return $html . '</ol>';
}

/**
 * Historial agrupado por invitado y día: cada fila trae los datos de la
 * persona, el día, sus visitas y el tiempo total adentro. $busqueda filtra
 * por nombre, cédula, empresa o tipo. Orden: el día más reciente primero
 * y, dentro de cada día, por hora de llegada.
 */
function historialPorInvitado(array $movimientos, $busqueda = '') {
    $grupos = [];
    foreach ($movimientos as $m) {
        $dia = substr($m['fecha'], 0, 10);
        $clave = $dia . '|' . $m['cedula'];
        if (!isset($grupos[$clave])) {
            $grupos[$clave] = [
                'dia' => $dia, 'cedula' => $m['cedula'], 'nombre' => $m['nombre'], 'empresa' => $m['empresa'],
                'tipo' => $m['tipo_asistente'], 'tipo_otro' => $m['tipo_otro'], 'estado' => $m['estado'],
                'movimientos' => [],
            ];
        }
        $grupos[$clave]['movimientos'][] = $m;
    }

    $busqueda = mb_strtolower(trim((string) $busqueda));
    $hoy = date('Y-m-d');
    $filas = [];
    foreach ($grupos as $g) {
        if ($busqueda !== '') {
            $texto = mb_strtolower($g['nombre'] . ' ' . $g['cedula'] . ' ' . $g['empresa'] . ' ' . tipoAsistente($g['tipo'], $g['tipo_otro']));
            if (mb_strpos($texto, $busqueda) === false) {
                continue;
            }
        }
        $ultimo = $g['movimientos'][count($g['movimientos']) - 1];
        $g['pares'] = paresMovimientos($g['movimientos']);
        $g['segundos'] = array_sum(array_column($g['pares'], 'segundos'));
        $g['sigue_adentro'] = $ultimo['tipo'] === 'entrada' && $g['dia'] === $hoy && $g['estado'] === 'dentro';
        $g['sin_salida'] = $ultimo['tipo'] === 'entrada' && !$g['sigue_adentro'];
        $filas[] = $g;
    }
    usort($filas, function ($a, $b) {
        return [$b['dia'], $a['movimientos'][0]['fecha']] <=> [$a['dia'], $b['movimientos'][0]['fecha']];
    });
    return $filas;
}

function minutoDelDia($fecha) {
    $t = strtotime($fecha);
    return (int) date('G', $t) * 60 + (int) date('i', $t);
}

/**
 * Eje de la línea de tiempo del historial, en minutos del día: de la hora
 * en punto antes del primer movimiento a la hora en punto después del
 * último (incluyendo el horario del evento y, si alguien sigue adentro,
 * la hora actual). Todas las filas usan el mismo eje para que se puedan
 * comparar. Devuelve ['inicio', 'fin', 'marcas' => [minutos con etiqueta]].
 */
function ejeLineaTiempo(array $filas, array $horario) {
    $minutos = [];
    foreach ($filas as $f) {
        foreach ($f['movimientos'] as $m) {
            $minutos[] = minutoDelDia($m['fecha']);
        }
        if ($f['sigue_adentro']) {
            $minutos[] = minutoDelDia(date('Y-m-d H:i:s'));
        }
    }
    foreach (['hora_inicio', 'hora_fin'] as $clave) {
        if ($horario[$clave] !== '') {
            [$hora, $minuto] = explode(':', $horario[$clave]);
            $minutos[] = (int) $hora * 60 + (int) $minuto;
        }
    }
    if (!$minutos) {
        return null;
    }
    $inicio = intdiv(min($minutos), 60) * 60;
    $fin = min(24 * 60, (intdiv(max($minutos), 60) + 1) * 60);
    $paso = ($fin - $inicio) > 12 * 60 ? 120 : 60;
    return ['inicio' => $inicio, 'fin' => $fin, 'marcas' => range($inicio, $fin, $paso)];
}

/**
 * Tramos de la línea de tiempo de una fila del historial, en % del eje:
 * verde lleno = visita completa, verde degradado = sigue adentro, rayado
 * amarillo = entrada sin salida (o salida sin entrada).
 */
function tramosLineaTiempo(array $pares, array $eje, $sigueAdentro) {
    $total = max(1, $eje['fin'] - $eje['inicio']);
    $marca = $total * 0.03; // ancho de una entrada o salida suelta
    $porcentaje = function ($minuto) use ($eje, $total) {
        return max(0, min(100, ($minuto - $eje['inicio']) / $total * 100));
    };
    $tramos = [];
    $ultima = count($pares) - 1;
    foreach ($pares as $i => $p) {
        $entrada = $p['entrada'] ? minutoDelDia($p['entrada']['fecha']) : null;
        $salida = $p['salida'] ? minutoDelDia($p['salida']['fecha']) : null;
        $horaEntrada = $p['entrada'] ? horaMovimiento($p['entrada']['fecha'], false) : '';
        $horaSalida = $p['salida'] ? horaMovimiento($p['salida']['fecha'], false) : '';
        if ($entrada !== null && $salida !== null) {
            [$clase, $desde, $hasta] = ['', $entrada, $salida];
            $titulo = 'Adentro de ' . $horaEntrada . ' a ' . $horaSalida . ($p['segundos'] > 0 ? ' (' . fmtDuracion($p['segundos']) . ')' : '');
        } elseif ($entrada !== null && $i === $ultima && $sigueAdentro) {
            [$clase, $desde, $hasta] = ['adentro', $entrada, max($entrada, minutoDelDia(date('Y-m-d H:i:s')))];
            $titulo = 'Entró a las ' . $horaEntrada . ' y sigue adentro';
        } elseif ($entrada !== null) {
            [$clase, $desde, $hasta] = ['falta', $entrada, $entrada + $marca];
            $titulo = 'Entró a las ' . $horaEntrada . ' y no registró salida';
        } else {
            [$clase, $desde, $hasta] = ['falta', $salida - $marca, $salida];
            $titulo = 'Salió a las ' . $horaSalida . ' sin entrada registrada';
        }
        $izquierda = $porcentaje($desde);
        $tramos[] = [
            'clase'     => $clase,
            'izquierda' => round($izquierda, 2),
            'ancho'     => round(max(0.6, $porcentaje($hasta) - $izquierda), 2),
            'titulo'    => $titulo,
        ];
    }
    return $tramos;
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

/** Cómo quedó una visita: 'Completa', 'Sin salida', 'Sigue adentro' o 'Sin entrada'. */
function estadoVisita(array $par, $sigueAdentro = false) {
    if (!$par['entrada']) {
        return 'Sin entrada';
    }
    if ($par['salida']) {
        return 'Completa';
    }
    return $sigueAdentro ? 'Sigue adentro' : 'Sin salida';
}

/**
 * Todas las visitas de todas las personas, una por fila, en orden: para
 * el PDF "Entradas y salidas" y la hoja del mismo nombre del Excel. Cada
 * fila dice de quién es, el día, qué número de visita es de esa persona
 * ese día, a qué hora entró y salió, cuánto duró y cómo quedó.
 */
function visitasPlanas(array $personas) {
    $filas = [];
    foreach ($personas as $p) {
        $numero = [];
        $ultima = count($p['pares']) - 1;
        foreach ($p['pares'] as $i => $par) {
            $referencia = $par['entrada'] ?? $par['salida'];
            $dia = substr($referencia['fecha'], 0, 10);
            $numero[$dia] = ($numero[$dia] ?? 0) + 1;
            $filas[] = [
                'nombre'   => $p['nombre'],
                'cedula'   => $p['cedula'],
                'tipo'     => $p['tipo'],
                'tipo_otro' => $p['tipo_otro'],
                'empresa'  => $p['empresa'],
                'dia'      => $dia,
                'numero'   => $numero[$dia],
                'entrada'  => $par['entrada'],
                'salida'   => $par['salida'],
                'segundos' => $par['segundos'],
                'estado'   => estadoVisita($par, $i === $ultima && $p['sigue_adentro']),
                'portero'  => $par['entrada']['portero'] ?? $par['salida']['portero'] ?? null,
                'orden'    => $referencia['fecha'],
            ];
        }
    }
    usort($filas, function ($a, $b) { return strcmp($a['orden'], $b['orden']); });
    return $filas;
}
