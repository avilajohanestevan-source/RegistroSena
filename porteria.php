<?php
/**
 * Pestaña Portería: códigos de registro para el personal de portería y
 * lista de porteros. Cada código se crea para una persona, se le envía
 * por correo (o se le comparte a mano) y sirve para crear una sola
 * cuenta en index.php, donde se valida contra la tabla codigos_porteria.
 */
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/codigos_porteria.php';
require_once __DIR__ . '/includes/mailer.php';

$usuario = usuarioActual();
$evento = nombreEvento($conn);
$valores = ['nombre' => '', 'correo' => '', 'cedula' => '', 'enviar' => '1'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $valores = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'correo' => trim($_POST['correo'] ?? ''),
            'cedula' => soloDigitos($_POST['cedula'] ?? ''),
            'enviar' => isset($_POST['enviar']) ? '1' : '',
        ];
        if (mb_strlen($valores['nombre']) < 3) {
            $errores['nombre'] = 'Escribe el nombre de la persona.';
        }
        if ($valores['correo'] !== '' && !filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Escribe un correo electrónico válido.';
        } elseif ($valores['enviar'] && $valores['correo'] === '') {
            $errores['correo'] = 'Escribe el correo para enviarle el código.';
        }
        if ($valores['cedula'] !== '' && mb_strlen($valores['cedula']) < 5) {
            $errores['cedula'] = 'Escribe una cédula válida o déjala vacía.';
        } elseif ($valores['cedula'] !== '' && buscarUsuario($conn, $valores['cedula'])) {
            $errores['cedula'] = 'Ya existe una cuenta de portería con esta cédula.';
        }

        if (!$errores) {
            $codigo = crearCodigoPorteria($conn, $valores['nombre'], $valores['correo'], $valores['cedula'], $usuario['id']);
            $correo = 'no';
            if ($valores['enviar']) {
                [$ok] = enviarCodigoPorteria($codigo, $evento, urlRegistroPorteria($codigo));
                if ($ok) {
                    marcarCodigoEnviado($conn, $codigo['id']);
                }
                $correo = $ok ? 'ok' : 'error';
            }
            header('Location: porteria.php?creado=' . $codigo['id'] . '&correo=' . $correo);
            exit;
        }
    } elseif ($accion === 'reenviar' || $accion === 'anular') {
        $codigo = codigoPorId($conn, (int) ($_POST['id'] ?? 0));
        if ($codigo && estadoCodigo($codigo) === 'disponible') {
            if ($accion === 'anular') {
                anularCodigoPorteria($conn, $codigo['id']);
                header('Location: porteria.php?anulado=' . $codigo['id']);
                exit;
            }
            if ($codigo['correo'] !== '') {
                [$ok] = enviarCodigoPorteria($codigo, $evento, urlRegistroPorteria($codigo));
                if ($ok) {
                    marcarCodigoEnviado($conn, $codigo['id']);
                }
                header('Location: porteria.php?reenviado=' . $codigo['id'] . '&correo=' . ($ok ? 'ok' : 'error'));
                exit;
            }
        }
        header('Location: porteria.php');
        exit;
    }
}

// Mensajes después de crear, reenviar o anular un código.
$nuevo = isset($_GET['creado']) ? codigoPorId($conn, (int) $_GET['creado']) : null;
$aviso = null;
$correoGet = $_GET['correo'] ?? '';
if ($nuevo) {
    if ($correoGet === 'ok') {
        $aviso = ['success', 'Código creado y enviado a ' . $nuevo['correo'] . '.'];
    } elseif ($correoGet === 'error') {
        $aviso = ['warning', 'El código se creó, pero no se pudo enviar el correo. Compártelo a mano con el código o el enlace de abajo.'];
    } else {
        $aviso = ['info', 'Código creado. Compártelo con ' . $nuevo['nombre'] . ' (el código o el enlace de abajo).'];
    }
} elseif (isset($_GET['reenviado']) && ($reenviado = codigoPorId($conn, (int) $_GET['reenviado']))) {
    $aviso = $correoGet === 'ok'
        ? ['success', 'Se envió de nuevo el código ' . formatoCodigo($reenviado['codigo']) . ' a ' . $reenviado['correo'] . '.']
        : ['warning', 'No se pudo enviar el correo con el código ' . formatoCodigo($reenviado['codigo']) . '.'];
} elseif (isset($_GET['anulado']) && ($anulado = codigoPorId($conn, (int) $_GET['anulado']))) {
    $aviso = ['success', 'El código ' . formatoCodigo($anulado['codigo']) . ' quedó anulado: ya no sirve para crear una cuenta.'];
}

