<?php
/**
 * Alerta modal (título, mensaje y botón), para los avisos que tienen que
 * verse sí o sí — por ejemplo al cerrar sesión o al entrar por URL sin
 * sesión. Antes de incluirla:
 *   $alerta = ['titulo' => ..., 'mensaje' => ..., 'boton' => ..., 'href' => ...]
 * ('boton' y 'href' son opcionales). Se abre sola al cargar la página
 * (ver assets/js/app.js) y se cierra con Esc o con "Entendido".
 */
?>
<dialog class="alerta-modal" data-alerta-auto aria-labelledby="alertaTitulo">
  <h2 class="alerta-titulo" id="alertaTitulo"><?= h($alerta['titulo']) ?></h2>
  <p class="alerta-mensaje"><?= h($alerta['mensaje']) ?></p>
  <div class="alerta-acciones">
    <form method="dialog">
      <button type="submit" class="btn btn-outline">Entendido</button>
    </form>
    <?php if (!empty($alerta['href'])): ?>
      <a class="btn btn-primary" href="<?= h($alerta['href']) ?>"><?= h($alerta['boton'] ?? 'Continuar') ?></a>
    <?php endif; ?>
  </div>
</dialog>
