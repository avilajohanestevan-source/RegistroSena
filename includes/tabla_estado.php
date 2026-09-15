<?php
/**
 * Dos tablas con quién está adentro ahora mismo y quién ya salió.
 * Se incluye tanto en entrada.php como en salida.php para que quien
 * atiende cualquiera de los dos puntos vea el estado completo.
 * Requiere $conn ya conectado.
 */
$adentro = listarPorEstado($conn, 'dentro');
$fuera = listarPorEstado($conn, 'fuera');
?>
<div class="card<?= $adentro ? ' card--fit' : '' ?>">
  <h2 class="section-title" style="font-size:16px;">Están adentro ahora (<?= count($adentro) ?>)</h2>
  <?php if (!$adentro): ?>
    <div class="empty-state" style="padding:16px;">Nadie está adentro en este momento.</div>
  <?php else: ?>
    <div class="table-wrap table-wrap--compact">
      <table>
        <thead><tr><th>Nombre</th><th>Cédula</th><th>Empresa</th><th>Hora de entrada</th></tr></thead>
        <tbody>
          <?php foreach ($adentro as $a): ?>
            <tr>
              <td><?= h($a['nombre']) ?></td>
              <td class="cedula-cell"><?= h($a['cedula']) ?></td>
              <td><?= h($a['empresa'] !== '' ? $a['empresa'] : '—') ?></td>
              <td class="mono"><?= $a['ultima_fecha'] ? fmtFecha($a['ultima_fecha']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card<?= $fuera ? ' card--fit' : '' ?>">
  <h2 class="section-title" style="font-size:16px;">Ya salieron / no han entrado (<?= count($fuera) ?>)</h2>
  <?php if (!$fuera): ?>
    <div class="empty-state" style="padding:16px;">No hay nadie afuera en este momento.</div>
  <?php else: ?>
    <div class="table-wrap table-wrap--compact">
      <table>
        <thead><tr><th>Nombre</th><th>Cédula</th><th>Empresa</th><th>Último movimiento</th></tr></thead>
        <tbody>
          <?php foreach ($fuera as $a): ?>
            <tr>
              <td><?= h($a['nombre']) ?></td>
              <td class="cedula-cell"><?= h($a['cedula']) ?></td>
              <td><?= h($a['empresa'] !== '' ? $a['empresa'] : '—') ?></td>
              <td class="mono"><?= $a['ultima_fecha'] ? fmtFecha($a['ultima_fecha']) : 'Nunca ha entrado' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
