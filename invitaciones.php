<?php
/**
 * Invitaciones (solo administrador): invitar al evento activo a los
 * asistentes de eventos anteriores. Se eligen de la lista (todos o
 * manual, con búsqueda), se les envía un correo con su enlace único y,
 * cuando confirman, quedan inscritos con su misma cédula — así su código
 * QR de siempre les sirve para entrar y salir.
 */
require_once __DIR__ . '/includes/panel_admin.php';
require_once __DIR__ . '/includes/invitaciones.php';
require_once __DIR__ . '/includes/mailer.php';

$nombreEvento = nombreEvento($conn);
$horario = horarioEvento($conn);
$textoDelHorario = horarioConfigurado($horario) ? textoHorario($horario) : '';
$diasEvento = $eventoActual ? diasDelEvento($eventoActual) : [];
$cronogramaEvento = $eventoActual ? cronogramaDelEvento($conn, $eventoActual['id']) : [];
$diasSinHorarioDelPrimero = count($diasEvento) > 1 ? diasDistintosDelPrimero($cronogramaEvento, $diasEvento) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eventoActual) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'invitar') {
        $elegidas = array_map('soloDigitos', (array) ($_POST['cedulas'] ?? []));
        $personas = array_filter(personasInvitables($conn, $eventoActual['id']), function ($p) use ($elegidas) {
            return in_array($p['cedula'], $elegidas, true);
        });
        $nuevas = [];
        foreach ($personas as $persona) {
            $nuevas[] = crearInvitacion($conn, $eventoActual['id'], $persona, $usuario['id']);
        }
        // Opciones del modal: aplicar el horario del día 1 a todos los días e incluir el cronograma.
        if (!empty($_POST['aplicar_cronograma_todos']) && count($diasEvento) > 1 && !empty($cronogramaEvento[1])) {
            aplicarCronogramaADias($conn, $eventoActual, 1, array_keys($diasEvento));
        }
        $cronogramaCorreo = !empty($_POST['incluir_cronograma']) ? cronogramaParaCorreo($conn, eventoPorId($conn, $eventoActual['id'])) : null;
        $enviadas = 0;
        if ($nuevas) {
            $promo = promocionParaCorreo(eventoPorId($conn, $eventoActual['id']), !empty($_POST['incluir_imagen']));
            $resultados = enviarInvitaciones($nuevas, $nombreEvento, $textoDelHorario, $cronogramaCorreo, $promo);
            foreach ($nuevas as $invitacion) {
                [$ok] = $resultados[$invitacion['id']];
                if ($ok) {
                    marcarInvitacionEnviada($conn, $invitacion['id']);
                    $enviadas++;
                }
            }
        }
        header('Location: invitaciones.php?creadas=' . count($nuevas) . '&enviadas=' . $enviadas);
        exit;
    }

    if ($accion === 'invitar_correo') {
        [$lista, $invalidas] = leerListaInvitados($_POST['lista'] ?? '');
        $nuevas = [];
        $yaInscritos = 0;
        foreach ($lista as [$nombre, $correo]) {
            // Quien ya está registrado en el evento con ese correo no necesita invitación.
            $stmt = $conn->prepare("SELECT 1 FROM asistentes WHERE evento_id = ? AND correo = ?");
            $eventoId = (int) $eventoActual['id'];
            $stmt->bind_param('is', $eventoId, $correo);
            $stmt->execute();
            $inscrito = (bool) $stmt->get_result()->fetch_row();
            $stmt->close();
            if ($inscrito) {
                $yaInscritos++;
                continue;
            }
            $invitacion = crearInvitacionCorreo($conn, $eventoActual['id'], $nombre, $correo, $usuario['id']);
            if ((int) $invitacion['inscrito'] === 0) {
                $nuevas[] = $invitacion;
            }
        }
        $enviadas = 0;
        if ($nuevas) {
            $eventoFila = eventoPorId($conn, $eventoActual['id']);
            $cronogramaCorreo = !empty($_POST['incluir_cronograma']) ? cronogramaParaCorreo($conn, $eventoFila) : null;
            $resultados = enviarInvitaciones($nuevas, $nombreEvento, $textoDelHorario, $cronogramaCorreo, promocionParaCorreo($eventoFila, !empty($_POST['incluir_imagen'])));
            foreach ($nuevas as $invitacion) {
                if ($resultados[$invitacion['id']][0]) {
                    marcarInvitacionEnviada($conn, $invitacion['id']);
                    $enviadas++;
                }
            }
        }
        header('Location: invitaciones.php?' . http_build_query([
            'por_correo' => count($nuevas), 'enviadas' => $enviadas, 'invalidas' => count($invalidas), 'ya_inscritos' => $yaInscritos,
        ]));
        exit;
    }

    if ($accion === 'reenviar') {
        $invitacion = invitacionPorId($conn, (int) ($_POST['id'] ?? 0));
        $enviadas = 0;
        if ($invitacion && (int) $invitacion['evento_id'] === (int) $eventoActual['id']) {
            // Al reenviar va el cronograma actual del evento, si tiene.
            $resultados = enviarInvitaciones([$invitacion], $nombreEvento, $textoDelHorario, cronogramaParaCorreo($conn, $eventoActual), promocionParaCorreo($eventoActual));
            [$ok] = $resultados[$invitacion['id']];
            if ($ok) {
                marcarInvitacionEnviada($conn, $invitacion['id']);
                $enviadas = 1;
            }
        }
        header('Location: invitaciones.php?reenviadas=' . $enviadas);
        exit;
    }

    if ($accion === 'excluir') {
        // Borra personas de la lista (una con "Quitar" o varias seleccionadas).
        $cedulas = isset($_POST['cedula']) ? [soloDigitos($_POST['cedula'])] : array_map('soloDigitos', (array) ($_POST['cedulas'] ?? []));
        $cedulas = array_values(array_filter(array_unique($cedulas)));
        foreach ($cedulas as $cedula) {
            excluirPersona($conn, $cedula, $eventoActual['id'], $usuario['id']);
        }
        header('Location: invitaciones.php?quitadas=' . count($cedulas));
        exit;
    }

    if ($accion === 'restaurar') {
        restaurarPersona($conn, soloDigitos($_POST['cedula'] ?? ''));
        header('Location: invitaciones.php?restaurada=1');
        exit;
    }

    if ($accion === 'eliminar_invitacion') {
        $borrada = eliminarInvitacion($conn, $_POST['id'] ?? 0, $eventoActual['id']);
        header('Location: invitaciones.php?invitacion_borrada=' . ($borrada ? '1' : '0'));
        exit;
    }

    if ($accion === 'inscribir') {
        // Añade al evento, en lote, a todos los que ya confirmaron.
        $inscritos = 0;
        foreach (invitacionesDelEvento($conn, $eventoActual['id']) as $invitacion) {
            if ($invitacion['estado'] === 'confirmado' && (int) $invitacion['inscrito'] === 0 && (string) $invitacion['cedula'] !== '') {
                inscribirInvitacion($conn, $invitacion);
                $inscritos++;
            }
        }
        header('Location: invitaciones.php?inscritos=' . $inscritos);
        exit;
    }
}

