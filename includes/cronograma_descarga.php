<?php
/**
 * Botón "Descargar cronograma" y su ventana para elegir formato (PDF o PNG)
 * y día. Antes de incluirlo:
 *   $eventoDescarga -> fila del evento
 *   $consultaEventoDescarga -> 'evento=ID&' para un evento archivado ('' para el activo)
 *   $claseBotonDescarga -> clases del botón (opcional)
 */
$diasDescarga = diasDelEvento($eventoDescarga);
$idDescarga = 'descargaCrono' . (int) $eventoDescarga['id'];
?>
<button type="button" class="<?= h($claseBotonDescarga ?? 'btn btn-outline btn-sm') ?>" data-abrir-dialogo="<?= $idDescarga ?>">
  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10.5l5 5 5-5M5 20h14"/></svg>
  Descargar cronograma
</button>
<dialog id="<?= $idDescarga ?>" class="alerta-modal modal-invitar" closedby="any" aria-labelledby="<?= $idDescarga ?>Titulo">
  <form method="get" action="cronograma_descargar.php" target="_blank">
    <?php if (!empty($consultaEventoDescarga)): ?>
      <input type="hidden" name="evento" value="<?= (int) $eventoDescarga['id'] ?>">
    <?php endif; ?>
    <h2 class="alerta-titulo" id="<?= $idDescarga ?>Titulo">Descargar cronograma</h2>
    <p class="alerta-mensaje">Con letra grande y los colores del SENA, listo para imprimir y pegar en la sede.</p>
    <div class="crono-modos crono-modos--form" style="margin-top:0;">
      <label class="crono-modo elegido">
        <input type="radio" name="formato" value="pdf" checked>
        <strong>PDF</strong>
        <span>Para imprimir: una hoja por día.</span>
      </label>
      <label class="crono-modo">
        <input type="radio" name="formato" value="png">
        <strong>Imagen PNG</strong>
        <span>Para compartir o proyectar en pantalla.</span>
      </label>
    </div>
    <?php if (count($diasDescarga) > 1): ?>
      <label for="<?= $idDescarga ?>Dia" style="margin-top:14px;">Día</label>
      <select id="<?= $idDescarga ?>Dia" name="dia">
        <option value="0">Todos los días</option>
        <?php foreach ($diasDescarga as $diaOpcion): ?>
          <option value="<?= $diaOpcion['dia'] ?>"><?= h($diaOpcion['etiqueta']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <div class="alerta-acciones" style="margin-top:20px;">
      <button type="button" class="btn btn-outline" data-cerrar-dialogo>Cancelar</button>
      <button type="submit" name="modo" value="vista" class="btn btn-outline">Vista previa</button>
      <button type="submit" name="modo" value="descarga" class="btn btn-primary">Descargar</button>
    </div>
  </form>
</dialog>
