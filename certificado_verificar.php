<?php
/**
 * Página PÚBLICA para verificar un certificado con su código (el que va
 * impreso en el pie del certificado y en el correo). Muestra solo lo
 * necesario para comprobarlo: nombre, cédula parcialmente oculta, evento,
 * criterio y fecha de expedición.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificados.php';

$codigo = trim((string) ($_GET['c'] ?? ''));
$certificado = $codigo !== '' ? certificadoPorCodigo($conn, $codigo) : null;

$evento = 'Verificación de certificados';
$subtitulo = 'Verificar certificado';
$tituloPagina = 'Verificar certificado · SENA';
require __DIR__ . '/includes/head.php';
?>
<body>
  <?php require __DIR__ . '/includes/header_publico.php'; ?>
  <main class="content content-center"><div class="content-inner" style="max-width:620px;">
    <div class="card card--marca portal-card" style="text-align:center;">
      <?php if ($certificado): ?>
        <span class="status-chip in">Certificado válido</span>
        <h2 class="section-title" style="margin-top:12px;"><?= h($certificado['nombre']) ?></h2>
        <p class="section-sub">C.C. terminada en <?= h(substr($certificado['cedula'], -4)) ?></p>
        <dl class="cert-verificado">
          <dt>Evento</dt><dd><?= h($certificado['evento_nombre']) ?></dd>
          <dt>Tipo</dt><dd><?= h(CRITERIOS_CERTIFICADO[$certificado['criterio']] ?? $certificado['criterio']) ?><?= $certificado['detalle'] !== '' ? ' · ' . h($certificado['detalle']) : '' ?></dd>
          <dt>Expedido</dt><dd><?= h(fechaLarga(substr($certificado['emitido_en'], 0, 10))) ?></dd>
          <dt>Código</dt><dd class="mono"><?= h(formatoCodigoCertificado($certificado['codigo'])) ?></dd>
        </dl>
      <?php else: ?>
        <h2 class="section-title"><?= $codigo === '' ? 'Verifica un certificado' : 'No encontramos ese certificado' ?></h2>
        <p class="section-sub">
          <?= $codigo === ''
              ? 'Escribe el código de verificación que aparece en el pie del certificado.'
              : 'Revisa que el código esté bien escrito. Si el problema sigue, el certificado no fue expedido por este sistema.' ?>
        </p>
      <?php endif; ?>
      <form method="get" class="cert-verificar-form">
        <label for="c" class="sr-only">Código de verificación</label>
        <input type="text" id="c" name="c" value="<?= h($codigo) ?>" placeholder="Ej. A1B2C-3D4E5" maxlength="20" autocomplete="off">
        <button type="submit" class="btn btn-primary">Verificar</button>
      </form>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
