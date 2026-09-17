<?php
/**
 * Pestaña Evento (solo administrador): el evento activo — crearlo,
 * editar su nombre, fecha y horario, y cerrarlo/archivarlo — y la lista
 * de todos los eventos con sus métricas clave. Al cerrar un evento, todo
 * su historial (asistentes, entradas y salidas, avisos, estadísticas)
 * queda guardado bajo él y el panel arranca limpio con el siguiente.
 */
require_once __DIR__ . '/includes/panel_admin.php';

$roles = rolesUsuario();
$valores = ['nombre' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'hora_inicio' => '', 'hora_fin' => '', 'cronograma_modo' => 'mismo'];
if ($eventoActual) {
    $valores = [
        'nombre'       => $eventoActual['nombre'],
        'fecha_inicio' => $eventoActual['fecha_inicio'],
        'fecha_fin'    => $eventoActual['fecha_fin'],
        'hora_inicio'  => substr($eventoActual['hora_inicio'], 0, 5),
        'hora_fin'     => substr($eventoActual['hora_fin'], 0, 5),
        'cronograma_modo' => modoCronograma($eventoActual),
    ];
}
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cerrar' && $eventoActual) {
        cerrarEvento($conn, $eventoActual['id'], $usuario['id']);
        header('Location: evento.php?aviso=cerrado');
        exit;
    }

    if ($accion === 'eliminar') {
        [$ok, $conservadas] = eliminarEvento($conn, $_POST['id'] ?? 0);
        header('Location: evento.php?' . ($ok ? 'aviso=eliminado&personas=' . $conservadas : 'aviso=no_eliminado'));
        exit;
    }

    if ($accion === 'crear' || $accion === 'guardar') {
        $valores = [
            'nombre'       => trim($_POST['nombre'] ?? ''),
            'fecha_inicio' => trim($_POST['fecha_inicio'] ?? ''),
            'fecha_fin'    => trim($_POST['fecha_fin'] ?? ''),
            'hora_inicio'  => trim($_POST['hora_inicio'] ?? ''),
            'hora_fin'     => trim($_POST['hora_fin'] ?? ''),
            'cronograma_modo' => ($_POST['cronograma_modo'] ?? '') === 'por_dia' ? 'por_dia' : 'mismo',
        ];
        if (mb_strlen($valores['nombre']) < 3) {
            $errores['nombre'] = 'Escribe el nombre del evento.';
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
            if ($accion === 'crear') {
                [$id, $error] = crearEvento($conn, $valores, $usuario['id']);
                if (!$id) {
                    $errores['nombre'] = $error;
                } else {
                    // Recién creado el evento se arma su cronograma (y desde ahí se invita).
                    header('Location: cronograma.php?aviso=evento_creado');
                    exit;
                }
            } else {
                actualizarEvento($conn, $eventoActual['id'], $valores);
                header('Location: evento.php?aviso=guardado');
                exit;
            }
        }
    }
}

$avisos = [
    'creado'   => ['success', 'Evento creado. El panel arranca limpio: los asistentes y los movimientos que se registren desde ahora quedan en este evento.'],
    'guardado' => ['success', 'Datos del evento guardados.'],
    'cerrado'  => ['success', 'Evento cerrado y archivado. Su historial queda guardado; crea uno nuevo cuando lo necesites.'],
    'eliminado' => ['success', 'Evento borrado con su historial. Sus asistentes (' . (int) ($_GET['personas'] ?? 0) . ') se conservan: siguen en la lista para invitarlos a próximos eventos, con su mismo código QR.'],
    'no_eliminado' => ['error', 'No se pudo borrar el evento. Solo se pueden borrar eventos archivados; si está activo, ciérralo primero.'],
];
$aviso = $avisos[$_GET['aviso'] ?? ''] ?? null;

$eventos = listarEventos($conn);
$metricas = $eventoActual ? metricasEvento($conn, $eventoActual['id']) : null;
$horario = horarioDeEvento($eventoActual);
$estado = estadoHorario($horario);

$activeTab = 'evento';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($aviso): ?>
  <div class="banner <?= $aviso[0] ?>"><?= h($aviso[1]) ?></div>
<?php endif; ?>

