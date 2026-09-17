<?php
/**
 * Página PÚBLICA a la que lleva el enlace del correo de invitación. Valida
 * el token, deja confirmar o rechazar y, al confirmar, inscribe a la
 * persona en el evento con su misma cédula: su código QR de siempre le
 * sirve, así que enseguida se le muestra su tarjeta.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/invitaciones.php';

$token = $_POST['t'] ?? $_GET['t'] ?? '';
$invitacion = invitacionPorToken($conn, $token);
if ($invitacion) {
    marcarInvitacionAbierta($conn, $invitacion);
    // Invitación a alguien nuevo (sin cédula): tiene que registrarse.
    if ($invitacion['cedula'] === null || $invitacion['cedula'] === '') {
        header('Location: registro.php?t=' . urlencode($invitacion['token']));
        exit;
    }
}
$eventoInvitado = $invitacion ? eventoPorId($conn, $invitacion['evento_id']) : null;
if ($eventoInvitado) {
    fijarEventoContexto($eventoInvitado['id']);
}
$abierto = $eventoInvitado && $eventoInvitado['estado'] === 'activo';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitacion && $abierto) {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'confirmar') {
        responderInvitacion($conn, $invitacion['id'], 'confirmado');
        inscribirInvitacion($conn, $invitacion);
        // Se le muestra su tarjeta: el QR es el mismo de siempre.
        header('Location: tarjeta.php?cedula=' . urlencode($invitacion['cedula']) . '&panel=0&confirmado=1');
        exit;
    }
    if ($accion === 'rechazar') {
        responderInvitacion($conn, $invitacion['id'], 'rechazado');
        header('Location: confirmar.php?t=' . urlencode($invitacion['token']));
        exit;
    }
}

$evento = $eventoInvitado ? $eventoInvitado['nombre'] : 'Control de ingreso SENA';
$horario = horarioDeEvento($eventoInvitado);
$cronograma = $eventoInvitado ? cronogramaDelEvento($conn, $eventoInvitado['id']) : [];
$dias = $cronograma ? diasDelEvento($eventoInvitado) : [];
$subtitulo = 'Invitación al evento';
$tituloPagina = 'Invitación · ' . $evento;
require __DIR__ . '/includes/head.php';
?>
<body>
  <?php require __DIR__ . '/includes/header_publico.php'; ?>
  <main class="content content-center"><div class="content-inner" style="max-width:720px;">
    <?php if ($invitacion && $abierto && $invitacion['estado'] !== 'confirmado'): ?>
      <?php
        $eventoPromo = $eventoInvitado;
        $saludoPromo = $invitacion['nombre'];
        $botonPromo = ['Confirmar mi asistencia', '#confirmar'];
        require __DIR__ . '/includes/tarjeta_promocional.php';
      ?>
    <?php endif; ?>
    <div class="card card--marca portal-card" style="text-align:center;" id="confirmar">

      <?php if (!$invitacion): ?>
        <h2 class="section-title">Esta invitación no es válida</h2>
        <p class="section-sub">El enlace está incompleto o ya no existe. Pídele al organizador del evento que te la envíe de nuevo.</p>

      <?php elseif (!$abierto): ?>
        <h2 class="section-title">Hola, <?= h($invitacion['nombre']) ?></h2>
        <p class="section-sub">Este evento ya no está abierto, así que la invitación no se puede confirmar. Cuando el SENA abra el próximo evento te llegará una nueva.</p>

      <?php elseif ($invitacion['estado'] === 'confirmado'): ?>
        <h2 class="section-title">Ya confirmaste tu asistencia</h2>
        <p class="section-sub">Estás inscrito en <strong><?= h($evento) ?></strong><?= horarioConfigurado($horario) ? ' · ' . h(textoHorario($horario)) : '' ?>. Tu código QR es el mismo de siempre.</p>
        <div class="portal-options">
          <a class="btn btn-primary btn-block btn-lg" href="tarjeta.php?cedula=<?= urlencode($invitacion['cedula']) ?>&amp;panel=0">Ver mi tarjeta con el QR</a>
        </div>

      <?php elseif ($invitacion['estado'] === 'rechazado'): ?>
        <h2 class="section-title">Gracias por avisar</h2>
        <p class="section-sub">Quedó registrado que no podrás asistir a <strong><?= h($evento) ?></strong>. Si cambias de idea, todavía puedes confirmar.</p>
        <form method="post">
          <input type="hidden" name="t" value="<?= h($invitacion['token']) ?>">
          <input type="hidden" name="accion" value="confirmar">
          <div class="portal-options">
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sí quiero asistir</button>
          </div>
        </form>

      <?php else: ?>
        <h2 class="section-title">Hola, <?= h($invitacion['nombre']) ?></h2>
        <p class="section-sub">Te invitamos a <strong><?= h($evento) ?></strong>.</p>
        <?php if (horarioConfigurado($horario)): ?>
          <div class="evento-fecha">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
            <?= h(textoHorario($horario)) ?>
          </div>
        <?php endif; ?>
        <p class="section-sub" style="margin-top:16px;">No tienes que registrarte otra vez: al confirmar quedas inscrito con tu misma cédula y <strong>tu código QR de siempre</strong> te sirve para entrar y salir.</p>
        <form method="post">
          <input type="hidden" name="t" value="<?= h($invitacion['token']) ?>">
          <div class="portal-options">
            <button type="submit" name="accion" value="confirmar" class="btn btn-primary btn-block btn-lg">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
              Confirmar mi asistencia
            </button>
            <button type="submit" name="accion" value="rechazar" class="btn btn-outline btn-block btn-lg">No podré asistir</button>
          </div>
        </form>
      <?php endif; ?>

    </div>

    <?php if ($invitacion && $abierto && $cronograma): ?>
      <div class="card">
        <h2 class="section-title">Cronograma del evento</h2>
        <p class="section-sub">Estas son las actividades programadas<?= count($dias) > 1 ? '; elige el día para ver cada uno' : '' ?>.</p>
        <?php require __DIR__ . '/includes/cronograma_vista.php'; ?>
        <div class="form-actions">
          <?php $eventoDescarga = $eventoInvitado; $consultaEventoDescarga = ''; $claseBotonDescarga = 'btn btn-primary'; require __DIR__ . '/includes/cronograma_descarga.php'; ?>
          <a class="btn btn-outline" href="cronograma_ver.php" target="_blank" rel="noopener">Ver en página completa</a>
        </div>
      </div>
    <?php endif; ?>
    <div class="card">
      <?php require __DIR__ . '/includes/facebook.php'; ?>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
