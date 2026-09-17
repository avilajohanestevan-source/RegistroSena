<?php
/**
 * Tarjeta promocional del evento (página de registro y de confirmación):
 * imagen, título, fecha y hora, "Regístrate aquí" y "Ver cronograma".
 * Si no hay imagen o no carga, se ve la versión HTML con el logo. La
 * imagen lleva texto alternativo y el título y la fecha van también como
 * texto. Antes de incluirlo:
 *   $eventoPromo      -> fila del evento
 *   $saludoPromo      -> (opcional) nombre de la persona invitada
 *   $botonPromo       -> (opcional) ['texto', 'href'] del botón principal; null para no mostrarlo
 */
$srcPromo = srcImagenEvento($eventoPromo);
$horarioPromo = horarioDeEvento($eventoPromo);
$botonPromo = array_key_exists('botonPromo', get_defined_vars()) ? $botonPromo : ['Regístrate aquí', '#formulario'];
?>
<section class="promo" aria-labelledby="promoTitulo">
  <?php if ($srcPromo): ?>
    <img class="promo-imagen" src="<?= h($srcPromo) ?>" alt="<?= h(altImagenEvento($eventoPromo)) ?>"
         onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
  <?php endif; ?>
  <div class="promo-respaldo"<?= $srcPromo ? ' hidden' : '' ?> aria-hidden="true">
    <img src="img/sena-logo-blanco.png" alt="">
    <span><?= h($eventoPromo['nombre']) ?></span>
  </div>
  <div class="promo-cuerpo">
    <?php if (!empty($saludoPromo)): ?>
      <p class="promo-saludo">Hola, <?= h($saludoPromo) ?>. Estás invitado a</p>
    <?php else: ?>
      <p class="promo-saludo">Te invitamos a</p>
    <?php endif; ?>
    <h2 class="promo-titulo" id="promoTitulo"><?= h($eventoPromo['nombre']) ?></h2>
    <?php if (horarioConfigurado($horarioPromo)): ?>
      <p class="promo-fecha">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
        <?= h(textoHorario($horarioPromo)) ?>
      </p>
    <?php endif; ?>
    <div class="promo-acciones">
      <?php if ($botonPromo): ?>
        <a class="btn btn-primary btn-lg" href="<?= h($botonPromo[1]) ?>"><?= h($botonPromo[0]) ?></a>
      <?php endif; ?>
      <a class="btn btn-outline btn-lg" href="cronograma_ver.php">Ver cronograma</a>
    </div>
  </div>
</section>
