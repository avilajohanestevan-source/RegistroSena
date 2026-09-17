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
    form.addEventListener('submit', function (e) {
      var boton = e.submitter || form.querySelector('button[type=submit]');
      if (!boton) return;
      // Se desactiva un instante después: si se desactiva ya, el navegador no
      // envía el name/value del botón (p. ej. accion=invitar).
      setTimeout(function () {
        boton.disabled = true;
        boton.textContent = form.getAttribute('data-enviando');
      }, 0);
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

// Vista previa de los exportes (estadisticas.php): cada botón
// [data-vista-previa] abre el <dialog id="vistaPrevia"> con el archivo en un
// iframe (el PDF tal cual, o el Excel convertido a HTML) y el botón para
// descargarlo. El diálogo se cierra con Esc, con el gesto "atrás" del
// celular o tocando fuera (closedby="any").
document.addEventListener('DOMContentLoaded', function () {
  var dialogo = document.getElementById('vistaPrevia');
  if (!dialogo || !dialogo.showModal) return;
  var marco = dialogo.querySelector('iframe');
  var cargando = dialogo.querySelector('.vista-previa-cargando');
  var titulo = document.getElementById('vistaPreviaTitulo');
  var archivo = dialogo.querySelector('.vista-previa-archivo');
  var descargar = dialogo.querySelector('[data-descargar]');
  var otraPestana = dialogo.querySelector('[data-otra-pestana]');

  document.querySelectorAll('[data-vista-previa]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      titulo.textContent = boton.getAttribute('data-titulo');
      archivo.textContent = boton.getAttribute('data-archivo');
      descargar.href = boton.getAttribute('data-descarga');
      otraPestana.href = boton.getAttribute('data-vista-previa');
      cargando.hidden = false;
      marco.src = boton.getAttribute('data-vista-previa');
      dialogo.showModal();
    });
  });
  marco.addEventListener('load', function () {
    if (dialogo.open) cargando.hidden = true;
  });
  // Al cerrar se descarga el iframe para no dejar el archivo cargado.
  dialogo.addEventListener('close', function () {
    marco.src = 'about:blank';
  });

  // Cerrar tocando fuera en navegadores sin closedby (Safari).
  if (!('closedBy' in HTMLDialogElement.prototype)) {
    dialogo.addEventListener('click', function (evento) {
      if (evento.target !== dialogo) return;
      var caja = dialogo.getBoundingClientRect();
      var adentro = caja.top <= evento.clientY && evento.clientY <= caja.top + caja.height &&
        caja.left <= evento.clientX && evento.clientX <= caja.left + caja.width;
      if (!adentro) dialogo.close();
    });
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

// Actualización en tiempo real del evento activo: cada 12 segundos se
// consulta pulso.php y se refrescan los contadores de la barra superior.
// Si otra persona registró una entrada o una salida, aparece el aviso
// para recargar (el bloque .pulso del control de acceso).
document.addEventListener('DOMContentLoaded', function () {
  var aviso = document.querySelector('[data-pulso]');
  var dentro = document.querySelector('[data-pulso-dentro]');
  if (!aviso && !dentro) return;
  var url = (aviso && aviso.getAttribute('data-pulso')) || 'pulso.php';
  var ultimoConocido = aviso ? parseInt(aviso.getAttribute('data-pulso-ultimo'), 10) : null;

  function pintar(elemento, valor) {
    if (elemento && String(valor) !== elemento.textContent) elemento.textContent = valor;
  }

  function consultar() {
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (datos) {
        if (!datos) return;
        pintar(dentro, datos.dentro);
        pintar(document.querySelector('[data-pulso-fuera]'), datos.fuera);
        pintar(document.querySelector('[data-pulso-total]'), datos.total);
        if (aviso && ultimoConocido !== null && datos.ultimo > ultimoConocido) aviso.hidden = false;
      })
      .catch(function () {});
  }
  setInterval(consultar, 12000);
});

// Alertas que deben verse sí o sí (sesión cerrada, acceso sin sesión):
// el <dialog data-alerta-auto> se abre solo al cargar la página.
document.addEventListener('DOMContentLoaded', function () {
  var alerta = document.querySelector('dialog[data-alerta-auto]');
  if (alerta && alerta.showModal && !alerta.open) alerta.showModal();
});

// Filtro de una lista larga (invitaciones.php): el campo con
// data-filtro="#id" oculta las filas [data-buscar] que no coinciden.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-filtro]').forEach(function (campo) {
    var lista = document.querySelector(campo.getAttribute('data-filtro'));
    var conteo = document.getElementById(campo.getAttribute('data-conteo-filtro') || '');
    if (!lista) return;
    // Enter en el buscador no debe enviar el formulario que lo contiene.
    campo.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    campo.addEventListener('input', function () {
      var busca = campo.value.trim().toLowerCase();
      var visibles = 0;
      lista.querySelectorAll('[data-buscar]').forEach(function (fila) {
        var coincide = busca === '' || fila.getAttribute('data-buscar').indexOf(busca) !== -1;
        fila.hidden = !coincide;
        if (coincide) visibles++;
      });
      if (conteo) conteo.textContent = visibles + (visibles === 1 ? ' persona' : ' personas');
    });
  });
});

