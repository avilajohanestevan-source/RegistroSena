// Dibuja el código QR de cada tarjeta (usa qrcode.min.js, cargado antes que este archivo).
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.qr-target').forEach(function (el) {
    var texto = el.getAttribute('data-texto');
    if (!texto || !window.QRCode) return;
    el.innerHTML = '';
    try {
      new QRCode(el, {
        text: texto,
        width: 176,
        height: 176,
        correctLevel: QRCode.CorrectLevel.M,
        colorDark: '#00304D',
        colorLight: '#ffffff'
      });
    } catch (e) {}
  });
});

// Casilla "seleccionar a todos" (reportes.php): data-check-todos lleva el
// name de las casillas que controla y data-conteo el id donde se muestra
// cuántas personas hay seleccionadas.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-check-todos]').forEach(function (todos) {
    var casillas = document.querySelectorAll('input[name="' + todos.getAttribute('data-check-todos') + '"]');
    var conteo = document.getElementById(todos.getAttribute('data-conteo'));
    function actualizar() {
      var marcadas = 0;
      casillas.forEach(function (c) { if (c.checked) marcadas++; });
      todos.checked = marcadas === casillas.length;
      todos.indeterminate = marcadas > 0 && marcadas < casillas.length;
      if (conteo) conteo.textContent = marcadas === 1 ? '1 persona seleccionada' : marcadas + ' personas seleccionadas';
    }
    todos.addEventListener('change', function () {
      casillas.forEach(function (c) { c.checked = todos.checked; });
      actualizar();
    });
    casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
    actualizar();
  });

  // Formularios con data-enviando: al enviarlos se desactiva el botón y
  // se muestra ese texto, para que no se envíe dos veces mientras espera.
  document.querySelectorAll('form[data-enviando]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var boton = form.querySelector('button[type=submit]');
      if (boton) {
        boton.disabled = true;
        boton.textContent = form.getAttribute('data-enviando');
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  // Pestañas simples (index.php): cada botón [data-panel] de un grupo
  // [data-tabs] muestra su panel y oculta los de los demás botones.
  document.querySelectorAll('[data-tabs]').forEach(function (grupo) {
    var botones = grupo.querySelectorAll('[data-panel]');
    botones.forEach(function (boton) {
      boton.addEventListener('click', function () {
        botones.forEach(function (b) {
          var activo = b === boton;
          b.setAttribute('aria-selected', activo ? 'true' : 'false');
          document.getElementById(b.getAttribute('data-panel')).hidden = !activo;
        });
      });
    });
  });

  // Campos que solo aparecen con cierta opción elegida, p. ej.
  // data-muestra-si="tipo=Otro" (el "¿Cuál?" del tipo de asistente).
  document.querySelectorAll('[data-muestra-si]').forEach(function (campo) {
    var partes = campo.getAttribute('data-muestra-si').split('=');
    var radios = document.querySelectorAll('input[name="' + partes[0] + '"]');
    function actualizar() {
      var elegido = document.querySelector('input[name="' + partes[0] + '"]:checked');
      campo.hidden = !(elegido && elegido.value === partes[1]);
    }
    radios.forEach(function (r) { r.addEventListener('change', actualizar); });
    actualizar();
  });

  // Control de acceso: vuelve a cargar la página (sin reenviar el
  // formulario) justo cuando el horario abre o cierra el ingreso.
  var barra = document.querySelector('[data-recargar-en]');
  if (barra) {
    var segundos = parseInt(barra.getAttribute('data-recargar-en'), 10);
    if (segundos > 0 && segundos < 86400) {
      setTimeout(function () { location.href = location.pathname; }, segundos * 1000);
    }
  }
});

// Copiar el enlace de autorregistro al portapapeles (usado en autorregistro.php).
function copiarEnlace(inputId, botonId) {
  var input = document.getElementById(inputId);
  var boton = document.getElementById(botonId);
  if (!input) return;
  var textoOriginal = boton ? boton.textContent : '';
  function marcarCopiado() {
    if (!boton) return;
    boton.textContent = 'Copiado';
    setTimeout(function () { boton.textContent = textoOriginal; }, 1800);
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(input.value).then(marcarCopiado, function () {
      input.select();
    });
  } else {
    input.select();
    try { document.execCommand('copy'); marcarCopiado(); } catch (e) {}
  }
}

// Escaneo de QR con la cámara (usado en control.php, una vez por sección
// "entrada" y "salida"). Requiere jsQR.js cargado antes que este archivo
// y, por cada sección, los elementos scanArea_<seccion>,
// scanVideo_<seccion>, scanCanvas_<seccion>, btnEscanear_<seccion>,
// cedula_<seccion> y form_<seccion>. Apenas se lee un código, se envía
// el formulario de esa sección solo — no hay que confirmar nada más.
var _escaneos = {}; // seccion -> { stream, raf }

function iniciarEscaneo(seccion) {
  var area = document.getElementById('scanArea_' + seccion);
  var video = document.getElementById('scanVideo_' + seccion);
  var canvas = document.getElementById('scanCanvas_' + seccion);
  var boton = document.getElementById('btnEscanear_' + seccion);
  var aviso = document.getElementById('scanAviso_' + seccion);
  if (!area || !video || !canvas || !window.jsQR) return;

  if (_escaneos[seccion] && _escaneos[seccion].stream) { detenerEscaneo(seccion); return; }

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    if (aviso) aviso.textContent = 'Este navegador no permite usar la cámara aquí. Usa el campo de texto.';
    return;
  }

  area.hidden = false;
  if (boton) boton.textContent = 'Detener cámara';
  _escaneos[seccion] = { stream: null, raf: null };

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
    _escaneos[seccion].stream = stream;
    video.srcObject = stream;
    video.play().catch(function () {});
    var ctx = canvas.getContext('2d');

    function tick() {
      if (!_escaneos[seccion] || !_escaneos[seccion].stream) return;
      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var imagen = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var codigo = jsQR(imagen.data, imagen.width, imagen.height);
        if (codigo && codigo.data) {
          var partes = codigo.data.split('|');
          var cedula = partes.length >= 2 ? partes[1].replace(/\D/g, '') : codigo.data.replace(/\D/g, '');
          if (cedula) {
            detenerEscaneo(seccion);
            var input = document.getElementById('cedula_' + seccion);
            var form = document.getElementById('form_' + seccion);
            if (input && form) {
              input.value = cedula;
              form.submit(); // se registra de una vez, sin pedir otro clic
            }
            return;
          }
        }
      }
      _escaneos[seccion].raf = requestAnimationFrame(tick);
    }
    _escaneos[seccion].raf = requestAnimationFrame(tick);
  }).catch(function () {
    if (aviso) aviso.textContent = 'No se pudo acceder a la cámara. Usa el campo de texto para buscar.';
    area.hidden = true;
    if (boton) boton.textContent = 'Escanear QR';
  });
}

function detenerEscaneo(seccion) {
  var estado = _escaneos[seccion];
  if (estado) {
    if (estado.raf) cancelAnimationFrame(estado.raf);
    if (estado.stream) estado.stream.getTracks().forEach(function (t) { t.stop(); });
  }
  _escaneos[seccion] = null;
  var area = document.getElementById('scanArea_' + seccion);
  var boton = document.getElementById('btnEscanear_' + seccion);
  if (area) area.hidden = true;
  if (boton) boton.textContent = 'Escanear QR';
}
