<?php
/**
 * Exportes del administrador para el rango elegido en Estadísticas (como
 * en TaxSync: PhpSpreadsheet para Excel y Dompdf para PDF).
 *   ?formato=xlsx                  → libro con las hojas Resumen, Invitados,
 *                                    Asistencia, Movimientos e Intentos fallidos
 *   ?formato=pdf&lista=invitados   → todos los registrados y si asistieron
 *   ?formato=pdf&lista=asistencia  → quienes asistieron, con cada entrada y salida
 *   ?formato=pdf&lista=movimientos → todas las entradas y salidas en orden,
 *                                    con quién las registró
 * Las librerías se instalan con Composer (carpeta vendor/, ver README).
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/estadisticas.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* ---------------------------- Excel ---------------------------- */

/** Título de la hoja (fila 1, verde SENA) y subtítulo (fila 2), de la columna A a $ultimaColumna. */
function tituloHoja(Worksheet $hoja, $titulo, $subtitulo, $ultimaColumna) {
    $hoja->setCellValue('A1', $titulo);
    $hoja->mergeCells('A1:' . $ultimaColumna . '1');
    $estilo = $hoja->getStyle('A1');
    $estilo->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
    $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('39A900');
    $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $hoja->getRowDimension(1)->setRowHeight(28);

    $hoja->setCellValue('A2', $subtitulo);
    $hoja->mergeCells('A2:' . $ultimaColumna . '2');
    $hoja->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('5B6660');
    $hoja->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

/**
 * Tabla con el encabezado azul SENA en $filaEncabezado y los datos
 * debajo: filas alternas, bordes, filtros y columnas autoajustadas. Los
 * textos se guardan siempre como texto (así una cédula no pierde los
 * ceros a la izquierda y nada se toma como fórmula). Devuelve la primera
 * fila libre debajo de la tabla.
 */
function tablaHoja(Worksheet $hoja, array $encabezados, array $filas, $filaEncabezado = 4, $fijarEncabezado = true) {
    $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
    foreach ($encabezados as $i => $titulo) {
        $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $filaEncabezado, $titulo);
    }
    $rangoEncabezado = 'A' . $filaEncabezado . ':' . $ultima . $filaEncabezado;
    $estilo = $hoja->getStyle($rangoEncabezado);
    $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00304D');
    $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $hoja->getRowDimension($filaEncabezado)->setRowHeight(22);

    $fila = $filaEncabezado + 1;
    foreach ($filas as $datos) {
        foreach (array_values($datos) as $i => $valor) {
            $celda = Coordinate::stringFromColumnIndex($i + 1) . $fila;
            if (is_int($valor) || is_float($valor)) {
                $hoja->setCellValue($celda, $valor);
            } else {
                $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
            }
        }
        if (($fila - $filaEncabezado) % 2 === 0) {
            $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4F9F0');
        }
        $fila++;
    }
    if (!$filas) {
        $hoja->setCellValue('A' . $fila, 'No hay datos en este rango.');
        $hoja->mergeCells('A' . $fila . ':' . $ultima . $fila);
        $hoja->getStyle('A' . $fila)->getFont()->setItalic(true)->getColor()->setRGB('5B6660');
        $fila++;
    }

    $hoja->getStyle('A' . $filaEncabezado . ':' . $ultima . ($fila - 1))->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DDE5D8');
    for ($i = 1; $i <= count($encabezados); $i++) {
        $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
    if ($fijarEncabezado) {
        $hoja->freezePane('A' . ($filaEncabezado + 1));
        if ($filas) {
            $hoja->setAutoFilter('A' . $filaEncabezado . ':' . $ultima . ($fila - 1));
        }
    }
    return $fila;
}

/** Columna de texto largo: ancho fijo y el texto en varias líneas. */
function columnaAncha(Worksheet $hoja, $columna, $ancho, $desdeFila, $hastaFila) {
    $hoja->getColumnDimension($columna)->setAutoSize(false)->setWidth($ancho);
    $hoja->getStyle($columna . $desdeFila . ':' . $columna . $hastaFila)->getAlignment()->setWrapText(true);
}

function fechaExcel($fecha) {
    return $fecha ? date('d/m/Y H:i', strtotime($fecha)) : '—';
}

function exportarExcel(array $d) {
    $libro = new Spreadsheet();
    $libro->getProperties()->setCreator('Control de ingreso SENA')->setTitle('Asistencia · ' . $d['evento']);
    $subtitulo = $d['evento'] . ' · ' . $d['rango'] . ' · Generado: ' . $d['generado'];
    $r = $d['resumen'];

    // Resumen: indicadores y asistencia por tipo.
    $hoja = $libro->getActiveSheet();
    $hoja->setTitle('Resumen');
    tituloHoja($hoja, 'RESUMEN DE ASISTENCIA', $subtitulo, 'C');
    $siguiente = tablaHoja($hoja, ['INDICADOR', 'VALOR'], [
        ['Registrados (invitados)', $r['registrados']],
        ['Asistieron', $r['asistieron']],
        ['No asistieron', $r['no_asistieron']],
        ['Entraron más de una vez', $r['reingresaron']],
        ['Registraron su salida', $r['con_salida']],
        ['Sin salida registrada', $r['sin_salida']],
        ['Entradas registradas', $r['entradas']],
        ['Salidas registradas', $r['salidas']],
        ['Intentos fallidos (no se dejaron registrar)', $r['intentos_fallidos']],
        ['Tiempo promedio adentro', fmtDuracion($r['promedio_dentro'])],
    ], 4, false);
    $filasTipo = array_map(function ($t) {
        return [$t['categoria'], $t['registrados'], $t['asistieron']];
    }, porTipoAsistente($d['personas']));
    tablaHoja($hoja, ['TIPO DE ASISTENTE', 'REGISTRADOS', 'ASISTIERON'], $filasTipo, $siguiente + 1, false);

    // Invitados: todos los registrados y si asistieron.
    $hoja = $libro->createSheet();
    $hoja->setTitle('Invitados');
    tituloHoja($hoja, 'LISTA DE INVITADOS (REGISTRADOS)', $subtitulo, 'J');
    $filas = [];
    foreach ($d['personas'] as $p) {
        $filas[] = [
            $p['nombre'], tipoAsistente($p['tipo'], $p['tipo_otro']), $p['cedula'], $p['correo'], $p['telefono'],
            $p['empresa'] !== '' ? $p['empresa'] : '—', fechaExcel($p['registrado_en']),
            $p['entradas'] > 0 ? 'Sí' : 'No', $p['entradas'], $p['salidas'],
        ];
    }
    tablaHoja($hoja, ['NOMBRE', 'TIPO', 'CÉDULA', 'CORREO', 'TELÉFONO', 'EMPRESA', 'REGISTRADO', '¿ASISTIÓ?', 'ENTRADAS', 'SALIDAS'], $filas);

    // Asistencia: quienes vinieron, con cada entrada y salida.
    $hoja = $libro->createSheet();
    $hoja->setTitle('Asistencia');
    tituloHoja($hoja, 'ASISTENCIA CON ENTRADAS Y SALIDAS', $subtitulo, 'K');
    $filas = [];
    foreach ($d['asistentes'] as $p) {
        $filas[] = [
            $p['nombre'], tipoAsistente($p['tipo'], $p['tipo_otro']), $p['cedula'], $p['empresa'] !== '' ? $p['empresa'] : '—',
            fechaExcel($p['primera_entrada']), fechaExcel($p['ultima_salida']), $p['entradas'], $p['salidas'],
            fmtDuracion($p['segundos_dentro']), $p['sin_salida'] ? 'No' : 'Sí', secuenciaTexto($p['movimientos'], $d['variosDias']),
        ];
    }
    $fin = tablaHoja($hoja, ['NOMBRE', 'TIPO', 'CÉDULA', 'EMPRESA', 'PRIMERA ENTRADA', 'ÚLTIMA SALIDA', 'ENTRADAS', 'SALIDAS', 'TIEMPO ADENTRO', '¿SALIDA REGISTRADA?', 'ENTRADAS (E) Y SALIDAS (S)'], $filas);
    columnaAncha($hoja, 'K', 60, 5, $fin - 1);

    // Movimientos: cada entrada y salida, con el número de vez de esa persona.
    $hoja = $libro->createSheet();
    $hoja->setTitle('Movimientos');
    tituloHoja($hoja, 'ENTRADAS Y SALIDAS', $subtitulo, 'H');
    $filas = [];
    foreach ($d['movimientos'] as $m) {
        $filas[] = [
            fechaExcel($m['fecha']), ucfirst($m['tipo']), $m['vez'], $m['nombre'], tipoAsistente($m['tipo_asistente'], $m['tipo_otro']),
            $m['cedula'], $m['empresa'] !== '' ? $m['empresa'] : '—', $m['portero'] ?? '—',
        ];
    }
    tablaHoja($hoja, ['FECHA Y HORA', 'MOVIMIENTO', 'N.° DE VEZ', 'NOMBRE', 'TIPO', 'CÉDULA', 'EMPRESA', 'REGISTRÓ'], $filas);

    // Intentos fallidos.
    $hoja = $libro->createSheet();
    $hoja->setTitle('Intentos fallidos');
    tituloHoja($hoja, 'INTENTOS QUE NO SE DEJARON REGISTRAR', $subtitulo, 'F');
    $filas = [];
    foreach ($d['avisos'] as $av) {
        $filas[] = [fechaExcel($av['fecha']), etiquetaAviso($av['tipo']), $av['nombre'] ?? '—', $av['cedula'], $av['mensaje'], $av['portero'] ?? '—'];
    }
    $fin = tablaHoja($hoja, ['FECHA Y HORA', 'MOTIVO', 'NOMBRE', 'CÉDULA', 'DETALLE', 'REGISTRÓ'], $filas);
    columnaAncha($hoja, 'E', 70, 5, $fin - 1);

    $libro->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $d['archivo'] . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($libro))->save('php://output');
    exit;
}

