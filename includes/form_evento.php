<?php
/**
 * Campos del evento (nombre, fechas y horario), compartidos por el
 * formulario de crear y el de editar en evento.php. Antes de incluirlo:
 *   $valores -> nombre, fecha_inicio, fecha_fin, hora_inicio, hora_fin, cronograma_modo
 *   $errores -> array campo => mensaje
 */
?>
<div class="form-grid">
  <div class="full">
    <label for="nombre">Nombre del evento</label>
    <input type="text" id="nombre" name="nombre" value="<?= h($valores['nombre']) ?>" placeholder="Ej. Encuentro ambiental del SENA">
    <?php if (!empty($errores['nombre'])): ?><div class="field-error"><?= h($errores['nombre']) ?></div><?php endif; ?>
  </div>
  <div>
    <label for="fecha_inicio">Fecha de inicio <span class="opt">(opcional)</span></label>
    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= h($valores['fecha_inicio']) ?>">
    <?php if (!empty($errores['fecha_inicio'])): ?><div class="field-error"><?= h($errores['fecha_inicio']) ?></div><?php endif; ?>
  </div>
  <div>
    <label for="fecha_fin">Fecha de finalización <span class="opt">(si dura varios días)</span></label>
    <input type="date" id="fecha_fin" name="fecha_fin" value="<?= h($valores['fecha_fin']) ?>">
    <?php if (!empty($errores['fecha_fin'])): ?><div class="field-error"><?= h($errores['fecha_fin']) ?></div><?php endif; ?>
  </div>
  <div>
    <label for="hora_inicio">Hora de apertura del ingreso <span class="opt">(opcional)</span></label>
    <input type="time" id="hora_inicio" name="hora_inicio" value="<?= h($valores['hora_inicio']) ?>">
    <?php if (!empty($errores['hora_inicio'])): ?><div class="field-error"><?= h($errores['hora_inicio']) ?></div><?php endif; ?>
  </div>
  <div>
    <label for="hora_fin">Hora de cierre del ingreso <span class="opt">(opcional)</span></label>
    <input type="time" id="hora_fin" name="hora_fin" value="<?= h($valores['hora_fin']) ?>">
    <?php if (!empty($errores['hora_fin'])): ?><div class="field-error"><?= h($errores['hora_fin']) ?></div><?php endif; ?>
  </div>
</div>
<fieldset class="crono-modos crono-modos--form" data-muestra-multidia>
  <legend>Si el evento dura varios días, su cronograma será…</legend>
  <label class="crono-modo<?= ($valores['cronograma_modo'] ?? 'mismo') === 'mismo' ? ' elegido' : '' ?>">
    <input type="radio" name="cronograma_modo" value="mismo"<?= ($valores['cronograma_modo'] ?? 'mismo') === 'mismo' ? ' checked' : '' ?>>
    <strong>Idéntico para todos los días</strong>
    <span>Defines las actividades una vez y se aplican a cada día.</span>
  </label>
  <label class="crono-modo<?= ($valores['cronograma_modo'] ?? '') === 'por_dia' ? ' elegido' : '' ?>">
    <input type="radio" name="cronograma_modo" value="por_dia"<?= ($valores['cronograma_modo'] ?? '') === 'por_dia' ? ' checked' : '' ?>>
    <strong>Personalizado por día</strong>
    <span>Cada día tiene sus propias actividades y horarios.</span>
  </label>
</fieldset>
<p class="field-hint">Fuera de estas fechas y horas el control de acceso no deja registrar entradas (el intento queda como irregularidad). Las salidas siguen funcionando. Deja los campos vacíos para no limitar.</p>
