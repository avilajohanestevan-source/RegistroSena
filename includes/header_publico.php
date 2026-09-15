<?php
/**
 * Cabecera de las pantallas públicas (portal, registro, consulta y
 * tarjeta). Antes de incluir este archivo hay que definir $evento y
 * $subtitulo.
 */
?>
<header class="topbar topbar--public">
  <div class="brand">
    <img class="brand-logo" src="img/sena-logo-blanco.png" alt="Logo SENA">
    <span class="brand-divider" aria-hidden="true"></span>
    <div class="brand-text">
      <span class="eyebrow">Servicio Nacional de Aprendizaje</span>
      <h1><?= h($evento) ?></h1>
      <span class="event-name"><?= h($subtitulo) ?></span>
    </div>
  </div>
</header>
