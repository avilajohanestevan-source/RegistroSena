# Control de ingreso — Evento SENA (PHP + MySQL)

Aplicación local en PHP puro + MySQL para registrar asistentes y controlar
su entrada y salida de un evento, con autorregistro por QR desde el
celular. Pensada para probarse en tu computador (XAMPP/WAMP/Laragon) y
luego subirse tal cual a un hosting en la nube.

## 1. Instalación local (XAMPP / WAMP / Laragon)

1. Copia toda esta carpeta dentro de `htdocs` (XAMPP) o `www` (WAMP), por
   ejemplo: `C:\xampp\htdocs\sena-evento`.
2. Prende Apache y MySQL desde el panel de control de XAMPP/WAMP.
3. Abre **phpMyAdmin** (`http://localhost/phpmyadmin`), pestaña
   **Importar**, y selecciona el archivo `database.sql`. Esto crea la
   base de datos `sena_evento` con las tablas y ya trae **datos de
   prueba quemados** (4 asistentes, algunos "dentro" y otros "fuera")
   para que puedas probar todo sin registrar a nadie primero.
4. Revisa `config.php`: los valores por defecto (`root` sin contraseña)
   funcionan tal cual en la mayoría de instalaciones de XAMPP/WAMP. Si tu
   MySQL tiene otra contraseña, cámbiala ahí.
5. Abre `http://localhost/sena-evento/index.php` en el navegador.

## 2. Probar el flujo completo (celular + computador)

Para que el QR de autorregistro funcione desde tu celular, **no entres al
panel con `localhost`** — tu celular no puede llegar a esa dirección.
Entra usando la IP de tu computador en la red local, por ejemplo
`http://192.168.1.15/sena-evento/index.php` (para ver tu IP: `ipconfig`
en Windows o `ifconfig`/`ip a` en Mac/Linux, y asegúrate de que el
celular esté en el mismo wifi).

1. En el PC, entra a **Autorregistro** y escanea ese QR con la cámara del
   celular (o abre el enlace directo desde el celular).
2. Regístrate desde el celular — verás tu propia tarjeta con tu QR.
3. En el PC, entra a **Control de acceso**, dale clic a "Escanear QR con
   cámara" (el navegador pedirá permiso de cámara) y apunta la webcam a
   la pantalla del celular con tu tarjeta.
4. Debe reconocer tu cédula y mostrar el botón "Registrar entrada".

Si el navegador no deja usar la cámara ahí, siempre puedes escribir la
cédula a mano en el mismo campo — funciona igual.

## 3. Estructura del proyecto

