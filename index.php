<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_evento'])) {
    $nuevo = trim($_POST['nombre_evento']);
    if ($nuevo !== '') {
        actualizarNombreEvento($conn, $nuevo);
    }
    header('Location: index.php');
    exit;
}

$evento = nombreEvento($conn);
$conteo = contarEstados($conn);
$activeTab = 'inicio';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="card">
  <h2 class="section-title">Nombre del evento</h2>
  <p class="section-sub">Se muestra en la barra superior, en el formulario de autorregistro y en las tarjetas.</p>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="text" name="nombre_evento" value="<?= h($evento) ?>" style="flex:1;min-width:220px;">
    <button type="submit" class="btn btn-primary">Guardar</button>
  </form>
</div>

<div class="card">
  <h2 class="section-title">Resumen</h2>
  <p class="section-sub">Estado del evento en este momento.</p>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <span class="pill in" style="background:var(--status-in-bg);color:var(--status-in);"><span class="dot"></span>Dentro <span class="num"><?= $conteo['dentro'] ?></span></span>
    <span class="pill out" style="background:var(--status-out-bg);color:var(--status-out);"><span class="dot"></span>Fuera <span class="num"><?= $conteo['fuera'] ?></span></span>
    <span class="pill total" style="background:var(--surface-2);color:var(--text);border:1px solid var(--border);"><span class="dot"></span>Registrados <span class="num"><?= $conteo['total'] ?></span></span>
  </div>
</div>

<div class="card">
  <h2 class="section-title">Accesos rápidos</h2>
  <div class="form-actions">
    <a class="btn btn-primary" href="autorregistro.php">QR de autorregistro</a>
    <a class="btn btn-outline" href="registro_admin.php">Registrar manualmente</a>
    <a class="btn btn-outline" href="control.php#entrada">Registrar entrada</a>
    <a class="btn btn-outline" href="control.php#salida">Registrar salida</a>
    <a class="btn btn-outline" href="historial.php">Historial general</a>
    <a class="btn btn-outline" href="asistentes.php">Ver asistentes</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
