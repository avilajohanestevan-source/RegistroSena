<?php
/**
 * Encabezado compartido por las páginas del panel de portería.
 * Antes de incluir este archivo hay que definir:
 *   $activeTab  -> 'control' | 'autorregistro' | 'registro' | 'asistentes' | 'historial' | 'reportes' | 'porteria' | 'evento'
 *   $wide       -> (opcional) true para un contenido más ancho (tablas)
 * y tener ya cargado includes/panel.php (conexión, funciones y sesión).
 */
$evento = nombreEvento($conn);
$conteo = contarEstados($conn);
$wide = $wide ?? false;
$horarioBarra = horarioEvento($conn);
$estadoBarra = horarioConfigurado($horarioBarra) ? estadoHorario($horarioBarra) : null;
$usuarioSesion = usuarioActual();
$pestanas = [
    'control'       => ['control.php', 'Control de acceso'],
    'autorregistro' => ['autorregistro.php', 'Autorregistro'],
    'registro'      => ['registro_admin.php', 'Registro'],
    'asistentes'    => ['asistentes.php', 'Asistentes'],
    'historial'     => ['historial.php', 'Historial'],
    'reportes'      => ['reportes.php', 'Reportes'],
    'porteria'      => ['porteria.php', 'Portería'],
    'evento'        => ['evento.php', 'Evento'],
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
          <span class="pill in"><span class="dot"></span>Dentro <span class="num"><?= $conteo['dentro'] ?></span></span>
          <span class="pill out"><span class="dot"></span>Fuera <span class="num"><?= $conteo['fuera'] ?></span></span>
          <span class="pill total"><span class="dot"></span>Registrados <span class="num"><?= $conteo['total'] ?></span></span>
        </div>
        <div class="sesion">
          <div class="sesion-datos">
            <span class="sesion-nombre"><?= h($usuarioSesion['nombre']) ?></span>
            <span class="sesion-punto"><?= h(puntosControl()[$usuarioSesion['punto']] ?? '') ?></span>
          </div>
          <a class="sesion-salir" href="salir.php">Cerrar sesión</a>
        </div>
      </div>
    </header>
    <nav class="tabs">
      <?php /* Nombres propios para no pisar variables de la página (p. ej. $url en autorregistro.php). */ ?>
      <?php foreach ($pestanas as $clavePestana => [$archivoPestana, $textoPestana]): ?>
        <a class="tab-btn<?= $activeTab === $clavePestana ? ' active' : '' ?>" href="<?= $archivoPestana ?>"><?= $textoPestana ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <main class="content"><div class="content-inner<?= $wide ? ' wide' : '' ?>">
