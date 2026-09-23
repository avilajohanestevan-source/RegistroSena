<?php
/**
 * Reportes por día: resumen de asistencia (también por tipo de
 * asistente), personas que entraron y no registraron salida (con envío de
 * un aviso por correo), turnos de portería, avisos que generó el control
 * de acceso y registro de los correos enviados. Desde aquí también se
 * descargan en CSV los movimientos del día.
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/mailer.php';

// Se puede consultar un evento archivado con ?evento=ID.
$eventoConsulta = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
$paramEvento = $eventoConsulta ? ['evento' => $eventoConsulta['id']] : [];
$horario = horarioEvento($conn);
$dia = $_GET['dia'] ?? ($_POST['dia'] ?? '');
if (!esFechaValida($dia)) {
    $dia = diaReportePorDefecto($horario);
}

if (($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="movimientos_' . $dia . '.csv"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF"); // BOM: así Excel muestra bien las tildes
    fputcsv($salida, ['Fecha y hora', 'Movimiento', 'Nombre', 'Tipo', 'Cédula', 'Correo', 'Teléfono', 'Empresa', 'Registró'], ';');
    foreach (movimientosDelDia($conn, $dia) as $m) {
        fputcsv($salida, [
            $m['fecha'], ucfirst($m['tipo']), $m['nombre'], tipoAsistente($m['tipo_asistente'], $m['tipo_otro']),
            $m['cedula'], $m['correo'], $m['telefono'], $m['empresa'], $m['portero'] ?? '',
        ], ';');
    }
    fclose($salida);
    exit;
}

$evento = nombreEvento($conn);
$asunto = 'Tu salida de {evento} no quedó registrada';
$mensaje = "Hola {nombre}:\n\n"
    . "Registramos tu entrada a {evento} el {hora_entrada}, pero no quedó registrada tu salida.\n\n"
    . "Te recordamos que cada vez que te retires del evento debes presentar tu código QR en el punto de control para registrar la salida. "
    . "Si aún te encuentras en el lugar, acércate al punto de control antes de irte.\n\n"
    . "Gracias por tu asistencia.";
$errorEnvio = '';
$seleccion = null; // null = selección por defecto: quienes aún no tienen aviso

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'enviar_avisos') {
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $seleccion = array_map('soloDigitos', (array) ($_POST['cedulas'] ?? []));
    // Solo se envía a quienes de verdad aparecen en la lista de ese día.
    $destinatarios = array_values(array_filter(sinSalidaDelDia($conn, $dia), function ($p) use ($seleccion) {
        return in_array($p['cedula'], $seleccion, true);
    }));

    if (!EMAIL_HABILITADO) {
        $errorEnvio = 'El envío de correo no está configurado (falta SMTP_USER/SMTP_PASS en config.php).';
    } elseif (!$destinatarios) {
        $errorEnvio = 'Selecciona al menos una persona de la lista.';
    } elseif ($asunto === '' || $mensaje === '') {
        $errorEnvio = 'Escribe el asunto y el mensaje del aviso.';
    } else {
        $resultados = enviarAvisos($destinatarios, $evento, $asunto, $mensaje);
        $enviados = 0;
        foreach ($destinatarios as $p) {
            [$ok, $detalle] = $resultados[$p['cedula']];
            registrarNotificacion($conn, $p['cedula'], $p['correo'], personalizarAviso($asunto, $p, $evento), $dia, $ok, $detalle);
            $enviados += $ok ? 1 : 0;
        }
        $fallidos = count($destinatarios) - $enviados;
        header('Location: reportes.php?dia=' . $dia . '&enviados=' . $enviados . '&fallidos=' . $fallidos . '#sin-salida');
        exit;
    }
}

$sinSalida = sinSalidaDelDia($conn, $dia);
$movimientos = movimientosDelDia($conn, $dia);
$avisosDia = avisosDelDia($conn, $dia);
$turnos = turnosDelDia($conn, $dia);
$correos = notificacionesDelDia($conn, $dia);

$entradas = array_filter($movimientos, function ($m) { return $m['tipo'] === 'entrada'; });
$asistieron = count(array_unique(array_column($entradas, 'cedula')));
// Personas distintas que entraron ese día, agrupadas por tipo de asistente.
$porTipo = [];
foreach ($entradas as $m) {
    $porTipo[$m['tipo_asistente'] !== '' ? $m['tipo_asistente'] : 'Sin tipo'][$m['cedula']] = true;
}
$porTipo = array_map('count', $porTipo);
arsort($porTipo);
$correosEnviados = count(array_filter($correos, function ($c) { return (int) $c['enviado'] === 1; }));
// Mientras la jornada de hoy no haya cerrado, quien sigue adentro también
// aparece como "sin salida".
$jornadaEnCurso = $dia === date('Y-m-d') && ($horario['hora_fin'] === '' || date('H:i') <= $horario['hora_fin']);
$puntos = puntosControl();

$resultadoEnvio = null;
if (isset($_GET['enviados'])) {
    $e = (int) $_GET['enviados'];
    $f = (int) ($_GET['fallidos'] ?? 0);
    $partes = [];
    if ($e) $partes[] = $e === 1 ? 'Se envió 1 aviso por correo.' : "Se enviaron $e avisos por correo.";
    if ($f) $partes[] = ($f === 1 ? '1 correo no se pudo enviar' : "$f correos no se pudieron enviar") . ' — revisa el detalle en "Correos de aviso enviados".';
    $resultadoEnvio = ['nivel' => $f ? 'warning' : 'success', 'texto' => implode(' ', $partes)];
}

$activeTab = 'reportes';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h2 class="section-title">Reportes</h2>
  <p class="section-sub">
    Elige un día para ver quién asistió, quién entró y no registró su salida, quién estuvo en la portería y los avisos que se generaron.
    <?php if (horarioConfigurado($horario)): ?>Fecha del evento: <?= h(textoHorario($horario)) ?>.<?php endif; ?>
  </p>
  <form method="get" class="filtro-dia">
    <?php foreach ($paramEvento as $clave => $valor): ?><input type="hidden" name="<?= h($clave) ?>" value="<?= h($valor) ?>"><?php endforeach; ?>
    <div>
      <label for="dia">Día del reporte</label>
      <input type="date" id="dia" name="dia" value="<?= h($dia) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Ver reporte</button>
    <a class="btn btn-outline" href="reportes.php?<?= h(http_build_query(['dia' => $dia, 'exportar' => 'csv'] + $paramEvento)) ?>">Descargar movimientos (CSV)</a>
  </form>
</div>

<div class="card">
  <h2 class="section-title">Resumen del <?= h(fmtDia($dia)) ?></h2>
  <p class="section-sub"><?= count($movimientos) === 1 ? '1 movimiento registrado' : count($movimientos) . ' movimientos de entrada y salida registrados' ?> este día.</p>
  <div class="stat-grid stat-grid--4">
    <div class="stat-card in"><div class="label">Asistieron</div><div class="value"><?= $asistieron ?></div></div>
    <div class="stat-card out"><div class="label">Sin salida registrada</div><div class="value"><?= count($sinSalida) ?></div></div>
    <div class="stat-card total"><div class="label">Avisos del control</div><div class="value"><?= count($avisosDia) ?></div></div>
    <div class="stat-card azul"><div class="label">Correos de aviso enviados</div><div class="value"><?= $correosEnviados ?></div></div>
  </div>
  <?php if ($porTipo): ?>
    <p class="por-tipo">
      <span class="text-muted">Asistieron por tipo:</span>
      <?php $i = 0; foreach ($porTipo as $tipo => $cantidad): ?><?= $i++ ? ' · ' : ' ' ?><?= h($tipo) ?> <strong><?= $cantidad ?></strong><?php endforeach; ?>
    </p>
  <?php endif; ?>
</div>

<div class="card" id="sin-salida">
  <h2 class="section-title">Entraron y no registraron salida (<?= count($sinSalida) ?>)</h2>
  <p class="section-sub">Personas cuyo último movimiento de ese día fue una entrada. Selecciona a quién enviarle un aviso por correo.</p>

  <?php if ($resultadoEnvio): ?>
    <div class="banner <?= $resultadoEnvio['nivel'] ?>"><?= h($resultadoEnvio['texto']) ?></div>
  <?php endif; ?>
  <?php if ($errorEnvio): ?>
    <div class="banner error"><?= h($errorEnvio) ?></div>
  <?php endif; ?>
  <?php if ($jornadaEnCurso && $sinSalida): ?>
    <div class="banner info">La jornada de hoy todavía no termina: en esta lista también aparecen quienes siguen dentro del evento.</div>
  <?php endif; ?>

  <?php if (!$sinSalida): ?>
    <div class="empty-state">Todas las personas que entraron este día registraron su salida.</div>
  <?php else: ?>
    <form method="post" data-enviando="Enviando avisos…">
      <input type="hidden" name="accion" value="enviar_avisos">
      <input type="hidden" name="dia" value="<?= h($dia) ?>">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" data-check-todos="cedulas[]" data-conteo="conteoSeleccion" aria-label="Seleccionar a todos"></th>
              <th>Nombre</th><th>Tipo</th><th>Cédula</th><th>Correo</th><th>Hora de entrada</th><th>Registró la entrada</th><th>Aviso por correo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sinSalida as $p): ?>
              <?php $marcada = $seleccion === null ? !$p['ultimo_aviso'] : in_array($p['cedula'], $seleccion, true); ?>
              <tr>
                <td class="col-check"><input type="checkbox" name="cedulas[]" value="<?= h($p['cedula']) ?>" aria-label="Seleccionar a <?= h($p['nombre']) ?>"<?= $marcada ? ' checked' : '' ?>></td>
                <td class="col-nombre"><?= h($p['nombre']) ?></td>
                <td><?= h(tipoAsistente($p['tipo'], $p['tipo_otro'])) ?></td>
                <td class="cedula-cell"><?= h($p['cedula']) ?></td>
                <td><?= h($p['correo'] !== '' ? $p['correo'] : '—') ?></td>
                <td class="mono"><?= fmtFecha($p['hora_entrada']) ?></td>
                <td><?= h($p['portero'] ?? '—') ?></td>
                <td>
                  <?php if ($p['ultimo_aviso']): ?>
                    <span class="status-chip in">Enviado <?= fmtFecha($p['ultimo_aviso']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Sin enviar</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="aviso-form">
        <h3 class="section-title" style="font-size:16px;">Aviso por correo</h3>
        <?php if (!EMAIL_HABILITADO): ?>
          <div class="banner warning" style="margin-top:12px;">El envío de correo no está configurado. Llena SMTP_USER y SMTP_PASS en config.php para poder enviar avisos.</div>
        <?php endif; ?>
        <div class="form-grid" style="margin-top:14px;">
          <div class="full">
            <label for="asunto">Asunto</label>
            <input type="text" id="asunto" name="asunto" value="<?= h($asunto) ?>">
          </div>
          <div class="full">
            <label for="mensaje">Mensaje</label>
            <textarea id="mensaje" name="mensaje" rows="8"><?= h($mensaje) ?></textarea>
            <p class="field-hint">Puedes usar <strong>{nombre}</strong>, <strong>{evento}</strong> y <strong>{hora_entrada}</strong>: se reemplazan con los datos de cada persona.</p>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"<?= EMAIL_HABILITADO ? '' : ' disabled' ?>>Enviar aviso a los seleccionados</button>
          <span class="text-muted" id="conteoSeleccion"></span>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title">Turnos de portería (<?= count($turnos) ?>)</h2>
  <p class="section-sub">Quién estuvo en cada punto de control ese día y cuántos movimientos registró durante su turno.</p>
  <?php if (!$turnos): ?>
    <div class="empty-state">Nadie inició sesión en la portería este día.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Portero</th><th>Punto de control</th><th>Inicio</th><th>Fin</th><th>Movimientos</th></tr></thead>
        <tbody>
          <?php foreach ($turnos as $t): ?>
            <tr>
              <td class="col-nombre"><?= h($t['nombre']) ?><div class="celda-detalle"><?= h($t['cedula']) ?></div></td>
              <td><?= h($puntos[$t['punto']] ?? $t['punto']) ?></td>
              <td class="mono"><?= fmtFecha($t['inicio']) ?></td>
              <td class="mono">
                <?php if ($t['fin']): ?>
                  <?= fmtFecha($t['fin']) ?>
                <?php elseif ($dia === date('Y-m-d')): ?>
                  <span class="status-chip in">En turno</span>
                <?php else: ?>
                  <span class="text-muted">No cerró sesión</span>
                <?php endif; ?>
              </td>
              <td><?= (int) $t['movimientos'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title">Avisos del control de acceso (<?= count($avisosDia) ?>)</h2>
  <p class="section-sub">Intentos de entrada o salida que no se dejaron registrar ese día: entradas repetidas, salidas sin entrada o entradas fuera del horario del evento.</p>
  <?php if (!$avisosDia): ?>
    <div class="empty-state">No hubo avisos este día.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Fecha y hora</th><th>Tipo</th><th>Persona</th><th>Detalle</th><th>Registró</th></tr></thead>
        <tbody>
          <?php foreach ($avisosDia as $av): ?>
            <tr>
              <td class="mono"><?= fmtFecha($av['fecha']) ?></td>
              <td><span class="type-out"><?= h(etiquetaAviso($av['tipo'])) ?></span></td>
              <td><?= h($av['nombre'] ?? '—') ?><div class="celda-detalle"><?= h($av['cedula']) ?></div></td>
              <td><?= h($av['mensaje']) ?></td>
              <td><?= h($av['portero'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title">Correos de aviso enviados (<?= count($correos) ?>)</h2>
  <p class="section-sub">Registro de los avisos por correo enviados sobre este día.</p>
  <?php if (!$correos): ?>
    <div class="empty-state">Todavía no se han enviado avisos sobre este día.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Enviado</th><th>Persona</th><th>Correo</th><th>Asunto</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($correos as $c): ?>
            <tr>
              <td class="mono"><?= fmtFecha($c['fecha']) ?></td>
              <td><?= h($c['nombre'] ?? $c['cedula']) ?></td>
              <td><?= h($c['correo']) ?></td>
              <td><?= h($c['asunto']) ?></td>
              <td>
                <?php if ((int) $c['enviado'] === 1): ?>
                  <span class="status-chip in">Enviado</span>
                <?php else: ?>
                  <span class="status-chip error">No enviado</span>
                  <?php if ($c['detalle'] !== ''): ?><div class="celda-detalle"><?= h($c['detalle']) ?></div><?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
