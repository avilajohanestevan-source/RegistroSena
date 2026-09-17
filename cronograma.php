<?php
/**
 * Cronograma del evento (solo administrador). Las actividades de cada día
 * con su horario. En un evento de varios días se elige el modo:
 *   - Mismo horario para todos los días: se arma una vez (día 1) y se
 *     aplica a todos con "Aplicar a todos los días".
 *   - Horarios por día: una pestaña por día, cada una con sus actividades.
 * Valida que cada actividad empiece antes de terminar y advierte cuando
 * dos actividades del mismo día se cruzan (se puede guardar igual).
 * Vista de tabla (edición) o de calendario (todos los días lado a lado).
 */
require_once __DIR__ . '/includes/panel_admin.php';

$eventoFila = fijarEventoConsulta($conn, $_GET['evento'] ?? null);
if (!$eventoFila) {
    header('Location: evento.php');
    exit;
}
$editable = $eventoFila['estado'] === 'activo';
$dias = diasDelEvento($eventoFila);
$multiDia = count($dias) > 1;
$modo = modoCronograma($eventoFila);
$consultaEvento = $editable ? '' : 'evento=' . (int) $eventoFila['id'] . '&';

$diaSel = (int) ($_POST['dia'] ?? $_GET['dia'] ?? 1);
if (!isset($dias[$diaSel]) || ($multiDia && $modo === 'mismo')) {
    $diaSel = 1;
}
$vista = (($_GET['vista'] ?? '') === 'calendario' || !$editable) ? 'calendario' : 'tabla';

$itemsForm = null;      // filas enviadas que se vuelven a mostrar (error o cruce)
$erroresForm = [];
$crucesForm = [];
$pendienteForzar = false;
$aplicarPedido = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editable) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'modo') {
        fijarModoCronograma($conn, $eventoFila['id'], $_POST['cronograma_modo'] ?? 'mismo');
        header('Location: cronograma.php?aviso=modo');
        exit;
    }

    if ($accion === 'guardar') {
        $boton = (string) ($_POST['modo_guardar'] ?? 'guardar');
        $forzar = strpos($boton, 'forzar') === 0;
        $aplicarPedido = substr($boton, -7) === 'aplicar';
        [$itemsForm, $erroresForm] = leerItemsCronograma((array) ($_POST['items'] ?? []));
        $crucesForm = $erroresForm ? [] : solapamientosCronograma($itemsForm);

        if (!$erroresForm && (!$crucesForm || $forzar)) {
            guardarDiaCronograma($conn, $eventoFila, $diaSel, $itemsForm);
            $aplicados = 0;
            if ($aplicarPedido) {
                $aplicados = aplicarCronogramaADias($conn, $eventoFila, $diaSel, (array) ($_POST['dias_destino'] ?? []));
            }
            header('Location: cronograma.php?' . http_build_query([
                'dia'       => $diaSel,
                'aviso'     => 'guardado',
                'aplicados' => $aplicados,
                'cruces'    => count($crucesForm),
            ]));
            exit;
        }
        $pendienteForzar = !$erroresForm && $crucesForm;
    }
}

$cronograma = cronogramaDelEvento($conn, $eventoFila['id']);
$items = $itemsForm ?? ($cronograma[$diaSel] ?? []);
$crucesGuardados = $itemsForm === null ? solapamientosCronograma($items) : $crucesForm;
if (!$items) {
    $items = [['titulo' => '', 'descripcion' => '', 'hora_inicio' => '', 'hora_fin' => '', 'ubicacion' => '', 'responsable' => '']];
}
$distintos = $multiDia ? diasDistintosDelPrimero($cronograma, $dias) : [];

$aviso = null;
switch ($_GET['aviso'] ?? '') {
    case 'evento_creado':
        $aviso = ['success', 'Evento creado. Arma aquí su cronograma: se envía junto con el QR y las invitaciones. Cuando termines, puedes invitar a los asistentes de eventos anteriores.'];
        break;
    case 'modo':
        $aviso = ['success', $modo === 'mismo'
            ? 'Modo: mismo horario para todos los días. Define las actividades una vez y aplícalas a todos los días.'
            : 'Modo: horarios por día. Elige cada día en las pestañas y arma su cronograma.'];
        break;
    case 'guardado':
        $aplicados = (int) ($_GET['aplicados'] ?? 0);
        $texto = 'Cronograma guardado.';
        if ($aplicados) {
            $texto .= ' Se aplicó el mismo horario a ' . $aplicados . ($aplicados === 1 ? ' día más.' : ' días más.');
        }
        if ((int) ($_GET['cruces'] ?? 0) > 0) {
            $texto .= ' Quedó guardado con actividades que se cruzan en horario.';
        }
        $aviso = [(int) ($_GET['cruces'] ?? 0) > 0 ? 'warning' : 'success', $texto];
        break;
}

