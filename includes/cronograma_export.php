<?php
/**
 * Descarga del cronograma para imprimir y pegar en la sede: letra grande,
 * colores del SENA y una hoja por día.
 *   - PDF: con Dompdf (carta vertical, una página por día).
 *   - PNG: dibujado en el servidor con GD y las fuentes DejaVu que trae
 *     Dompdf, así no depende de nada externo.
 * $dias son los días a incluir (de diasDelEvento()) y $cronograma las
 * actividades por día (de cronogramaDelEvento()).
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/** Título grande de cada día: "Miércoles 16 de septiembre de 2026" o "Día del evento". */
function tituloDiaCartel(array $d, $multiDia) {
    if ($d['fecha'] === '') {
        return 'Cronograma del evento';
    }
    $texto = fechaLarga($d['fecha'], true);
    $texto = mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    return $multiDia ? 'Día ' . $d['dia'] . ' · ' . $texto : $texto;
}

/* ----------------------------------------------------------------- PDF */

function htmlCronogramaPdf(array $evento, array $dias, array $cronograma, $multiDia) {
    $logoBlanco = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../img/sena-logo-blanco.png'));
    $logoVerde = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../img/sena-logo-verde.png'));
    $paginas = [];
    foreach ($dias as $d) {
        $items = $cronograma[$d['dia']] ?? [];
        $filas = '';
        foreach ($items as $i => $item) {
            $lugar = '';
            if ($item['ubicacion'] !== '') {
                $lugar .= '<div class="lugar">' . h($item['ubicacion']) . '</div>';
            }
            if ($item['responsable'] !== '') {
                $lugar .= '<div class="resp">' . h($item['responsable']) . '</div>';
            }
            $filas .= '<tr class="' . ($i % 2 ? 'par' : '') . '">
                <td class="hora"><span class="inicio">' . h(fmtHora12($item['hora_inicio'])) . '</span><span class="fin">a ' . h(fmtHora12($item['hora_fin'])) . '</span></td>
                <td class="act"><div class="titulo">' . h($item['titulo']) . '</div>'
                    . ($item['descripcion'] !== '' ? '<div class="desc">' . h($item['descripcion']) . '</div>' : '') . '</td>
                <td class="donde">' . ($lugar !== '' ? $lugar : '<span class="vacio">—</span>') . '</td>
            </tr>';
        }
        if ($filas === '') {
            $filas = '<tr><td colspan="3" class="sin">No hay actividades programadas para este día.</td></tr>';
        }
        $paginas[] = '
        <div class="pagina">
          <table class="cabecera"><tr>
            <td class="logo"><img src="' . $logoBlanco . '" alt="SENA"></td>
            <td>
              <div class="etiqueta">Cronograma</div>
              <div class="evento">' . h($evento['nombre']) . '</div>
            </td>
          </tr></table>
          <div class="dia">' . h(tituloDiaCartel($d, $multiDia)) . '</div>
          <div class="contenido"><table class="actividades">
            <tr class="enc"><td class="hora">HORA</td><td>ACTIVIDAD</td><td class="donde">LUGAR Y RESPONSABLE</td></tr>
            ' . $filas . '
          </table></div>
        </div>';
    }

    return '<html><head><meta charset="UTF-8"><style>
        @page { margin: 0 0 58px 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1B1B1B; margin: 0; }
        .pagina { page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }
        .marca { position: fixed; right: -60px; bottom: 40px; width: 380px; opacity: 0.06; }
        footer { position: fixed; bottom: -40px; left: 40px; right: 40px; height: 26px; border-top: 2px solid #39A900; padding-top: 8px; font-size: 10px; color: #5B6660; }
        footer .der { float: right; }
        table.cabecera { width: 100%; border-collapse: collapse; background: #00304D; border-bottom: 10px solid #39A900; }
        table.cabecera td { padding: 26px 40px 24px 0; color: #FFFFFF; vertical-align: middle; }
        table.cabecera td.logo { width: 92px; padding-left: 40px; padding-right: 22px; }
        table.cabecera img { width: 86px; height: 86px; }
        .etiqueta { font-size: 13px; letter-spacing: 4px; text-transform: uppercase; font-weight: bold; color: #9BE06B; }
        .evento { font-size: 27px; font-weight: bold; line-height: 1.2; margin-top: 4px; }
        .dia { margin: 26px 40px 14px; font-size: 22px; font-weight: bold; color: #00304D; border-left: 8px solid #39A900; padding: 4px 0 4px 14px; }
        .contenido { padding: 0 40px; }
        table.actividades { border-collapse: collapse; width: 100%; }
        table.actividades tr.enc td { background: #E4F4DA; color: #007832; font-size: 11px; font-weight: bold; padding: 10px 12px; border-bottom: none; }
        table.actividades tr.enc td.act { border-left: none; }
        table.actividades td { padding: 14px 12px; border-bottom: 1px solid #DDE5D8; vertical-align: top; }
        table.actividades tr.par td { background: #F6FAF3; }
        td.hora { width: 104px; }
        td.hora .inicio { display: block; font-size: 18px; font-weight: bold; color: #00304D; }
        td.hora .fin { display: block; font-size: 12px; color: #5B6660; margin-top: 3px; }
        td.act { border-left: 4px solid #39A900; }
        td.act .titulo { font-size: 17px; font-weight: bold; line-height: 1.25; }
        td.act .desc { font-size: 12.5px; color: #4D4D4D; margin-top: 4px; line-height: 1.35; }
        td.donde { width: 150px; }
        td.donde .lugar { font-size: 13px; font-weight: bold; color: #007832; }
        td.donde .resp { font-size: 11.5px; color: #5B6660; margin-top: 3px; }
        .vacio { color: #9AA59E; }
        td.sin { text-align: center; color: #5B6660; font-size: 15px; padding: 40px; }
    </style></head><body>
        <img class="marca" src="' . $logoVerde . '" alt="">
        <footer><span class="der">Generado el ' . h(fechaLarga(date('Y-m-d'))) . '</span>Servicio Nacional de Aprendizaje &mdash; SENA &middot; sena.edu.co</footer>
        ' . implode('', $paginas) . '
    </body></html>';
}

/** Genera el PDF del cronograma y lo envía al navegador. */
function descargarCronogramaPdf(array $evento, array $dias, array $cronograma, $multiDia, $archivo, $comoDescarga) {
    $opciones = new Options();
    $opciones->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($opciones);
    $dompdf->loadHtml(htmlCronogramaPdf($evento, $dias, $cronograma, $multiDia), 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream($archivo . '.pdf', ['Attachment' => $comoDescarga]);
}

/* ----------------------------------------------------------------- PNG */

function fuenteCartel($negrita = false) {
    return realpath(__DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/' . ($negrita ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
}

/** Parte un texto en líneas que caben en $ancho píxeles. */
function lineasCartel($texto, $fuente, $tamano, $ancho) {
    $lineas = [];
    foreach (preg_split('/\R/u', trim((string) $texto)) as $parrafo) {
        $actual = '';
        foreach (preg_split('/\s+/u', $parrafo) as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            $caja = imagettfbbox($tamano, 0, $fuente, $prueba);
            if ($actual !== '' && ($caja[2] - $caja[0]) > $ancho) {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        $lineas[] = $actual;
    }
    return $lineas;
}

function colorCartel($imagen, $hex) {
    $hex = ltrim($hex, '#');
    return imagecolorallocate($imagen, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/**
 * Dibuja el cartel. Se llama dos veces: la primera sin imagen, solo para
 * medir el alto; la segunda dibuja. Devuelve el alto total en píxeles.
 */
function dibujarCartel($imagen, array $evento, array $dias, array $cronograma, $multiDia) {
    $W = 1240;
    $M = 70;                       // margen lateral
    $regular = fuenteCartel();
    $negrita = fuenteCartel(true);
    $c = function ($hex) use ($imagen) { return $imagen ? colorCartel($imagen, $hex) : 0; };
    $texto = function ($tam, $x, $y, $color, $fuente, $cadena) use ($imagen) {
        if ($imagen) {
            imagettftext($imagen, $tam, 0, (int) $x, (int) $y, $color, $fuente, $cadena);
        }
    };
    $rect = function ($x1, $y1, $x2, $y2, $color) use ($imagen) {
        if ($imagen) {
            imagefilledrectangle($imagen, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $color);
        }
    };

    // Encabezado azul con el logo y el nombre del evento.
    $lineasEvento = lineasCartel($evento['nombre'], $negrita, 38, $W - $M - 250);
    $altoCabecera = max(250, 150 + count($lineasEvento) * 58);
    $rect(0, 0, $W, $altoCabecera, $c('#00304D'));
    $rect(0, $altoCabecera, $W, $altoCabecera + 16, $c('#39A900'));
    if ($imagen) {
        $logo = imagecreatefrompng(__DIR__ . '/../img/sena-logo-blanco.png');
        imagecopyresampled($imagen, $logo, $M, (int) (($altoCabecera - 140) / 2), 0, 0, 140, 140, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
    $x = $M + 180;
    $y = (int) (($altoCabecera - (44 + count($lineasEvento) * 58)) / 2) + 34;
    $texto(18, $x, $y, $c('#9BE06B'), $negrita, 'C R O N O G R A M A');
    foreach ($lineasEvento as $linea) {
        $y += 58;
        $texto(38, $x, $y, $c('#FFFFFF'), $negrita, $linea);
    }
    $y = $altoCabecera + 16;

    $anchoHora = 256;
    $anchoDonde = 290;
    $anchoAct = $W - 2 * $M - $anchoHora - $anchoDonde;

    foreach ($dias as $d) {
        // Título del día
        $y += 60;
        $rect($M, $y, $M + 12, $y + 62, $c('#39A900'));
        $texto(30, $M + 34, $y + 46, $c('#00304D'), $negrita, tituloDiaCartel($d, $multiDia));
        $y += 88;

        // Encabezado de la tabla
        $rect($M, $y, $W - $M, $y + 54, $c('#E4F4DA'));
        $texto(15, $M + 20, $y + 35, $c('#007832'), $negrita, 'HORA');
        $texto(15, $M + $anchoHora + 20, $y + 35, $c('#007832'), $negrita, 'ACTIVIDAD');
        $texto(15, $W - $M - $anchoDonde + 16, $y + 35, $c('#007832'), $negrita, 'LUGAR Y RESPONSABLE');
        $y += 54;

        $items = $cronograma[$d['dia']] ?? [];
        if (!$items) {
            $texto(22, $M + 20, $y + 70, $c('#5B6660'), $regular, 'No hay actividades programadas para este día.');
            $y += 110;
        }
        foreach ($items as $i => $item) {
            $titulo = lineasCartel($item['titulo'], $negrita, 26, $anchoAct - 44);
            $desc = $item['descripcion'] !== '' ? lineasCartel($item['descripcion'], $regular, 19, $anchoAct - 44) : [];
            $lugar = $item['ubicacion'] !== '' ? lineasCartel($item['ubicacion'], $negrita, 19, $anchoDonde - 32) : [];
            $resp = $item['responsable'] !== '' ? lineasCartel($item['responsable'], $regular, 17, $anchoDonde - 32) : [];
            $altoAct = count($titulo) * 40 + count($desc) * 30 + ($desc ? 8 : 0);
            $altoDonde = count($lugar) * 30 + count($resp) * 27 + ($lugar && $resp ? 6 : 0);
            $alto = max(110, 44 + max($altoAct, $altoDonde, 70));

            if ($i % 2) {
                $rect($M, $y, $W - $M, $y + $alto, $c('#F6FAF3'));
            }
            $rect($M + $anchoHora, $y + 14, $M + $anchoHora + 6, $y + $alto - 14, $c('#39A900'));
            $texto(30, $M + 20, $y + 56, $c('#00304D'), $negrita, fmtHora12($item['hora_inicio']));
            $texto(18, $M + 20, $y + 92, $c('#5B6660'), $regular, 'a ' . fmtHora12($item['hora_fin']));

            $ty = $y + 22;
            foreach ($titulo as $linea) {
                $ty += 40;
                $texto(26, $M + $anchoHora + 30, $ty - 8, $c('#1B1B1B'), $negrita, $linea);
            }
            $ty += $desc ? 8 : 0;
            foreach ($desc as $linea) {
                $ty += 30;
                $texto(19, $M + $anchoHora + 30, $ty - 6, $c('#4D4D4D'), $regular, $linea);
            }

            $dy = $y + 22;
            foreach ($lugar as $linea) {
                $dy += 30;
                $texto(19, $W - $M - $anchoDonde + 16, $dy - 4, $c('#007832'), $negrita, $linea);
            }
            $dy += $lugar && $resp ? 6 : 0;
            foreach ($resp as $linea) {
                $dy += 27;
                $texto(17, $W - $M - $anchoDonde + 16, $dy - 4, $c('#5B6660'), $regular, $linea);
            }
            if (!$lugar && !$resp) {
                $texto(19, $W - $M - $anchoDonde + 16, $y + 56, $c('#9AA59E'), $regular, '—');
            }

            $y += $alto;
            $rect($M, $y - 1, $W - $M, $y, $c('#DDE5D8'));
        }
    }

    // Pie
    $y += 60;
    $rect($M, $y, $W - $M, $y + 3, $c('#39A900'));
    $texto(16, $M, $y + 42, $c('#5B6660'), $regular, 'Servicio Nacional de Aprendizaje — SENA · sena.edu.co');
    $generado = 'Generado el ' . fechaLarga(date('Y-m-d'));
    $caja = imagettfbbox(16, 0, $regular, $generado);
    $texto(16, $W - $M - ($caja[2] - $caja[0]), $y + 42, $c('#5B6660'), $regular, $generado);
    return $y + 80;
}

/** Genera el PNG del cronograma y lo envía al navegador. */
function descargarCronogramaPng(array $evento, array $dias, array $cronograma, $multiDia, $archivo, $comoDescarga) {
    $alto = max(1754, dibujarCartel(null, $evento, $dias, $cronograma, $multiDia));
    $imagen = imagecreatetruecolor(1240, $alto);
    imagealphablending($imagen, true);
    imagefilledrectangle($imagen, 0, 0, 1240, $alto, colorCartel($imagen, '#FFFFFF'));
    dibujarCartel($imagen, $evento, $dias, $cronograma, $multiDia);

    header('Content-Type: image/png');
    header('Content-Disposition: ' . ($comoDescarga ? 'attachment' : 'inline') . '; filename="' . $archivo . '.png"');
    imagepng($imagen, null, 6);
    imagedestroy($imagen);
}
