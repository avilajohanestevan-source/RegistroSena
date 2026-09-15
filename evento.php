<?php
/**
 * Pestaña Evento del panel: nombre, fecha(s) y horario de ingreso del
 * evento, resumen de asistentes y accesos rápidos.
 */
require_once __DIR__ . '/includes/panel_admin.php';

// Valores del formulario "Datos del evento". fecha_fin se lee tal cual
// (horarioEvento() la rellena con la de inicio cuando está vacía).
$valores = ['nombre_evento' => nombreEvento($conn)] + horarioEvento($conn);
$valores['fecha_fin'] = configValor($conn, 'fecha_fin');
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_evento'])) {
    $valores = [
        'nombre_evento' => trim($_POST['nombre_evento']),
        'fecha_inicio'  => trim($_POST['fecha_inicio'] ?? ''),
        'fecha_fin'     => trim($_POST['fecha_fin'] ?? ''),
        'hora_inicio'   => trim($_POST['hora_inicio'] ?? ''),
        'hora_fin'      => trim($_POST['hora_fin'] ?? ''),
    ];

    if ($valores['nombre_evento'] === '') {
        $errores['nombre_evento'] = 'Escribe el nombre del evento.';
    }
    foreach (['fecha_inicio', 'fecha_fin'] as $campo) {
        if ($valores[$campo] !== '' && !esFechaValida($valores[$campo])) {
            $errores[$campo] = 'Escribe una fecha válida.';
        }
    }
    foreach (['hora_inicio', 'hora_fin'] as $campo) {
        if ($valores[$campo] !== '' && !esHoraValida($valores[$campo])) {
            $errores[$campo] = 'Escribe una hora válida.';
        }
    }
    if (!$errores) {
        if ($valores['fecha_fin'] !== '' && $valores['fecha_inicio'] === '') {
            $errores['fecha_inicio'] = 'Indica también la fecha de inicio.';
        } elseif ($valores['fecha_fin'] !== '' && $valores['fecha_fin'] < $valores['fecha_inicio']) {
            $errores['fecha_fin'] = 'Debe ser igual o posterior a la fecha de inicio.';
        }
        if ($valores['hora_inicio'] !== '' && $valores['hora_fin'] !== '' && $valores['hora_fin'] <= $valores['hora_inicio']) {
            $errores['hora_fin'] = 'Debe ser posterior a la hora de apertura.';
        }
    }

    if (!$errores) {
        foreach ($valores as $clave => $valor) {
            guardarConfig($conn, $clave, $valor);
        }
        header('Location: evento.php?guardado=1');
        exit;
    }
}

$evento = nombreEvento($conn);
$conteo = contarEstados($conn);
$horario = horarioEvento($conn);
$estado = estadoHorario($horario);
$activeTab = 'evento';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="card">
  <h2 class="section-title">Datos del evento</h2>
  <p class="section-sub">El nombre se muestra en la barra superior, en el autorregistro y en las tarjetas. La fecha y el horario limitan cuándo se pueden registrar entradas.</p>
  <?php if (isset($_GET['guardado'])): ?>
    <div class="banner success">Datos del evento guardados.</div>
  <?php endif; ?>
  <form method="post" novalidate>
    <div class="form-grid">
      <div class="full">
        <label for="nombre_evento">Nombre del evento</label>
        <input type="text" id="nombre_evento" name="nombre_evento" value="<?= h($valores['nombre_evento']) ?>">
        <?php if (!empty($errores['nombre_evento'])): ?><div class="field-error"><?= h($errores['nombre_evento']) ?></div><?php endif; ?>
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
    <p class="field-hint">Fuera de estas fechas y horas el control de acceso no deja registrar entradas (el intento queda como aviso en Reportes). Las salidas y el autorregistro siguen funcionando. Deja los campos vacíos para no limitar.</p>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Guardar</button>
      <?php if (horarioConfigurado($horario)): ?>
        <span class="status-chip <?= $estado['abierto'] ? 'in' : 'out' ?>"><?= $estado['abierto'] ? 'Ingreso abierto ahora' : 'Ingreso cerrado: ' . h($estado['motivo']) ?></span>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2 class="section-title">Resumen</h2>
  <p class="section-sub">Estado del evento en este momento.</p>
  <div class="stat-grid">
    <div class="stat-card in"><div class="label">Dentro del evento</div><div class="value"><?= $conteo['dentro'] ?></div></div>
    <div class="stat-card out"><div class="label">Fuera del evento</div><div class="value"><?= $conteo['fuera'] ?></div></div>
    <div class="stat-card total"><div class="label">Registrados</div><div class="value"><?= $conteo['total'] ?></div></div>
  </div>
</div>

<div class="card">
  <h2 class="section-title">Accesos rápidos</h2>
  <div class="form-actions">
    <a class="btn btn-primary" href="control.php">Control de acceso</a>
    <a class="btn btn-outline" href="estadisticas.php">Estadísticas y exportes</a>
    <a class="btn btn-outline" href="autorregistro.php">QR de autorregistro</a>
    <a class="btn btn-outline" href="registro_admin.php">Registrar manualmente</a>
    <a class="btn btn-outline" href="reportes.php">Ver reportes</a>
    <a class="btn btn-outline" href="historial.php">Historial general</a>
    <a class="btn btn-outline" href="asistentes.php">Ver asistentes</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