```
config.php              Datos de conexión a la base de datos + SMTP para el correo
database.sql            Esquema + datos de prueba (impórtalo en phpMyAdmin)
migracion_avisos.sql    Migración: agrega la tabla `avisos` a una BD ya existente
migracion_telefono.sql  Migración: agrega la columna `telefono` a una BD ya existente
includes/
  db.php                Conexión mysqli
  functions.php         Funciones de acceso a datos (registrar, buscar, listar...)
  auth.php              Sesión de portería: cuentas, turnos y punto de control
  panel.php             Arranque de las páginas del panel (exige sesión iniciada)
  panel_admin.php       Arranque de las páginas que solo ve el administrador
  eventos.php           Eventos: activo, archivados, métricas y contexto de consulta
  publico.php           Arranque de las pantallas públicas (evento activo)
  sin_evento.php        Pantalla pública cuando no hay evento activo
  form_evento.php       Campos del evento (nombre, fechas y horario)
  invitaciones.php      Invitaciones: crear, enviar, confirmar e inscribir
  cronograma.php        Cronograma: días del evento, validación, cruces y copia entre días
  cronograma_vista.php  Cronograma de solo lectura (tarjeta, confirmación, página pública)
  alerta.php            Alerta modal (avisos de sesión)
  no_autorizado.php     Respuesta 401 cuando se entra por URL sin sesión
  estadisticas.php      Cálculos de asistencia para estadísticas y exportes
  codigos_porteria.php  Códigos de registro de portería (crear, validar, usar, anular)
  mailer.php             Envío por correo de la tarjeta con QR (PHPMailer + SMTP)
  head.php               <head> compartido (favicon, tipografía Work Sans, estilos)
  header_publico.php     Cabecera de las pantallas públicas (logo SENA blanco)
  layout_top.php         Cabecera y menú del panel admin
  layout_bottom.php       Cierre de página + pie + scripts
  footer.php              Pie de página (panel y pantallas públicas, logo SENA)
  form_registro.php       Formulario reutilizable (público y admin)
  badge.php               Tarjeta/carné con el QR
  tabla_estado.php        Tablas "adentro"/"salieron" (usadas en entrada.php y salida.php)
lib/
  PHPMailer/              Librería PHPMailer (sin composer, 3 archivos)
assets/
  css/style.css           Estilos con los colores institucionales del SENA
  js/app.js                Dibuja los QR y maneja el escaneo por cámara
  js/estadisticas.js       Gráficos de Estadísticas (amCharts 5)
  js/qrcode.min.js          Librería para generar códigos QR (vendida localmente)
  js/jsQR.js                 Librería para leer códigos QR desde la cámara
img/
  Sena-Logo.png            Logo original (se conserva como referencia)
  sena-logo-verde.png      Logo en verde institucional #39A900 (favicon y marcas de agua)
  sena-logo-blanco.png     Logo en blanco / negativo (cabeceras, pie y tarjeta)

index.php              Inicio: qué es el sistema + inicio de sesión / registro de portería
salir.php              Cierra la sesión (y el turno) del portero
porteria.php           Panel (admin): códigos de registro + cuentas del equipo
estadisticas.php       Panel (admin): indicadores, gráficos amCharts y botones de exporte
exportar.php           Panel (admin): Excel (PhpSpreadsheet) y PDF (Dompdf)
composer.json          Librerías de Composer (PhpSpreadsheet, Dompdf) → carpeta vendor/
evento.php             Panel (admin): evento activo (crear, editar, cerrar) + lista de eventos
evento_resumen.php     Panel (admin): vista resumida de un evento archivado
pulso.php              JSON con los contadores del evento (actualización en tiempo real)
invitaciones.php       Panel (admin): invitar, borrar o restaurar asistentes de eventos anteriores
migracion_personas.sql Directorio de personas, QR permanente y personas borradas de la lista
cronograma.php         Panel (admin): cronograma por día (tabla y calendario)
cronograma_ver.php     PÚBLICO: cronograma del evento activo, con selector de día
migracion_cronograma.sql Tabla `cronograma` y modo del cronograma en `eventos`
confirmar.php           PÚBLICO: enlace del correo para confirmar o rechazar la invitación
autorregistro.php      Panel: QR + enlace de autorregistro
registro_admin.php     Panel: registrar manualmente a alguien
entrada.php            Panel: registrar/escanear entradas + quién está adentro/afuera
salida.php             Panel: registrar/escanear salidas + quién está adentro/afuera
historial.php          Panel (admin): historial por invitado (visitas entrada → salida,
                       descansos y línea de tiempo)
reportes.php           Panel: reportes por día, sin salida + aviso por correo, CSV
control.php            (en desuso) redirige a entrada.php por compatibilidad
asistentes.php         Panel: listado y búsqueda de todos los asistentes
registro.php            PÚBLICO: formulario que abre el QR de autorregistro
tarjeta.php              Tarjeta de un asistente (se ve dentro y fuera del panel)
```

## 4. Identidad visual (Manual SENA — Resolución 1825 de 2024)

- **Color institucional:** verde `#39A900` (botones, acentos, bordes, logo).
- **Paleta secundaria:** verde oscuro `#007832`, azul oscuro `#00304D`
  (cabecera y pie), azul claro `#50E5F9`, violeta `#71277A`, amarillo `#FDC300`.
- **Tipografía:** Work Sans (Calibri como respaldo para web); nunca en
  sus pesos Thin/ExtraLight.
- **Logo:** solo en verde institucional sobre fondos claros o en blanco
  (negativo) sobre fondos oscuros, sin sombras, degradados ni rotaciones.
- **Marcas de agua:** el símbolo SENA muy tenue en el fondo de la página,
  en la cabecera, en el pie, en las tarjetas públicas y en el carné.
- **Íconos:** de línea, sin relleno.
- Fondo blanco `#FFFFFF` en todo el contenido.

