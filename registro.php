<?php
/**
 * Página PÚBLICA de autorregistro. Este es el enlace que va en el QR de
 * autorregistro.php: cualquier asistente la abre desde su celular, se
 * registra y ve al instante su propia tarjeta con su código QR.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$evento = nombreEvento($conn);
$valores = ['nombre' => '', 'cedula' => '', 'telefono' => '', 'correo' => '', 'empresa' => '', 'direccion' => ''];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'nombre'    => trim($_POST['nombre'] ?? ''),
        'cedula'    => soloDigitos($_POST['cedula'] ?? ''),
        'telefono'  => soloDigitos($_POST['telefono'] ?? ''),
        'correo'    => trim($_POST['correo'] ?? ''),
        'empresa'   => trim($_POST['empresa'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
    ];

    if (mb_strlen($valores['nombre']) < 3) {
        $errores['nombre'] = 'Escribe el nombre completo.';
    }
    if (mb_strlen($valores['cedula']) < 5) {
        $errores['cedula'] = 'Escribe un número de cédula válido.';
    } elseif (buscarAsistente($conn, $valores['cedula'])) {
        $errores['cedula'] = 'Ya existe un asistente registrado con esta cédula.';
    }
    if (mb_strlen($valores['telefono']) < 7) {
        $errores['telefono'] = 'Escribe un número de teléfono válido.';
    }
    if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Escribe un correo electrónico válido.';
    }

    if (!$errores) {
        [$ok, $mensaje] = registrarAsistente($conn, $valores);
        if ($ok) {
            [$correoOk] = enviarCorreoTarjeta($valores, $evento);
            header('Location: tarjeta.php?cedula=' . urlencode($valores['cedula']) . '&panel=0&correo=' . ($correoOk ? '1' : '0'));
            exit;
        }
        $errores['general'] = $mensaje;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Regístrate · <?= h($evento) ?></title>
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
        <span class="event-name">Registro de ingreso</span>
      </div>
    </div>
  </header>
  <main class="content"><div class="content-inner">
    <div style="margin-bottom:14px;">
      <a class="btn btn-outline btn-sm" href="ingreso.php">← Volver al inicio</a>
    </div>
    <div class="card">
      <h2 class="section-title">Regístrate para el evento</h2>
      <p class="section-sub">Completa tus datos. La cédula es tu identificador — con ella se genera tu código QR de ingreso.</p>
      <?php if (!empty($errores['general'])): ?>
        <div class="banner error"><?= h($errores['general']) ?></div>
      <?php endif; ?>
      <?php
        $textoBoton = 'Registrarme y ver mi QR';
        require __DIR__ . '/includes/form_registro.php';
      ?>
      <p class="section-sub" style="margin-top:18px;">¿Ya te registraste antes? <a href="consultar.php">Consulta tu tarjeta aquí</a>.</p>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer_publico.php'; ?>
</body>
</html>