$activeTab = 'evento';
$wide = true;
require __DIR__ . '/includes/layout_top.php';

$vacio = ['titulo' => '', 'descripcion' => '', 'hora_inicio' => '', 'hora_fin' => '', 'ubicacion' => '', 'responsable' => ''];
/** Una fila editable del cronograma. */
$filaEditor = function ($n, array $item, array $errores = [], array $cruzaCon = [], array $todos = []) {
    $clase = 'crono-fila' . ($errores ? ' crono-fila--error' : '') . ($cruzaCon ? ' crono-fila--solapa' : '');
    ob_start(); ?>
    <div class="<?= $clase ?>" data-crono-fila>
      <div class="crono-campo crono-campo--hora">
        <label>Inicio</label>
        <input type="time" name="items[<?= $n ?>][hora_inicio]" value="<?= h($item['hora_inicio']) ?>" data-campo="inicio">
      </div>
      <div class="crono-campo crono-campo--hora">
        <label>Fin</label>
        <input type="time" name="items[<?= $n ?>][hora_fin]" value="<?= h($item['hora_fin']) ?>" data-campo="fin">
      </div>
      <div class="crono-campo crono-campo--titulo">
        <label>Título</label>
        <input type="text" name="items[<?= $n ?>][titulo]" value="<?= h($item['titulo']) ?>" maxlength="150" placeholder="Ej. Charla de apertura" data-campo="titulo">
      </div>
      <div class="crono-campo crono-campo--desc">
        <label>Descripción corta <span class="opt">(opcional)</span></label>
        <input type="text" name="items[<?= $n ?>][descripcion]" value="<?= h($item['descripcion']) ?>" maxlength="255">
      </div>
      <div class="crono-campo">
        <label>Ubicación <span class="opt">(opcional)</span></label>
        <input type="text" name="items[<?= $n ?>][ubicacion]" value="<?= h($item['ubicacion']) ?>" maxlength="150" placeholder="Ej. Auditorio">
      </div>
      <div class="crono-campo">
        <label>Responsable <span class="opt">(opcional)</span></label>
        <input type="text" name="items[<?= $n ?>][responsable]" value="<?= h($item['responsable']) ?>" maxlength="150">
      </div>
      <button type="button" class="crono-quitar" data-crono-quitar aria-label="Quitar actividad" title="Quitar actividad">×</button>
      <div class="crono-mensajes" data-crono-mensajes>
        <?php foreach ($errores as $mensaje): ?><span class="field-error"><?= h($mensaje) ?></span><?php endforeach; ?>
        <?php if ($cruzaCon): ?>
          <span class="crono-cruce">Se cruza con: <?= h(implode(', ', array_map(function ($i) use ($todos) { return ($todos[$i]['titulo'] ?: 'otra actividad') . ' (' . textoHoraActividad($todos[$i]) . ')'; }, $cruzaCon))) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php return ob_get_clean();
};
?>

<?php if ($aviso): ?>
  <div class="banner <?= $aviso[0] ?>"><?= h($aviso[1]) ?></div>
<?php endif; ?>