## 5. Enviar la tarjeta por correo al registrarse

Cuando alguien se registra (por autorregistro o desde el panel), el
sistema intenta enviarle un correo con su tarjeta y su código QR. Esto
**no usa la función `mail()` de PHP** (que no funciona en XAMPP/Windows
sin instalar un servidor de correo aparte) — en vez de eso se conecta por
SMTP directamente a una cuenta de Gmail, tal como lo haría cualquier
programa de correo. Por eso funciona igual en localhost que cuando subas
esto a un servidor en la nube.

Para activarlo:

1. Entra a `https://myaccount.google.com/security` con la cuenta de
   Gmail que va a enviar los correos (puede ser una cuenta nueva creada
   solo para esto).
2. Activa **"Verificación en dos pasos"** si no la tienes activada (es
   obligatorio para el paso siguiente).
3. Entra a `https://myaccount.google.com/apppasswords`, crea una
   contraseña de aplicación nueva (ponle un nombre como "Evento SENA") y
   copia el código de 16 letras que te da Google.
4. Abre `config.php` y reemplaza:
   - `SMTP_USER` por el correo completo de Gmail.
   - `SMTP_PASS` por el código de 16 letras (esa contraseña de
     aplicación, **no** la contraseña normal del correo).
5. Guarda y vuelve a intentar un registro — debería llegar el correo en
   unos segundos.

Mientras no llenes esos dos datos, el registro sigue funcionando normal
(nadie ve ningún error) — simplemente no se envía el correo, y la
tarjeta se sigue viendo y pudiendo imprimir en pantalla como siempre.

Si el envío llega a fallar (por ejemplo, sin internet en ese momento),
tampoco se cae el registro: la persona queda registrada igual y ve un
aviso de que no se pudo mandar el correo, pero su tarjeta en pantalla
sigue funcionando para entrar y salir.

El código QR que se pone en el correo se genera con un servicio gratuito
(`api.qrserver.com`), así que ese paso sí necesita internet — algo que ya
tienes disponible normalmente, tanto en tu computador como cuando subas
esto a un hosting.

## 6. Portería: inicio de sesión y turnos

**Si ya tenías la base de datos creada**, importa en phpMyAdmin, en este
orden: `migracion_reportes.sql`, `migracion_porteria.sql`,
`migracion_codigos.sql`, `migracion_roles.sql`, `migracion_eventos.sql`,
`migracion_invitaciones.sql`, `migracion_personas.sql` y `migracion_cronograma.sql`.

- `index.php` es ahora la página de inicio: explica qué es el sistema y
  tiene **Iniciar sesión** y **Registrarme** para el personal de portería.
  Todo el panel (control de acceso, registro, reportes...) exige sesión.
- Para crear una cuenta hace falta un **código de registro**. Cada código
  se crea en la pestaña **Portería** para una persona (con su correo y,
  si quieres, su cédula), se le envía por correo con un enlace que abre
  el registro con el código ya puesto, y sirve para crear **una sola
  cuenta**: al registrarse se valida contra la tabla `codigos_porteria` y
  queda marcado como usado. Mientras no se use, se puede reenviar o anular.
- La **primera cuenta** (cuando todavía no hay ningún portero) se crea con
  el código inicial de `config.php` (`CODIGO_REGISTRO_PORTERIA`). En
  cuanto existe un portero, ese código deja de servir.
- Al entrar, el portero elige su **punto de control**: portería de
  entrada, de salida, o ambas. El control de acceso muestra solo lo que
  le corresponde, y cada entrada, salida o aviso queda a su nombre.
- Cada sesión es un **turno** (inicio y fin). En *Reportes* se ve quién
  estuvo en cada punto de control ese día y cuántos movimientos registró.
- En el registro, cada asistente elige **qué es**: Aprendiz, Instructor,
  Funcionario, Visitante, Contratista u Otro (y escribe cuál).

## 7. Eventos (crear, cerrar y archivar)

Cada **evento** es una unidad: sus asistentes, entradas y salidas,
irregularidades y estadísticas viven dentro de él. Solo puede haber un
evento **activo** a la vez, y todo el panel trabaja sobre ese evento.

- **Crear** (pestaña *Eventos*): se abre un evento activo y el panel
  arranca limpio, sin asistentes ni movimientos.
