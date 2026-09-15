<?php
/**
 * Control de acceso: entrada y salida en una sola pantalla, dividida en
 * dos columnas — izquierda = entrada, derecha = salida — para que quien
 * atiende la puerta no tenga que cambiar de pestaña entre una acción y
 * la otra. Cada formulario postea a esta misma página con un campo
 * oculto "accion" que dice cuál de las dos se está registrando.
 * Abajo, un reporte grande con los movimientos más recientes (el
 * historial completo, con búsqueda, sigue en historial.php).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$resultadoEntrada = null;
$resultadoSalida = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $cedula = soloDigitos($_POST['cedula'] ?? '');
    if ($cedula !== '' && $accion === 'entrada') {
        $resultadoEntrada = intentarMovimiento($conn, $cedula, 'entrada');
    } elseif ($cedula !== '' && $accion === 'salida') {
        $resultadoSalida = intentarMovimiento($conn, $cedula, 'salida');
    }
}

$adentro = listarPorEstado($conn, 'dentro');
$fuera = listarPorEstado($conn, 'fuera');
$avisos = listarAvisos($conn, 6);
$movimientos = historialGeneral($conn, '', 50);

$activeTab = 'control';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="control-grid">

    <div class="card" id="entrada">
      <h2 class="section-title">Registrar entrada</h2>
      <p class="section-sub">Escanea el QR de la tarjeta — se registra al instante — o escribe la cédula y da clic en "Registrar entrada".</p>

      <form method="post" id="form_entrada">
        <input type="hidden" name="accion" value="entrada">
        <div class="lookup-row">
          <input type="text" id="cedula_entrada" name="cedula" inputmode="numeric" placeholder="Número de cédula" autocomplete="off">
          <button type="submit" class="btn btn-primary">Registrar entrada</button>
          <button type="button" class="btn btn-outline" id="btnEscanear_entrada" onclick="iniciarEscaneo('entrada')">Escanear QR</button>
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
          <?php if (!$resultadoEntrada['asistente']): ?> <a href="registro_admin.php">Registrar esta cédula</a>.<?php endif; ?>
        </div>
        <?php if ($resultadoEntrada['asistente']): $a = $resultadoEntrada['asistente']; $enDentro = $a['estado'] === 'dentro'; ?>
          <div class="attendee-panel" style="margin-top:8px;">
            <div class="attendee-info">
              <div class="name"><?= h($a['nombre']) ?></div>
              <div class="meta mono">C.C. <?= h($a['cedula']) ?></div>
              <span class="status-chip <?= $enDentro ? 'in' : 'out' ?>"><?= $enDentro ? '● Dentro del evento' : '○ Fuera del evento' ?></span>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card" id="salida">
      <h2 class="section-title">Registrar salida</h2>
      <p class="section-sub">Escanea el QR de la tarjeta — se registra al instante — o escribe la cédula y da clic en "Registrar salida".</p> <br>

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
          <?php if (!$resultadoSalida['asistente']): ?> <a href="registro_admin.php">Registrar esta cédula</a>.<?php endif; ?>
        </div>
        <?php if ($resultadoSalida['asistente']): $a = $resultadoSalida['asistente']; $enDentro = $a['estado'] === 'dentro'; ?>
          <div class="attendee-panel" style="margin-top:8px;">
            <div class="attendee-info">
              <div class="name"><?= h($a['nombre']) ?></div>
              <div class="meta mono">C.C. <?= h($a['cedula']) ?></div>
              <span class="status-chip <?= $enDentro ? 'in' : 'out' ?>"><?= $enDentro ? '● Dentro del evento' : '○ Fuera del evento' ?></span>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

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

<div class="card" style="margin-top:20px;">
  <h2 class="section-title">Reportes e historial</h2>
  <p class="section-sub">Los <?= count($movimientos) ?> movimientos más recientes de entrada y salida. <a href="historial.php">Ver historial completo y buscar →</a></p>
  <?php if (!$movimientos): ?>
    <div class="empty-state">No hay movimientos registrados todavía.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Fecha y hora</th><th>Movimiento</th><th>Nombre</th><th>Cédula</th><th>Empresa</th></tr></thead>
        <tbody>
          <?php foreach ($movimientos as $m): ?>
            <tr>
              <td class="mono"><?= fmtFecha($m['fecha']) ?></td>
              <td><span class="<?= $m['tipo'] === 'entrada' ? 'type-in' : 'type-out' ?>"><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?></span></td>
              <td><?= h($m['nombre']) ?></td>
              <td class="cedula-cell"><?= h($m['cedula']) ?></td>
              <td><?= h($m['empresa'] !== '' ? $m['empresa'] : '—') ?></td>
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
          <span class="when"><?= fmtFecha($av['fecha']) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script src="assets/js/jsQR.js?v=<?= assetVersion('assets/js/jsQR.js') ?>"></script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
