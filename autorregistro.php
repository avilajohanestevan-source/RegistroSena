<?php
require_once __DIR__ . '/includes/panel_admin.php';

$url = urlAutorregistro();
$activeTab = 'autorregistro';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="card">
  <h2 class="section-title">QR de control de entrada</h2>
  <p class="section-sub">Comparte este código o el enlace en la entrada. Cada asistente lo escanea con su celular y llega a un portal donde puede registrarse por primera vez, o consultar de nuevo su tarjeta si ya se había registrado — sin que tú tengas que digitar nada.</p>
  <div class="badge-qr" style="margin-bottom:16px;">
    <div class="qr-target" data-texto="<?= h($url) ?>"></div>
  </div>
  <div class="form-grid full" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input type="text" id="selfUrlInput" readonly value="<?= h($url) ?>" style="flex:1;min-width:220px;">
    <button type="button" class="btn btn-outline btn-sm" id="btnCopiar" onclick="copiarEnlace('selfUrlInput','btnCopiar')">Copiar enlace</button>
  </div>
  <p class="section-sub" style="margin-top:16px;">
    Este enlace solo funciona si otros equipos pueden llegar a esta dirección: si estás probando en tu propio computador (localhost),
    solo funcionará desde el mismo computador. Cuando esto se publique en un servidor real, cualquier celular con internet podrá usarlo.
  </p>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