- **Cerrar / archivar**: el evento pasa a *archivado* con todo su
  historial guardado; el control de acceso y el autorregistro quedan
  cerrados hasta que se cree otro.
- **Vista resumida del archivo** (`evento_resumen.php`): de un evento
  archivado se muestra lo esencial — métricas clave, lista de
  asistentes, irregularidades y accesos a los reportes. Los datos
  completos siguen en la base y el administrador los consulta o los
  descarga (las páginas de estadísticas, historial, reportes y
  asistentes aceptan `?evento=ID`).
- **Sin evento activo**: el index no muestra la sección del evento, el
  portal público avisa que no hay evento y el control de acceso invita
  al administrador a crear uno.
- **En tiempo real**: mientras el evento está activo, los contadores de
  la barra superior se actualizan solos (cada 12 s, ver `pulso.php`) y
  el control avisa cuando otra persona registra una entrada o salida.
- **Invitar a los asistentes de eventos anteriores** (pestaña *Eventos* →
  *Invitar asistentes anteriores*, o apenas se crea el evento): se elige
  a quién invitar (todos o manual, con búsqueda) y a cada persona le
  llega un correo con un **enlace único**. Al confirmar queda inscrita en
  el evento nuevo con su misma cédula, así que **su código QR de siempre
  le sirve**; también puede responder que no asistirá. El administrador
  ve el estado de cada invitación (pendiente, confirmado, no asistirá),
  puede reenviarlas y tiene el botón *Añadir confirmados al evento* para
  inscribir en lote.
- **Borrar invitados anteriores**: desde la misma página se borra a una
  persona de la lista (*Borrar*, o *Borrar seleccionados*) y se eliminan
  invitaciones que aún no se convirtieron en inscripción. Los eventos
  archivados conservan todos sus datos; las personas borradas se pueden
  *Restaurar*.
- **El QR es de la persona, no del evento** (tabla `codigos_qr`): se crea
  en su primer registro. Las tarjetas de un evento archivado muestran los
  datos pero no el QR, y si la persona se vuelve a registrar en otro
  evento recibe exactamente el mismo código (y sale sola de la lista de
  borrados).
- **Borrar eventos**: los eventos archivados tienen el botón *Borrar* en la
  lista de eventos (el activo hay que cerrarlo primero). Se borra su
  historial (entradas, salidas, avisos, turnos e invitaciones), pero **los
  asistentes no se pierden**: sus datos quedan en el directorio `personas`
  y su QR en `codigos_qr`, así que siguen en la lista para invitarlos a
  próximos eventos. Conviene descargar antes el Excel del evento.
- **Cronograma por día** (*Eventos* → *Cronograma*, y se abre solo al crear
  un evento): cada actividad tiene título, descripción corta, hora de
  inicio y fin, y ubicación y responsable opcionales. En eventos de varios
  días se elige el modo (también al crear el evento):
  *Mismo horario para todos los días* (se arma una vez y se usa *Guardar y
  aplicar a todos los días*) u *Horarios por día* (una pestaña por día; se
  puede copiar un día a otros). Si la hora de inicio no es anterior a la de
  fin no se guarda; si dos actividades del mismo día se cruzan, se advierte
  y se puede *Guardar de todas formas*. Hay vista de tabla y de calendario.
  Si cambian las fechas del evento, se quitan las actividades de los días
  que ya no existen.
- **El cronograma llega al asistente**: en el correo con el QR (el día de
  hoy si el evento está en curso, o todos si aún no empieza), en las
  invitaciones (el modal de *Invitar* tiene *Incluir cronograma* y
  *Aplicar cronograma a todos los días*), en la página de confirmación y en
  la tarjeta con el botón *Ver cronograma* (con selector de día). El
  enlace público es `cronograma_ver.php`.

## 8. Administrador, estadísticas y exportes

Hay dos roles de cuenta:

- **Administrador**: maneja todo el evento — fecha y horario (pestaña
  *Evento*), códigos de registro (pestaña *Portería*), asistentes,
  historial, reportes y **Estadísticas**. Al iniciar sesión llega a
  Estadísticas. La primera cuenta del sistema es administrador, y en
  *Portería* se pueden crear códigos de administrador para otras personas.
