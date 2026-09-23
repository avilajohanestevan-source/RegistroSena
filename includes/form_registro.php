<?php
/**
 * Formulario de registro reutilizable (autorregistro público y registro
 * desde el panel). Antes de incluir este archivo, definir en scope:
 *   $valores      -> (opcional) lo que ya se escribió, ver validarRegistro()
 *   $errores      -> array asociativo campo => mensaje
 *   $textoBoton   -> texto del botón de enviar
 *   $camposOcultos -> (opcional) [nombre => valor] que viajan con el formulario
 */
$valores = ($valores ?? []) + [
    'nombre' => '', 'tipo' => '', 'tipo_otro' => '', 'cedula' => '',
    'telefono' => '', 'correo' => '', 'empresa' => '', 'direccion' => '',
];
$errores = $errores ?? [];
$textoBoton = $textoBoton ?? 'Registrar';
?>
<form method="post" novalidate>
<?php foreach (($camposOcultos ?? []) as $campoOculto => $valorOculto): ?>
  <input type="hidden" name="<?= h($campoOculto) ?>" value="<?= h($valorOculto) ?>">
<?php endforeach; ?>
  <div class="form-grid">
    <div class="full">
      <label for="nombre">Nombre completo</label>
      <input type="text" id="nombre" name="nombre" value="<?= h($valores['nombre']) ?>" autocomplete="off" placeholder="Ej. Laura Andrea Gómez Pérez">
      <?php if (!empty($errores['nombre'])): ?><div class="field-error"><?= h($errores['nombre']) ?></div><?php endif; ?>
    </div>
    <fieldset class="full">
      <legend>¿Qué eres?</legend>
      <div class="opciones">
        <?php foreach (TIPOS_ASISTENTE as $tipo): ?>
          <label class="opcion">
            <input type="radio" name="tipo" value="<?= h($tipo) ?>"<?= $valores['tipo'] === $tipo ? ' checked' : '' ?>>
            <span><?= h($tipo) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($errores['tipo'])): ?><div class="field-error"><?= h($errores['tipo']) ?></div><?php endif; ?>
    </fieldset>
    <div class="full" data-muestra-si="tipo=Otro"<?= $valores['tipo'] === 'Otro' ? '' : ' hidden' ?>>
      <label for="tipo_otro">¿Cuál?</label>
      <input type="text" id="tipo_otro" name="tipo_otro" maxlength="60" value="<?= h($valores['tipo_otro']) ?>" autocomplete="off" placeholder="Ej. Egresado, proveedor, familiar">
      <?php if (!empty($errores['tipo_otro'])): ?><div class="field-error"><?= h($errores['tipo_otro']) ?></div><?php endif; ?>
    </div>
    <div>
      <label for="cedula">Número de cédula <span class="opt">(identificador)</span></label>
      <input type="text" id="cedula" name="cedula" inputmode="numeric" value="<?= h($valores['cedula']) ?>" autocomplete="off" placeholder="Ej. 1017234567">
      <?php if (!empty($errores['cedula'])): ?><div class="field-error"><?= h($errores['cedula']) ?></div><?php endif; ?>
    </div>
    <div>
      <label for="telefono">Número de teléfono</label>
      <input type="text" id="telefono" name="telefono" inputmode="tel" value="<?= h($valores['telefono']) ?>" autocomplete="off" placeholder="Ej. 3101234567">
      <?php if (!empty($errores['telefono'])): ?><div class="field-error"><?= h($errores['telefono']) ?></div><?php endif; ?>
    </div>
    <div>
      <label for="correo">Correo electrónico</label>
      <input type="email" id="correo" name="correo" value="<?= h($valores['correo']) ?>" autocomplete="off" placeholder="nombre@correo.com">
      <?php if (!empty($errores['correo'])): ?><div class="field-error"><?= h($errores['correo']) ?></div><?php endif; ?>
    </div>
    <div>
      <label for="empresa">Empresa <span class="opt">(opcional)</span></label>
      <input type="text" id="empresa" name="empresa" value="<?= h($valores['empresa']) ?>" autocomplete="off" placeholder="Empresa o entidad">
    </div>
    <div class="full">
      <label for="direccion">Dirección de residencia <span class="opt">(opcional)</span></label>
      <input type="text" id="direccion" name="direccion" value="<?= h($valores['direccion']) ?>" autocomplete="off" placeholder="Dirección de residencia">
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-block"><?= h($textoBoton) ?></button>
  </div>
</form>