// Modales genéricos: [data-abrir-dialogo="id"] abre el <dialog> y
// [data-cerrar-dialogo] lo cierra. Clic fuera de la caja también cierra
// (para los navegadores que todavía no soportan closedby="any").
document.addEventListener('DOMContentLoaded', function () {
  // Modal de invitar: cuenta a los seleccionados y no deja enviar sin nadie.
  function actualizarSeleccionModal(dialogo) {
    var boton = dialogo.querySelector('[data-requiere-seleccion]');
    var resumen = dialogo.querySelector('[data-resumen-invitar]');
    if (!boton) return;
    var nombre = boton.getAttribute('data-requiere-seleccion');
    var marcadas = document.querySelectorAll('input[name="' + nombre + '"]:checked').length;
    boton.disabled = marcadas === 0;
    if (resumen) {
      resumen.textContent = marcadas === 0
        ? 'No has seleccionado a nadie. Cierra esta ventana y marca a las personas que quieres invitar.'
        : 'Se enviará el correo con su enlace para confirmar a ' + (marcadas === 1 ? '1 persona.' : marcadas + ' personas.');
    }
  }

  document.querySelectorAll('[data-abrir-dialogo]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var dialogo = document.getElementById(boton.getAttribute('data-abrir-dialogo'));
      if (!dialogo || !dialogo.showModal) return;
      actualizarSeleccionModal(dialogo);
      dialogo.showModal();
    });
  });
  document.querySelectorAll('[data-cerrar-dialogo]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var dialogo = boton.closest('dialog');
      if (dialogo) dialogo.close();
    });
  });
  document.querySelectorAll('dialog[closedby="any"]').forEach(function (dialogo) {
    dialogo.addEventListener('click', function (e) {
      if (e.target !== dialogo) return;
      var caja = dialogo.getBoundingClientRect();
      var adentro = e.clientX >= caja.left && e.clientX <= caja.right && e.clientY >= caja.top && e.clientY <= caja.bottom;
      if (!adentro) dialogo.close();
    });
  });
});

// Cronograma para el asistente: el selector de día muestra ese día.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-crono-vista]').forEach(function (vista) {
    var selector = vista.querySelector('[data-crono-selector]');
    if (!selector) return;
    selector.addEventListener('change', function () {
      vista.querySelectorAll('[data-crono-dia]').forEach(function (dia) {
        dia.hidden = dia.getAttribute('data-crono-dia') !== selector.value;
      });
    });
  });
});

// Opciones de modo del cronograma (tarjetas con radio).
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.crono-modos').forEach(function (grupo) {
    grupo.querySelectorAll('input[type=radio]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        grupo.querySelectorAll('.crono-modo').forEach(function (op) {
          op.classList.toggle('elegido', op.contains(radio) && radio.checked);
        });
        if (grupo.hasAttribute('data-autoenvio')) grupo.submit();
      });
    });
  });

  // En el formulario del evento, el modo solo aparece si dura varios días.
  var fieldset = document.querySelector('[data-muestra-multidia]');
  if (fieldset) {
    var form = fieldset.closest('form');
    var inicio = form.querySelector('[name=fecha_inicio]');
    var fin = form.querySelector('[name=fecha_fin]');
    var revisar = function () {
      fieldset.hidden = !(inicio.value && fin.value && fin.value > inicio.value);
    };
    inicio.addEventListener('change', revisar);
    fin.addEventListener('change', revisar);
    revisar();
  }
});

