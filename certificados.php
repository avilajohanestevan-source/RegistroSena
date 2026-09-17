<?php
/**
 * Certificados de asistencia (solo administrador).
 *  1. Se elige el evento, el criterio (asistencia completa, parcial o una
 *     charla del cronograma) y los roles (tipos de asistente).
 *  2. El sistema muestra quién cumple y quién no, con su asistencia.
 *  3. "Generar certificados" crea un lote con los seleccionados; cada
 *     certificado se puede ver antes (vista previa) y después descargar
 *     (PDF, ZIP o un solo PDF para imprimir) o enviar por correo.
 * La plantilla (texto, logo, firma, sello) se edita en certificado_plantilla.php.
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/certificados.php';
require_once __DIR__ . '/includes/mailer.php';

$eventos = listarEventos($conn);
$entrada = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$eventoSel = eventoPorId($conn, $entrada['evento'] ?? 0) ?? eventoActivo($conn) ?? ($eventos ? eventoPorId($conn, $eventos[0]['id']) : null);
$opciones = $eventoSel ? opcionesCertificado($entrada, $eventoSel, $conn) : null;
$plantilla = plantillaCertificado($conn);

/** Envía por correo los certificados indicados y marca los que salieron. */
function enviarYMarcar(mysqli $conn, array $plantilla, array $certificados) {
    $items = [];
    foreach ($certificados as $c) {
        $items[] = [$c, eventoPorId($conn, $c['evento_id'])];
    }
    $enviados = 0;
    foreach (enviarCertificados($plantilla, $items) as $id => [$ok]) {
        if ($ok) {
            marcarCertificadoEnviado($conn, $id);
            $enviados++;
        }
    }
    return $enviados;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'generar' && $eventoSel) {
        [$loteId, $emitidos, $omitidos] = emitirCertificados($conn, $eventoSel, $opciones, (array) ($_POST['cedulas'] ?? []), $usuario['id']);
        header('Location: certificados.php?' . http_build_query($loteId
            ? ['lote' => $loteId, 'aviso' => 'emitidos', 'n' => $emitidos, 'omitidos' => $omitidos]
            : ['evento' => $eventoSel['id'], 'aviso' => 'sin_emitir']));
        exit;
    }

    if ($accion === 'enviar_lote') {
        $lote = loteCertificados($conn, $_POST['lote'] ?? 0);
        if ($lote) {
            $soloPendientes = ($_POST['cuales'] ?? 'pendientes') === 'pendientes';
            $porEnviar = array_filter(certificadosDelLote($conn, $lote['id']), function ($c) use ($soloPendientes) {
                return $c['correo'] !== '' && (!$soloPendientes || $c['enviado_en'] === null);
            });
            $enviados = enviarYMarcar($conn, $plantilla, $porEnviar);
            header('Location: certificados.php?' . http_build_query(['lote' => $lote['id'], 'aviso' => 'enviados', 'n' => $enviados, 'de' => count($porEnviar)]));
            exit;
        }
    }

    if ($accion === 'enviar_uno') {
        $certificado = certificadoPorId($conn, $_POST['id'] ?? 0);
        if ($certificado) {
            $enviados = $certificado['correo'] !== '' ? enviarYMarcar($conn, $plantilla, [$certificado]) : 0;
            header('Location: certificados.php?' . http_build_query(['lote' => $certificado['lote_id'], 'aviso' => 'enviados', 'n' => $enviados, 'de' => 1]));
            exit;
        }
    }
}

$aviso = null;
switch ($_GET['aviso'] ?? '') {
    case 'emitidos':
        $n = (int) ($_GET['n'] ?? 0);
        $omitidos = (int) ($_GET['omitidos'] ?? 0);
        $aviso = ['success', $n . ($n === 1 ? ' certificado generado.' : ' certificados generados.')
            . ($omitidos ? ' ' . $omitidos . ' no se generaron porque ya tenían ese certificado o no cumplen el criterio.' : '')
            . ' Revísalos, descárgalos o envíalos por correo.'];
        break;
    case 'sin_emitir':
        $aviso = ['warning', 'No se generó ningún certificado: selecciona personas que cumplan el criterio y que todavía no tengan ese certificado.'];
        break;
    case 'enviados':
        $n = (int) ($_GET['n'] ?? 0);
        $de = (int) ($_GET['de'] ?? 0);
        $aviso = [$n === $de && $de > 0 ? 'success' : 'warning', $de === 0
            ? 'No había certificados pendientes de enviar con correo registrado.'
            : $n . ' de ' . $de . ($de === 1 ? ' correo enviado.' : ' correos enviados.') . ($n < $de ? ' Los que fallaron se pueden reenviar desde la lista.' : '')];
        break;
}

