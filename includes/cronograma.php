<?php
/**
 * Cronograma del evento: las actividades de cada día (título, descripción
 * corta, hora de inicio y fin, ubicación y responsable opcionales).
 *
 * Un evento de un día tiene un solo cronograma. Uno de varios días se
 * organiza por día (`dia` = 1, 2, 3...) y el administrador elige cómo
 * armarlo (eventos.cronograma_modo):
 *   'mismo'   -> define las actividades una vez (día 1) y las aplica a
 *                todos los días con "Aplicar a todos los días".
 *   'por_dia' -> cada día tiene su propio conjunto de actividades.
 */

const CRONOGRAMA_MAX_DIAS = 31;
const CRONOGRAMA_MODOS = ['mismo', 'por_dia'];

function diaSemanaCorto($ts) {
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    return $dias[(int) date('w', $ts)];
}

/**
 * Los días del evento: [dia => ['dia', 'fecha', 'etiqueta', 'corta']].
 * Sin fechas definidas el evento tiene un solo día (sin fecha).
 */
function diasDelEvento($evento) {
    $horario = horarioDeEvento($evento);
    if ($horario['fecha_inicio'] === '' || !esFechaValida($horario['fecha_inicio'])) {
        return [1 => ['dia' => 1, 'fecha' => '', 'etiqueta' => 'Día del evento', 'corta' => 'Día 1']];
    }
    $inicio = strtotime($horario['fecha_inicio']);
    $fin = esFechaValida($horario['fecha_fin']) ? strtotime($horario['fecha_fin']) : $inicio;
    $dias = [];
    for ($ts = $inicio, $n = 1; $ts <= $fin && $n <= CRONOGRAMA_MAX_DIAS; $ts = strtotime('+1 day', $ts), $n++) {
        $texto = diaSemanaCorto($ts) . ' ' . date('j', $ts) . ' ' . mesCorto($ts);
        $dias[$n] = [
            'dia'      => $n,
            'fecha'    => date('Y-m-d', $ts),
            'etiqueta' => 'Día ' . $n . ' · ' . $texto,
            'corta'    => $texto,
        ];
    }
    return $dias;
}

/** El día del evento que corresponde a hoy (o null si hoy no es día del evento). */
function diaDeHoy(array $dias) {
    $hoy = date('Y-m-d');
    foreach ($dias as $d) {
        if ($d['fecha'] === $hoy) {
            return $d['dia'];
        }
    }
    return null;
}

function modoCronograma($evento) {
    $modo = $evento['cronograma_modo'] ?? 'mismo';
    return in_array($modo, CRONOGRAMA_MODOS, true) ? $modo : 'mismo';
}

/** Todas las actividades del evento agrupadas por día: [dia => [items ordenados por hora]]. */
function cronogramaDelEvento(mysqli $conn, $eventoId) {
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("SELECT * FROM cronograma WHERE evento_id = ? ORDER BY dia, hora_inicio, hora_fin, orden, id");
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $porDia = [];
    foreach ($filas as $fila) {
        $porDia[(int) $fila['dia']][] = $fila;
    }
    return $porDia;
}

function totalActividades(array $cronograma) {
    return array_sum(array_map('count', $cronograma));
}

function limpiarHora($hora) {
    $hora = substr(trim((string) $hora), 0, 5);
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) ? $hora : '';
}

/**
 * Lee las filas del formulario (items[n][titulo], ...). Ignora las filas
 * totalmente vacías. Devuelve [items, errores por fila].
 */
