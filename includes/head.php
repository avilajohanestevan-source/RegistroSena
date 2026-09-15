<?php
/**
 * Apertura del documento y <head> compartido por todas las páginas
 * (panel y públicas): metadatos, favicon con el logo SENA, tipografía
 * institucional (Work Sans) y hoja de estilos.
 * Antes de incluir este archivo hay que definir $tituloPagina.
 */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00304D">
  <title><?= h($tituloPagina) ?></title>
  <link rel="icon" type="image/png" href="img/sena-logo-verde.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
</head>
