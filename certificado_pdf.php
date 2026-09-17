<?php
/**
 * PDF de certificados (solo administrador).
 *   ?id=N                  un certificado emitido
 *   ?lote=N&formato=zip    todos los del lote, un PDF por persona en un ZIP
 *   ?lote=N&formato=pdf    todos los del lote en un solo PDF (para imprimir)
 *   ?muestra=1&evento=ID&criterio=...&cedula=...  vista previa sin emitir
 * &modo=descarga lo descarga; si no, se abre en el navegador.
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/certificados.php';

$plantilla = plantillaCertificado($conn);
$comoDescarga = ($_GET['modo'] ?? '') === 'descarga';
@set_time_limit(600);

function enviarPdf($bytes, $archivo, $comoDescarga) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($comoDescarga ? 'attachment' : 'inline') . '; filename="' . $archivo . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

// Vista previa sin emitir: con una persona real del evento o con datos de ejemplo.
if (isset($_GET['muestra'])) {
    $evento = eventoPorId($conn, $_GET['evento'] ?? 0) ?? eventoActivo($conn)
        ?? ['id' => 0, 'nombre' => 'Nombre del evento', 'fecha_inicio' => date('Y-m-d'), 'fecha_fin' => '', 'hora_inicio' => '', 'hora_fin' => ''];
    $opciones = opcionesCertificado($_GET, $evento, $conn);
    $persona = null;
    if (!empty($_GET['cedula']) && $evento['id']) {
        foreach (candidatosCertificado($conn, $evento, array_merge($opciones, ['roles' => []])) as $c) {
            if ($c['cedula'] === $_GET['cedula']) {
                $persona = $c;
                break;
            }
        }
    }
    $certificado = certificadoDeMuestra($opciones, $evento, $persona);
    enviarPdf(pdfCertificados($plantilla, [[$certificado, $evento]]), 'vista_previa_certificado.pdf', $comoDescarga);
}

if (isset($_GET['id'])) {
    $certificado = certificadoPorId($conn, $_GET['id']);
    $evento = $certificado ? eventoPorId($conn, $certificado['evento_id']) : null;
    if (!$certificado || !$evento) {
        http_response_code(404);
        exit('Certificado no encontrado.');
    }
    enviarPdf(pdfCertificados($plantilla, [[$certificado, $evento]]), nombreArchivoCertificado($certificado), $comoDescarga);
}

$lote = isset($_GET['lote']) ? loteCertificados($conn, $_GET['lote']) : null;
if (!$lote) {
    http_response_code(404);
    exit('Lote no encontrado.');
}
$evento = eventoPorId($conn, $lote['evento_id']);
$certificados = certificadosDelLote($conn, $lote['id']);
$nombreLote = 'certificados_lote_' . $lote['id'];

if (($_GET['formato'] ?? 'zip') === 'pdf') {
    $items = array_map(function ($c) use ($evento) { return [$c, $evento]; }, $certificados);
    enviarPdf(pdfCertificados($plantilla, $items), $nombreLote . '.pdf', $comoDescarga);
}

$ruta = tempnam(sys_get_temp_dir(), 'cert');
$zip = new ZipArchive();
$zip->open($ruta, ZipArchive::OVERWRITE);
foreach ($certificados as $c) {
    $zip->addFromString(nombreArchivoCertificado($c), pdfCertificados($plantilla, [[$c, $evento]]));
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nombreLote . '.zip"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
unlink($ruta);
