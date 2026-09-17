<?php
/**
 * Página PÚBLICA con el cronograma del evento activo: es el enlace que va
 * en los correos (tarjeta con QR e invitaciones) y en la tarjeta. Si el
 * evento dura varios días, se elige el día con el selector (?dia=N abre
 * directamente ese día).
 */
require_once __DIR__ . '/includes/publico.php';

if (!$eventoActual) {
    require __DIR__ . '/includes/sin_evento.php';
    exit;
}

$dias = diasDelEvento($eventoActual);
$cronograma = cronogramaDelEvento($conn, $eventoActual['id']);
$diaPedido = (int) ($_GET['dia'] ?? 0);
$diaInicial = isset($dias[$diaPedido]) ? $diaPedido : null;
$horario = horarioDeEvento($eventoActual);

$tituloPagina = 'Cronograma · ' . $evento;
$subtitulo = 'Cronograma del evento';
require __DIR__ . '/includes/head.php';
?>
<body>
  <?php require __DIR__ . '/includes/header_publico.php'; ?>
  <main class="content"><div class="content-inner" style="max-width:680px;">
    <div class="card card--marca">
      <h2 class="section-title">Cronograma</h2>
      <p class="section-sub"><strong><?= h($evento) ?></strong><?= horarioConfigurado($horario) ? ' · ' . h(textoHorario($horario)) : '' ?></p>
      <?php if (!$cronograma): ?>
        <div class="empty-state">El cronograma de este evento todavía no está publicado.</div>
      <?php else: ?>
        <?php require __DIR__ . '/includes/cronograma_vista.php'; ?>
      <?php endif; ?>
      <div class="form-actions">
        <a class="btn btn-outline" href="consultar.php">Ver mi tarjeta con el QR</a>
      </div>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
