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
      <img class="badge-logo" src="img/sena-logo-blanco.png" alt="Logo SENA">
      <div>
        <div class="org">Tarjeta de ingreso</div>
        <div class="ev"><?= h($evento) ?></div>
      </div>
    </div>
    <div class="badge-body">
      <div class="badge-qr"><div class="qr-target" data-texto="<?= h($qrTexto) ?>"></div></div>
      <?php if (!empty($asistente['tipo'])): ?>
        <div class="badge-tipo"><?= h(tipoAsistente($asistente['tipo'], $asistente['tipo_otro'] ?? '')) ?></div>
      <?php endif; ?>
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