/* ----------------------------- PDF ----------------------------- */

/** Documento con encabezado (logo SENA, título, evento y rango) y pie con el número de página. */
function documentoPdf($titulo, array $d, $cuerpo) {
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/img/sena-logo-verde.png'));
    return '<html><head><meta charset="UTF-8"><style>
        @page { margin: 112px 32px 64px 32px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #1B1B1B; }
        header { position: fixed; top: -92px; left: 0; right: 0; height: 72px; border-bottom: 3px solid #39A900; }
        header img { position: absolute; left: 0; top: 0; width: 60px; height: 60px; }
        header .textos { margin-left: 74px; padding-top: 3px; }
        header .titulo { font-size: 16px; font-weight: bold; color: #00304D; }
        header .evento { font-size: 11px; font-weight: bold; color: #007832; margin-top: 2px; }
        header .sub { font-size: 8.5px; color: #5B6660; margin-top: 3px; }
        footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 30px; border-top: 1px solid #DDE5D8; padding-top: 6px; font-size: 8px; color: #5B6660; }
        table.indicadores { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
        table.indicadores td { border: 1px solid #DDE5D8; border-top: 3px solid #39A900; padding: 6px 8px; }
        table.indicadores .valor { font-size: 15px; font-weight: bold; color: #00304D; }
        table.indicadores .etiqueta { font-size: 8px; color: #5B6660; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th { background: #00304D; color: #FFFFFF; padding: 6px 5px; text-align: left; font-size: 8px; text-transform: uppercase; }
        table.datos td { padding: 5px; border-bottom: 1px solid #DDE5D8; vertical-align: top; }
        table.datos tr:nth-child(even) td { background: #F4F9F0; }
        .muted { color: #5B6660; font-size: 8px; }
        .si, .e { color: #007832; font-weight: bold; }
        .no, .s { color: #8A5E00; font-weight: bold; }
        .alerta { color: #B3261E; font-weight: bold; }
        .vacio { text-align: center; color: #5B6660; padding: 18px; }
    </style></head><body>
        <header>
            <img src="' . $logo . '" alt="SENA">
            <div class="textos">
                <div class="titulo">' . h($titulo) . '</div>
                <div class="evento">' . h($d['evento']) . '</div>
                <div class="sub">' . h($d['rango']) . ' &middot; Generado: ' . h($d['generado']) . '</div>
            </div>
        </header>
        <footer>Servicio Nacional de Aprendizaje &mdash; SENA &middot; sena.edu.co</footer>
        ' . $cuerpo . '
    </body></html>';
}

/** Fila de indicadores: [[valor, etiqueta], ...] */
function indicadoresPdf(array $items) {
    $celdas = '';
    foreach ($items as [$valor, $etiqueta]) {
        $celdas .= '<td><div class="valor">' . h($valor) . '</div><div class="etiqueta">' . h($etiqueta) . '</div></td>';
    }
    return '<table class="indicadores"><tr>' . $celdas . '</tr></table>';
}

/** Tabla de datos; $filas son los <tr> ya armados. */
function tablaPdf(array $encabezados, array $filas) {
    $th = '';
    foreach ($encabezados as $titulo) {
        $th .= '<th>' . h($titulo) . '</th>';
    }
    $cuerpo = $filas ? implode('', $filas) : '<tr><td colspan="' . count($encabezados) . '" class="vacio">No hay datos en este rango.</td></tr>';
    return '<table class="datos"><thead><tr>' . $th . '</tr></thead><tbody>' . $cuerpo . '</tbody></table>';
}

function exportarPdf($lista, array $d) {
    $r = $d['resumen'];
    $orientacion = 'portrait';

    if ($lista === 'asistencia') {
        $titulo = 'Asistencia con entradas y salidas';
        $orientacion = 'landscape';
        $filas = [];
        foreach ($d['asistentes'] as $i => $p) {
            $secuencia = array_map(function ($s) {
                return '<span class="' . ($s['tipo'] === 'entrada' ? 'e' : 's') . '">' . $s['letra'] . '</span> ' . h($s['hora']);
            }, secuenciaMovimientos($p['movimientos'], $d['variosDias']));
            $filas[] = '<tr>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . h($p['nombre']) . '<br><span class="muted">C.C. ' . h($p['cedula']) . '</span></td>'
                . '<td>' . h(tipoAsistente($p['tipo'], $p['tipo_otro'])) . '</td>'
                . '<td>' . h(fechaExcel($p['primera_entrada'])) . '</td>'
                . '<td>' . h(fechaExcel($p['ultima_salida'])) . '</td>'
                . '<td>' . $p['entradas'] . ' / ' . $p['salidas'] . '</td>'
                . '<td>' . h(fmtDuracion($p['segundos_dentro'])) . '</td>'
                . '<td>' . implode(' &middot; ', $secuencia) . ($p['sin_salida'] ? ' <span class="alerta">(sin salida)</span>' : '') . '</td>'
                . '</tr>';
        }
        $cuerpo = indicadoresPdf([
            [$r['asistieron'], 'Asistieron'],
            [$r['reingresaron'], 'Entraron más de una vez'],
            [$r['sin_salida'], 'Sin salida registrada'],
            [fmtDuracion($r['promedio_dentro']), 'Tiempo promedio adentro'],
        ]) . tablaPdf(['#', 'Nombre', 'Tipo', 'Primera entrada', 'Última salida', 'Entradas / salidas', 'Tiempo adentro', 'Entradas (E) y salidas (S)'], $filas);
    } elseif ($lista === 'movimientos') {
        $titulo = 'Entradas y salidas';
        $filas = [];
        foreach ($d['movimientos'] as $m) {
            $esEntrada = $m['tipo'] === 'entrada';
            $filas[] = '<tr>'
                . '<td>' . h(fechaExcel($m['fecha'])) . '</td>'
                . '<td><span class="' . ($esEntrada ? 'e' : 's') . '">' . ($esEntrada ? 'Entrada' : 'Salida') . '</span>'
                . ($m['vez'] > 1 ? ' <span class="muted">(' . $m['vez'] . '.ª vez)</span>' : '') . '</td>'
                . '<td>' . h($m['nombre']) . '</td>'
                . '<td>' . h($m['cedula']) . '</td>'
                . '<td>' . h(tipoAsistente($m['tipo_asistente'], $m['tipo_otro'])) . '</td>'
                . '<td>' . h($m['portero'] ?? '—') . '</td>'
                . '</tr>';
        }
        $cuerpo = indicadoresPdf([
            [$r['entradas'], 'Entradas'],
            [$r['salidas'], 'Salidas'],
            [$r['intentos_fallidos'], 'Intentos fallidos'],
        ]) . tablaPdf(['Fecha y hora', 'Movimiento', 'Nombre', 'Cédula', 'Tipo', 'Registró'], $filas);
    } else {
        $titulo = 'Lista de invitados';
        $filas = [];
        $n = 0;
        foreach ($d['personas'] as $p) {
            $asistio = $p['entradas'] > 0;
            $filas[] = '<tr>'
                . '<td>' . (++$n) . '</td>'
                . '<td>' . h($p['nombre']) . '</td>'
                . '<td>' . h(tipoAsistente($p['tipo'], $p['tipo_otro'])) . '</td>'
                . '<td>' . h($p['cedula']) . '</td>'
                . '<td>' . h($p['empresa'] !== '' ? $p['empresa'] : '—') . '</td>'
                . '<td><span class="' . ($asistio ? 'si' : 'no') . '">' . ($asistio ? 'Sí' : 'No') . '</span></td>'
                . '<td>' . $p['entradas'] . '</td>'
                . '</tr>';
        }
        $cuerpo = indicadoresPdf([
            [$r['registrados'], 'Registrados'],
            [$r['asistieron'], 'Asistieron'],
            [$r['no_asistieron'], 'No asistieron'],
        ]) . tablaPdf(['#', 'Nombre', 'Tipo', 'Cédula', 'Empresa', '¿Asistió?', 'Entradas'], $filas);
        $lista = 'invitados';
    }

    $opciones = new Options();
    $opciones->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($opciones);
    $dompdf->loadHtml(documentoPdf($titulo, $d, $cuerpo), 'UTF-8');
    $dompdf->setPaper('letter', $orientacion);
    $dompdf->render();

    // Número de página en el pie, a la derecha. Dompdf lo escribe sobre cada
    // página ya armada (el contador de páginas por CSS no le funciona).
    $lienzo = $dompdf->getCanvas();
    $metricas = $dompdf->getFontMetrics();
    $fuente = $metricas->getFont('DejaVu Sans');
    $ancho = $metricas->getTextWidth('Página 00 de 00', $fuente, 6);
    $lienzo->page_text($lienzo->get_width() - 24 - $ancho, $lienzo->get_height() - 34, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 6, [0.357, 0.4, 0.376]);

    $dompdf->stream($d['archivo'] . '_' . $lista . '.pdf', ['Attachment' => false]);
    exit;
}

/* --------------------------- Datos --------------------------- */

$horario = horarioEvento($conn);
[$desde, $hasta] = rangoEstadisticas($conn, $horario, $_GET['desde'] ?? '', $_GET['hasta'] ?? '');
$movimientos = movimientosEnRango($conn, $desde, $hasta);
$avisos = avisosEnRango($conn, $desde, $hasta);
$personas = asistenciaPorPersona($conn, $movimientos);

// Número de vez de cada entrada/salida por persona ("2.ª entrada"), para ver los reingresos.
$vez = [];
foreach ($movimientos as $i => $m) {
    $clave = $m['cedula'] . '|' . $m['tipo'];
    $vez[$clave] = ($vez[$clave] ?? 0) + 1;
    $movimientos[$i]['vez'] = $vez[$clave];
}

$asistentes = array_values(array_filter($personas, function ($p) { return $p['entradas'] > 0; }));
usort($asistentes, function ($a, $b) { return strcmp($a['primera_entrada'], $b['primera_entrada']); });

$datos = [
    'evento'      => nombreEvento($conn),
    'rango'       => ucfirst(textoRango($desde, $hasta)),
    'generado'    => date('d/m/Y H:i'),
    'resumen'     => resumenAsistencia($personas, $avisos),
    'personas'    => $personas,
    'asistentes'  => $asistentes,
    'movimientos' => $movimientos,
    'avisos'      => $avisos,
    'variosDias'  => $desde !== $hasta,
    'archivo'     => 'asistencia_' . $desde . ($desde !== $hasta ? '_a_' . $hasta : ''),
];

$formato = $_GET['formato'] ?? '';
if ($formato === 'xlsx') {
    exportarExcel($datos);
} elseif ($formato === 'pdf') {
    exportarPdf($_GET['lista'] ?? 'invitados', $datos);
}
header('Location: estadisticas.php');
exit;
