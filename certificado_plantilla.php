<?php
/**
 * Plantilla de los certificados (solo administrador): título, texto de
 * cada criterio con marcadores ({nombre}, {cedula}...), pie, firmante,
 * firma electrónica (imagen), logo, sello institucional y color.
 * Mientras no se suba una firma, el certificado lleva de ejemplo el
 * nombre del firmante escrito; después se reemplaza por la firma oficial.
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/certificados.php';

$plantilla = plantillaCertificado($conn);
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'titulo'          => mb_substr(trim($_POST['titulo'] ?? ''), 0, 120),
        'texto_completa'  => trim($_POST['texto_completa'] ?? ''),
        'texto_parcial'   => trim($_POST['texto_parcial'] ?? ''),
        'texto_charla'    => trim($_POST['texto_charla'] ?? ''),
        'pie'             => mb_substr(trim($_POST['pie'] ?? ''), 0, 255),
        'firmante_nombre' => mb_substr(trim($_POST['firmante_nombre'] ?? ''), 0, 150),
        'firmante_cargo'  => mb_substr(trim($_POST['firmante_cargo'] ?? ''), 0, 150),
        'mostrar_sello'   => !empty($_POST['mostrar_sello']),
        'color'           => $_POST['color'] ?? '#39A900',
        'firma_archivo'   => $plantilla['firma_archivo'],
        'logo_archivo'    => $plantilla['logo_archivo'],
    ];
    if ($datos['titulo'] === '') {
        $errores['titulo'] = 'Escribe el título del certificado.';
    }
    foreach (['texto_completa', 'texto_parcial', 'texto_charla'] as $campo) {
        if (mb_strlen($datos[$campo]) < 20) {
            $errores[$campo] = 'Escribe el texto de la certificación.';
        }
    }
    if ($datos['firmante_nombre'] === '') {
        $errores['firmante_nombre'] = 'Escribe el nombre de quien firma.';
    }

    if (!$errores) {
        $reemplazadas = [];
        $subidas = [];
        foreach (['firma' => 'firma_archivo', 'logo' => 'logo_archivo'] as $campoArchivo => $columna) {
            if (!empty($_POST['quitar_' . $campoArchivo])) {
                $reemplazadas[] = $datos[$columna];
                $datos[$columna] = '';
            }
            [$nuevo, $error] = guardarImagenCertificado($_FILES[$campoArchivo] ?? [], $campoArchivo);
            if ($error !== '') {
                $errores[$campoArchivo] = $error;
            } elseif ($nuevo) {
                $subidas[] = $nuevo;
                $reemplazadas[] = $datos[$columna];
                $datos[$columna] = $nuevo;
            }
        }
        if (!$errores) {
            guardarPlantillaCertificado($conn, $datos, $usuario['id']);
            foreach (array_filter(array_unique($reemplazadas)) as $vieja) {
                borrarImagenCertificado($vieja);
            }
            header('Location: certificado_plantilla.php?aviso=guardada');
            exit;
        }
        // No se guardó: las imágenes que sí alcanzaron a subirse sobran.
        array_map('borrarImagenCertificado', $subidas);
        $datos['firma_archivo'] = $plantilla['firma_archivo'];
        $datos['logo_archivo'] = $plantilla['logo_archivo'];
    }
    $plantilla = array_merge($plantilla, $datos);
}

$firma = imagenCertificadoDataUri($plantilla['firma_archivo']);
$logo = imagenCertificadoDataUri($plantilla['logo_archivo']);
$marcadores = [
    '{nombre}' => 'Nombre completo', '{cedula}' => 'Número de cédula', '{evento}' => 'Nombre del evento',
    '{fechas}' => '"realizado el…" con las fechas', '{tipo}' => 'Rol (Aprendiz, Instructor…)',
    '{dias_asistidos}' => 'Días que asistió', '{total_dias}' => 'Días del evento',
    '{charla}' => 'Nombre de la charla', '{charla_horario}' => 'Día y hora de la charla',
];
$textos = [
    'texto_completa' => ['Asistencia completa', 'Para quienes asistieron a todos los días.'],
    'texto_parcial'  => ['Asistencia parcial', 'Para quienes asistieron a algunos días. Usa {dias_asistidos} y {total_dias}.'],
    'texto_charla'   => ['Charla corta', 'Certificado específico de una charla del cronograma. Usa {charla} y {charla_horario}.'],
];

$activeTab = 'certificados';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<?php if (($_GET['aviso'] ?? '') === 'guardada'): ?>
  <div class="banner success">Plantilla guardada. Los certificados que se descarguen o envíen desde ahora usan estos cambios.</div>
<?php elseif ($errores): ?>
  <div class="banner error">No se guardó: revisa los campos marcados.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <div class="card">
    <div class="historial-cabecera">
      <div>
        <h2 class="section-title">Plantilla del certificado</h2>
        <p class="section-sub" style="margin-bottom:0;">
          Una sola plantilla para todos los certificados. Edita el texto, el logo y la firma del director académico.
          <?php if (!empty($plantilla['actualizado_en'])): ?>Última edición: <?= h(fmtFecha($plantilla['actualizado_en'])) ?>.<?php endif; ?>
        </p>
      </div>
      <div class="crono-cabecera-acciones">
        <a class="btn btn-outline btn-sm" href="certificados.php">← Certificados</a>
        <button type="button" class="btn btn-outline btn-sm"
          data-vista-previa="certificado_pdf.php?muestra=1&amp;criterio=completa"
          data-descarga="certificado_pdf.php?muestra=1&amp;criterio=completa&amp;modo=descarga"
          data-titulo="Vista previa de la plantilla guardada"
          data-archivo="Con datos de ejemplo · guarda para ver tus cambios">Vista previa</button>
      </div>
    </div>

    <div class="form-grid">
      <div class="full">
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" maxlength="120" value="<?= h($plantilla['titulo']) ?>">
        <?php if (!empty($errores['titulo'])): ?><div class="field-error"><?= h($errores['titulo']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="cert-marcadores">
      <strong>Marcadores:</strong> se reemplazan por los datos de cada persona.
      <div class="cert-marcadores-lista">
        <?php foreach ($marcadores as $marcador => $explicacion): ?>
          <button type="button" class="cert-marcador" data-insertar="<?= h($marcador) ?>" title="<?= h($explicacion) ?>"><?= h($marcador) ?></button>
        <?php endforeach; ?>
      </div>
      <span class="field-hint">Haz clic en un marcador para insertarlo donde está el cursor.</span>
    </div>

    <?php foreach ($textos as $campo => [$etiqueta, $ayuda]): ?>
      <div class="cert-texto">
        <label for="<?= $campo ?>">Texto · <?= h($etiqueta) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="5" data-marcadores><?= h($plantilla[$campo]) ?></textarea>
        <div class="field-hint"><?= h($ayuda) ?> Deja una línea en blanco para separar párrafos.</div>
        <?php if (!empty($errores[$campo])): ?><div class="field-error"><?= h($errores[$campo]) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="form-grid">
      <div class="full">
        <label for="pie">Texto del pie <span class="opt">(opcional)</span></label>
        <input type="text" id="pie" name="pie" maxlength="255" value="<?= h($plantilla['pie']) ?>" placeholder="Ej. Centro de Gestión Industrial · Regional Cundinamarca">
      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="section-title">Firma electrónica y sello</h2>
    <p class="section-sub">Mientras no subas la firma, el certificado lleva de ejemplo el nombre del firmante escrito. Cuando tengas la firma oficial del director, súbela aquí (PNG con fondo transparente, hasta 2 MB).</p>

    <div class="cert-firma-grid">
      <div>
        <div class="form-grid">
          <div class="full">
            <label for="firmante_nombre">Nombre de quien firma</label>
            <input type="text" id="firmante_nombre" name="firmante_nombre" maxlength="150" value="<?= h($plantilla['firmante_nombre']) ?>">
            <?php if (!empty($errores['firmante_nombre'])): ?><div class="field-error"><?= h($errores['firmante_nombre']) ?></div><?php endif; ?>
          </div>
          <div class="full">
            <label for="firmante_cargo">Cargo</label>
            <input type="text" id="firmante_cargo" name="firmante_cargo" maxlength="150" value="<?= h($plantilla['firmante_cargo']) ?>">
          </div>
          <div class="full">
            <label for="firma">Archivo de la firma</label>
            <input type="file" id="firma" name="firma" accept="image/png,image/jpeg">
            <?php if (!empty($errores['firma'])): ?><div class="field-error"><?= h($errores['firma']) ?></div><?php endif; ?>
            <?php if ($firma): ?>
              <label class="check-linea" style="margin-top:8px;"><input type="checkbox" name="quitar_firma" value="1"> Quitar la firma y volver al ejemplo con el nombre</label>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="cert-firma-muestra">
        <span class="horario-etiqueta">Así se ve la firma</span>
        <?php if ($firma): ?>
          <img src="<?= $firma ?>" alt="Firma actual">
        <?php else: ?>
          <div class="cert-firma-ejemplo"><?= h($plantilla['firmante_nombre']) ?></div>
          <span class="text-muted">Ejemplo con el nombre (sin archivo de firma)</span>
        <?php endif; ?>
        <div class="cert-firma-linea"></div>
        <strong><?= h($plantilla['firmante_nombre']) ?></strong>
        <span class="text-muted"><?= h($plantilla['firmante_cargo']) ?></span>
      </div>
    </div>

    <label class="check-linea" style="margin-top:16px;">
      <input type="checkbox" name="mostrar_sello" value="1"<?= !empty($plantilla['mostrar_sello']) ? ' checked' : '' ?>> Mostrar el sello institucional del SENA junto a la firma
    </label>
  </div>

  <div class="card">
    <h2 class="section-title">Logo y estilo</h2>
    <div class="form-grid">
      <div>
        <label for="logo">Logo <span class="opt">(opcional, por defecto el del SENA)</span></label>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg">
        <?php if (!empty($errores['logo'])): ?><div class="field-error"><?= h($errores['logo']) ?></div><?php endif; ?>
        <div class="cert-logo-muestra">
          <img src="<?= $logo ?: 'img/sena-logo-verde.png' ?>" alt="Logo actual">
          <?php if ($logo): ?>
            <label class="check-linea"><input type="checkbox" name="quitar_logo" value="1"> Volver al logo del SENA</label>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <label>Color del marco</label>
        <div class="cert-colores">
          <?php foreach (COLORES_CERTIFICADO as $hex => $nombre): ?>
            <label class="cert-color" title="<?= h($nombre) ?>">
              <input type="radio" name="color" value="<?= $hex ?>"<?= $plantilla['color'] === $hex ? ' checked' : '' ?>>
              <span style="background:<?= $hex ?>;"></span><?= h($nombre) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Guardar plantilla</button>
      <a class="btn btn-outline" href="certificados.php">Cancelar</a>
    </div>
  </div>
</form>

<?php require __DIR__ . '/includes/vista_previa.php'; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
