<?php
/**
 * Página de inicio: explica qué es el sistema y es la entrada del
 * personal. Con la sesión iniciada lleva al panel: el administrador a
 * Estadísticas y el portero al control de acceso.
 *
 * Cuentas nuevas: el administrador crea un código para cada persona en la
 * pestaña Portería y se le envía por correo. La persona solo escribe su
 * cédula, una contraseña y el código (el nombre y el rol salen del
 * código). La primera cuenta del sistema se crea con el código inicial de
 * config.php y queda como administrador.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/codigos_porteria.php';

if (usuarioActual()) {
    header('Location: ' . paginaInicioRol());
    exit;
}

$evento = nombreEvento($conn);
$horario = horarioEvento($conn);
$puntos = puntosControl();
$primeraCuenta = !hayPorteros($conn);
// El enlace del correo con el código trae ?modo=registro&codigo=XXXX-XXXX.
$codigoEnlace = normalizarCodigo($_GET['codigo'] ?? '');
$modo = ($_GET['modo'] ?? '') === 'registro' || $codigoEnlace !== '' ? 'registro' : 'ingreso';
$ingreso = ['cedula' => '', 'punto' => 'ambas'];
$registro = ['nombre' => '', 'cedula' => '', 'codigo' => formatoCodigo($codigoEnlace)];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'ingresar') {
        $modo = 'ingreso';
        $punto = array_key_exists($_POST['punto'] ?? '', $puntos) ? $_POST['punto'] : 'ambas';
        $ingreso = ['cedula' => soloDigitos($_POST['cedula'] ?? ''), 'punto' => $punto];
        $usuario = $ingreso['cedula'] !== '' ? buscarUsuario($conn, $ingreso['cedula']) : null;
        if ($usuario && password_verify($_POST['contrasena'] ?? '', $usuario['contrasena'])) {
            iniciarTurno($conn, $usuario, $punto);
            header('Location: ' . paginaInicioRol());
            exit;
        }
        $errores['ingreso'] = 'La cédula o la contraseña no son correctas.';
    } elseif ($accion === 'registrarse') {
        $modo = 'registro';
        $registro = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'cedula' => soloDigitos($_POST['cedula'] ?? ''),
            'codigo' => formatoCodigo(normalizarCodigo($_POST['codigo'] ?? '')),
        ];
        $contrasena = $_POST['contrasena'] ?? '';

        // Solo la primera cuenta escribe su nombre; las demás lo toman del código.
        if ($primeraCuenta && mb_strlen($registro['nombre']) < 3) {
            $errores['nombre'] = 'Escribe tu nombre completo.';
        }
        if (mb_strlen($registro['cedula']) < 5) {
            $errores['cedula'] = 'Escribe un número de cédula válido.';
        } elseif (buscarUsuario($conn, $registro['cedula'])) {
            $errores['cedula'] = 'Ya existe una cuenta con esta cédula. Inicia sesión.';
        }
        if (mb_strlen($contrasena) < 6) {
            $errores['contrasena'] = 'Usa al menos 6 caracteres.';
        } elseif ($contrasena !== ($_POST['confirmar'] ?? '')) {
            $errores['confirmar'] = 'Las contraseñas no coinciden.';
        }
        // El código se valida contra la tabla codigos_porteria (y contra la
        // cédula, si el código se le asignó a una).
        [$codigoFila, $errorCodigo] = validarCodigoPorteria($conn, normalizarCodigo($registro['codigo']), $registro['cedula']);
        if ($errorCodigo !== '') {
            $errores['codigo'] = $errorCodigo;
        }

        if (!$errores) {
            // El nombre y el rol los puso el administrador al crear el código;
            // la primera cuenta (código inicial de config.php) es el administrador.
            $nombre = $codigoFila ? $codigoFila['nombre'] : $registro['nombre'];
            $rol = $codigoFila ? $codigoFila['rol'] : 'admin';

            // La cuenta y la marca de código usado se guardan juntas: si otra
            // persona alcanzó a usar el mismo código un instante antes, no se
            // crea nada.
            $conn->begin_transaction();
            $usuario = registrarUsuario($conn, $nombre, $registro['cedula'], $contrasena, $rol);
            if ($codigoFila && !marcarCodigoUsado($conn, $codigoFila['id'], $usuario['id'])) {
                $conn->rollback();
                $errores['codigo'] = 'Este código ya se usó. Cada código sirve para crear una sola cuenta.';
            } else {
                $conn->commit();
                iniciarTurno($conn, $usuario, 'ambas');
                header('Location: ' . paginaInicioRol());
                exit;
            }
        }
    }
}

// Si el código escrito (o el del enlace) está disponible, se saluda a la
// persona por su nombre.
$codigoInfo = null;
if (!$primeraCuenta && $registro['codigo'] !== '') {
    $fila = buscarCodigoPorteria($conn, normalizarCodigo($registro['codigo']));
    if ($fila && estadoCodigo($fila) === 'disponible') {
        $codigoInfo = $fila;
    }
}
$estado = estadoHorario($horario);

/** Opciones del selector de punto de control, con la elegida marcada. */
function opcionesPunto(array $puntos, $elegido) {
    foreach ($puntos as $valor => $texto) {
        echo '<option value="' . h($valor) . '"' . ($valor === $elegido ? ' selected' : '') . '>' . h($texto) . '</option>';
    }
}

