<?php
/**
 * Cronograma de solo lectura para el asistente (tarjeta con QR y página
 * pública). Si el evento dura varios días hay un selector de fecha para
 * ver cada día. Antes de incluirlo:
 *   $cronograma -> cronogramaDelEvento()
 *   $dias       -> diasDelEvento()
 *   $diaInicial -> día que se muestra primero (opcional)
 */
$diaInicial = $diaInicial ?? (diaDeHoy($dias) ?? array_key_first($dias));
$idVista = 'crono' . substr(md5(uniqid('', true)), 0, 6);
?>
<div class="crono-vista" data-crono-vista>
  <?php if (count($dias) > 1): ?>
    <label class="crono-selector" for="<?= $idVista ?>Dia">
      <span>Día</span>
      <select id="<?= $idVista ?>Dia" data-crono-selector>
        <?php foreach ($dias as $d): ?>
          <option value="<?= $d['dia'] ?>"<?= $d['dia'] === $diaInicial ? ' selected' : '' ?>>
            <?= h($d['etiqueta']) ?><?= empty($cronograma[$d['dia']]) ? ' (sin actividades)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>

  <?php foreach ($dias as $d): $items = $cronograma[$d['dia']] ?? []; ?>
    <section class="crono-dia" data-crono-dia="<?= $d['dia'] ?>"<?= $d['dia'] === $diaInicial ? '' : ' hidden' ?>>
      <?php if (count($dias) === 1 && $d['fecha'] !== ''): ?>
        <p class="crono-fecha"><?= h(fmtDia($d['fecha'])) ?></p>
      <?php endif; ?>
      <?php if (!$items): ?>
        <p class="empty-state">No hay actividades programadas para este día.</p>
      <?php else: ?>
        <ol class="crono-lista">
          <?php foreach ($items as $item): ?>
            <li>
              <span class="crono-hora"><?= h(fmtHora12($item['hora_inicio'])) ?><small><?= h(fmtHora12($item['hora_fin'])) ?></small></span>
              <span class="crono-info">
                <strong><?= h($item['titulo']) ?></strong>
                <?php if ($item['descripcion'] !== ''): ?><span><?= h($item['descripcion']) ?></span><?php endif; ?>
                <?php if ($item['ubicacion'] !== '' || $item['responsable'] !== ''): ?>
                  <span class="crono-meta">
                    <?= $item['ubicacion'] !== '' ? 'Lugar: ' . h($item['ubicacion']) : '' ?>
                    <?= $item['ubicacion'] !== '' && $item['responsable'] !== '' ? ' · ' : '' ?>
                    <?= $item['responsable'] !== '' ? 'A cargo de: ' . h($item['responsable']) : '' ?>
                  </span>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