$codigos = listarCodigosPorteria($conn);
$porteros = listarPorteros($conn);
$disponibles = count(array_filter($codigos, function ($c) { return estadoCodigo($c) === 'disponible'; }));

$activeTab = 'porteria';
$wide = true;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h2 class="section-title">Nuevo código de registro</h2>
  <p class="section-sub">Crea un código para cada persona de portería. Con él crea su cuenta en la página de inicio; cada código sirve para una sola cuenta.</p>

  <?php if ($aviso): ?>
    <div class="banner <?= $aviso[0] ?>"><?= h($aviso[1]) ?></div>
  <?php endif; ?>
  <?php if ($nuevo): ?>
    <div class="codigo-nuevo">
      <div>
        <span class="horario-etiqueta">Código para <?= h($nuevo['nombre']) ?></span>
        <input type="text" id="codigoNuevo" class="codigo-grande" readonly value="<?= h(formatoCodigo($nuevo['codigo'])) ?>" aria-label="Código de registro">
      </div>
      <button type="button" class="btn btn-outline btn-sm" id="btnCopiarCodigo" onclick="copiarEnlace('codigoNuevo', 'btnCopiarCodigo')">Copiar código</button>
      <div class="codigo-enlace">
        <label for="enlaceNuevo" class="horario-etiqueta">Enlace directo para crear la cuenta (con el código ya puesto)</label>
        <div class="fila-copiar">
          <input type="text" id="enlaceNuevo" readonly value="<?= h(urlRegistroPorteria($nuevo)) ?>">
          <button type="button" class="btn btn-outline btn-sm" id="btnCopiarEnlace" onclick="copiarEnlace('enlaceNuevo', 'btnCopiarEnlace')">Copiar enlace</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" novalidate data-enviando="Creando código…">
    <input type="hidden" name="accion" value="crear">
    <div class="form-grid">
      <div class="full">
        <label for="nombre">Nombre de la persona</label>
        <input type="text" id="nombre" name="nombre" value="<?= h($valores['nombre']) ?>" autocomplete="off" placeholder="Ej. Carlos Andrés Rojas">
        <?php if (!empty($errores['nombre'])): ?><div class="field-error"><?= h($errores['nombre']) ?></div><?php endif; ?>
      </div>
      <div>
        <label for="correo">Correo electrónico</label>
        <input type="email" id="correo" name="correo" value="<?= h($valores['correo']) ?>" autocomplete="off" placeholder="nombre@correo.com">
        <?php if (!empty($errores['correo'])): ?><div class="field-error"><?= h($errores['correo']) ?></div><?php endif; ?>
      </div>
      <div>
        <label for="cedula">Cédula <span class="opt">(opcional: solo esa cédula podrá usar el código)</span></label>
        <input type="text" id="cedula" name="cedula" inputmode="numeric" value="<?= h($valores['cedula']) ?>" autocomplete="off">
        <?php if (!empty($errores['cedula'])): ?><div class="field-error"><?= h($errores['cedula']) ?></div><?php endif; ?>
      </div>
      <div class="full">
        <label class="check-linea"><input type="checkbox" name="enviar" value="1"<?= $valores['enviar'] ? ' checked' : '' ?><?= EMAIL_HABILITADO ? '' : ' disabled' ?>> Enviarle el código por correo</label>
        <?php if (!EMAIL_HABILITADO): ?><p class="field-hint">El envío de correo no está configurado en config.php: comparte el código a mano.</p><?php endif; ?>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Crear código</button>
    </div>
    <p class="field-hint">El enlace del correo usa la misma dirección con la que entraste al panel: si estás en <em>localhost</em>, solo funcionará en este computador.</p>
  </form>
