<?php
/**
 * Exportes del administrador para el rango elegido en Estadísticas (como
 * en TaxSync: PhpSpreadsheet para Excel y Dompdf para PDF).
 *   ?formato=xlsx                  → libro con las hojas Resumen, Invitados,
 *                                    Asistencia, Entradas y salidas, Movimientos e Intentos fallidos
 *   ?formato=pdf&lista=invitados   → todos los registrados y si asistieron
 *   ?formato=pdf&lista=asistencia  → el Detalle de asistencia tal como se ve
 *                                    en pantalla (visitas con colores)
 *   ?formato=pdf&lista=visitas     → una fila por visita: quién, cuándo entró
 *                                    y cuándo salió
 * &modo=vista muestra el archivo en el navegador (la vista previa de
 * estadisticas.php: el PDF tal cual, o el Excel convertido a HTML con sus
 * hojas); &modo=descarga lo descarga.
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
use PhpOffice\PhpSpreadsheet\Writer\Html as EscritorHtml;
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
    $hoja->getStyle('A' . ($filaEncabezado + 1) . ':' . $ultima . ($fila - 1))->getAlignment()
        ->setVertical(Alignment::VERTICAL_TOP);
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

/** Pinta la columna de estado: rojo si falta la entrada o la salida, verde si sigue adentro. */
function colorearEstados(Worksheet $hoja, $columna, $desdeFila, $hastaFila) {
    $colores = ['Sin salida' => 'B3261E', 'Sin entrada' => 'B3261E', 'Sigue adentro' => '007832'];
    for ($fila = $desdeFila; $fila <= $hastaFila; $fila++) {
        $estado = (string) $hoja->getCell($columna . $fila)->getValue();
        if (isset($colores[$estado])) {
            $hoja->getStyle($columna . $fila)->getFont()->setBold(true)->getColor()->setRGB($colores[$estado]);
        }
    }
}

function fechaExcel($fecha) {
    return $fecha ? date('d/m/Y g:i A', strtotime($fecha)) : '—';
}

function construirLibro(array $d) {
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

    // Asistencia: quienes vinieron, con cada visita en su propia línea.
    $hoja = $libro->createSheet();
    $hoja->setTitle('Asistencia');
    tituloHoja($hoja, 'ASISTENCIA CON ENTRADAS Y SALIDAS', $subtitulo, 'K');
    $filas = [];
    foreach ($d['asistentes'] as $p) {
        $filas[] = [
            $p['nombre'], tipoAsistente($p['tipo'], $p['tipo_otro']), $p['cedula'], $p['empresa'] !== '' ? $p['empresa'] : '—',
            fechaExcel($p['primera_entrada']), fechaExcel($p['ultima_salida']), $p['entradas'], $p['salidas'],
            fmtDuracion($p['segundos_dentro']), $p['sin_salida'] ? 'No' : 'Sí', visitasTexto($p['pares'], $d['variosDias']),
        ];
    }
    $fin = tablaHoja($hoja, ['NOMBRE', 'TIPO', 'CÉDULA', 'EMPRESA', 'PRIMERA ENTRADA', 'ÚLTIMA SALIDA', 'ENTRADAS', 'SALIDAS', 'TIEMPO ADENTRO', '¿SALIDA REGISTRADA?', 'VISITAS (ENTRADA → SALIDA)'], $filas);
    columnaAncha($hoja, 'K', 42, 5, $fin - 1);

    // Entradas y salidas: una fila por visita (entró → salió), como se ve
    // en el Detalle de asistencia de la pantalla.
    $hoja = $libro->createSheet();
    $hoja->setTitle('Entradas y salidas');
    tituloHoja($hoja, 'ENTRADAS Y SALIDAS POR VISITA', $subtitulo, 'J');
    $filas = [];
    foreach ($d['visitas'] as $v) {
        $filas[] = [
            $v['nombre'], tipoAsistente($v['tipo'], $v['tipo_otro']), $v['cedula'], fmtDia($v['dia']), $v['numero'],
            $v['entrada'] ? date('H:i', strtotime($v['entrada']['fecha'])) : '—',
            $v['salida'] ? date('H:i', strtotime($v['salida']['fecha'])) : '—',
            fmtDuracion($v['segundos']), $v['estado'], $v['portero'] ?? '—',
        ];
    }
    $fin = tablaHoja($hoja, ['NOMBRE', 'TIPO', 'CÉDULA', 'DÍA', 'VISITA N.°', 'ENTRÓ', 'SALIÓ', 'TIEMPO', 'ESTADO', 'REGISTRÓ'], $filas);
    colorearEstados($hoja, 'I', 5, $fin - 1);

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
    return $libro;
}

/**
 * Vista previa del Excel: el mismo libro convertido a HTML por
 * PhpSpreadsheet, con las hojas como pestañas (se ve una a la vez) y la
 * letra y los colores del SENA.
 */
