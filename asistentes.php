<?php
require_once __DIR__ . '/includes/panel_admin.php';

// Se puede consultar un evento archivado con ?evento=ID.
$eventoConsulta = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
$paramEvento = $eventoConsulta ? ['evento' => $eventoConsulta['id']] : [];

$busqueda = trim($_GET['q'] ?? '');
$lista = listarAsistentes($conn, $busqueda);

$activeTab = 'asistentes';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>
<div class="card">
  <h2 class="section-title">Asistentes registrados</h2>
  <p class="section-sub">Busca por nombre, cédula, empresa o tipo de asistente.</p>
  <form method="get">
    <?php foreach ($paramEvento as $clave => $valor): ?><input type="hidden" name="<?= h($clave) ?>" value="<?= h($valor) ?>"><?php endforeach; ?>
    <input type="search" name="q" placeholder="Buscar asistente…" value="<?= h($busqueda) ?>" style="max-width:320px;margin-bottom:16px;">
  </form>
  <?php if (!$lista): ?>
    <div class="empty-state">No se encontraron asistentes.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Nombre</th><th>Tipo</th><th>Cédula</th><th>Teléfono</th><th>Empresa</th><th>Estado</th><th>Último movimiento</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($lista as $a): ?>
            <?php $enDentro = $a['estado'] === 'dentro'; $ultimo = ultimoMovimiento($conn, $a['cedula']); ?>
            <tr>
              <td><?= h($a['nombre']) ?></td>
              <td><?= h(tipoAsistente($a['tipo'], $a['tipo_otro'])) ?></td>
              <td class="cedula-cell"><?= h($a['cedula']) ?></td>
              <td class="mono"><?= h($a['telefono'] !== '' ? $a['telefono'] : '—') ?></td>
              <td><?= h($a['empresa'] !== '' ? $a['empresa'] : '—') ?></td>
              <td><span class="status-chip <?= $enDentro ? 'in' : 'out' ?>"><?= $enDentro ? 'Dentro' : 'Fuera' ?></span></td>
              <td class="mono"><?= $ultimo ? fmtFecha($ultimo['fecha']) : '—' ?></td>
              <td><a class="table-link" href="tarjeta.php?cedula=<?= urlencode($a['cedula']) ?>">Ver tarjeta</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
