<?php
/**
 * Campos del evento (nombre, fechas y horario), compartidos por el
 * formulario de crear y el de editar en evento.php. Antes de incluirlo:
 *   $valores -> nombre, fecha_inicio, fecha_fin, hora_inicio, hora_fin, cronograma_modo,
 *              imagen_alt, imagen_en_pagina
 *   $eventoImagen -> (opcional) el evento que se edita, para mostrar su imagen actual
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

<fieldset class="promo-campos">
  <legend>Imagen promocional <span class="opt">(opcional)</span></legend>
  <p class="field-hint" style="margin-top:0;">Se muestra en los correos de invitación, en la página de registro y se puede descargar para imprimir o proyectar en las pantallas de la sede. PNG, JPG o SVG de hasta 5 MB; mejor horizontal (por ejemplo 1200 × 630 px).</p>
  <div class="promo-campos-grid">
    <div>
      <?php $srcActual = !empty($eventoImagen) ? srcImagenEvento($eventoImagen) : null; ?>
      <?php if ($srcActual): ?>
        <img class="promo-miniatura" src="<?= h($srcActual) ?>" alt="<?= h(altImagenEvento($eventoImagen)) ?>">
        <div class="form-actions" style="margin-top:8px;">
          <a class="btn btn-outline btn-sm" href="evento_imagen.php?id=<?= (int) $eventoImagen['id'] ?>&amp;descarga=1">Descargar para imprimir o pantallas</a>
        </div>
        <label class="check-linea" style="margin-top:8px;"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la imagen</label>
      <?php else: ?>
        <div class="promo-miniatura promo-miniatura--vacia">Sin imagen: el correo usa una tarjeta con el logo del SENA.</div>
      <?php endif; ?>
    </div>
    <div>
      <label for="imagen"><?= $srcActual ? 'Cambiar imagen' : 'Subir imagen' ?></label>
      <input type="file" id="imagen" name="imagen" accept="image/png,image/jpeg,image/svg+xml,.svg">
      <?php if (!empty($errores['imagen'])): ?><div class="field-error"><?= h($errores['imagen']) ?></div><?php endif; ?>
      <label for="imagen_alt" style="margin-top:12px;">Texto alternativo <span class="opt">(lo que dice la imagen, para quien no la puede ver)</span></label>
      <input type="text" id="imagen_alt" name="imagen_alt" maxlength="255" value="<?= h($valores['imagen_alt'] ?? '') ?>" placeholder="Ej. Encuentro ambiental del SENA, 16 al 18 de septiembre, Villeta">
      <div class="opciones-lista" style="margin-top:12px;">
        <label class="check-linea"><input type="radio" name="imagen_en_pagina" value="1"<?= (int) ($valores['imagen_en_pagina'] ?? 1) === 1 ? ' checked' : '' ?>> Incluir en el correo y en la página de registro <span class="text-muted">(recomendado)</span></label>
        <label class="check-linea"><input type="radio" name="imagen_en_pagina" value="0"<?= (int) ($valores['imagen_en_pagina'] ?? 1) === 0 ? ' checked' : '' ?>> Incluir solo en el correo</label>
      </div>
      <p class="field-hint">Con "solo en el correo", quien abre el enlace de su invitación igual la ve; quien entra directo a registrarse ve la página sin la tarjeta promocional.</p>
    </div>
  </div>
</fieldset>
