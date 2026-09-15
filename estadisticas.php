<?php
/**
 * Estadísticas del evento (solo administrador): indicadores, gráficos con
 * amCharts y exportes a Excel (PhpSpreadsheet) y PDF (Dompdf) de la lista
 * de invitados, la asistencia y cada entrada y salida, para un rango de
 * días. Es la página a la que llega el administrador al iniciar sesión.
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/estadisticas.php';

$horario = horarioEvento($conn);
[$desde, $hasta] = rangoEstadisticas($conn, $horario, $_GET['desde'] ?? '', $_GET['hasta'] ?? '');
$movimientos = movimientosEnRango($conn, $desde, $hasta);
$avisos = avisosEnRango($conn, $desde, $hasta);
$personas = asistenciaPorPersona($conn, $movimientos);
$resumen = resumenAsistencia($personas, $avisos);
$graficos = datosGraficos($personas, $avisos, $movimientos, $resumen, $desde, $hasta);
$variosDias = $desde !== $hasta;

// Quienes asistieron, en el orden en que llegaron.
$asistentes = array_values(array_filter($personas, function ($p) { return $p['entradas'] > 0; }));
usort($asistentes, function ($a, $b) { return strcmp($a['primera_entrada'], $b['primera_entrada']); });

$consulta = http_build_query(['desde' => $desde, 'hasta' => $hasta]);

$activeTab = 'estadisticas';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h2 class="section-title">Estadísticas del evento</h2>
  <p class="section-sub">Asistencia <?= h(textoRango($desde, $hasta)) ?>. Cambia el rango para ver otros días; los exportes usan el mismo rango.</p>
  <form method="get" class="filtro-dia">
    <div>
      <label for="desde">Desde</label>
      <input type="date" id="desde" name="desde" value="<?= h($desde) ?>">
    </div>
    <div>
      <label for="hasta">Hasta</label>
      <input type="date" id="hasta" name="hasta" value="<?= h($hasta) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Ver estadísticas</button>
  </form>
  <div class="exportes">
    <span class="horario-etiqueta">Exportar</span>
    <a class="btn btn-outline btn-sm" href="exportar.php?formato=xlsx&amp;<?= h($consulta) ?>">Excel completo (.xlsx)</a>
    <a class="btn btn-outline btn-sm" href="exportar.php?formato=pdf&amp;lista=invitados&amp;<?= h($consulta) ?>" target="_blank" rel="noopener">PDF · Lista de invitados</a>
    <a class="btn btn-outline btn-sm" href="exportar.php?formato=pdf&amp;lista=asistencia&amp;<?= h($consulta) ?>" target="_blank" rel="noopener">PDF · Asistencia con entradas y salidas</a>
    <a class="btn btn-outline btn-sm" href="exportar.php?formato=pdf&amp;lista=movimientos&amp;<?= h($consulta) ?>" target="_blank" rel="noopener">PDF · Movimientos</a>
  </div>
</div>

<div class="card">
  <h2 class="section-title">Resumen</h2>
  <p class="section-sub"><?= $resumen['entradas'] ?> entradas y <?= $resumen['salidas'] ?> salidas registradas · Tiempo promedio adentro: <?= h(fmtDuracion($resumen['promedio_dentro'])) ?></p>
  <div class="stat-grid stat-grid--6">
    <div class="stat-card azul-oscuro"><div class="label">Registrados (invitados)</div><div class="value"><?= $resumen['registrados'] ?></div></div>
    <div class="stat-card in"><div class="label">Asistieron</div><div class="value"><?= $resumen['asistieron'] ?></div></div>
    <div class="stat-card gris"><div class="label">No asistieron</div><div class="value"><?= $resumen['no_asistieron'] ?></div></div>
    <div class="stat-card total"><div class="label">Entraron más de una vez</div><div class="value"><?= $resumen['reingresaron'] ?></div></div>
    <div class="stat-card out"><div class="label">Sin salida registrada</div><div class="value"><?= $resumen['sin_salida'] ?></div></div>
    <div class="stat-card rojo"><div class="label">Intentos fallidos</div><div class="value"><?= $resumen['intentos_fallidos'] ?></div></div>
  </div>
</div>

<div class="graficos-grid">
  <div class="card">
    <h3 class="grafico-titulo">¿Cuántos asistieron?</h3>
    <p class="grafico-sub">De <?= $resumen['registrados'] ?> personas registradas.</p>
    <div id="graficoAsistencia" class="grafico"></div>
  </div>
  <div class="card">
    <h3 class="grafico-titulo">¿Registraron su salida?</h3>
    <p class="grafico-sub">De quienes asistieron: salida registrada o se fueron sin registrarla.</p>
    <div id="graficoSalida" class="grafico"></div>
  </div>
  <div class="card">
    <h3 class="grafico-titulo">Asistencia por tipo</h3>
    <p class="grafico-sub">Registrados y cuántos de ellos asistieron.</p>
    <div id="graficoTipos" class="grafico"></div>
  </div>
  <div class="card">
    <h3 class="grafico-titulo">Intentos fallidos por motivo</h3>
    <p class="grafico-sub">Entradas o salidas que el control de acceso no dejó registrar.</p>
    <div id="graficoFallidos" class="grafico"></div>
  </div>
  <div class="card">
    <h3 class="grafico-titulo">Entradas y salidas por hora</h3>
    <p class="grafico-sub">A qué horas hubo más movimiento en la portería.</p>
    <div id="graficoHoras" class="grafico"></div>
  </div>
  <div class="card">
    <h3 class="grafico-titulo">¿Cuántas veces entró cada persona?</h3>
    <p class="grafico-sub">Personas que salieron y volvieron a entrar.</p>
    <div id="graficoVeces" class="grafico"></div>
  </div>
  <div class="card<?= $variosDias ? '' : ' grafico-ancho' ?>">
    <h3 class="grafico-titulo">Registros por portero</h3>
    <p class="grafico-sub">Entradas y salidas que registró cada persona de la portería.</p>
    <div id="graficoPorteros" class="grafico"></div>
  </div>
  <?php if ($variosDias): ?>
    <div class="card">
      <h3 class="grafico-titulo">Entradas y salidas por día</h3>
      <p class="grafico-sub">Movimiento de cada día del rango.</p>
      <div id="graficoDias" class="grafico"></div>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:20px;">
  <h2 class="section-title">Detalle de asistencia (<?= count($asistentes) ?>)</h2>
  <p class="section-sub">Quienes asistieron, en orden de llegada, con cada entrada (E) y salida (S).</p>
  <?php if (!$asistentes): ?>
    <div class="empty-state">Nadie registró entrada en este rango.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nombre</th><th>Tipo</th><th>Cédula</th><th>Entradas</th><th>Salidas</th><th>Tiempo adentro</th><th>Entradas y salidas</th></tr></thead>
        <tbody>
          <?php foreach ($asistentes as $p): ?>
            <tr>
              <td class="col-nombre"><?= h($p['nombre']) ?></td>
              <td><?= h(tipoAsistente($p['tipo'], $p['tipo_otro'])) ?></td>
              <td class="cedula-cell"><?= h($p['cedula']) ?></td>
              <td><?= $p['entradas'] ?></td>
              <td><?= $p['salidas'] ?></td>
              <td><?= h(fmtDuracion($p['segundos_dentro'])) ?></td>
              <td>
                <div class="secuencia">
                  <?php foreach (secuenciaMovimientos($p['movimientos'], $variosDias) as $s): ?>
                    <span class="mov <?= $s['tipo'] === 'entrada' ? 'mov-e' : 'mov-s' ?>"><?= $s['letra'] ?> <?= h($s['hora']) ?></span>
                  <?php endforeach; ?>
                  <?php if ($p['sin_salida']): ?><span class="status-chip out">Sin salida</span><?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>window.datosEstadisticas = <?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<script src="https://cdn.amcharts.com/lib/5/locales/es_ES.js"></script>
<script src="assets/js/estadisticas.js?v=<?= assetVersion('assets/js/estadisticas.js') ?>"></script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