function leerItemsCronograma(array $filas) {
    $items = [];
    $errores = [];
    foreach ($filas as $fila) {
        $item = [
            'titulo'      => mb_substr(trim((string) ($fila['titulo'] ?? '')), 0, 150),
            'descripcion' => mb_substr(trim((string) ($fila['descripcion'] ?? '')), 0, 255),
            'hora_inicio' => trim((string) ($fila['hora_inicio'] ?? '')),
            'hora_fin'    => trim((string) ($fila['hora_fin'] ?? '')),
            'ubicacion'   => mb_substr(trim((string) ($fila['ubicacion'] ?? '')), 0, 150),
            'responsable' => mb_substr(trim((string) ($fila['responsable'] ?? '')), 0, 150),
        ];
        if (implode('', $item) === '') {
            continue;
        }
        $n = count($items);
        $mensajes = [];
        if ($item['titulo'] === '') {
            $mensajes[] = 'Escribe el título de la actividad.';
        }
        $inicio = limpiarHora($item['hora_inicio']);
        $fin = limpiarHora($item['hora_fin']);
        if ($inicio === '' || $fin === '') {
            $mensajes[] = 'Indica la hora de inicio y la de fin.';
        } elseif ($inicio >= $fin) {
            $mensajes[] = 'La hora de inicio debe ser anterior a la de fin.';
        }
        $item['hora_inicio'] = $inicio !== '' ? $inicio : $item['hora_inicio'];
        $item['hora_fin'] = $fin !== '' ? $fin : $item['hora_fin'];
        if ($mensajes) {
            $errores[$n] = $mensajes;
        }
        $items[] = $item;
    }
    return [$items, $errores];
}

/**
 * Actividades de un mismo día que se cruzan en horario. Devuelve
 * [indice => [índices con los que se cruza]].
 */
function solapamientosCronograma(array $items) {
    $cruces = [];
    $total = count($items);
    for ($i = 0; $i < $total; $i++) {
        for ($j = $i + 1; $j < $total; $j++) {
            $a = $items[$i];
            $b = $items[$j];
            if ($a['hora_inicio'] === '' || $b['hora_inicio'] === '' || $a['hora_inicio'] >= $a['hora_fin'] || $b['hora_inicio'] >= $b['hora_fin']) {
                continue;
            }
            if ($a['hora_inicio'] < $b['hora_fin'] && $b['hora_inicio'] < $a['hora_fin']) {
                $cruces[$i][] = $j;
                $cruces[$j][] = $i;
            }
        }
    }
    return $cruces;
}

