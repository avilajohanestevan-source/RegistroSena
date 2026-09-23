<?php
/**
 * Pantalla pública que se muestra cuando no hay ningún evento activo: el
 * autorregistro y la consulta de tarjetas pertenecen a un evento, así que
 * quedan cerrados hasta que el administrador cree uno.
 * Se incluye desde ingreso.php, registro.php y consultar.php.
 */
$evento = 'Control de ingreso SENA';
$subtitulo = 'Sin evento activo';
$tituloPagina = 'Control de ingreso · SENA';
require __DIR__ . '/head.php';
?>
<body>
  <?php require __DIR__ . '/header_publico.php'; ?>
  <main class="content content-center"><div class="content-inner" style="max-width:620px;">
    <div class="card card--marca portal-card" style="text-align:center;">
      <h2 class="section-title">No hay ningún evento activo</h2>
      <p class="section-sub">En este momento no hay un evento abierto, así que el registro de asistentes está cerrado.</p>
      <p class="portal-hint">Cuando el SENA abra el próximo evento, vuelve a escanear el código QR o a abrir este enlace y podrás registrarte.</p>
    </div>
  </div></main>
  <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
