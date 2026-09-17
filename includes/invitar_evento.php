<?php
/**
 * Botón "Invitar" del evento activo y su ventana: invitar por correo
 * (cada persona recibe su enlace con token), copiar el enlace público o
 * ir a re-invitar a los asistentes de eventos anteriores. Antes de
 * incluirlo: $eventoActual.
 */
$tieneImagenInvitar = (bool) srcImagenEvento($eventoActual);
$tieneCronogramaInvitar = (bool) cronogramaDelEvento($conn, $eventoActual['id']);
?>
<button type="button" class="btn btn-primary btn-sm" data-abrir-dialogo="modalInvitarEvento">
  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/></svg>
  Invitar
</button>
<dialog id="modalInvitarEvento" class="alerta-modal modal-invitar-evento" closedby="any" aria-labelledby="tituloInvitarEvento">
  <div class="crono-modal-cabecera">
    <h2 class="alerta-titulo" id="tituloInvitarEvento">Invitar a <?= h($eventoActual['nombre']) ?></h2>
    <button type="button" class="crono-quitar" data-cerrar-dialogo aria-label="Cerrar">×</button>
  </div>

  <section class="invitar-seccion">
    <h3>Por correo</h3>
    <p class="field-hint">A cada persona le llega su enlace personal: al abrirlo ve la invitación con su nombre y se registra. Así se sabe quién entró por el correo.</p>
    <form method="post" action="invitaciones.php" data-enviando="Enviando invitaciones…">
      <input type="hidden" name="accion" value="invitar_correo">
      <label for="listaInvitados">Una persona por línea: nombre y correo</label>
      <textarea id="listaInvitados" name="lista" rows="5" required placeholder="Laura Gómez, laura.gomez@correo.com&#10;Carlos Pérez; carlos@empresa.com&#10;invitado@correo.com"></textarea>
      <div class="modal-opciones" style="margin:12px 0;">
        <label class="check-linea">
          <input type="checkbox" name="incluir_imagen" value="1"<?= $tieneImagenInvitar ? ' checked' : ' disabled' ?>>
          <span><strong>Incluir la imagen promocional</strong>
            <small><?= $tieneImagenInvitar ? 'Va como tarjeta destacada del correo.' : 'Este evento no tiene imagen: el correo lleva una tarjeta con el logo del SENA. Súbela en el formulario del evento.' ?></small></span>
        </label>
        <label class="check-linea">
          <input type="checkbox" name="incluir_cronograma" value="1"<?= $tieneCronogramaInvitar ? ' checked' : ' disabled' ?>>
          <span><strong>Incluir cronograma</strong>
            <small><?= $tieneCronogramaInvitar ? 'Las actividades del evento y el botón para descargarlo.' : 'El evento todavía no tiene cronograma.' ?></small></span>
        </label>
      </div>
      <div class="alerta-acciones">
        <button type="submit" class="btn btn-primary"<?= EMAIL_HABILITADO ? '' : ' disabled' ?>>Enviar invitaciones</button>
      </div>
      <?php if (!EMAIL_HABILITADO): ?><p class="field-hint">El envío de correo no está configurado en config.php.</p><?php endif; ?>
    </form>
  </section>

  <section class="invitar-seccion">
    <h3>Enlace público</h3>
    <p class="field-hint">Para compartir en redes o grupos. Quien se registra con este enlace queda marcado como "enlace público" en las invitaciones.</p>
    <div class="copiar-enlace">
      <input type="text" id="enlacePublicoEvento" value="<?= h(urlEnlacePublico()) ?>" readonly onclick="this.select()">
      <button type="button" class="btn btn-outline" id="botonCopiarPublico" onclick="copiarEnlace('enlacePublicoEvento', 'botonCopiarPublico')">Copiar</button>
    </div>
  </section>

  <section class="invitar-seccion">
    <h3>Asistentes de eventos anteriores</h3>
    <p class="field-hint">Re-invítalos con la misma imagen: confirman con su mismo código QR, sin registrarse de nuevo.</p>
    <a class="btn btn-outline" href="invitaciones.php">Re-invitar asistentes anteriores</a>
  </section>
</dialog>
