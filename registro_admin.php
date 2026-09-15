<?php
/**
 * Registro manual desde el panel administrativo (cuando el personal en la
 * entrada digita los datos de alguien en vez de que la persona use el QR
 * de autorregistro.php).
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/mailer.php';

$valores = [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$valores, $errores] = validarRegistro($conn, $_POST);

    if (!$errores) {
        [$ok, $mensaje] = registrarAsistente($conn, $valores);
        if ($ok) {
            $eventoActual = nombreEvento($conn);
            [$correoOk] = enviarCorreoTarjeta($valores, $eventoActual);
            header('Location: tarjeta.php?cedula=' . urlencode($valores['cedula']) . '&correo=' . ($correoOk ? '1' : '0'));
            exit;
        }
        $errores['general'] = $mensaje;
    }
}

$activeTab = 'registro';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="card">
  <h2 class="section-title">Registrar asistente</h2>
  <p class="section-sub">La cédula es el identificador único de cada persona. Con estos datos se genera su código QR y su tarjeta de ingreso.</p>
  <?php if (!empty($errores['general'])): ?>
    <div class="banner error"><?= h($errores['general']) ?></div>
  <?php endif; ?>
  <?php
    $textoBoton = 'Registrar y generar QR';
    require __DIR__ . '/includes/form_registro.php';
  ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