// Editor del cronograma (cronograma.php): añadir y quitar actividades,
// y advertir en vivo si una actividad termina antes de empezar o si se
// cruza con otra del mismo día. El servidor vuelve a validar al guardar.
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-crono-editor]');
  if (!form) return;
  var filas = form.querySelector('[data-crono-filas]');
  var plantilla = form.querySelector('[data-crono-plantilla]');
  var aviso = form.querySelector('[data-crono-aviso]');
  var contador = filas.querySelectorAll('[data-crono-fila]').length;
  var cambios = false;

  function hora12(valor) {
    var p = valor.split(':');
    var h = parseInt(p[0], 10);
    return (h % 12 || 12) + ':' + p[1] + (h < 12 ? ' AM' : ' PM');
  }

  function escapar(texto) {
    var div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
  }

  function revisar() {
    var datos = Array.prototype.map.call(filas.querySelectorAll('[data-crono-fila]'), function (fila) {
      return {
        fila: fila,
        inicio: fila.querySelector('[data-campo=inicio]').value,
        fin: fila.querySelector('[data-campo=fin]').value,
        titulo: fila.querySelector('[data-campo=titulo]').value.trim()
      };
    });
    var invalidas = 0;
    var cruzadas = 0;
    datos.forEach(function (a) {
      var mensajes = [];
      var alReves = Boolean(a.inicio && a.fin && a.inicio >= a.fin);
      if (alReves) {
        mensajes.push('<span class="field-error">La hora de inicio debe ser anterior a la de fin.</span>');
        invalidas++;
      }
      var cruces = alReves || !a.inicio || !a.fin ? [] : datos.filter(function (b) {
        return b !== a && b.inicio && b.fin && b.inicio < b.fin && a.inicio < b.fin && b.inicio < a.fin;
      });
      if (cruces.length) {
        cruzadas++;
        mensajes.push('<span class="crono-cruce">Se cruza con: ' + cruces.map(function (b) {
          return escapar(b.titulo || 'otra actividad') + ' (' + hora12(b.inicio) + ' – ' + hora12(b.fin) + ')';
        }).join(', ') + '</span>');
      }
      a.fila.classList.toggle('crono-fila--error', alReves);
      a.fila.classList.toggle('crono-fila--solapa', cruces.length > 0);
      a.fila.querySelector('[data-crono-mensajes]').innerHTML = mensajes.join('');
    });
    if (aviso) {
      var partes = [];
      if (invalidas) partes.push(invalidas === 1 ? '1 actividad termina antes de empezar' : invalidas + ' actividades terminan antes de empezar');
      if (cruzadas) partes.push(cruzadas + ' actividades se cruzan en horario (al guardar podrás confirmar)');
      aviso.textContent = partes.length ? 'Atención: ' + partes.join(' · ') + '.' : '';
      aviso.hidden = partes.length === 0;
    }
  }

  form.querySelector('[data-crono-agregar]').addEventListener('click', function () {
    filas.insertAdjacentHTML('beforeend', plantilla.innerHTML.replace(/__N__/g, 'n' + (contador++)));
    var nueva = filas.lastElementChild;
    var anterior = nueva.previousElementSibling;
    // La nueva actividad arranca donde terminó la anterior.
    if (anterior) {
      var finAnterior = anterior.querySelector('[data-campo=fin]').value;
      if (finAnterior) nueva.querySelector('[data-campo=inicio]').value = finAnterior;
    }
    nueva.querySelector('[data-campo=titulo]').focus();
    cambios = true;
  });

  filas.addEventListener('click', function (e) {
    var quitar = e.target.closest('[data-crono-quitar]');
    if (!quitar) return;
    var fila = quitar.closest('[data-crono-fila]');
    if (filas.querySelectorAll('[data-crono-fila]').length > 1) {
      fila.remove();
    } else {
      fila.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    }
    cambios = true;
    revisar();
  });

  form.addEventListener('input', function () { cambios = true; revisar(); });
  // Enter en un campo no envía (podría disparar "Guardar de todas formas").
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
  });
  form.addEventListener('submit', function () { cambios = false; });
  window.addEventListener('beforeunload', function (e) {
    if (cambios) { e.preventDefault(); e.returnValue = ''; }
  });
});

// Plantilla de certificados: los botones [data-insertar] ponen el marcador
// en el último texto [data-marcadores] que tuvo el foco, donde está el cursor.
document.addEventListener('DOMContentLoaded', function () {
  var textos = document.querySelectorAll('textarea[data-marcadores]');
  if (!textos.length) return;
  var ultimo = textos[0];
  textos.forEach(function (t) { t.addEventListener('focus', function () { ultimo = t; }); });
  document.querySelectorAll('[data-insertar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var marcador = boton.getAttribute('data-insertar');
      var inicio = ultimo.selectionStart;
      var fin = ultimo.selectionEnd;
      ultimo.value = ultimo.value.slice(0, inicio) + marcador + ultimo.value.slice(fin);
      ultimo.focus();
      ultimo.selectionStart = ultimo.selectionEnd = inicio + marcador.length;
    });
  });
});
