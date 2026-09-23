<?php
/**
 * Historial de entradas y salidas organizado por invitado: una fila por
 * persona y día, con cada visita emparejada (entrada → salida y cuánto
 * duró), los descansos entre visitas y una línea de tiempo con los tramos
 * en que estuvo adentro (el mismo eje para todas las filas). Se filtra
 * por rango de días y por nombre, cédula, empresa o tipo.
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/estadisticas.php';

// Se puede consultar un evento archivado con ?evento=ID.
$eventoConsulta = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
$paramEvento = $eventoConsulta ? ['evento' => $eventoConsulta['id']] : [];
$horario = horarioEvento($conn);
[$desde, $hasta] = rangoEstadisticas($conn, $horario, $_GET['desde'] ?? '', $_GET['hasta'] ?? '');
$busqueda = trim($_GET['q'] ?? '');

$filas = historialPorInvitado(movimientosEnRango($conn, $desde, $hasta), $busqueda);
$limite = 400;
$recortado = count($filas) > $limite;
$filas = array_slice($filas, 0, $limite);
$eje = $filas ? ejeLineaTiempo($filas, $horario) : null;
$visitas = array_sum(array_map(function ($f) { return count($f['pares']); }, $filas));
$sinSalida = count(array_filter($filas, function ($f) { return $f['sin_salida']; }));
$personasDistintas = count(array_unique(array_column($filas, 'cedula')));

$activeTab = 'historial';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h2 class="section-title">Historial de entradas y salidas</h2>
  <p class="section-sub">Cada invitado con sus visitas de cada día: a qué hora entró, a qué hora salió, cuánto se quedó y los descansos entre una visita y otra.</p>
  <form method="get" class="filtro-dia">
    <?php foreach ($paramEvento as $clave => $valor): ?><input type="hidden" name="<?= h($clave) ?>" value="<?= h($valor) ?>"><?php endforeach; ?>
    <div class="filtro-busqueda">
      <label for="q">Buscar</label>
      <input type="search" id="q" name="q" value="<?= h($busqueda) ?>" placeholder="Nombre, cédula, empresa o tipo">
    </div>
    <div>
      <label for="desde">Desde</label>
      <input type="date" id="desde" name="desde" value="<?= h($desde) ?>">
    </div>
    <div>
      <label for="hasta">Hasta</label>
      <input type="date" id="hasta" name="hasta" value="<?= h($hasta) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Buscar</button>
    <?php if ($busqueda !== ''): ?>
      <a class="btn btn-outline" href="historial.php?<?= h(http_build_query(['desde' => $desde, 'hasta' => $hasta] + $paramEvento)) ?>">Limpiar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="historial-cabecera">
    <div>
      <h2 class="section-title" style="font-size:16px;">Invitados (<?= $personasDistintas ?>)</h2>
      <p class="section-sub" style="margin-bottom:0;">
        <?= $visitas === 1 ? '1 visita' : $visitas . ' visitas' ?> <?= h(textoRango($desde, $hasta)) ?><?= $sinSalida ? ' · ' . $sinSalida . ' sin salida registrada' : '' ?>
      </p>
    </div>
    <div class="leyenda" aria-label="Convenciones">
      <span class="mov mov-e">Entrada</span>
      <span class="mov mov-s">Salida</span>
      <span class="mov mov-falta">Sin salida</span>
      <span class="leyenda-tramo" aria-hidden="true"></span> Tiempo adentro
    </div>
  </div>

  <?php if ($recortado): ?>
    <div class="banner info">Se muestran las primeras <?= $limite ?> filas. Usa la búsqueda o un rango más corto para ver el resto.</div>
  <?php endif; ?>

  <?php if (!$filas): ?>
    <div class="empty-state">No hay entradas ni salidas <?= $busqueda !== '' ? 'que coincidan con la búsqueda ' : '' ?>en este rango.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tabla-historial">
        <thead>
          <tr>
            <th>Invitado</th>
            <th>Día</th>
            <th>Entradas y salidas</th>
            <th>Tiempo adentro</th>
            <th class="col-linea">
              Línea de tiempo
              <div class="eje" aria-hidden="true">
                <?php $ultimaMarca = count($eje['marcas']) - 1; ?>
                <?php foreach ($eje['marcas'] as $i => $minuto): ?>
                  <span class="<?= $i === 0 ? 'primera' : ($i === $ultimaMarca ? 'ultima' : '') ?>" style="left:<?= round(($minuto - $eje['inicio']) / ($eje['fin'] - $eje['inicio']) * 100, 2) ?>%"><?= sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60) ?></span>
                <?php endforeach; ?>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filas as $f): $t = strtotime($f['dia']); ?>
            <tr>
              <td class="col-nombre">
                <?= h($f['nombre']) ?>
                <div class="celda-detalle"><?= h(tipoAsistente($f['tipo'], $f['tipo_otro'])) ?> · C.C. <?= h($f['cedula']) ?></div>
              </td>
              <td class="mono"><?= date('j', $t) . ' ' . mesCorto($t) ?></td>
              <td><?= htmlVisitas($f['pares'], false, $f['sigue_adentro']) ?></td>
              <td>
                <strong><?= h(fmtDuracion($f['segundos'])) ?></strong>
                <div class="celda-detalle"><?= count($f['pares']) === 1 ? '1 visita' : count($f['pares']) . ' visitas' ?></div>
              </td>
              <td class="col-linea">
                <div class="linea">
                  <?php foreach ($eje['marcas'] as $minuto): ?>
                    <i class="marca" style="left:<?= round(($minuto - $eje['inicio']) / ($eje['fin'] - $eje['inicio']) * 100, 2) ?>%"></i>
                  <?php endforeach; ?>
                  <?php foreach (tramosLineaTiempo($f['pares'], $eje, $f['sigue_adentro']) as $tramo): ?>
                    <span class="tramo <?= $tramo['clase'] ?>" style="left:<?= $tramo['izquierda'] ?>%;width:<?= $tramo['ancho'] ?>%" title="<?= h($tramo['titulo']) ?>"></span>
                  <?php endforeach; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
