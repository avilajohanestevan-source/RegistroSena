<?php
/**
 * Ventana de vista previa de archivos (PDF o Excel convertido a HTML). La
 * abren los botones [data-vista-previa] (ver assets/js/app.js).
 */
?>
<dialog id="vistaPrevia" class="vista-previa" closedby="any" aria-labelledby="vistaPreviaTitulo">
  <div class="vista-previa-cabecera">
    <div>
      <span class="horario-etiqueta">Vista previa</span>
      <h2 id="vistaPreviaTitulo" class="vista-previa-titulo"></h2>
      <span class="vista-previa-archivo"></span>
    </div>
    <form method="dialog">
      <button type="submit" class="vista-previa-cerrar" aria-label="Cerrar la vista previa">&times;</button>
    </form>
  </div>
  <div class="vista-previa-cuerpo">
    <iframe title="Vista previa del archivo" src="about:blank"></iframe>
    <p class="vista-previa-cargando">Generando la vista previa…</p>
  </div>
  <div class="vista-previa-pie">
    <span class="vista-previa-nota">¿No se ve el archivo? <a data-otra-pestana href="#" target="_blank" rel="noopener">Ábrelo en otra pestaña</a></span>
    <form method="dialog">
      <button type="submit" class="btn btn-outline">Cerrar</button>
    </form>
    <a class="btn btn-primary" data-descargar href="#">Descargar</a>
  </div>
</dialog>