function errorCampo(array $errores, $campo) {
    if (!empty($errores[$campo])) {
        echo '<div class="field-error">' . h($errores[$campo]) . '</div>';
    }
}

$tituloPagina = 'Control de ingreso · ' . $evento;
require __DIR__ . '/includes/head.php';
?>
<body>
  <header class="topbar">
    <div class="brand">
      <img class="brand-logo" src="img/sena-logo-blanco.png" alt="Logo SENA">
      <span class="brand-divider" aria-hidden="true"></span>
      <div class="brand-text">
        <h1>Control de ingreso</h1>
        <span class="event-name"><?= h($evento) ?></span>
      </div>
    </div>
    <a class="topbar-link" href="ingreso.php">¿Vienes al evento? Regístrate aquí →</a>
  </header>

  <main class="content"><div class="content-inner wide">
    <div class="inicio-grid">
      <section class="inicio-intro">
        <span class="eyebrow-verde">SENA · Portería del evento</span>
        <h2 class="inicio-titulo">Control de entrada y salida</h2>
        <p class="inicio-lead">
          Aquí se registra quién entra y quién sale de <strong><?= h($evento) ?></strong>.
          Cada asistente tiene un código QR; en la portería se escanea para marcar su entrada
          y su salida, y cada registro queda a nombre del portero que lo hizo.
        </p>

        <div class="inicio-evento">
          <div>
            <span class="horario-etiqueta">Fecha y horario</span>
            <strong><?= horarioConfigurado($horario) ? h(textoHorario($horario)) : 'Por definir' ?></strong>
          </div>
          <?php if (horarioConfigurado($horario)): ?>
            <span class="status-chip <?= $estado['abierto'] ? 'in' : 'out' ?>"><?= $estado['abierto'] ? 'Ingreso abierto' : 'Ingreso cerrado' ?></span>
          <?php endif; ?>
        </div>

        <ul class="inicio-pasos">
          <li>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2M20 14v2M14 20h2M18 18h2v2"/></svg>
            <div>
              <strong>Autorregistro con QR</strong>
              <span>Aprendices, instructores, funcionarios, visitantes y contratistas se registran desde su celular y reciben su tarjeta.</span>
            </div>
          </li>
          <li>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.3 2.9-6 6.5-6s6.5 2.7 6.5 6"/><path d="M16 11l2 2 4-4"/></svg>
            <div>
              <strong>Control en portería, desde el celular</strong>
              <span>Los porteros escanean el QR con la cámara del celular; cada entrada y salida queda con la hora y el portero que la registró.</span>
            </div>
          </li>
          <li>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16"/><path d="M7 16v-5M12 16V7M17 16v-8"/></svg>
            <div>
              <strong>Estadísticas y exportes</strong>
              <span>El administrador ve gráficos de asistencia y descarga en Excel o PDF la lista de invitados, quiénes asistieron y cada entrada y salida.</span>
            </div>
          </li>
        </ul>
      </section>

      <section class="acceso-card" aria-label="Acceso del personal">
        <div class="acceso-tabs" role="tablist" data-tabs>
          <button type="button" role="tab" data-panel="panelIngreso" aria-selected="<?= $modo === 'ingreso' ? 'true' : 'false' ?>">Iniciar sesión</button>
          <button type="button" role="tab" data-panel="panelRegistro" aria-selected="<?= $modo === 'registro' ? 'true' : 'false' ?>">Crear mi cuenta</button>
        </div>

        <div id="panelIngreso" role="tabpanel"<?= $modo === 'ingreso' ? '' : ' hidden' ?>>
          <h3 class="acceso-titulo">Ingreso del personal</h3>
          <p class="section-sub">Entra con tu cédula y tu contraseña. Si estás en la portería, elige tu punto de control.</p>
          <?php if (!empty($errores['ingreso'])): ?>
            <div class="banner error"><?= h($errores['ingreso']) ?></div>
          <?php endif; ?>
          <form method="post" novalidate>
            <input type="hidden" name="accion" value="ingresar">
            <div class="form-grid una-columna">
              <div>
                <label for="ing_cedula">Cédula</label>
                <input type="text" id="ing_cedula" name="cedula" inputmode="numeric" autocomplete="username" value="<?= h($ingreso['cedula']) ?>">
              </div>
              <div>
                <label for="ing_contrasena">Contraseña</label>
                <input type="password" id="ing_contrasena" name="contrasena" autocomplete="current-password">
              </div>
              <div>
                <label for="ing_punto">Punto de control</label>
                <select id="ing_punto" name="punto"><?php opcionesPunto($puntos, $ingreso['punto']); ?></select>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </div>
          </form>
        </div>

        <div id="panelRegistro" role="tabpanel"<?= $modo === 'registro' ? '' : ' hidden' ?>>
          <h3 class="acceso-titulo">Crear mi cuenta</h3>
          <?php if ($codigoInfo): ?>
            <p class="section-sub">Hola <strong><?= h($codigoInfo['nombre']) ?></strong>: tu código es para <?= $codigoInfo['rol'] === 'admin' ? 'administrador del evento' : 'la portería' ?>. Solo escribe tu cédula y una contraseña.</p>
          <?php else: ?>
            <p class="section-sub">Escribe tu cédula, una contraseña y el código que te llegó al correo.</p>
          <?php endif; ?>
          <?php if ($primeraCuenta): ?>
            <div class="banner info">Todavía no hay ninguna cuenta: la primera se crea con el código inicial de <code>config.php</code> y queda como administrador. Después, los códigos los crea el administrador en la pestaña <strong>Portería</strong>.</div>
          <?php endif; ?>
          <form method="post" novalidate>
            <input type="hidden" name="accion" value="registrarse">
            <div class="form-grid">
              <?php if ($primeraCuenta): ?>
                <div class="full">
                  <label for="reg_nombre">Nombre completo</label>
                  <input type="text" id="reg_nombre" name="nombre" autocomplete="name" value="<?= h($registro['nombre']) ?>">
                  <?php errorCampo($errores, 'nombre'); ?>
                </div>
              <?php endif; ?>
              <div class="full">
                <label for="reg_cedula">Cédula</label>
                <input type="text" id="reg_cedula" name="cedula" inputmode="numeric" autocomplete="username" value="<?= h($registro['cedula']) ?>">
                <?php errorCampo($errores, 'cedula'); ?>
              </div>
              <div>
                <label for="reg_contrasena">Contraseña</label>
                <input type="password" id="reg_contrasena" name="contrasena" autocomplete="new-password">
                <?php errorCampo($errores, 'contrasena'); ?>
              </div>
              <div>
                <label for="reg_confirmar">Repite la contraseña</label>
                <input type="password" id="reg_confirmar" name="confirmar" autocomplete="new-password">
                <?php errorCampo($errores, 'confirmar'); ?>
              </div>
              <div class="full">
                <label for="reg_codigo">Código de registro</label>
                <input type="text" id="reg_codigo" name="codigo" class="input-codigo" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX" value="<?= h($registro['codigo']) ?>">
                <?php errorCampo($errores, 'codigo'); ?>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary btn-block">Crear cuenta y entrar</button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/app.js?v=<?= assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
