<?php
/**
 * Control de acceso: la pantalla principal de la portería. Arriba muestra
 * el horario del evento y si el ingreso está abierto; debajo, los
 * formularios de entrada y salida (según el punto de control que eligió
 * el portero al iniciar sesión) y quién está adentro / afuera. Cada
 * movimiento queda a nombre del portero con la sesión abierta. Fuera del
 * horario no se registran entradas (las salidas sí), y la página se
 * recarga sola cuando el ingreso abre o cierra.
 * Abajo, un reporte con los movimientos más recientes (el historial
 * completo, con búsqueda, sigue en historial.php).
 */
require_once __DIR__ . '/includes/panel.php';

$usuario = usuarioActual();
$resultadoEntrada = null;
$resultadoSalida = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $cedula = soloDigitos($_POST['cedula'] ?? '');
    // Solo se registra lo que permite el punto de control de la sesión.
    if ($cedula !== '' && in_array($accion, ['entrada', 'salida'], true) && puedeRegistrar($accion)) {
        $resultado = intentarMovimiento($conn, $cedula, $accion, $usuario['id']);
        if ($accion === 'entrada') {
            $resultadoEntrada = $resultado;
        } else {
            $resultadoSalida = $resultado;
        }
    }
}

$horario = horarioEvento($conn);
$ingreso = estadoHorario($horario);
$recargarEn = segundosHastaCambioHorario($horario);
$bloqueoEntrada = $ingreso['abierto'] ? '' : ' disabled';
$verEntrada = puedeRegistrar('entrada');
$verSalida = puedeRegistrar('salida');

$adentro = listarPorEstado($conn, 'dentro');
$fuera = listarPorEstado($conn, 'fuera');
$admin = esAdmin();
// El administrador ve abajo el historial reciente y los avisos; el
// portero, solo lo que él mismo registró hoy.
$avisos = $admin ? listarAvisos($conn, 6) : [];
$movimientos = $admin ? historialGeneral($conn, '', 50) : [];
$misMovimientos = $admin ? [] : movimientosDePortero($conn, $usuario['id']);

