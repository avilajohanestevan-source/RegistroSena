<?php
/**
 * Formulario de registro reutilizable. Antes de incluir este archivo,
 * definir en scope:
 *   $valores      -> array con nombre|cedula|telefono|correo|empresa|direccion
 *   $errores      -> array asociativo campo => mensaje
 *   $textoBoton   -> texto del botón de enviar
 */
$valores = $valores ?? ['nombre' => '', 'cedula' => '', 'telefono' => '', 'correo' => '', 'empresa' => '', 'direccion' => ''];
$errores = $errores ?? [];
$textoBoton = $textoBoton ?? 'Registrar';
?>
<form method="post" novalidate>
  <div class="form-grid">
    <div class="full">
      <label>Nombre completo</label>
      <input type="text" name="nombre" value="<?= h($valores['nombre']) ?>" autocomplete="off" placeholder="Ej. Laura Andrea Gómez Pérez">
      <?php if (!empty($errores['nombre'])): ?><div class="field-error"><?= h($errores['nombre']) ?></div><?php endif; ?>
    </div>
    <div>
      <label>Número de cédula <span class="opt">(identificador)</span></label>
      <input type="text" name="cedula" inputmode="numeric" value="<?= h($valores['cedula']) ?>" autocomplete="off" placeholder="Ej. 1017234567">
      <?php if (!empty($errores['cedula'])): ?><div class="field-error"><?= h($errores['cedula']) ?></div><?php endif; ?>
    </div>
    <div>
      <label>Número de teléfono</label>
      <input type="text" name="telefono" inputmode="tel" value="<?= h($valores['telefono']) ?>" autocomplete="off" placeholder="Ej. 3101234567">
      <?php if (!empty($errores['telefono'])): ?><div class="field-error"><?= h($errores['telefono']) ?></div><?php endif; ?>
    </div>
    <div>
      <label>Correo electrónico</label>
      <input type="email" name="correo" value="<?= h($valores['correo']) ?>" autocomplete="off" placeholder="nombre@correo.com">
      <?php if (!empty($errores['correo'])): ?><div class="field-error"><?= h($errores['correo']) ?></div><?php endif; ?>
    </div>
    <div>
      <label>Empresa <span class="opt">(opcional)</span></label>
      <input type="text" name="empresa" value="<?= h($valores['empresa']) ?>" autocomplete="off" placeholder="Empresa o entidad">
    </div>
    <div class="full">
      <label>Dirección de residencia <span class="opt">(opcional)</span></label>
      <input type="text" name="direccion" value="<?= h($valores['direccion']) ?>" autocomplete="off" placeholder="Dirección de residencia">
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-block"><?= h($textoBoton) ?></button>
  </div>
</form>
