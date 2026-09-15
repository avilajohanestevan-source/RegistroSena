<?php
/**
 * Historial general: todos los movimientos de entrada y salida, del más
 * reciente al más antiguo, con búsqueda opcional por nombre/cédula/empresa.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$busqueda = trim($_GET['q'] ?? '');
$movimientos = historialGeneral($conn, $busqueda, 300);

$activeTab = 'historial';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h2 class="section-title">Historial general</h2>
  <p class="section-sub">Todos los registros de entrada y salida del evento, del más reciente al más antiguo.</p>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;">
    <input type="text" name="q" value="<?= h($busqueda) ?>" placeholder="Buscar por nombre, cédula o empresa" style="flex:1;min-width:220px;">
    <button type="submit" class="btn btn-primary">Buscar</button>
    <?php if ($busqueda !== ''): ?><a class="btn btn-outline" href="historial.php">Limpiar</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2 class="section-title" style="font-size:16px;">Movimientos (<?= count($movimientos) ?>)</h2>
  <?php if (!$movimientos): ?>
    <div class="empty-state" style="padding:16px;">No hay movimientos que coincidan con la búsqueda.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Fecha y hora</th><th>Movimiento</th><th>Nombre</th><th>Cédula</th><th>Empresa</th></tr></thead>
        <tbody>
          <?php foreach ($movimientos as $m): ?>
            <tr>
              <td class="mono"><?= fmtFecha($m['fecha']) ?></td>
              <td><span class="<?= $m['tipo'] === 'entrada' ? 'type-in' : 'type-out' ?>"><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?></span></td>
              <td><?= h($m['nombre']) ?></td>
              <td class="cedula-cell"><?= h($m['cedula']) ?></td>
              <td><?= h($m['empresa'] !== '' ? $m['empresa'] : '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