- **Portero**: solo ve el **Control de acceso** (pensado para usarse desde
  el celular: escanea el QR con la cámara) y la lista de lo que él mismo
  registró hoy. Se registra con el código que le llega al correo, solo
  con su cédula y una contraseña.
- **Permiso de cada cuenta**: *solo entrada*, *solo salida* o *ambas*. Lo
  asigna el administrador al crear el código (y lo puede cambiar después
  en *Portería*); el control de acceso muestra únicamente el flujo que
  le corresponde.
- **Mensajes de sesión**: al cerrar sesión aparece una alerta con el
  título "Sesión cerrada". Si alguien entra por URL a una página del
  panel sin sesión, el sistema responde **401 (no autorizado)**, muestra
  la alerta "Es necesario iniciar sesión para acceder" y lleva al inicio
  de sesión (al entrar vuelve a la página que pedía). Si acababa de
  cerrar sesión, el mensaje dice "La sesión ha sido cerrada, necesitas
  volver a iniciar sesión".

**Estadísticas** (`estadisticas.php`) muestra, para el rango de días que
elijas, los indicadores del evento y gráficos hechos con **amCharts 5**
(se cargan desde internet): cuántos asistieron y cuántos no, quién
registró su salida, asistencia por tipo, intentos fallidos por motivo,
entradas y salidas por hora y por día, cuántas veces entró cada persona y
registros por portero. Debajo, cada asistente con todas sus entradas y
salidas.

Desde ahí mismo se exporta (`exportar.php`), igual que en TaxSync. Cada
botón abre primero una **vista previa** del archivo (el PDF tal cual, o el
Excel con sus hojas como pestañas) y desde ahí se descarga:

- **Excel** (PhpSpreadsheet): un libro con las hojas *Resumen*,
  *Invitados* (todos los registrados y si asistieron), *Asistencia*
  (primera entrada, última salida, veces que entró y salió, tiempo
  adentro y el detalle de cada entrada y salida), *Movimientos* (cada
  entrada y salida, con el número de vez y quién la registró) e
  *Intentos fallidos*.
- **PDF** (Dompdf): el *Detalle de asistencia* tal como se ve en pantalla
  (cada invitado con sus visitas, lo que falta en rojo), *Entradas y salidas*
  (una fila por visita) y la *Lista de invitados*, con el logo del SENA.

Las librerías se instalan con Composer, en la carpeta del proyecto:

```
composer install
```

## 9. Fecha, horario y reportes

**Si ya tenías la base de datos creada**, importa `migracion_reportes.sql`
en phpMyAdmin (pestaña **Importar**) antes de usar esta parte.

- **Fecha y horario del evento** (pestaña *Evento*): fecha de inicio,
  fecha de finalización (si dura varios días) y hora de apertura y cierre
  del ingreso. Fuera de ese rango el control de acceso no deja registrar
  entradas y el intento queda como aviso "Fuera de horario". Las salidas
  y el autorregistro siguen funcionando. Si dejas los campos vacíos, no
  se limita nada.
- **Reportes** (pestaña *Reportes*): eliges un día y ves cuántos
  asistieron, quién **entró y no registró salida**, los avisos del
  control de acceso y el registro de los correos enviados. También puedes
  descargar los movimientos del día en CSV (se abre en Excel).
- **Aviso por correo**: en la lista de quienes no registraron salida
  marcas a las personas, ajustas el asunto y el mensaje (con `{nombre}`,
  `{evento}` y `{hora_entrada}`) y das clic en *Enviar aviso*. Queda
  registrado si se envió o no, y a quién ya se le mandó aviso.

## 10. Cuando esto pase a la nube

Solo tendrías que:
1. Subir estos mismos archivos al hosting (por FTP o el panel del proveedor),
   incluida la carpeta `vendor/` (o ejecutar `composer install` allá).
2. Crear la base de datos allá e importar `database.sql`.
3. Cambiar las 4 constantes de `config.php` por los datos que te dé el
   proveedor (host, usuario, contraseña, nombre de la base de datos).

Nada más del código cambia — por eso el proyecto ya está armado con PHP +
MySQL "de verdad" (sin nada dependiente de Claude ni de la nube de
Anthropic), tal como lo pediste.