<div class="card">
  <div class="historial-cabecera">
    <div>
      <h2 class="section-title">Cronograma · <?= h($eventoFila['nombre']) ?></h2>
      <p class="section-sub" style="margin-bottom:0;">
        <?= $multiDia ? count($dias) . ' días' : ($dias[1]['fecha'] !== '' ? h(fmtDia($dias[1]['fecha'])) : 'Evento de un día (sin fecha definida)') ?>
        · <?= totalActividades($cronograma) ?> actividades
        <?php if (!$editable): ?> · <span class="status-chip neutro">Archivado · solo lectura</span><?php endif; ?>
      </p>
    </div>
    <div class="crono-cabecera-acciones">
      <?php if ($editable): ?>
        <div class="crono-vistas" role="group" aria-label="Vista">
          <a href="cronograma.php<?= $diaSel > 1 ? '?dia=' . $diaSel : '' ?>" class="<?= $vista === 'tabla' ? 'activa' : '' ?>">Tabla</a>
          <a href="cronograma.php?vista=calendario" class="<?= $vista === 'calendario' ? 'activa' : '' ?>">Calendario</a>
        </div>
        <a class="btn btn-outline btn-sm" href="cronograma_ver.php" target="_blank" rel="noopener">Ver como asistente</a>
        <a class="btn btn-outline btn-sm" href="invitaciones.php">Invitar asistentes</a>
      <?php else: ?>
        <a class="btn btn-outline btn-sm" href="evento_resumen.php?evento=<?= (int) $eventoFila['id'] ?>">← Resumen del evento</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($multiDia && $editable): ?>
    <form method="post" class="crono-modos" data-autoenvio>
      <input type="hidden" name="accion" value="modo">
      <label class="crono-modo<?= $modo === 'mismo' ? ' elegido' : '' ?>">
        <input type="radio" name="cronograma_modo" value="mismo"<?= $modo === 'mismo' ? ' checked' : '' ?>>
        <strong>Mismo horario para todos los días</strong>
        <span>Defines las actividades una vez y las aplicas a los <?= count($dias) ?> días.</span>
      </label>
      <label class="crono-modo<?= $modo === 'por_dia' ? ' elegido' : '' ?>">
        <input type="radio" name="cronograma_modo" value="por_dia"<?= $modo === 'por_dia' ? ' checked' : '' ?>>
        <strong>Horarios por día</strong>
        <span>Cada día tiene sus propias actividades y horarios.</span>
      </label>
      <noscript><button type="submit" class="btn btn-outline btn-sm">Cambiar modo</button></noscript>
    </form>
  <?php endif; ?>
</div>

<?php if ($vista === 'calendario'): ?>
  <div class="card">
    <h2 class="section-title">Calendario</h2>
    <p class="section-sub">Todos los días del evento, lado a lado. Las actividades marcadas se cruzan en horario con otra del mismo día.</p>
    <div class="crono-calendario" style="--dias: <?= min(count($dias), 7) ?>;">
      <?php foreach ($dias as $d): $itemsDia = $cronograma[$d['dia']] ?? []; $crucesDia = solapamientosCronograma($itemsDia); ?>
        <section class="crono-columna">
          <header>
            <strong><?= h($multiDia ? $d['etiqueta'] : ($d['fecha'] !== '' ? fmtDia($d['fecha']) : 'Día del evento')) ?></strong>
            <span><?= count($itemsDia) ?> actividades</span>
          </header>
          <?php if (!$itemsDia): ?>
            <p class="crono-sin">Sin actividades</p>
          <?php endif; ?>
          <?php foreach ($itemsDia as $i => $item): ?>
            <article class="crono-bloque<?= isset($crucesDia[$i]) ? ' crono-bloque--solapa' : '' ?>">
              <span class="crono-bloque-hora"><?= h(textoHoraActividad($item)) ?></span>
              <strong><?= h($item['titulo']) ?></strong>
              <?php if ($item['ubicacion'] !== ''): ?><span><?= h($item['ubicacion']) ?></span><?php endif; ?>
              <?php if ($item['responsable'] !== ''): ?><span><?= h($item['responsable']) ?></span><?php endif; ?>
              <?php if (isset($crucesDia[$i])): ?><span class="crono-cruce">Se cruza en horario</span><?php endif; ?>
            </article>
          <?php endforeach; ?>
          <?php if ($editable && (!$multiDia || $modo === 'por_dia' || $d['dia'] === 1)): ?>
            <a class="table-link crono-editar" href="cronograma.php?dia=<?= $d['dia'] ?>">Editar este día</a>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  </div>

