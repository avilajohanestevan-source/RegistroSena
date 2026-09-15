<?php
/**
 * Tarjeta / carné imprimible con el QR de un asistente.
 * Antes de incluir este archivo, definir: $asistente (fila de la tabla
 * asistentes) y $evento (nombre del evento).
 */
$qrTexto = textoQR($asistente['cedula'], $asistente['nombre']);
?>
<div class="badge-wrap">
  <div class="badge card-print">
    <div class="badge-head">
      <div class="org">SENA</div>
      <div class="ev"><?= h($evento) ?></div>
    </div>
    <div class="badge-body">
      <div class="badge-qr"><div class="qr-target" data-texto="<?= h($qrTexto) ?>"></div></div>
      <div class="badge-name"><?= h($asistente['nombre']) ?></div>
      <div class="badge-cedula">C.C. <?= h($asistente['cedula']) ?></div>
      <?php if (!empty($asistente['telefono'])): ?>
        <div class="badge-empresa"><?= h($asistente['telefono']) ?></div>
      <?php endif; ?>
      <?php if (!empty($asistente['empresa'])): ?>
        <div class="badge-empresa"><?= h($asistente['empresa']) ?></div>
      <?php endif; ?>
    </div>
    <div class="badge-foot">
      <span>Registrado <?= fmtFecha($asistente['registrado_en']) ?></span>
      <span>Presenta este QR en el ingreso</span>
    </div>
  </div>
</div>
