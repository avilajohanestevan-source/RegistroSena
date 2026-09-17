<?php
/**
 * Encabezado compartido por las páginas del panel.
 * Antes de incluir este archivo hay que definir:
 *   $activeTab  -> clave de la pestaña activa (ver $pestanas abajo)
 *   $wide       -> (opcional) true para un contenido más ancho (tablas)
 * y tener ya cargado includes/panel.php (conexión, funciones y sesión).
 * El administrador ve todas las pestañas; el portero solo el control de
 * acceso (y entonces no se muestra la barra de pestañas).
 */
$evento = nombreEvento($conn);
$conteo = contarEstados($conn);
$wide = $wide ?? false;
$horarioBarra = horarioEvento($conn);
$estadoBarra = horarioConfigurado($horarioBarra) ? estadoHorario($horarioBarra) : null;
$usuarioSesion = usuarioActual();
$eventoBarra = eventoContextoFila($conn);
$eventoArchivado = $eventoBarra && $eventoBarra['estado'] !== 'activo';
$esAdminSesion = esAdmin();
$pestanas = $esAdminSesion ? [
    'control'       => ['control.php', 'Control de acceso'],
    'estadisticas'  => ['estadisticas.php', 'Estadísticas'],
    'asistentes'    => ['asistentes.php', 'Asistentes'],
    'historial'     => ['historial.php', 'Historial'],
    'reportes'      => ['reportes.php', 'Reportes'],
    'autorregistro' => ['autorregistro.php', 'Autorregistro'],
    'registro'      => ['registro_admin.php', 'Registro'],
    'porteria'      => ['porteria.php', 'Portería'],
    'evento'        => ['evento.php', 'Eventos'],
] : [
    'control'       => ['control.php', 'Control de acceso'],
];
$tituloPagina = 'Panel de ingreso · ' . $evento;
require __DIR__ . '/head.php';
?>
<body>
  <div class="site-header">
    <header class="topbar">
      <div class="brand">
        <img class="brand-logo" src="img/sena-logo-blanco.png" alt="Logo SENA">
        <span class="brand-divider" aria-hidden="true"></span>
        <div class="brand-text">
          <h1>Control de ingreso</h1>
          <span class="event-name"><?= h($evento) ?></span>
        </div>
      </div>
      <div class="topbar-derecha">
        <div class="stat-pills">
          <?php if ($estadoBarra): ?>
            <span class="pill <?= $estadoBarra['abierto'] ? 'abierto' : 'cerrado' ?>" title="<?= h(textoHorario($horarioBarra)) ?>"><span class="dot"></span><?= $estadoBarra['abierto'] ? 'Ingreso abierto' : 'Ingreso cerrado' ?></span>
          <?php endif; ?>
          <span class="pill in"><span class="dot"></span>Dentro <span class="num" data-pulso-dentro><?= $conteo['dentro'] ?></span></span>
          <span class="pill out"><span class="dot"></span>Fuera <span class="num" data-pulso-fuera><?= $conteo['fuera'] ?></span></span>
          <span class="pill total"><span class="dot"></span>Registrados <span class="num" data-pulso-total><?= $conteo['total'] ?></span></span>
        </div>
        <div class="sesion">
          <div class="sesion-datos">
            <span class="sesion-nombre"><?= h($usuarioSesion['nombre']) ?></span>
            <span class="sesion-punto"><?= $esAdminSesion ? 'Administrador · ' : '' ?><?= h(puntosControl()[$usuarioSesion['punto']] ?? '') ?></span>
          </div>
          <a class="sesion-salir" href="salir.php">Cerrar sesión</a>
        </div>
      </div>
    </header>
    <?php if (count($pestanas) > 1): ?>
      <nav class="tabs">
        <?php /* Nombres propios para no pisar variables de la página (p. ej. $url en autorregistro.php). */ ?>
        <?php foreach ($pestanas as $clavePestana => [$archivoPestana, $textoPestana]): ?>
          <a class="tab-btn<?= $activeTab === $clavePestana ? ' active' : '' ?>" href="<?= $archivoPestana ?><?= $eventoArchivado ? '?evento=' . (int) $eventoBarra['id'] : '' ?>"><?= $textoPestana ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>
  <main class="content"><div class="content-inner<?= $wide ? ' wide' : '' ?>">
    <?php if ($eventoArchivado): ?>
      <div class="banner info">Estás viendo el evento archivado <strong><?= h($eventoBarra['nombre']) ?></strong>: los datos son solo de consulta. <a href="evento.php">Volver a los eventos</a></div>
    <?php endif; ?>
