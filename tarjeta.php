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
$asistente = $cedula ? buscarAsistente($conn, $cedula) : null;
$evento = nombreEvento($conn);
$panel = ($_GET['panel'] ?? '1') !== '0';

if ($panel) {
    require_once __DIR__ . '/includes/auth.php';
    requerirSesion();
}

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
        <?php if (!$panel): ?>
          <div class="banner success">Quedaste registrado. Guarda o imprime esta tarjeta — la necesitas para entrar y salir del evento.</div>
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
          <button class="btn btn-outline" onclick="window.print()">Imprimir / guardar tarjeta</button>
          <?php if (!$panel): ?>
            <a class="btn btn-outline" href="registro.php">Registrar a otra persona</a>
          <?php else: ?>
            <a class="btn btn-outline" href="asistentes.php">Ver todos los asistentes</a>
          <?php endif; ?>
        </div>
      </div>
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
