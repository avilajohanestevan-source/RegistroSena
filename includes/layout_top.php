<?php
/**
 * Encabezado compartido por las páginas del panel administrativo.
 * Antes de incluir este archivo hay que definir:
 *   $activeTab  -> 'inicio' | 'autorregistro' | 'registro' | 'control' | 'historial' | 'asistentes'
 *   $wide       -> (opcional) true para un contenido más ancho (tabla de asistentes)
 * y tener ya cargado includes/db.php + includes/functions.php.
 */
$evento = nombreEvento($conn);
$conteo = contarEstados($conn);
$wide = $wide ?? false;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de ingreso · <?= h($evento) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Source+Sans+3:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
</head>
<body>
  <div class="site-header">
    <header class="topbar">
      <div class="brand">
        <img class="brand-mark" src="img/Sena-Logo.png" alt="Logo SENA">
        <div class="brand-text">
          <h1>Control de ingreso</h1>
          <span class="event-name"><?= h($evento) ?></span>
        </div>
      </div>
      <div class="stat-pills">
        <span class="pill in"><span class="dot"></span>Dentro <span class="num"><?= $conteo['dentro'] ?></span></span>
        <span class="pill out"><span class="dot"></span>Fuera <span class="num"><?= $conteo['fuera'] ?></span></span>
        <span class="pill total"><span class="dot"></span>Registrados <span class="num"><?= $conteo['total'] ?></span></span>
      </div>
    </header>
    <nav class="tabs">
      <a class="tab-btn<?= $activeTab === 'inicio' ? ' active' : '' ?>" href="index.php">Inicio</a>
      <a class="tab-btn<?= $activeTab === 'autorregistro' ? ' active' : '' ?>" href="autorregistro.php">Autorregistro</a>
      <a class="tab-btn<?= $activeTab === 'registro' ? ' active' : '' ?>" href="registro_admin.php">Registro</a>
      <a class="tab-btn<?= $activeTab === 'control' ? ' active' : '' ?>" href="control.php">Control de acceso</a>
      <a class="tab-btn<?= $activeTab === 'historial' ? ' active' : '' ?>" href="historial.php">Historial</a>
      <a class="tab-btn<?= $activeTab === 'asistentes' ? ' active' : '' ?>" href="asistentes.php">Asistentes</a>
    </nav>
  </div>
  <main class="content"><div class="content-inner<?= $wide ? ' wide' : '' ?>">