// Mensajes después de cada acción.
$aviso = null;
if (($_GET['aviso'] ?? '') === 'evento_creado') {
    $aviso = ['success', 'Evento creado. Si quieres, invita aquí a los asistentes de eventos anteriores: les llega un correo y, al confirmar, quedan inscritos con su mismo código QR.'];
} elseif (isset($_GET['creadas'])) {
    $creadas = (int) $_GET['creadas'];
    $enviadas = (int) ($_GET['enviadas'] ?? 0);
    if (!$creadas) {
        $aviso = ['warning', 'No se seleccionó a nadie para invitar.'];
    } else {
        $aviso = [$enviadas === $creadas ? 'success' : 'warning',
            $creadas . ($creadas === 1 ? ' invitación creada' : ' invitaciones creadas') . ' · ' .
            ($enviadas === $creadas
                ? 'el correo se envió a todos.'
                : $enviadas . ' correo(s) enviados; a los demás puedes reenviarles desde la lista.')];
    }
} elseif (isset($_GET['por_correo'])) {
    $n = (int) $_GET['por_correo'];
    $enviadas = (int) ($_GET['enviadas'] ?? 0);
    $partes = [$n . ($n === 1 ? ' invitación por correo' : ' invitaciones por correo') . ' · ' . $enviadas . ($enviadas === 1 ? ' correo enviado' : ' correos enviados') . '.'];
    if ((int) ($_GET['invalidas'] ?? 0)) {
        $partes[] = (int) $_GET['invalidas'] . ' líneas no tenían un correo válido.';
    }
    if ((int) ($_GET['ya_inscritos'] ?? 0)) {
        $partes[] = (int) $_GET['ya_inscritos'] . ' ya estaban registrados en el evento.';
    }
    $aviso = [$n > 0 && $enviadas === $n ? 'success' : 'warning', implode(' ', $partes)];
} elseif (isset($_GET['reenviadas'])) {
    $aviso = (int) $_GET['reenviadas'] === 1
        ? ['success', 'Invitación reenviada por correo.']
        : ['warning', 'No se pudo reenviar el correo de la invitación.'];
} elseif (isset($_GET['quitadas'])) {
    $quitadas = (int) $_GET['quitadas'];
    $aviso = $quitadas
        ? ['success', $quitadas . ($quitadas === 1 ? ' persona borrada' : ' personas borradas') . ' de la lista. Sus datos en los eventos archivados se conservan y, si se vuelven a registrar, reciben su mismo código QR.']
        : ['warning', 'No se seleccionó a nadie para borrar.'];
} elseif (isset($_GET['restaurada'])) {
    $aviso = ['success', 'La persona volvió a la lista de asistentes anteriores.'];
} elseif (isset($_GET['invitacion_borrada'])) {
    $aviso = $_GET['invitacion_borrada'] === '1'
        ? ['success', 'Invitación eliminada. La persona vuelve a aparecer en la lista para invitarla de nuevo si quieres.']
        : ['warning', 'No se pudo eliminar: la invitación ya no existe o la persona ya quedó inscrita.'];
} elseif (isset($_GET['inscritos'])) {
    $inscritos = (int) $_GET['inscritos'];
    $aviso = $inscritos
        ? ['success', $inscritos . ($inscritos === 1 ? ' persona quedó inscrita' : ' personas quedaron inscritas') . ' en el evento con su mismo código QR.']
        : ['info', 'No había confirmados pendientes de inscribir.'];
}

