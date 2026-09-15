<?php
/**
 * Página PÚBLICA para consultar de nuevo una tarjeta ya registrada, por
 * si alguien perdió el correo o el pantallazo. Solo pide la cédula: si
 * existe, manda directo a tarjeta.php (misma vista que ve quien se acaba
 * de registrar, con su QR y el botón para imprimir/guardar).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$evento = nombreEvento($conn);
$error = '';
$cedulaEnviada = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedulaEnviada = trim($_POST['cedula'] ?? '');
    $cedula = soloDigitos($cedulaEnviada);
    if ($cedula === '') {
        $error = 'Escribe tu número de cédula.';
    } elseif (buscarAsistente($conn, $cedula)) {
        header('Location: tarjeta.php?cedula=' . urlencode($cedula) . '&panel=0');
        exit;
    } else {
        $error = 'No encontramos ningún registro con esa cédula.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Consultar tarjeta · <?= h($evento) ?></title>
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
        <span class="event-name">Consultar tarjeta</span>
      </div>
    </div>
  </header>
  <main class="content"><div class="content-inner">
    <div style="margin-bottom:14px;">
      <a class="btn btn-outline btn-sm" href="ingreso.php">← Volver al inicio</a>
    </div>
    <div class="card">
      <h2 class="section-title">Consulta tu tarjeta</h2>
      <p class="section-sub">Escribe el número de cédula con el que te registraste para ver de nuevo tu tarjeta y tu código QR, y poder descargarla o imprimirla.</p>
      <?php if ($error): ?>
        <div class="banner error"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="form-grid">
          <div class="full">
            <label>Número de cédula</label>
            <input type="text" name="cedula" inputmode="numeric" autocomplete="off" placeholder="Ej. 1017234567" value="<?= h($cedulaEnviada) ?>">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-block">Ver mi tarjeta</button>
        </div>
      </form>
      <p class="section-sub" style="margin-top:18px;">¿Aún no te has registrado? <a href="registro.php">Regístrate aquí</a>.</p>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer_publico.php'; ?>
</body>
</html>