$loteVer = isset($_GET['lote']) ? loteCertificados($conn, $_GET['lote']) : null;
$certificadosLote = $loteVer ? certificadosDelLote($conn, $loteVer['id']) : [];
$lotes = lotesCertificados($conn);

$buscando = $eventoSel && ($opciones['criterio'] !== 'charla' || $opciones['actividad']);
$candidatos = $buscando ? candidatosCertificado($conn, $eventoSel, $opciones) : [];
$cumplen = array_filter($candidatos, function ($c) { return $c['cumple'] && !$c['certificado_id']; });
$yaTienen = array_filter($candidatos, function ($c) { return $c['certificado_id']; });
$cronogramaSel = $eventoSel ? cronogramaDelEvento($conn, $eventoSel['id']) : [];
$diasSel = $eventoSel ? diasDelEvento($eventoSel) : [];

// Los filtros actuales, para la vista previa y para el formulario de generar.
$filtros = $opciones ? [
    'evento'         => $eventoSel['id'],
    'criterio'       => $opciones['criterio'],
    'minimo_dias'    => $opciones['minimo_dias'],
    'actividad'      => $opciones['actividad']['id'] ?? '',
    'minimo_minutos' => $opciones['actividad'] ? $opciones['minimo_minutos'] : '',
] : [];

$activeTab = 'certificados';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($aviso): ?>
  <div class="banner <?= $aviso[0] ?>"><?= h($aviso[1]) ?></div>
<?php endif; ?>