<?php else: ?>
  <div class="card">
    <?php if ($multiDia && $modo === 'por_dia'): ?>
      <nav class="crono-tabs" aria-label="Días del evento">
        <?php foreach ($dias as $d): $n = count($cronograma[$d['dia']] ?? []); ?>
          <a href="cronograma.php?dia=<?= $d['dia'] ?>" class="<?= $d['dia'] === $diaSel ? 'activa' : '' ?>"<?= $d['dia'] === $diaSel ? ' aria-current="page"' : '' ?>>
            <?= h($d['etiqueta']) ?> <span class="crono-conteo"><?= $n ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <h2 class="section-title">
      <?php if ($multiDia && $modo === 'mismo'): ?>
        Actividades de cada día
      <?php elseif ($multiDia): ?>
        Actividades del <?= h($dias[$diaSel]['etiqueta']) ?>
      <?php else: ?>
        Actividades del evento
      <?php endif; ?>
    </h2>
    <?php if ($multiDia && $modo === 'mismo'): ?>
      <p class="section-sub">Se definen una sola vez y se copian con las mismas horas a los días que elijas.</p>
      <?php if ($distintos && !empty($cronograma[1])): ?>
        <div class="banner warning">
          <?= count($distintos) === 1 ? 'El ' : 'Los ' ?><?= h(implode(', ', array_map(function ($n) use ($dias) { return $dias[$n]['etiqueta']; }, $distintos))) ?>
          todavía no <?= count($distintos) === 1 ? 'tiene' : 'tienen' ?> este horario. Usa <strong>Guardar y aplicar a todos los días</strong>.
        </div>
      <?php elseif (!empty($cronograma[1])): ?>
        <div class="banner success">Los <?= count($dias) ?> días tienen este mismo horario.</div>
      <?php endif; ?>
    <?php else: ?>
      <p class="section-sub">Cada actividad necesita título, hora de inicio y hora de fin. Descripción, ubicación y responsable son opcionales.</p>
    <?php endif; ?>

    <?php if ($pendienteForzar): ?>
      <div class="banner warning crono-aviso-forzar">
        <strong>Hay <?= count($crucesForm) ?> actividades que se cruzan en horario.</strong>
        Revisa las marcadas en naranja. Si el cruce es intencional (por ejemplo, talleres en salones distintos), puedes guardar de todas formas.
      </div>
    <?php elseif ($erroresForm): ?>
      <div class="banner error">No se guardó: revisa las actividades marcadas en rojo.</div>
    <?php elseif ($crucesGuardados && $itemsForm === null): ?>
      <div class="banner warning">Este día tiene actividades que se cruzan en horario (marcadas en naranja).</div>
    <?php endif; ?>

    <form method="post" data-crono-editor novalidate>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="dia" value="<?= $diaSel ?>">

      <div class="banner warning" data-crono-aviso hidden></div>

      <div class="crono-filas" data-crono-filas>
        <?php foreach (array_values($items) as $n => $item): ?>
          <?= $filaEditor($n, $item + $vacio, $erroresForm[$n] ?? [], $crucesGuardados[$n] ?? [], array_values($items)) ?>
        <?php endforeach; ?>
      </div>
      <template data-crono-plantilla><?= $filaEditor('__N__', $vacio) ?></template>

      <button type="button" class="btn btn-outline btn-sm crono-agregar" data-crono-agregar>+ Añadir actividad</button>

      <?php if ($multiDia): $otros = array_filter($dias, function ($d) use ($diaSel) { return $d['dia'] !== $diaSel; }); ?>
        <details class="crono-aplicar"<?= $modo === 'mismo' || $aplicarPedido ? ' open' : '' ?>>
          <summary><?= $modo === 'mismo' ? 'Días a los que se aplica' : 'Aplicar este mismo horario a otros días' ?></summary>
          <p class="field-hint">Copia estas actividades, con las mismas horas, a los días marcados. Lo que esos días tuvieran antes se reemplaza.</p>
          <label class="check-linea"><input type="checkbox" data-check-todos="dias_destino[]"<?= $modo === 'mismo' ? ' checked' : '' ?>> Seleccionar todos</label>
          <div class="crono-dias-destino">
            <?php foreach ($otros as $d): ?>
              <label class="check-linea">
                <input type="checkbox" name="dias_destino[]" value="<?= $d['dia'] ?>"<?= $modo === 'mismo' || in_array((string) $d['dia'], (array) ($_POST['dias_destino'] ?? []), true) ? ' checked' : '' ?>>
                <?= h($d['etiqueta']) ?> <span class="text-muted">(<?= count($cronograma[$d['dia']] ?? []) ?> actividades)</span>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>

      <div class="form-actions">
        <?php if ($pendienteForzar): ?>
          <button type="submit" name="modo_guardar" value="<?= $aplicarPedido ? 'forzar_aplicar' : 'forzar' ?>" class="btn btn-primary">Guardar de todas formas</button>
          <span class="text-muted">o corrige las horas y vuelve a guardar:</span>
        <?php endif; ?>
        <?php if ($multiDia && $modo === 'mismo'): ?>
          <button type="submit" name="modo_guardar" value="aplicar" class="btn <?= $pendienteForzar ? 'btn-outline' : 'btn-primary' ?>">Guardar y aplicar a todos los días</button>
          <button type="submit" name="modo_guardar" value="guardar" class="btn btn-outline">Solo guardar</button>
        <?php else: ?>
          <button type="submit" name="modo_guardar" value="guardar" class="btn <?= $pendienteForzar ? 'btn-outline' : 'btn-primary' ?>">Guardar cronograma<?= $multiDia ? ' del día' : '' ?></button>
          <?php if ($multiDia): ?>
            <button type="submit" name="modo_guardar" value="aplicar" class="btn btn-outline">Guardar y aplicar a los días marcados</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
