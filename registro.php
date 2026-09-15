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
$valores = [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$valores, $errores] = validarRegistro($conn, $_POST);

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
$tituloPagina = 'Regístrate · ' . $evento;
$subtitulo = 'Registro de ingreso';
require __DIR__ . '/includes/head.php';
?>
<body>
  <?php require __DIR__ . '/includes/header_publico.php'; ?>
  <main class="content"><div class="content-inner">
    <div style="margin-bottom:14px;">
      <a class="btn btn-outline btn-sm" href="ingreso.php">← Volver al inicio</a>
    </div>
    <div class="card card--marca">
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
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
