<?php
/**
 * Vista resumida de un evento (solo administrador): lo esencial de un
 * evento archivado — métricas clave, la lista de asistentes, las
 * irregularidades y los reportes. Los datos completos siguen guardados:
 * desde aquí se descargan en Excel o PDF y se abren las páginas
 * completas (estadísticas, historial y reportes) de ese evento.
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/estadisticas.php';

// El evento que se está consultando (puede ser uno archivado).
$eventoFila = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
if (!$eventoFila) {
    header('Location: evento.php');
    exit;
}

$horario = horarioDeEvento($eventoFila);
$metricas = metricasEvento($conn, $eventoFila['id']);
$asistentes = listarAsistentes($conn);
$avisos = listarAvisos($conn, 10);
$avisosPorTipo = [];
foreach (listarAvisos($conn, 1000) as $av) {
    $motivo = etiquetaAviso($av['tipo']);
    $avisosPorTipo[$motivo] = ($avisosPorTipo[$motivo] ?? 0) + 1;
}
arsort($avisosPorTipo);

// Los exportes y las páginas completas se abren sobre este mismo evento.
$consulta = http_build_query(['evento' => $eventoFila['id']]);
$limiteLista = 50;
$muestra = array_slice($asistentes, 0, $limiteLista);

$activeTab = 'evento';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <div class="historial-cabecera">
    <div>
      <h2 class="section-title"><?= h($eventoFila['nombre']) ?></h2>
      <p class="section-sub" style="margin-bottom:0;">
        <span class="status-chip <?= $eventoFila['estado'] === 'activo' ? 'in' : 'neutro' ?>"><?= $eventoFila['estado'] === 'activo' ? 'Activo' : 'Archivado' ?></span>
        <?= horarioConfigurado($horario) ? ' · ' . h(textoHorario($horario)) : '' ?>
        <?php if ($eventoFila['cerrado_en']): ?> · Cerrado <?= h(fmtFecha($eventoFila['cerrado_en'])) ?><?php endif; ?>
      </p>
    </div>
    <a class="btn btn-outline btn-sm" href="evento.php">← Volver a eventos</a>
  </div>

  <div class="stat-grid stat-grid--6">
    <div class="stat-card azul-oscuro"><div class="label">Registrados</div><div class="value"><?= (int) $metricas['registrados'] ?></div></div>
    <div class="stat-card in"><div class="label">Asistieron</div><div class="value"><?= (int) $metricas['asistieron'] ?></div></div>
    <div class="stat-card gris"><div class="label">No asistieron</div><div class="value"><?= (int) $metricas['registrados'] - (int) $metricas['asistieron'] ?></div></div>
    <div class="stat-card total"><div class="label">Entradas / salidas</div><div class="value"><?= (int) $metricas['entradas'] ?>/<?= (int) $metricas['salidas'] ?></div></div>
    <div class="stat-card rojo"><div class="label">Irregularidades</div><div class="value"><?= (int) $metricas['irregularidades'] ?></div></div>
    <div class="stat-card azul"><div class="label">Porteros</div><div class="value"><?= (int) $metricas['porteros'] ?></div></div>
  </div>

  <div class="exportes">
    <span class="horario-etiqueta">Ver completo</span>
    <a class="btn btn-outline btn-sm" href="estadisticas.php?<?= h($consulta) ?>">Estadísticas</a>
    <a class="btn btn-outline btn-sm" href="historial.php?<?= h($consulta) ?>">Historial</a>
    <a class="btn btn-outline btn-sm" href="reportes.php?<?= h($consulta) ?>">Reportes</a>
    <a class="btn btn-outline btn-sm" href="asistentes.php?<?= h($consulta) ?>">Asistentes</a>
    <a class="btn btn-outline btn-sm" href="cronograma.php?<?= h($consulta) ?>">Cronograma</a>
    <a class="btn btn-primary btn-sm" href="exportar.php?formato=xlsx&amp;modo=descarga&amp;<?= h($consulta) ?>">Descargar Excel</a>
  </div>
</div>

<div class="card">
  <h2 class="section-title">Asistentes (<?= count($asistentes) ?>)</h2>
  <p class="section-sub">
    <?php if (count($asistentes) > $limiteLista): ?>
      Se muestran los primeros <?= $limiteLista ?>; los demás están en <a href="asistentes.php?<?= h($consulta) ?>">la lista completa</a> y en el Excel.
    <?php else: ?>
      Lista de quienes se registraron para este evento.
    <?php endif; ?>
  </p>
  <?php if (!$muestra): ?>
    <div class="empty-state">Nadie se registró en este evento.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nombre</th><th>Tipo</th><th>Cédula</th><th>Empresa</th><th>Estado final</th></tr></thead>
        <tbody>
          <?php foreach ($muestra as $a): ?>
            <tr>
              <td class="col-nombre"><?= h($a['nombre']) ?></td>
              <td><?= h(tipoAsistente($a['tipo'], $a['tipo_otro'])) ?></td>
              <td class="cedula-cell"><?= h($a['cedula']) ?></td>
              <td><?= h($a['empresa'] !== '' ? $a['empresa'] : '—') ?></td>
              <td><span class="status-chip <?= $a['estado'] === 'dentro' ? 'in' : 'out' ?>"><?= $a['estado'] === 'dentro' ? 'Quedó dentro' : 'Fuera' ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title">Irregularidades (<?= (int) $metricas['irregularidades'] ?>)</h2>
  <p class="section-sub">Intentos de entrada o salida que el control no dejó registrar.</p>
  <?php if (!$avisos): ?>
    <div class="empty-state">No hubo irregularidades en este evento.</div>
  <?php else: ?>
    <p class="por-tipo">
      <span class="text-muted">Por motivo:</span>
      <?php $i = 0; foreach ($avisosPorTipo as $motivo => $cantidad): ?><?= $i++ ? ' · ' : ' ' ?><?= h($motivo) ?> <strong><?= $cantidad ?></strong><?php endforeach; ?>
    </p>
    <div class="table-wrap" style="margin-top:14px;">
      <table>
        <thead><tr><th>Fecha y hora</th><th>Motivo</th><th>Persona</th><th>Detalle</th><th>Registró</th></tr></thead>
        <tbody>
          <?php foreach ($avisos as $av): ?>
            <tr>
              <td class="mono"><?= fmtFecha($av['fecha']) ?></td>
              <td><span class="type-out"><?= h(etiquetaAviso($av['tipo'])) ?></span></td>
              <td><?= h($av['nombre'] ?? '—') ?><div class="celda-detalle"><?= h($av['cedula']) ?></div></td>
              <td><?= h($av['mensaje']) ?></td>
              <td><?= h($av['portero'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="field-hint">Se muestran las 10 más recientes; todas quedan en el Excel y en los reportes del evento.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
