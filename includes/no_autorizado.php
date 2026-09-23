<?php
/**
 * Respuesta cuando alguien entra por URL a una página del panel sin
 * sesión: devuelve 401 (no autorizado), muestra la alerta en pantalla y
 * lleva al inicio de sesión (donde vuelve a mostrarse el mensaje). Si la
 * persona acababa de cerrar sesión, el texto se lo recuerda.
 *
 * Lo incluye requerirSesion() (includes/auth.php) y espera:
 *   $destino     -> a qué página volver después de iniciar sesión
 *   $cerroSesion -> true si viene de cerrar sesión
 */
http_response_code(401);
$destino = $destino ?? '';
$cerroSesion = !empty($cerroSesion);

$alerta = $cerroSesion
    ? [
        'titulo'  => 'Sesión cerrada',
        'mensaje' => 'La sesión ha sido cerrada, necesitas volver a iniciar sesión.',
    ]
    : [
        'titulo'  => 'Es necesario iniciar sesión',
        'mensaje' => 'Es necesario iniciar sesión para acceder.',
    ];
$urlLogin = 'index.php?aviso=' . ($cerroSesion ? 'salida' : 'login')
    . ($destino !== '' ? '&destino=' . urlencode($destino) : '');
$alerta['boton'] = 'Iniciar sesión';
$alerta['href'] = $urlLogin;

$tituloPagina = $alerta['titulo'] . ' · Control de ingreso SENA';
require __DIR__ . '/head.php';
?>
<body>
  <header class="topbar">
    <div class="brand">
      <img class="brand-logo" src="img/sena-logo-blanco.png" alt="Logo SENA">
      <span class="brand-divider" aria-hidden="true"></span>
      <div class="brand-text">
        <h1>Control de ingreso</h1>
        <span class="event-name">Acceso restringido</span>
      </div>
    </div>
  </header>
  <main class="content content-center"><div class="content-inner" style="max-width:560px;">
    <div class="card card--marca portal-card" style="text-align:center;">
      <h2 class="section-title"><?= h($alerta['titulo']) ?></h2>
      <p class="section-sub"><?= h($alerta['mensaje']) ?></p>
      <div class="portal-options">
        <a class="btn btn-primary btn-block btn-lg" href="<?= h($urlLogin) ?>">Iniciar sesión</a>
      </div>
      <p class="portal-hint">Te llevamos al inicio de sesión en unos segundos…</p>
    </div>
  </div></main>
  <?php require __DIR__ . '/footer.php'; ?>
  <?php require __DIR__ . '/alerta.php'; ?>
  <script>setTimeout(function () { location.href = <?= json_encode($urlLogin) ?>; }, 6000);</script>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
