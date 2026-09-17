<?php
/**
 * Página PÚBLICA de registro. Llegan por tres caminos:
 *   - registro.php?t=TOKEN     el enlace del correo de invitación: versión
 *                              personalizada ("Hola, Nombre") con la imagen
 *                              del evento y el formulario ya con su nombre y
 *                              correo. Si la invitación es de alguien que ya
 *                              estuvo en otro evento, lo lleva a confirmar.
 *   - registro.php?origen=enlace  el enlace público que se comparte.
 *   - registro.php             acceso directo (el QR de autorregistro en la sede).
 * Al registrarse, cada uno ve su tarjeta con su código QR.
 */
require_once __DIR__ . '/includes/publico.php';
require_once __DIR__ . '/includes/invitaciones.php';
require_once __DIR__ . '/includes/mailer.php';

// Sin evento activo no hay registro de asistentes ni tarjetas.
if (!$eventoActual) {
    require __DIR__ . '/includes/sin_evento.php';
    exit;
}

$token = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
$invitacion = $token !== '' ? invitacionPorToken($conn, $token) : null;
if ($invitacion && (int) $invitacion['evento_id'] !== (int) $eventoActual['id']) {
    $invitacion = null; // invitación de otro evento
}
$tokenInvalido = $token !== '' && !$invitacion;
if ($invitacion) {
    marcarInvitacionAbierta($conn, $invitacion);
    // Quien ya estuvo en otro evento no se registra de nuevo: confirma con su mismo QR.
    if ($invitacion['cedula'] !== null && $invitacion['cedula'] !== '') {
        header('Location: confirmar.php?t=' . urlencode($invitacion['token']));
        exit;
    }
    if ((int) $invitacion['inscrito'] === 1) {
        header('Location: consultar.php');
        exit;
    }
}
$origen = $invitacion ? 'email_link' : ((($_POST['origen'] ?? $_GET['origen'] ?? '') === 'enlace') ? 'public_link' : 'directo');

$valores = $invitacion ? ['nombre' => $invitacion['nombre'], 'correo' => $invitacion['correo']] : [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$valores, $errores] = validarRegistro($conn, $_POST);

    if (!$errores) {
        [$ok, $mensaje, $qrReutilizado] = registrarAsistente($conn, $valores);
        if ($ok) {
            registrarInvitacionAlInscribirse($conn, $eventoActual['id'], $valores, $invitacion, $origen);
            [$correoOk] = enviarCorreoTarjeta($valores, $evento);
            header('Location: tarjeta.php?cedula=' . urlencode($valores['cedula']) . '&panel=0&correo=' . ($correoOk ? '1' : '0') . ($qrReutilizado ? '&qr=reutilizado' : ''));
            exit;
        }
        $errores['general'] = $mensaje;
    }
}

$conPromocion = mostrarPromocionEnPagina($eventoActual, (bool) $invitacion);
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

    <?php if ($tokenInvalido): ?>
      <div class="banner warning">Este enlace de invitación no es válido o ya no está vigente. Puedes registrarte igual con el formulario.</div>
    <?php endif; ?>

    <?php if ($conPromocion): ?>
      <?php
        $eventoPromo = $eventoActual;
        $saludoPromo = $invitacion['nombre'] ?? null;
        $botonPromo = ['Regístrate aquí', '#formulario'];
        require __DIR__ . '/includes/tarjeta_promocional.php';
      ?>
    <?php endif; ?>

    <div class="card card--marca" id="formulario">
      <h2 class="section-title"><?= $invitacion ? 'Completa tu registro' : 'Regístrate para el evento' ?></h2>
      <p class="section-sub">
        <?= $invitacion
            ? 'Ya tenemos tu nombre y tu correo. Completa los demás datos: con tu cédula se genera tu código QR de ingreso.'
            : 'Completa tus datos. La cédula es tu identificador — con ella se genera tu código QR de ingreso.' ?>
      </p>
      <?php if (!empty($errores['general'])): ?>
        <div class="banner error"><?= h($errores['general']) ?></div>
      <?php endif; ?>
      <?php
        $textoBoton = 'Registrarme y ver mi QR';
        $camposOcultos = $invitacion ? ['t' => $invitacion['token']] : ($origen === 'public_link' ? ['origen' => 'enlace'] : []);
        require __DIR__ . '/includes/form_registro.php';
      ?>
      <p class="section-sub" style="margin-top:18px;">¿Ya te registraste antes? <a href="consultar.php">Consulta tu tarjeta aquí</a>.</p>
    </div>

    <div class="card">
      <?php require __DIR__ . '/includes/facebook.php'; ?>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