/** Reemplaza las actividades de un día por las recibidas (ya validadas). */
function guardarDiaCronograma(mysqli $conn, array $evento, $dia, array $items) {
    $eventoId = (int) $evento['id'];
    $dia = (int) $dia;
    $dias = diasDelEvento($evento);
    $fecha = $dias[$dia]['fecha'] ?? '';

    usort($items, function ($a, $b) {
        return [$a['hora_inicio'], $a['hora_fin']] <=> [$b['hora_inicio'], $b['hora_fin']];
    });

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM cronograma WHERE evento_id = ? AND dia = ?");
        $stmt->bind_param('ii', $eventoId, $dia);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO cronograma (evento_id, dia, fecha, titulo, descripcion, hora_inicio, hora_fin, ubicacion, responsable, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $orden => $item) {
            $stmt->bind_param(
                'iisssssssi',
                $eventoId, $dia, $fecha, $item['titulo'], $item['descripcion'],
                $item['hora_inicio'], $item['hora_fin'], $item['ubicacion'], $item['responsable'], $orden
            );
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * "Aplicar a todos los días": copia las actividades de $diaOrigen a los
 * días indicados, con las mismas horas. Devuelve cuántos días se actualizaron.
 */
function aplicarCronogramaADias(mysqli $conn, array $evento, $diaOrigen, array $diasDestino) {
    $cronograma = cronogramaDelEvento($conn, $evento['id']);
    $items = $cronograma[(int) $diaOrigen] ?? [];
    $dias = diasDelEvento($evento);
    $aplicados = 0;
    foreach (array_unique(array_map('intval', $diasDestino)) as $dia) {
        if ($dia === (int) $diaOrigen || !isset($dias[$dia])) {
            continue;
        }
        if (guardarDiaCronograma($conn, $evento, $dia, $items)) {
            $aplicados++;
        }
    }
    return $aplicados;
}

function fijarModoCronograma(mysqli $conn, $eventoId, $modo) {
    $modo = in_array($modo, CRONOGRAMA_MODOS, true) ? $modo : 'mismo';
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("UPDATE eventos SET cronograma_modo = ? WHERE id = ?");
    $stmt->bind_param('si', $modo, $eventoId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Cuando cambian las fechas del evento: recalcula la fecha de cada día y
 * quita las actividades de los días que ya no existen.
 */
function sincronizarCronograma(mysqli $conn, $eventoId) {
    $evento = eventoPorId($conn, $eventoId);
    if (!$evento) {
        return;
    }
    $dias = diasDelEvento($evento);
    $id = (int) $evento['id'];
    $total = count($dias);
    $stmt = $conn->prepare("DELETE FROM cronograma WHERE evento_id = ? AND dia > ?");
    $stmt->bind_param('ii', $id, $total);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE cronograma SET fecha = ? WHERE evento_id = ? AND dia = ?");
    foreach ($dias as $d) {
        $stmt->bind_param('sii', $d['fecha'], $id, $d['dia']);
        $stmt->execute();
    }
    $stmt->close();
}

/** ¿Todos los días tienen exactamente las mismas actividades que el día 1? */
function diasDistintosDelPrimero(array $cronograma, array $dias) {
    $firma = function ($items) {
        return implode('|', array_map(function ($i) {
            return $i['hora_inicio'] . $i['hora_fin'] . $i['titulo'] . $i['descripcion'] . $i['ubicacion'] . $i['responsable'];
        }, $items ?? []));
    };
    $base = $firma($cronograma[1] ?? []);
    $distintos = [];
    foreach ($dias as $d) {
        if ($d['dia'] !== 1 && $firma($cronograma[$d['dia']] ?? []) !== $base) {
            $distintos[] = $d['dia'];
        }
    }
    return $distintos;
}

function textoHoraActividad(array $item) {
    return fmtHora12($item['hora_inicio']) . ' – ' . fmtHora12($item['hora_fin']);
}

/** URL pública del cronograma del evento activo (opcionalmente en un día). */
function urlCronograma($dia = null) {
    return urlDelSistema('cronograma_ver.php') . ($dia ? '?dia=' . (int) $dia : '');
}

/**
 * Los días que van en un correo: el de hoy si el evento ya está en curso
 * (el "día correspondiente"), o todos si todavía no empieza. Devuelve
 * ['dias' => [[etiqueta, items]], 'faltan' => días que no caben, 'url'].
 */
function cronogramaParaCorreo(mysqli $conn, $evento, $maxDias = 4) {
    if (!$evento) {
        return null;
    }
    $cronograma = cronogramaDelEvento($conn, $evento['id']);
    if (!$cronograma) {
        return null;
    }
    $dias = diasDelEvento($evento);
    $hoy = diaDeHoy($dias);
    $elegidos = $hoy ? [$dias[$hoy]] : array_values($dias);
    $conActividades = array_values(array_filter($elegidos, function ($d) use ($cronograma) {
        return !empty($cronograma[$d['dia']]);
    }));
    if (!$conActividades) {
        return null;
    }
    $bloques = [];
    foreach (array_slice($conActividades, 0, $maxDias) as $d) {
        $bloques[] = [
            'etiqueta' => count($dias) > 1 ? $d['etiqueta'] : ($d['fecha'] !== '' ? $d['corta'] : 'Cronograma'),
            'items'    => $cronograma[$d['dia']],
        ];
    }
    return [
        'dias'   => $bloques,
        'faltan' => max(0, count($conActividades) - $maxDias),
        'url'    => urlCronograma($hoy),
        'pdf'    => urlDelSistema('cronograma_descargar.php') . '?formato=pdf',
    ];
}

/** '2026-09-16' → '16 de septiembre de 2026' (con $conDia: 'miércoles 16 de septiembre de 2026'). */
function fechaLarga($fecha, $conDia = false) {
    if (!esFechaValida((string) $fecha)) {
        return '';
    }
    $ts = strtotime($fecha);
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $texto = date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    return $conDia ? $dias[(int) date('w', $ts)] . ' ' . $texto : $texto;
}
