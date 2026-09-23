<?php
/**
 * Descarga del cronograma en PDF o PNG (ver includes/cronograma_export.php).
 *   ?formato=pdf|png  &dia=0 (todos) o N  &modo=descarga|vista
 * Sin ?evento es el cronograma del evento activo y es público (el mismo
 * que ve cualquier asistente). Con ?evento=ID (un evento archivado) solo
 * lo puede descargar el administrador.
 */
require_once __DIR__ . '/vendor/autoload.php';

if (isset($_GET['evento'])) {
    require_once __DIR__ . '/includes/panel_admin.php';
    $eventoFila = fijarEventoConsulta($conn, $_GET['evento']);
} else {
    require_once __DIR__ . '/includes/publico.php';
    $eventoFila = $eventoActual;
}
require_once __DIR__ . '/includes/cronograma_export.php';

if (!$eventoFila) {
    http_response_code(404);
    exit('No hay un evento con cronograma para descargar.');
}

$formato = ($_GET['formato'] ?? 'pdf') === 'png' ? 'png' : 'pdf';
$comoDescarga = ($_GET['modo'] ?? 'descarga') !== 'vista';
$todosLosDias = diasDelEvento($eventoFila);
$cronograma = cronogramaDelEvento($conn, $eventoFila['id']);
$dia = (int) ($_GET['dia'] ?? 0);

if (isset($todosLosDias[$dia])) {
    $dias = [$dia => $todosLosDias[$dia]];
} else {
    // Todos los días: los que tienen actividades (o todos, si ninguno tiene).
    $conActividades = array_filter($todosLosDias, function ($d) use ($cronograma) { return !empty($cronograma[$d['dia']]); });
    $dias = $conActividades ?: $todosLosDias;
}

$base = preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $eventoFila['nombre'])));
$archivo = 'cronograma_' . trim($base, '_') . (count($dias) === 1 && count($todosLosDias) > 1 ? '_dia_' . array_key_first($dias) : '');

@set_time_limit(120);
if ($formato === 'png') {
    descargarCronogramaPng($eventoFila, $dias, $cronograma, count($todosLosDias) > 1, $archivo, $comoDescarga);
} else {
    descargarCronogramaPdf($eventoFila, $dias, $cronograma, count($todosLosDias) > 1, $archivo, $comoDescarga);
}