$activeTab = 'control';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="horario-bar <?= $ingreso['abierto'] ? 'abierto' : 'cerrado' ?>"<?= $recargarEn ? ' data-recargar-en="' . $recargarEn . '"' : '' ?>>
  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
  <div class="horario-texto">
    <span class="horario-etiqueta">Horario del evento</span>
    <?php if (horarioConfigurado($horario)): ?>
      <strong><?= h(textoHorario($horario)) ?></strong>
    <?php else: ?>
      <strong>Sin fecha ni horario definidos <a class="horario-link" href="evento.php">Configurar</a></strong>
    <?php endif; ?>
  </div>
  <div class="horario-estado">
    <?php if ($ingreso['abierto']): ?>
      <span class="status-chip in">Ingreso abierto</span>
      <?php if ($horario['hora_fin'] !== ''): ?><span class="text-muted">Cierra a las <?= h($horario['hora_fin']) ?></span><?php endif; ?>
    <?php else: ?>
      <span class="status-chip out">Ingreso cerrado</span>
      <span class="text-muted"><?= h(ucfirst($ingreso['motivo'])) ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="control-grid">

  <?php if ($verEntrada): ?>
    <div class="card<?= $verSalida ? '' : ' card--ancha' ?>" id="entrada">
      <h2 class="section-title">Registrar entrada</h2>
      <?php if ($ingreso['abierto']): ?>
        <p class="section-sub">Escanea el QR de la tarjeta — se registra al instante — o escribe la cédula y da clic en "Registrar entrada".</p>
      <?php else: ?>
        <div class="banner warning" style="margin-top:12px;">No se pueden registrar entradas: <?= h($ingreso['motivo']) ?> Las salidas sí se siguen registrando.</div>
      <?php endif; ?>

      <form method="post" id="form_entrada">
        <input type="hidden" name="accion" value="entrada">
        <div class="lookup-row">
          <input type="text" id="cedula_entrada" name="cedula" inputmode="numeric" placeholder="Número de cédula" autocomplete="off"<?= $bloqueoEntrada ?>>
          <button type="submit" class="btn btn-primary"<?= $bloqueoEntrada ?>>Registrar entrada</button>
          <button type="button" class="btn btn-outline" id="btnEscanear_entrada" onclick="iniciarEscaneo('entrada')"<?= $bloqueoEntrada ?>>Escanear QR</button>
        </div>
      </form>

      <div class="scan-area" id="scanArea_entrada" hidden>
        <div class="scan-video-wrap">
          <video id="scanVideo_entrada" playsinline muted></video>
          <div class="scan-frame"></div>
        </div>
        <canvas id="scanCanvas_entrada" style="display:none;"></canvas>
        <p class="section-sub" id="scanAviso_entrada" style="margin:10px 0 0;">Apunta la cámara al código QR de la tarjeta.</p>
      </div>

      <?php if ($resultadoEntrada): ?>
        <div class="banner <?= h($resultadoEntrada['nivel']) ?>" style="margin-top:18px;">
          <?= h($resultadoEntrada['mensaje']) ?>
          <?php if (!$resultadoEntrada['asistente']): ?> <a href="<?= $admin ? 'registro_admin.php' : 'registro.php' ?>">Registrar esta cédula</a>.<?php endif; ?>
        </div>
        <?php if ($resultadoEntrada['asistente']): $a = $resultadoEntrada['asistente']; $enDentro = $a['estado'] === 'dentro'; ?>
          <div class="attendee-panel" style="margin-top:8px;">
            <div class="attendee-info">
              <div class="name"><?= h($a['nombre']) ?></div>
              <div class="meta mono">C.C. <?= h($a['cedula']) ?> · <?= h(tipoAsistente($a['tipo'] ?? '', $a['tipo_otro'] ?? '')) ?></div>
              <span class="status-chip <?= $enDentro ? 'in' : 'out' ?>"><?= $enDentro ? '● Dentro del evento' : '○ Fuera del evento' ?></span>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($verSalida): ?>
    <div class="card<?= $verEntrada ? '' : ' card--ancha' ?>" id="salida">
      <h2 class="section-title">Registrar salida</h2>
      <p class="section-sub">Escanea el QR de la tarjeta — se registra al instante — o escribe la cédula y da clic en "Registrar salida".</p>

      <form method="post" id="form_salida">
        <input type="hidden" name="accion" value="salida">
        <div class="lookup-row">
          <input type="text" id="cedula_salida" name="cedula" inputmode="numeric" placeholder="Número de cédula" autocomplete="off">
          <button type="submit" class="btn btn-out">Registrar salida</button>
          <button type="button" class="btn btn-outline" id="btnEscanear_salida" onclick="iniciarEscaneo('salida')">Escanear QR</button>
        </div>
      </form>

      <div class="scan-area" id="scanArea_salida" hidden>
        <div class="scan-video-wrap">
          <video id="scanVideo_salida" playsinline muted></video>
          <div class="scan-frame"></div>
        </div>
        <canvas id="scanCanvas_salida" style="display:none;"></canvas>
        <p class="section-sub" id="scanAviso_salida" style="margin:10px 0 0;">Apunta la cámara al código QR de la tarjeta.</p>
      </div>

      <?php if ($resultadoSalida): ?>
        <div class="banner <?= h($resultadoSalida['nivel']) ?>" style="margin-top:18px;">
          <?= h($resultadoSalida['mensaje']) ?>
          <?php if (!$resultadoSalida['asistente']): ?> <a href="<?= $admin ? 'registro_admin.php' : 'registro.php' ?>">Registrar esta cédula</a>.<?php endif; ?>
        </div>
        <?php if ($resultadoSalida['asistente']): $a = $resultadoSalida['asistente']; $enDentro = $a['estado'] === 'dentro'; ?>
          <div class="attendee-panel" style="margin-top:8px;">
            <div class="attendee-info">
              <div class="name"><?= h($a['nombre']) ?></div>
              <div class="meta mono">C.C. <?= h($a['cedula']) ?> · <?= h(tipoAsistente($a['tipo'] ?? '', $a['tipo_otro'] ?? '')) ?></div>
              <span class="status-chip <?= $enDentro ? 'in' : 'out' ?>"><?= $enDentro ? '● Dentro del evento' : '○ Fuera del evento' ?></span>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2 class="section-title" style="font-size:16px;">Están adentro ahora (<?= count($adentro) ?>)</h2>
    <?php if (!$adentro): ?>
      <div class="empty-state" style="padding:16px;">Nadie está adentro en este momento.</div>
    <?php else: ?>
      <div class="table-wrap table-wrap--compact">
        <table>
          <thead><tr><th>Nombre</th><th>Cédula</th><th>Hora de entrada</th></tr></thead>
          <tbody>
            <?php foreach ($adentro as $a): ?>
              <tr>
                <td><?= h($a['nombre']) ?></td>
                <td class="cedula-cell"><?= h($a['cedula']) ?></td>
                <td class="mono"><?= $a['ultima_fecha'] ? fmtFecha($a['ultima_fecha']) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="section-title" style="font-size:16px;">Ya salieron / no han entrado (<?= count($fuera) ?>)</h2>
    <?php if (!$fuera): ?>
      <div class="empty-state" style="padding:16px;">No hay nadie afuera en este momento.</div>
    <?php else: ?>
      <div class="table-wrap table-wrap--compact">
        <table>
          <thead><tr><th>Nombre</th><th>Cédula</th><th>Último movimiento</th></tr></thead>
          <tbody>
            <?php foreach ($fuera as $a): ?>
              <tr>
                <td><?= h($a['nombre']) ?></td>
                <td class="cedula-cell"><?= h($a['cedula']) ?></td>
                <td class="mono"><?= $a['ultima_fecha'] ? fmtFecha($a['ultima_fecha']) : 'Nunca ha entrado' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php if ($admin): ?>
