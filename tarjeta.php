<?php
/**
 * Tarjeta / carné con el QR de un asistente.
 *  - Con &panel=0 se muestra sola (así llega quien se acaba de
 *    autorregistrar desde registro.php, sin ver el panel admin).
 *  - Sin ese parámetro se muestra dentro del panel administrativo.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$cedula = soloDigitos($_GET['cedula'] ?? '');
$panel = ($_GET['panel'] ?? '1') !== '0';

// Primero se fija el evento: la tarjeta pública es la del evento activo y
// la del panel puede ser la de un evento archivado (?evento=ID).
if ($panel) {
    require_once __DIR__ . '/includes/panel_admin.php';
    $eventoTarjeta = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
} else {
    $eventoTarjeta = eventoActivo($conn);
    fijarEventoContexto($eventoTarjeta['id'] ?? 0);
}
// Los eventos archivados conservan los datos de la persona, pero no su QR.
$mostrarQr = ($eventoTarjeta['estado'] ?? '') === 'activo';

$asistente = $cedula ? buscarAsistente($conn, $cedula) : null;
// Cronograma del evento de la tarjeta (se ve con el botón "Ver cronograma").
$cronograma = $eventoTarjeta && $mostrarQr ? cronogramaDelEvento($conn, $eventoTarjeta['id']) : [];
$dias = $cronograma ? diasDelEvento($eventoTarjeta) : [];
$evento = nombreEvento($conn);

if ($panel) {
    $activeTab = 'asistentes';
    require __DIR__ . '/includes/layout_top.php';
} else {
    $tituloPagina = 'Tu tarjeta · ' . $evento;
    $subtitulo = 'Tu tarjeta de ingreso';
    require __DIR__ . '/includes/head.php';
?>
<body>
  <?php require __DIR__ . '/includes/header_publico.php'; ?>
  <main class="content"><div class="content-inner">
<?php
}
?>
    <?php if (!$asistente): ?>
      <div class="card"><div class="empty-state">No encontramos ese registro.
        <?php if (!$panel): ?><a href="registro.php">Intenta de nuevo</a>.<?php endif; ?>
      </div></div>
    <?php else: ?>
      <div class="card">
        <?php if (!$panel && isset($_GET['confirmado'])): ?>
          <div class="banner success">¡Listo! Confirmaste tu asistencia a <?= h($evento) ?> y quedaste inscrito con <strong>tu mismo código QR</strong>: guárdalo o imprímelo para entrar y salir.</div>
        <?php elseif (!$panel): ?>
          <div class="banner success">Quedaste registrado. Guarda o imprime esta tarjeta — la necesitas para entrar y salir del evento.</div>
        <?php endif; ?>
        <?php if (($_GET['qr'] ?? '') === 'reutilizado'): ?>
          <div class="banner info" style="margin-top:10px;">Esta persona ya había asistido a un evento anterior: se le asignó <strong>su mismo código QR</strong>, el que ya tenía.</div>
        <?php endif; ?>
        <?php if (!$mostrarQr): ?>
          <div class="banner info">Este evento está archivado: se conservan los datos de la persona, pero no su QR. Si vuelve a registrarse en un evento nuevo, recibe el mismo código.</div>
        <?php endif; ?>
        <?php if (isset($_GET['correo'])): ?>
          <?php if ($_GET['correo'] === '1'): ?>
            <div class="banner success" style="margin-top:10px;">También te enviamos esta tarjeta al correo <?= h($asistente['correo']) ?>.</div>
          <?php elseif (EMAIL_HABILITADO): ?>
            <div class="banner warning" style="margin-top:10px;">No pudimos enviar el correo automáticamente — puedes guardar o imprimir esta tarjeta igual.</div>
          <?php endif; ?>
        <?php endif; ?>
        <?php require __DIR__ . '/includes/badge.php'; ?>
        <div class="form-actions" style="justify-content:center;">
          <?php if ($cronograma): ?>
            <button type="button" class="btn btn-primary" data-abrir-dialogo="modalCronograma">Ver cronograma</button>
            <?php if ($eventoTarjeta['estado'] === 'activo'): $eventoDescarga = $eventoTarjeta; $consultaEventoDescarga = ''; $claseBotonDescarga = 'btn btn-outline'; require __DIR__ . '/includes/cronograma_descarga.php'; endif; ?>
          <?php endif; ?>
          <?php if ($mostrarQr): ?>
            <button class="btn btn-outline" onclick="window.print()">Imprimir / guardar tarjeta</button>
          <?php endif; ?>
          <?php if (!$panel): ?>
            <a class="btn btn-outline" href="registro.php">Registrar a otra persona</a>
          <?php else: ?>
            <a class="btn btn-outline" href="asistentes.php">Ver todos los asistentes</a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($cronograma): ?>
        <dialog id="modalCronograma" class="alerta-modal crono-modal" closedby="any" aria-labelledby="tituloCronograma">
          <div class="crono-modal-cabecera">
            <h2 class="alerta-titulo" id="tituloCronograma">Cronograma · <?= h($evento) ?></h2>
            <button type="button" class="crono-quitar" data-cerrar-dialogo aria-label="Cerrar">×</button>
          </div>
          <?php require __DIR__ . '/includes/cronograma_vista.php'; ?>
          <div class="alerta-acciones">
            <?php if (!$panel): ?><a class="btn btn-outline" href="cronograma_ver.php" target="_blank" rel="noopener">Abrir en página completa</a><?php endif; ?>
            <button type="button" class="btn btn-primary" data-cerrar-dialogo>Cerrar</button>
          </div>
        </dialog>
      <?php endif; ?>
    <?php endif; ?>
<?php
if ($panel) {
    require __DIR__ . '/includes/layout_bottom.php';
} else {
?>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/qrcode.min.js?v=<?= assetVersion('assets/js/qrcode.min.js') ?>"></script>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
<?php
}
?>