<?php if ($loteVer): $pendientes = count(array_filter($certificadosLote, function ($c) { return $c['correo'] !== '' && $c['enviado_en'] === null; })); ?>
  <div class="card card--marca">
    <div class="historial-cabecera">
      <div>
        <h2 class="section-title">Lote #<?= (int) $loteVer['id'] ?> · <?= h($loteVer['evento_nombre']) ?></h2>
        <p class="section-sub" style="margin-bottom:0;">
          <?= h(CRITERIOS_CERTIFICADO[$loteVer['criterio']] ?? $loteVer['criterio']) ?><?= $loteVer['detalle'] !== '' ? ' · ' . h($loteVer['detalle']) : '' ?>
          · <?= $loteVer['roles'] !== '' ? h($loteVer['roles']) : 'Todos los roles' ?>
          · Generado <?= h(fmtFecha($loteVer['creado_en'])) ?><?= $loteVer['creado_por_nombre'] ? ' por ' . h($loteVer['creado_por_nombre']) : '' ?>
        </p>
      </div>
      <a class="btn btn-outline btn-sm" href="certificados.php">← Volver</a>
    </div>

    <div class="stat-grid stat-grid--4" style="margin-bottom:18px;">
      <div class="stat-card azul-oscuro"><div class="label">Certificados</div><div class="value"><?= count($certificadosLote) ?></div></div>
      <div class="stat-card in"><div class="label">Enviados</div><div class="value"><?= (int) $loteVer['enviados'] ?></div></div>
      <div class="stat-card out"><div class="label">Por enviar</div><div class="value"><?= $pendientes ?></div></div>
      <div class="stat-card gris"><div class="label">Sin correo</div><div class="value"><?= count($certificadosLote) - (int) $loteVer['con_correo'] ?></div></div>
    </div>

    <div class="form-actions" style="margin-top:0;">
      <a class="btn btn-primary" href="certificado_pdf.php?lote=<?= (int) $loteVer['id'] ?>&amp;formato=zip">Descargar ZIP</a>
      <a class="btn btn-outline" href="certificado_pdf.php?lote=<?= (int) $loteVer['id'] ?>&amp;formato=pdf&amp;modo=descarga">Un solo PDF para imprimir</a>
      <?php if (EMAIL_HABILITADO): ?>
        <form method="post" data-enviando="Enviando correos…" onsubmit="return confirm('¿Enviar por correo los <?= $pendientes ?> certificados pendientes? Cada persona recibe su PDF.');">
          <input type="hidden" name="accion" value="enviar_lote">
          <input type="hidden" name="lote" value="<?= (int) $loteVer['id'] ?>">
          <input type="hidden" name="cuales" value="pendientes">
          <button type="submit" class="btn btn-outline"<?= $pendientes ? '' : ' disabled' ?>>Enviar por correo (<?= $pendientes ?>)</button>
        </form>
      <?php else: ?>
        <span class="text-muted">El envío de correo no está configurado en config.php.</span>
      <?php endif; ?>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
      <table>
        <thead><tr><th>Asistente</th><th>Rol</th><th>Correo</th><th>Asistencia</th><th>Código</th><th>Correo enviado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($certificadosLote as $c): ?>
            <tr>
              <td class="col-nombre"><?= h($c['nombre']) ?><div class="celda-detalle">C.C. <?= h($c['cedula']) ?></div></td>
              <td><?= h(tipoAsistente($c['tipo'], $c['tipo_otro'])) ?></td>
              <td><?= $c['correo'] !== '' ? h($c['correo']) : '<span class="text-muted">sin correo</span>' ?></td>
              <td><?= $c['criterio'] === 'charla' ? h($c['detalle']) : (int) $c['dias_asistidos'] . ' de ' . (int) $c['total_dias'] . ' días' ?></td>
              <td class="mono"><?= h(formatoCodigoCertificado($c['codigo'])) ?></td>
              <td><?= $c['enviado_en'] ? '<span class="status-chip in">' . h(fmtFecha($c['enviado_en'])) . '</span>' : '<span class="status-chip out">Pendiente</span>' ?></td>
              <td class="acciones-fila">
                <button type="button" class="boton-enlace"
                  data-vista-previa="certificado_pdf.php?id=<?= (int) $c['id'] ?>"
                  data-descarga="certificado_pdf.php?id=<?= (int) $c['id'] ?>&amp;modo=descarga"
                  data-titulo="Certificado · <?= h($c['nombre']) ?>"
                  data-archivo="<?= h(nombreArchivoCertificado($c)) ?>">Ver</button>
                <?php if (EMAIL_HABILITADO && $c['correo'] !== ''): ?>
                  <form method="post" data-enviando="Enviando…">
                    <input type="hidden" name="accion" value="enviar_uno">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="boton-enlace"><?= $c['enviado_en'] ? 'Reenviar' : 'Enviar' ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="historial-cabecera">
    <div>
      <h2 class="section-title">Generar certificados</h2>
      <p class="section-sub" style="margin-bottom:0;">Elige el evento, el criterio de asistencia y a qué roles se emiten. El sistema identifica quién cumple.</p>
    </div>
    <div class="crono-cabecera-acciones">
      <a class="btn btn-outline btn-sm" href="certificado_plantilla.php">Editar plantilla y firma</a>
    </div>
  </div>

  <?php if (!$eventoSel): ?>
    <div class="empty-state">Todavía no hay eventos.</div>
  <?php else: ?>
    <form method="get" class="cert-filtros">
      <input type="hidden" name="buscar" value="1">
      <div class="form-grid">
        <div class="full">
          <label for="evento">Evento</label>
          <select id="evento" name="evento" onchange="this.form.submit()">
            <?php foreach ($eventos as $e): ?>
              <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === (int) $eventoSel['id'] ? ' selected' : '' ?>>
                <?= h($e['nombre']) ?> · <?= $e['estado'] === 'activo' ? 'activo' : 'archivado' ?> · <?= (int) $e['asistieron'] ?> asistieron
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <fieldset class="crono-modos cert-criterios">
        <legend>Criterio de asistencia</legend>
        <?php foreach (CRITERIOS_CERTIFICADO as $clave => $etiqueta): ?>
          <label class="crono-modo<?= $opciones['criterio'] === $clave ? ' elegido' : '' ?>">
            <input type="radio" name="criterio" value="<?= $clave ?>"<?= $opciones['criterio'] === $clave ? ' checked' : '' ?><?= $clave === 'parcial' && $opciones['total_dias'] < 2 ? ' disabled' : '' ?>>
            <strong><?= h($etiqueta) ?></strong>
            <span><?php
              if ($clave === 'completa') {
                  echo $opciones['total_dias'] > 1 ? 'Asistió a los ' . $opciones['total_dias'] . ' días del evento.' : 'Asistió al evento.';
              } elseif ($clave === 'parcial') {
                  echo $opciones['total_dias'] > 1 ? 'Asistió a varios días, sin llegar a todos.' : 'Solo para eventos de varios días.';
              } else {
                  echo 'Estuvo en una charla del cronograma.';
              }
            ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="form-grid cert-opciones">
        <?php if ($opciones['total_dias'] > 1): ?>
          <div data-muestra-si="criterio=parcial">
            <label for="minimo_dias">Mínimo de días asistidos</label>
            <input type="number" id="minimo_dias" name="minimo_dias" min="1" max="<?= $opciones['total_dias'] - 1 ?>" value="<?= (int) $opciones['minimo_dias'] ?>">
            <div class="field-hint">De <?= $opciones['total_dias'] ?> días. Quien asistió a todos recibe el de asistencia completa.</div>
          </div>
        <?php endif; ?>
        <div data-muestra-si="criterio=charla">
          <label for="actividad">Charla del cronograma</label>
          <?php if (!$cronogramaSel): ?>
            <p class="field-hint">Este evento no tiene cronograma. <a href="cronograma.php<?= $eventoSel['estado'] === 'activo' ? '' : '?evento=' . (int) $eventoSel['id'] ?>">Ver cronograma</a></p>
          <?php else: ?>
            <select id="actividad" name="actividad">
              <option value="">Elige la charla…</option>
              <?php foreach ($cronogramaSel as $dia => $itemsDia): ?>
                <optgroup label="<?= h($diasSel[$dia]['etiqueta'] ?? 'Día ' . $dia) ?>">
                  <?php foreach ($itemsDia as $item): ?>
                    <option value="<?= (int) $item['id'] ?>"<?= (int) ($opciones['actividad']['id'] ?? 0) === (int) $item['id'] ? ' selected' : '' ?>>
                      <?= h(fmtHora12($item['hora_inicio']) . ' – ' . $item['titulo']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div data-muestra-si="criterio=charla">
          <label for="minimo_minutos">Mínimo de minutos presentes</label>
          <input type="number" id="minimo_minutos" name="minimo_minutos" min="1" value="<?= $opciones['actividad'] ? (int) $opciones['minimo_minutos'] : '' ?>" placeholder="La mitad de la charla">
          <div class="field-hint">Según sus entradas y salidas durante el horario de la charla.</div>
        </div>
      </div>

      <fieldset class="cert-roles">
        <legend>Roles a los que se emite</legend>
        <div class="cert-roles-lista">
          <?php foreach (ROLES_CERTIFICADO as $rol => $etiqueta): ?>
            <label class="check-linea"><input type="checkbox" name="roles[]" value="<?= h($rol) ?>"<?= in_array($rol, $opciones['roles'], true) ? ' checked' : '' ?>> <?= h($etiqueta) ?></label>
          <?php endforeach; ?>
        </div>
        <p class="field-hint">Si no marcas ninguno, se incluyen todos.</p>
      </fieldset>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Buscar quién cumple</button>
        <button type="button" class="btn btn-outline"
          data-vista-previa="certificado_pdf.php?<?= h(http_build_query(['muestra' => 1] + $filtros)) ?>"
          data-descarga="certificado_pdf.php?<?= h(http_build_query(['muestra' => 1, 'modo' => 'descarga'] + $filtros)) ?>"
          data-titulo="Vista previa con datos de ejemplo"
          data-archivo="Así se verá el certificado con la plantilla actual">Vista previa del certificado</button>
      </div>
    </form>

    <?php if ($opciones['criterio'] === 'charla' && !$opciones['actividad']): ?>
      <div class="banner info" style="margin-top:18px;">Elige la charla del cronograma para ver quién estuvo presente.</div>
    <?php elseif ($buscando): ?>
      <form method="post" class="cert-resultados" data-enviando="Generando certificados…">
        <input type="hidden" name="accion" value="generar">
        <?php foreach ($filtros as $campo => $valor): ?>
          <input type="hidden" name="<?= h($campo) ?>" value="<?= h($valor) ?>">
        <?php endforeach; ?>
        <?php foreach ($opciones['roles'] as $rol): ?>
          <input type="hidden" name="roles[]" value="<?= h($rol) ?>">
        <?php endforeach; ?>

        <div class="barra-lista">
          <strong><?= count($cumplen) ?> <?= count($cumplen) === 1 ? 'persona cumple' : 'personas cumplen' ?></strong>
          <span class="text-muted">de <?= count($candidatos) ?> registrados<?= $yaTienen ? ' · ' . count($yaTienen) . ' ya tienen este certificado' : '' ?></span>
          <?php if ($cumplen): ?>
            <label class="check-linea"><input type="checkbox" data-check-todos="cedulas[]" data-conteo="conteoCertificados" checked> Seleccionar a todos los que cumplen</label>
          <?php endif; ?>
        </div>

        <?php if (!$candidatos): ?>
          <div class="empty-state">No hay registrados de los roles elegidos en este evento.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th></th><th>Asistente</th><th>Rol</th><th>Asistencia</th><th>Estado</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($candidatos as $c): $puede = $c['cumple'] && !$c['certificado_id']; ?>
                  <tr class="<?= $c['cumple'] ? '' : 'fila-apagada' ?>">
                    <td><input type="checkbox" name="cedulas[]" value="<?= h($c['cedula']) ?>" aria-label="Seleccionar a <?= h($c['nombre']) ?>"<?= $puede ? ' checked' : ' disabled' ?>></td>
                    <td class="col-nombre"><?= h($c['nombre']) ?><div class="celda-detalle">C.C. <?= h($c['cedula']) ?><?= $c['correo'] === '' ? ' · sin correo' : '' ?></div></td>
                    <td><?= h(tipoAsistente($c['tipo'], $c['tipo_otro'])) ?></td>
                    <td><?= h($c['detalle_asistencia']) ?></td>
                    <td>
                      <?php if ($c['certificado_id']): ?>
                        <span class="status-chip neutro">Ya tiene certificado</span>
                      <?php elseif ($c['cumple']): ?>
                        <span class="status-chip in">Cumple</span>
                      <?php else: ?>
                        <span class="status-chip out">No cumple</span>
                      <?php endif; ?>
                    </td>
                    <td class="acciones-fila">
                      <?php if ($c['certificado_id']): ?>
                        <button type="button" class="boton-enlace"
                          data-vista-previa="certificado_pdf.php?id=<?= (int) $c['certificado_id'] ?>"
                          data-descarga="certificado_pdf.php?id=<?= (int) $c['certificado_id'] ?>&amp;modo=descarga"
                          data-titulo="Certificado · <?= h($c['nombre']) ?>"
                          data-archivo="Certificado ya emitido">Ver</button>
                      <?php elseif ($c['cumple']): ?>
                        <button type="button" class="boton-enlace"
                          data-vista-previa="certificado_pdf.php?<?= h(http_build_query(['muestra' => 1, 'cedula' => $c['cedula']] + $filtros)) ?>"
                          data-descarga="certificado_pdf.php?<?= h(http_build_query(['muestra' => 1, 'cedula' => $c['cedula'], 'modo' => 'descarga'] + $filtros)) ?>"
                          data-titulo="Vista previa · <?= h($c['nombre']) ?>"
                          data-archivo="Sin emitir todavía">Vista previa</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary"<?= $cumplen ? '' : ' disabled' ?>>Generar certificados</button>
            <span class="text-muted" id="conteoCertificados"></span>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title">Lotes generados (<?= count($lotes) ?>)</h2>
  <p class="section-sub">Cada vez que se generan certificados queda un lote para descargarlo o enviarlo por correo.</p>
  <?php if (!$lotes): ?>
    <div class="empty-state">Todavía no se han generado certificados.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Lote</th><th>Evento</th><th>Criterio</th><th>Roles</th><th>Certificados</th><th>Enviados</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($lotes as $l): ?>
            <tr>
              <td class="mono">#<?= (int) $l['id'] ?><div class="celda-detalle"><?= h(fmtFecha($l['creado_en'])) ?></div></td>
              <td class="col-nombre"><?= h($l['evento_nombre']) ?></td>
              <td><?= h(CRITERIOS_CERTIFICADO[$l['criterio']] ?? $l['criterio']) ?><?= $l['detalle'] !== '' ? '<div class="celda-detalle">' . h($l['detalle']) . '</div>' : '' ?></td>
              <td><?= $l['roles'] !== '' ? h($l['roles']) : 'Todos' ?></td>
              <td><?= (int) $l['certificados'] ?></td>
              <td><?= (int) $l['enviados'] ?> / <?= (int) $l['con_correo'] ?></td>
              <td class="acciones-fila">
                <a class="table-link" href="certificados.php?lote=<?= (int) $l['id'] ?>">Ver</a>
                <a class="table-link" href="certificado_pdf.php?lote=<?= (int) $l['id'] ?>&amp;formato=zip">ZIP</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/vista_previa.php'; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
