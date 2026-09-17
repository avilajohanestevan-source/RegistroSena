<?php
/**
 * Portal público de "Control de entrada". Esta es la página a la que
 * lleva el QR que se comparte (ver urlAutorregistro() en functions.php):
 * cualquiera que lo escanee llega aquí primero y elige si quiere
 * registrarse por primera vez, o consultar la tarjeta que ya tiene.
 */
require_once __DIR__ . '/includes/publico.php';

// Sin evento activo no hay registro de asistentes ni tarjetas.
if (!$eventoActual) {
    require __DIR__ . '/includes/sin_evento.php';
    exit;
}

$horario = horarioEvento($conn);
$tituloPagina = 'Control de entrada · ' . $evento;
$subtitulo = 'Control de entrada';
require __DIR__ . '/includes/head.php';
?>
<body>
  <?php require __DIR__ . '/includes/header_publico.php'; ?>
  <main class="content content-center"><div class="content-inner" style="max-width:620px;">
    <div class="card card--marca portal-card" style="text-align:center;">
      <h2 class="section-title">¿Qué necesitas hacer?</h2>
      <p class="section-sub">Elige una opción para continuar.</p>
      <?php if (horarioConfigurado($horario)): ?>
        <div class="evento-fecha">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
          <?= h(textoHorario($horario)) ?>
        </div>
      <?php endif; ?>
      <div class="portal-options">
        <a class="btn btn-primary btn-block btn-lg" href="registro.php">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="8" r="4"/><path d="M3 20c0-3.3 3.1-6 7-6 1.3 0 2.5.3 3.5.8"/><path d="M18 14v6M15 17h6"/></svg>
          Registrarme para el evento
        </a>
        <a class="btn btn-outline btn-block btn-lg" href="consultar.php">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg>
          Consultar mi tarjeta con mi cédula
        </a>
      </div>
      <p class="section-sub portal-hint">
        Si es tu primera vez aquí, regístrate — quedas con tu propio código QR para entrar y salir.
        Si ya te registraste antes y perdiste tu tarjeta o correo, consúltala de nuevo con tu cédula.
      </p>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