$personas = $eventoActual ? personasInvitables($conn, $eventoActual['id']) : [];
// Los que ya tienen invitación aparecen abajo, en la lista de invitaciones.
$personas = array_values(array_filter($personas, function ($p) { return (int) $p['invitada'] === 0; }));
$invitaciones = $eventoActual ? invitacionesDelEvento($conn, $eventoActual['id']) : [];
$excluidas = personasExcluidas($conn);
$resumen = resumenInvitaciones($invitaciones);

$activeTab = 'evento';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($aviso): ?>
  <div class="banner <?= $aviso[0] ?>"><?= h($aviso[1]) ?></div>
<?php endif; ?>

<?php if (!$eventoActual): ?>
  <div class="card card--marca">
    <h2 class="section-title">No hay ningún evento activo</h2>
    <p class="section-sub">Las invitaciones se hacen sobre un evento abierto. Crea el evento y vuelve aquí para invitar a los asistentes de eventos anteriores.</p>
    <div class="form-actions"><a class="btn btn-primary" href="evento.php">Crear un evento</a></div>
  </div>
<?php else: ?>

<div class="card">
  <div class="historial-cabecera">
    <div>
      <h2 class="section-title">Invitar asistentes anteriores</h2>
      <p class="section-sub" style="margin-bottom:0;">
        A cada persona le llega un correo con su enlace para confirmar. Al confirmar queda inscrita en
        <strong><?= h($nombreEvento) ?></strong> con su misma cédula, así que <strong>su código QR de siempre le sirve</strong>.
      </p>
    </div>
    <?php if ($resumen['por_inscribir'] > 0): ?>
      <form method="post">
        <input type="hidden" name="accion" value="inscribir">
        <button type="submit" class="btn btn-primary">Añadir confirmados al evento (<?= $resumen['por_inscribir'] ?>)</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="stat-grid stat-grid--4" style="margin-bottom:20px;">
    <div class="stat-card azul-oscuro"><div class="label">Invitaciones</div><div class="value"><?= $resumen['total'] ?></div></div>
    <div class="stat-card in"><div class="label">Confirmados</div><div class="value"><?= $resumen['confirmado'] ?></div></div>
    <div class="stat-card out"><div class="label">Pendientes</div><div class="value"><?= $resumen['pendiente'] ?></div></div>
    <div class="stat-card gris"><div class="label">No asistirán</div><div class="value"><?= $resumen['rechazado'] ?></div></div>
  </div>
  <p class="invitaciones-origen">
    <strong>Por correo:</strong> <?= $resumen['email_link'] ?> invitaciones · <?= $resumen['abiertas'] ?> abrieron su enlace · <?= $resumen['por_correo_inscritos'] ?> se registraron
    &nbsp;·&nbsp; <strong>Por enlace público:</strong> <?= $resumen['public_link'] ?> registrados
    &nbsp;·&nbsp; <button type="button" class="boton-enlace" onclick="copiarEnlace('enlacePublicoLista', this.id)" id="copiarPublicoLista">Copiar enlace público</button>
    <input type="text" id="enlacePublicoLista" value="<?= h(urlEnlacePublico()) ?>" readonly class="sr-only" tabindex="-1" aria-hidden="true">
  </p>

  <div class="crono-estado">
    <?php if ($cronogramaEvento): ?>
      <span><strong>Cronograma:</strong> <?= totalActividades($cronogramaEvento) ?> actividades<?= count($diasEvento) > 1 ? ' en ' . count($cronogramaEvento) . ' de ' . count($diasEvento) . ' días' : '' ?>. Puedes incluirlo en el correo.</span>
    <?php else: ?>
      <span><strong>Cronograma:</strong> este evento todavía no tiene actividades.</span>
    <?php endif; ?>
    <a class="table-link" href="cronograma.php"><?= $cronogramaEvento ? 'Editar cronograma' : 'Armar cronograma' ?></a>
  </div>

  <?php if (!EMAIL_HABILITADO): ?>
    <div class="banner warning">El envío de correo no está configurado en config.php: las invitaciones se crean igual, pero tendrás que compartir el enlace a mano desde la lista de abajo.</div>
  <?php endif; ?>

  <?php if (!$personas): ?>
    <div class="empty-state">No hay asistentes de eventos anteriores por invitar: o ya están invitados, o ya están inscritos en este evento, o se borraron de la lista.</div>
  <?php else: ?>
    <!-- "Borrar" de cada fila: formulario aparte, porque la fila está dentro del de invitar. -->
    <form method="post" id="formQuitar">
      <input type="hidden" name="accion" value="excluir">
    </form>
    <form method="post" data-enviando="Procesando…">
      <div class="barra-lista">
        <input type="search" id="buscarPersona" data-filtro="#listaPersonas" data-conteo-filtro="conteoLista" placeholder="Buscar por nombre, cédula, correo o empresa">
        <label class="check-linea">
          <input type="checkbox" data-check-todos="cedulas[]" data-conteo="conteoSeleccion"> Seleccionar todos
        </label>
        <span class="text-muted" id="conteoLista"><?= count($personas) ?> personas</span>
      </div>
      <div class="lista-invitar" id="listaPersonas">
        <?php foreach ($personas as $p): ?>
          <label class="fila-persona" data-buscar="<?= h(mb_strtolower($p['nombre'] . ' ' . $p['cedula'] . ' ' . $p['correo'] . ' ' . $p['empresa'])) ?>">
            <input type="checkbox" name="cedulas[]" value="<?= h($p['cedula']) ?>"<?= $p['correo'] === '' ? ' disabled' : '' ?>>
            <span class="datos">
              <strong><?= h($p['nombre']) ?></strong>
              <span><?= h(tipoAsistente($p['tipo'], $p['tipo_otro'])) ?> · C.C. <?= h($p['cedula']) ?> · <?= h($p['correo'] !== '' ? $p['correo'] : 'sin correo registrado') ?></span>
              <span>Estuvo en: <?= h($p['evento_nombre']) ?><?= $p['evento_borrado'] ? ' (evento borrado)' : '' ?></span>
            </span>
            <button type="submit" form="formQuitar" name="cedula" value="<?= h($p['cedula']) ?>" class="boton-enlace peligro"
                    onclick="return confirm('¿Borrar a <?= h(addslashes($p['nombre'])) ?> de la lista? Sus datos en los eventos archivados se conservan.');">Borrar</button>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-primary" data-abrir-dialogo="modalInvitar">Invitar a los seleccionados</button>
        <button type="submit" name="accion" value="excluir" class="btn btn-outline btn-peligro"
                onclick="return confirm('¿Borrar de la lista a las personas seleccionadas? Sus datos en los eventos archivados se conservan.');">Borrar seleccionados</button>
        <span class="text-muted" id="conteoSeleccion"></span>
      </div>

      <dialog id="modalInvitar" class="alerta-modal modal-invitar" closedby="any" aria-labelledby="tituloInvitar">
        <h2 class="alerta-titulo" id="tituloInvitar">Enviar invitaciones</h2>
        <p class="alerta-mensaje" data-resumen-invitar data-conteo-de="cedulas[]">
          Se enviará un correo con su enlace para confirmar a las personas seleccionadas.
        </p>
        <div class="modal-opciones">
          <label class="check-linea">
            <input type="checkbox" name="incluir_imagen" value="1"<?= srcImagenEvento($eventoActual) ? ' checked' : ' disabled' ?>>
            <span>
              <strong>Incluir imagen promocional</strong>
              <small><?= srcImagenEvento($eventoActual)
                  ? 'La misma imagen del evento, como tarjeta destacada del correo.'
                  : 'El evento no tiene imagen: el correo lleva una tarjeta con el logo del SENA.' ?></small>
            </span>
          </label>
          <label class="check-linea">
            <input type="checkbox" name="incluir_cronograma" value="1"<?= $cronogramaEvento ? ' checked' : ' disabled' ?>>
            <span>
              <strong>Incluir cronograma</strong>
              <small><?= $cronogramaEvento
                  ? 'El correo lleva las actividades del evento y el enlace para ver el cronograma completo.'
                  : 'Este evento todavía no tiene cronograma.' ?></small>
            </span>
          </label>
          <?php if (count($diasEvento) > 1): ?>
            <label class="check-linea">
              <input type="checkbox" name="aplicar_cronograma_todos" value="1"<?= empty($cronogramaEvento[1]) ? ' disabled' : '' ?><?= $diasSinHorarioDelPrimero && !empty($cronogramaEvento[1]) && modoCronograma($eventoActual) === 'mismo' ? ' checked' : '' ?>>
              <span>
                <strong>Aplicar cronograma a todos los días</strong>
                <small><?php if (empty($cronogramaEvento[1])): ?>
                  El primer día no tiene actividades para copiar.
                <?php elseif ($diasSinHorarioDelPrimero): ?>
                  Copia las actividades del <?= h($diasEvento[1]['etiqueta']) ?> a los <?= count($diasEvento) ?> días antes de enviar (<?= count($diasSinHorarioDelPrimero) ?> <?= count($diasSinHorarioDelPrimero) === 1 ? 'día tiene' : 'días tienen' ?> otro horario).
                <?php else: ?>
                  Todos los días ya tienen el mismo horario.
                <?php endif; ?></small>
              </span>
            </label>
          <?php endif; ?>
        </div>
        <div class="alerta-acciones">
          <button type="button" class="btn btn-outline" data-cerrar-dialogo>Cancelar</button>
          <button type="submit" name="accion" value="invitar" class="btn btn-primary" data-requiere-seleccion="cedulas[]">Enviar invitaciones</button>
        </div>
      </dialog>
    </form>
  <?php endif; ?>

  <?php if ($excluidas): ?>
    <details class="plegable" style="margin-top:18px;">
      <summary>Personas borradas de la lista (<?= count($excluidas) ?>)</summary>
      <p class="field-hint">No aparecen para invitar. Sus datos en los eventos archivados siguen guardados y su código QR también: si se registran otra vez, vuelven solas a la lista y reciben el mismo QR.</p>
      <div class="lista-invitar">
        <?php foreach ($excluidas as $x): ?>
          <div class="fila-persona">
            <span class="datos">
              <strong><?= h($x['nombre']) ?></strong>
              <span>C.C. <?= h($x['cedula']) ?> · <?= h($x['correo'] !== '' ? $x['correo'] : 'sin correo') ?> · Estuvo en: <?= h($x['evento_nombre']) ?><?= $x['evento_borrado'] ? ' (evento borrado)' : '' ?></span>
              <span>Borrada <?= h(fmtFecha($x['excluido_en'])) ?></span>
            </span>
            <form method="post">
              <input type="hidden" name="accion" value="restaurar">
              <input type="hidden" name="cedula" value="<?= h($x['cedula']) ?>">
              <button type="submit" class="boton-enlace">Restaurar</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title">Invitaciones enviadas (<?= $resumen['total'] ?>)</h2>
  <p class="section-sub">Quién confirmó, quién no ha respondido y quién dijo que no podrá asistir. Los confirmados quedan inscritos en el evento automáticamente.</p>
  <?php if (!$invitaciones): ?>
    <div class="empty-state">Todavía no se ha invitado a nadie a este evento.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Persona</th><th>Correo</th><th>Origen</th><th>Estado</th><th>En el evento</th><th>Enviada</th><th>Abrió el enlace</th><th>Respondió</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($invitaciones as $i): ?>
            <tr>
              <td class="col-nombre"><?= h($i['nombre']) ?><div class="celda-detalle"><?= (string) $i['cedula'] !== '' ? 'C.C. ' . h($i['cedula']) : 'Nuevo · aún sin registrarse' ?></div></td>
              <td><?= h($i['correo'] !== '' ? $i['correo'] : '—') ?></td>
              <td><?= $i['origen'] === 'public_link' ? '<span class="status-chip neutro">Enlace público</span>' : '<span class="status-chip azul">Correo</span>' ?></td>
              <td>
                <?php if ($i['estado'] === 'confirmado'): ?>
                  <span class="status-chip in">Confirmado</span>
                <?php elseif ($i['estado'] === 'rechazado'): ?>
                  <span class="status-chip error">No asistirá</span>
                <?php else: ?>
                  <span class="status-chip out">Pendiente</span>
                <?php endif; ?>
              </td>
              <td><?= (int) $i['inscrito'] === 1 ? '<span class="status-chip neutro">Inscrito</span>' : '<span class="text-muted">—</span>' ?></td>
              <td class="mono"><?= $i['enviado_en'] ? fmtFecha($i['enviado_en']) : '—' ?></td>
              <td class="mono"><?= $i['origen'] === 'email_link' && $i['abierto_en'] ? fmtFecha($i['abierto_en']) : '—' ?></td>
              <td class="mono"><?= $i['respondido_en'] ? fmtFecha($i['respondido_en']) : '—' ?></td>
              <td class="acciones-fila">
                <?php if ($i['origen'] === 'email_link' && (int) $i['inscrito'] === 0 && $i['estado'] !== 'rechazado' && $i['correo'] !== '' && EMAIL_HABILITADO): ?>
                  <form method="post" data-enviando="Enviando…">
                    <input type="hidden" name="accion" value="reenviar">
                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                    <button type="submit" class="boton-enlace"><?= $i['enviado_en'] ? 'Reenviar' : 'Enviar' ?></button>
                  </form>
                <?php endif; ?>
                <?php if ((int) $i['inscrito'] === 0): ?>
                  <form method="post" onsubmit="return confirm('¿Eliminar la invitación de <?= h(addslashes($i['nombre'])) ?>? Su enlace dejará de funcionar.');">
                    <input type="hidden" name="accion" value="eliminar_invitacion">
                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                    <button type="submit" class="boton-enlace peligro">Eliminar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="field-hint">El enlace de cada invitación es único y personal. Si alguien no recibe el correo, usa "Reenviar".</p>
  <?php endif; ?>
</div>

<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