function vistaPreviaExcel(Spreadsheet $libro) {
    $agregado = '<style>
        body { margin: 0; padding: 0 16px 24px; background: #fff; font-family: "Work Sans", Calibri, Arial, sans-serif; }
        ul.navigation { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 6px; list-style: none;
            margin: 0 -16px 14px; padding: 10px 16px; background: #F4F9F0; border-bottom: 1px solid #DDE5D8; }
        ul.navigation a { display: block; padding: 7px 14px; border: 1px solid #CBD6C4; border-radius: 8px; background: #fff;
            color: #1B1B1B; font-size: 13px; font-weight: 600; text-decoration: none; }
        ul.navigation a.activa { background: #39A900; border-color: #39A900; color: #fff; }
        table.oculta { display: none !important; }
        table.gridlines td { border-color: #EEF1ED; }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        var enlaces = [].slice.call(document.querySelectorAll("ul.navigation a"));
        function mostrar(id) {
            document.querySelectorAll("table[id^=sheet]").forEach(function (t) { t.classList.toggle("oculta", t.id !== id); });
            enlaces.forEach(function (a) { a.classList.toggle("activa", a.getAttribute("href") === "#" + id); });
        }
        enlaces.forEach(function (a) {
            a.addEventListener("click", function (e) { e.preventDefault(); mostrar(a.getAttribute("href").slice(1)); });
        });
        if (enlaces.length) mostrar("sheet0");
    });
    </script>';

    $escritor = new EscritorHtml($libro);
    $escritor->writeAllSheets();
    $escritor->setEditHtmlCallback(function ($html) use ($agregado) {
        return str_replace('</head>', $agregado . '</head>', $html);
    });
    header('Content-Type: text/html; charset=utf-8');
    echo $escritor->generateHtmlAll();
    exit;
}

function exportarExcel(array $d, $modo) {
    $libro = construirLibro($d);
    if ($modo === 'vista') {
        vistaPreviaExcel($libro);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $d['archivo'] . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($libro))->save('php://output');
    exit;
}

/* ----------------------------- PDF ----------------------------- */

/** Documento con encabezado (logo SENA, título, evento y rango) y pie. */
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

/**
 * Etiqueta de color de una entrada o una salida, como en la pantalla. Los
 * colores van en el propio elemento para que no se los pise el fondo de
 * las filas alternas.
 */
function etiquetaPdf($texto, $fondo, $color) {
    return '<td style="background:' . $fondo . ';color:' . $color . ';padding:2px 6px;border-radius:3px;'
        . 'font-weight:bold;font-size:8px;white-space:nowrap;border:none;">' . h($texto) . '</td>';
}

/**
 * Las visitas de una persona igual que en el Detalle de asistencia: una
 * fila por visita (entrada → salida y cuánto duró) y, entre una y otra del
 * mismo día, el descanso. Lo que falta sale en rojo.
 */
function visitasTablaPdf(array $pares, $conFecha, $sigueAdentro) {
    $filas = '';
    $anterior = null;
    $ultima = count($pares) - 1;
    foreach ($pares as $i => $p) {
        if ($anterior && $anterior['salida'] && $p['entrada']
            && substr($anterior['salida']['fecha'], 0, 10) === substr($p['entrada']['fecha'], 0, 10)) {
            $pausa = strtotime($p['entrada']['fecha']) - strtotime($anterior['salida']['fecha']);
            $filas .= '<tr><td colspan="4" style="border:none;padding:1px 0 1px 10px;color:#5B6660;font-size:7.5px;">Descanso de '
                . h(fmtDuracion($pausa)) . '</td></tr>';
        }
        $filas .= '<tr>';
        $filas .= $p['entrada']
            ? etiquetaPdf('Entrada ' . horaMovimiento($p['entrada']['fecha'], $conFecha), '#E4F4DA', '#007832')
            : etiquetaPdf('Sin entrada', '#FBE6E4', '#B3261E');
        $filas .= '<td style="border:none;padding:0 6px;color:#5B6660;">&rarr;</td>';
        if ($p['salida']) {
            $filas .= etiquetaPdf('Salida ' . horaMovimiento($p['salida']['fecha'], $conFecha), '#FFF4CC', '#8A5E00');
        } elseif ($i === $ultima && $sigueAdentro) {
            $filas .= etiquetaPdf('Sigue adentro', '#39A900', '#FFFFFF');
        } else {
            $filas .= etiquetaPdf('Sin salida', '#FBE6E4', '#B3261E');
        }
        $filas .= '<td style="border:none;padding-left:8px;color:#5B6660;font-size:8px;">'
            . ($p['segundos'] > 0 ? h(fmtDuracion($p['segundos'])) : '') . '</td>';
        $filas .= '</tr>';
        $anterior = $p;
    }
    return '<table style="border-collapse:separate;border-spacing:0 2px;">' . $filas . '</table>';
}

function exportarPdf($lista, array $d, $modo) {
    $r = $d['resumen'];
    $orientacion = 'portrait';

    if ($lista === 'asistencia') {
        // La misma tabla del Detalle de asistencia de la pantalla.
        $titulo = 'Detalle de asistencia';
        $orientacion = 'landscape';
        $filas = [];
        foreach ($d['asistentes'] as $i => $p) {
            $filas[] = '<tr>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . h($p['nombre']) . '<br><span class="muted">C.C. ' . h($p['cedula']) . '</span></td>'
                . '<td>' . h(tipoAsistente($p['tipo'], $p['tipo_otro'])) . '</td>'
                . '<td>' . visitasTablaPdf($p['pares'], $d['variosDias'], $p['sigue_adentro']) . '</td>'
                . '<td><strong>' . h(fmtDuracion($p['segundos_dentro'])) . '</strong><br><span class="muted">'
                . $p['entradas'] . ' entrada' . ($p['entradas'] === 1 ? '' : 's') . ' &middot; '
                . $p['salidas'] . ' salida' . ($p['salidas'] === 1 ? '' : 's') . '</span></td>'
                . '</tr>';
        }
        $cuerpo = indicadoresPdf([
            [$r['asistieron'], 'Asistieron'],
            [$r['reingresaron'], 'Entraron más de una vez'],
            [$r['sin_salida'], 'Sin salida registrada'],
            [fmtDuracion($r['promedio_dentro']), 'Tiempo promedio adentro'],
        ]) . tablaPdf(['#', 'Invitado', 'Tipo', 'Entradas y salidas', 'Tiempo adentro'], $filas);
    } elseif ($lista === 'visitas' || $lista === 'movimientos') {
        // Una fila por visita: quién, cuándo entró y cuándo salió.
        $lista = 'visitas';
        $titulo = 'Entradas y salidas';
        $orientacion = 'landscape';
        $clases = ['Completa' => 'muted', 'Sin salida' => 'alerta', 'Sin entrada' => 'alerta', 'Sigue adentro' => 'si'];
        $filas = [];
        foreach ($d['visitas'] as $i => $v) {
            $filas[] = '<tr>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . h($v['nombre']) . '<br><span class="muted">C.C. ' . h($v['cedula']) . '</span></td>'
                . '<td>' . h(tipoAsistente($v['tipo'], $v['tipo_otro'])) . '</td>'
                . '<td>' . h(fmtDia($v['dia'])) . '</td>'
                . '<td>' . $v['numero'] . '</td>'
                . '<td>' . ($v['entrada'] ? '<span class="e">' . h(date('H:i', strtotime($v['entrada']['fecha']))) . '</span>' : '<span class="alerta">sin entrada</span>') . '</td>'
                . '<td>' . ($v['salida'] ? '<span class="s">' . h(date('H:i', strtotime($v['salida']['fecha']))) . '</span>' : '<span class="alerta">sin salida</span>') . '</td>'
                . '<td>' . h(fmtDuracion($v['segundos'])) . '</td>'
                . '<td><span class="' . $clases[$v['estado']] . '">' . h($v['estado']) . '</span></td>'
                . '<td>' . h($v['portero'] ?? '—') . '</td>'
                . '</tr>';
        }
        $completas = count(array_filter($d['visitas'], function ($v) { return $v['estado'] === 'Completa'; }));
        $cuerpo = indicadoresPdf([
            [count($d['visitas']), 'Visitas (entró y salió)'],
            [$completas, 'Visitas completas'],
            [count($d['visitas']) - $completas, 'Sin entrada o sin salida'],
            [$r['reingresaron'], 'Entraron más de una vez'],
        ]) . tablaPdf(['#', 'Invitado', 'Tipo', 'Día', 'Visita', 'Entró', 'Salió', 'Tiempo', 'Estado', 'Registró'], $filas);
    } else {
        $titulo = 'Lista de invitados';
        $lista = 'invitados';
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

    // En la vista previa el PDF se muestra en el navegador; al descargar, se guarda.
    $dompdf->stream($d['archivo'] . '_' . $lista . '.pdf', ['Attachment' => $modo === 'descarga']);
    exit;
}

/* --------------------------- Datos --------------------------- */

$eventoConsulta = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
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
    'visitas'     => visitasPlanas($personas),
    'avisos'      => $avisos,
    'variosDias'  => $desde !== $hasta,
    'archivo'     => nombreArchivoExporte($desde, $hasta),
];

$formato = $_GET['formato'] ?? '';
$modo = $_GET['modo'] ?? '';
if ($formato === 'xlsx') {
    exportarExcel($datos, $modo);
} elseif ($formato === 'pdf') {
    exportarPdf($_GET['lista'] ?? 'invitados', $datos, $modo);
}
header('Location: estadisticas.php');
exit;
