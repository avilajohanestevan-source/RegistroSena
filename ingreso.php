<?php
/**
 * Portal público de "Control de entrada". Esta es la página a la que
 * lleva el QR que se comparte (ver urlAutorregistro() en functions.php):
 * cualquiera que lo escanee llega aquí primero y elige si quiere
 * registrarse por primera vez, o consultar la tarjeta que ya tiene.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$evento = nombreEvento($conn);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Control de entrada · <?= h($evento) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Source+Sans+3:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
</head>
<body>
  <header class="topbar" style="justify-content:center;">
    <div class="brand">
      <img class="brand-mark" src="img/Sena-Logo.png" alt="Logo SENA">
      <div class="brand-text">
        <h1><?= h($evento) ?></h1>
        <span class="event-name">Control de entrada</span>
      </div>
    </div>
  </header>
  <main class="content content-center"><div class="content-inner" style="max-width:620px;">
    <div class="card portal-card" style="text-align:center;">
      <h2 class="section-title">¿Qué necesitas hacer?</h2>
      <p class="section-sub">Elige una opción para continuar.</p>
      <div class="portal-options">
        <a class="btn btn-primary btn-block btn-lg" href="registro.php">Registrarme para el evento</a>
        <a class="btn btn-outline btn-block btn-lg" href="consultar.php">Consultar mi tarjeta con mi cédula</a>
      </div>
      <p class="section-sub portal-hint">
        Si es tu primera vez aquí, regístrate — quedas con tu propio código QR para entrar y salir.
        Si ya te registraste antes y perdiste tu tarjeta o correo, consúltala de nuevo con tu cédula.
      </p>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer_publico.php'; ?>
</body>
</html>