<?php if (!$eventoActual): ?>
  <div class="card card--marca">
    <h2 class="section-title">Crear un evento</h2>
    <p class="section-sub">No hay ningún evento activo: el control de acceso y el autorregistro están cerrados hasta que crees uno. El evento nuevo arranca sin asistentes ni movimientos; lo del evento anterior queda guardado en el archivo.</p>
    <form method="post" novalidate>
      <input type="hidden" name="accion" value="crear">
      <?php require __DIR__ . '/includes/form_evento.php'; ?>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Crear evento y activarlo</button>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <div class="historial-cabecera">
      <div>
        <h2 class="section-title">Evento activo</h2>
        <p class="section-sub" style="margin-bottom:0;">
          <?= horarioConfigurado($horario) ? h(textoHorario($horario)) : 'Sin fecha ni horario definidos' ?>
          <?php if (horarioConfigurado($horario)): ?>
            · <span class="status-chip <?= $estado['abierto'] ? 'in' : 'out' ?>"><?= $estado['abierto'] ? 'Ingreso abierto' : 'Ingreso cerrado' ?></span>
          <?php endif; ?>
        </p>
      </div>
      <form method="post" onsubmit="return confirm('¿Cerrar el evento <?= h($eventoActual['nombre']) ?>? Su historial queda archivado y el control de acceso deja de funcionar hasta que crees otro evento.');">
        <input type="hidden" name="accion" value="cerrar">
        <button type="submit" class="btn btn-out">Cerrar y archivar evento</button>
      </form>
    </div>

    <div class="stat-grid stat-grid--4" style="margin-bottom:22px;">
      <div class="stat-card azul-oscuro"><div class="label">Registrados</div><div class="value"><?= (int) $metricas['registrados'] ?></div></div>
      <div class="stat-card in"><div class="label">Asistieron</div><div class="value"><?= (int) $metricas['asistieron'] ?></div></div>
      <div class="stat-card total"><div class="label">Movimientos</div><div class="value"><?= (int) $metricas['entradas'] + (int) $metricas['salidas'] ?></div></div>
      <div class="stat-card rojo"><div class="label">Irregularidades</div><div class="value"><?= (int) $metricas['irregularidades'] ?></div></div>
    </div>

    <form method="post" novalidate>
      <input type="hidden" name="accion" value="guardar">
      <?php require __DIR__ . '/includes/form_evento.php'; ?>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-outline" href="cronograma.php">Cronograma</a>
        <a class="btn btn-outline" href="invitaciones.php">Invitar asistentes anteriores</a>
        <a class="btn btn-outline" href="estadisticas.php">Ver estadísticas</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2 class="section-title">Eventos (<?= count($eventos) ?>)</h2>
  <p class="section-sub">De los eventos archivados se muestra lo esencial; los datos completos quedan guardados y se pueden consultar y descargar desde su resumen. Si borras un evento archivado, sus asistentes se conservan para invitarlos otra vez.</p>
  <?php if (!$eventos): ?>
    <div class="empty-state">Todavía no hay eventos.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Evento</th><th>Estado</th><th>Fecha y horario</th><th>Registrados</th><th>Asistieron</th><th>Movimientos</th><th>Irregularidades</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($eventos as $e): $h = horarioDeEvento($e); ?>
            <tr>
              <td class="col-nombre">
                <?= h($e['nombre']) ?>
                <div class="celda-detalle">
                  Creado <?= fmtFecha($e['creado_en']) ?><?= $e['creado_por_nombre'] ? ' por ' . h($e['creado_por_nombre']) : '' ?>
                  <?php if ($e['cerrado_en']): ?><br>Cerrado <?= fmtFecha($e['cerrado_en']) ?><?= $e['cerrado_por_nombre'] ? ' por ' . h($e['cerrado_por_nombre']) : '' ?><?php endif; ?>
                </div>
              </td>
              <td>
                <?php if ($e['estado'] === 'activo'): ?>
                  <span class="status-chip in">Activo</span>
                <?php else: ?>
                  <span class="status-chip neutro">Archivado</span>
                <?php endif; ?>
              </td>
              <td><?= horarioConfigurado($h) ? h(textoHorario($h)) : '—' ?></td>
              <td><?= (int) $e['registrados'] ?></td>
              <td><?= (int) $e['asistieron'] ?></td>
              <td><?= (int) $e['movimientos'] ?></td>
              <td><?= (int) $e['irregularidades'] ?></td>
              <td class="acciones-fila">
                <a class="table-link" href="evento_resumen.php?evento=<?= (int) $e['id'] ?>">Ver resumen</a>
                <?php if ($e['estado'] !== 'activo'): ?>
                  <form method="post" onsubmit="return confirm('¿Borrar el evento <?= h(addslashes($e['nombre'])) ?>?\n\nSe borra su historial: entradas, salidas, avisos, turnos e invitaciones. No se puede deshacer; si lo necesitas, descarga antes el Excel desde Ver resumen.\n\nLos asistentes NO se pierden: siguen disponibles para invitarlos a próximos eventos con su mismo QR.');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                    <button type="submit" class="boton-enlace peligro">Borrar</button>
                  </form>
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