<div class="card" style="margin-top:20px;">
  <h2 class="section-title">Reportes e historial</h2>
  <p class="section-sub">Los <?= count($movimientos) ?> movimientos más recientes de entrada y salida. <a href="historial.php">Ver historial completo y buscar →</a></p>
  <?php if (!$movimientos): ?>
    <div class="empty-state">No hay movimientos registrados todavía.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Fecha y hora</th><th>Movimiento</th><th>Nombre</th><th>Tipo</th><th>Cédula</th><th>Registró</th></tr></thead>
        <tbody>
          <?php foreach ($movimientos as $m): ?>
            <tr>
              <td class="mono"><?= fmtFecha($m['fecha']) ?></td>
              <td><span class="<?= $m['tipo'] === 'entrada' ? 'type-in' : 'type-out' ?>"><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?></span></td>
              <td><?= h($m['nombre']) ?></td>
              <td><?= h(tipoAsistente($m['tipo_asistente'], $m['tipo_otro'])) ?></td>
              <td class="cedula-cell"><?= h($m['cedula']) ?></td>
              <td><?= h($m['portero'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title" style="font-size:16px;">Avisos recientes</h2>
  <p class="section-sub">Intentos de entrada o salida que no se dejaron registrar porque no correspondían.</p>
  <div class="activity-feed">
    <?php if (!$avisos): ?>
      <div class="empty-state" style="padding:16px;">No hay avisos todavía.</div>
    <?php else: ?>
      <?php foreach ($avisos as $av): ?>
        <div class="activity-row">
          <span class="who"><?= h($av['nombre'] ?? $av['cedula']) ?> <span class="mono" style="color:var(--text-muted);font-weight:400;">· <?= h($av['cedula']) ?></span> —
            <span class="type-out"><?= h(etiquetaAviso($av['tipo'])) ?></span></span>
          <span class="when"><?= fmtFecha($av['fecha']) ?><?= $av['portero'] ? ' · ' . h($av['portero']) : '' ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-top:20px;">
  <h2 class="section-title" style="font-size:16px;">Tus registros de hoy</h2>
  <?php if (!$misMovimientos): ?>
    <div class="empty-state" style="padding:16px;">Todavía no has registrado entradas ni salidas hoy.</div>
  <?php else: ?>
    <div class="activity-feed">
      <?php foreach ($misMovimientos as $m): ?>
        <div class="activity-row">
          <span class="who"><?= h($m['nombre']) ?> — <span class="<?= $m['tipo'] === 'entrada' ? 'type-in' : 'type-out' ?>"><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?></span></span>
          <span class="when"><?= date('H:i', strtotime($m['fecha'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script src="assets/js/jsQR.js?v=<?= assetVersion('assets/js/jsQR.js') ?>"></script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