</div>

<div class="card">
  <h2 class="section-title">Códigos de registro (<?= count($codigos) ?>)</h2>
  <p class="section-sub"><?= $disponibles === 1 ? '1 código disponible' : $disponibles . ' códigos disponibles' ?>. Los códigos usados quedan a nombre de la cuenta que se creó con ellos.</p>
  <?php if (!$codigos): ?>
    <div class="empty-state">Todavía no se ha creado ningún código.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Código</th><th>Para</th><th>Correo</th><th>Estado</th><th>Enviado</th><th>Creado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($codigos as $c): $estado = estadoCodigo($c); ?>
            <tr>
              <td class="codigo-celda"><?= h(formatoCodigo($c['codigo'])) ?></td>
              <td><?= h($c['nombre']) ?><?php if ($c['cedula'] !== ''): ?><div class="celda-detalle">Solo C.C. <?= h($c['cedula']) ?></div><?php endif; ?></td>
              <td><?= h($c['correo'] !== '' ? $c['correo'] : '—') ?></td>
              <td>
                <?php if ($estado === 'disponible'): ?>
                  <span class="status-chip in">Disponible</span>
                <?php elseif ($estado === 'usado'): ?>
                  <span class="status-chip neutro">Usado</span>
                  <div class="celda-detalle"><?= h($c['usado_por_nombre'] ?? 'Cuenta eliminada') ?> · <?= fmtFecha($c['usado_en']) ?></div>
                <?php else: ?>
                  <span class="status-chip error">Anulado</span>
                <?php endif; ?>
              </td>
              <td class="mono"><?= $c['enviado_en'] ? fmtFecha($c['enviado_en']) : '—' ?></td>
              <td class="mono"><?= fmtFecha($c['creado_en']) ?><?php if ($c['creado_por_nombre']): ?><div class="celda-detalle"><?= h($c['creado_por_nombre']) ?></div><?php endif; ?></td>
              <td class="acciones-fila">
                <?php if ($estado === 'disponible'): ?>
                  <?php if ($c['correo'] !== '' && EMAIL_HABILITADO): ?>
                    <form method="post" data-enviando="Enviando…">
                      <input type="hidden" name="accion" value="reenviar">
                      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                      <button type="submit" class="boton-enlace"><?= $c['enviado_en'] ? 'Reenviar' : 'Enviar' ?></button>
                    </form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('¿Anular el código <?= h(formatoCodigo($c['codigo'])) ?>? Ya no servirá para crear una cuenta.');">
                    <input type="hidden" name="accion" value="anular">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="boton-enlace peligro">Anular</button>
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

<div class="card">
  <h2 class="section-title">Porteros registrados (<?= count($porteros) ?>)</h2>
  <p class="section-sub">Personas con cuenta para entrar al control de acceso.</p>
  <?php if (!$porteros): ?>
    <div class="empty-state">Todavía no hay porteros registrados.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nombre</th><th>Cédula</th><th>Cuenta creada</th><th>Último turno</th><th>Turnos</th></tr></thead>
        <tbody>
          <?php foreach ($porteros as $p): ?>
            <tr>
              <td><?= h($p['nombre']) ?><?php if ((int) $p['id'] === (int) $usuario['id']): ?> <span class="text-muted">(tú)</span><?php endif; ?></td>
              <td class="cedula-cell"><?= h($p['cedula']) ?></td>
              <td class="mono"><?= fmtFecha($p['creado_en']) ?></td>
              <td class="mono"><?= $p['ultimo_turno'] ? fmtFecha($p['ultimo_turno']) : '—' ?></td>
              <td><?= (int) $p['turnos'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
