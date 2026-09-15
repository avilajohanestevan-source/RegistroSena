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
  mailer.php             Envío por correo de la tarjeta con QR (PHPMailer + SMTP)
  layout_top.php         Cabecera y menú del panel admin
  layout_bottom.php       Cierre de página + scripts
  footer_publico.php      Pie de página de las pantallas públicas (logo SENA)
  form_registro.php       Formulario reutilizable (público y admin)
  badge.php               Tarjeta/carné con el QR
  tabla_estado.php        Tablas "adentro"/"salieron" (usadas en entrada.php y salida.php)
lib/
  PHPMailer/              Librería PHPMailer (sin composer, 3 archivos)
assets/
  css/style.css           Estilos con los colores institucionales del SENA
  js/app.js                Dibuja los QR y maneja el escaneo por cámara
  js/qrcode.min.js          Librería para generar códigos QR (vendida localmente)
  js/jsQR.js                 Librería para leer códigos QR desde la cámara
img/
  Sena-Logo.png            Logo oficial usado en cabeceras, pie de página y tarjeta

index.php              Panel: inicio / nombre del evento / resumen
autorregistro.php      Panel: QR + enlace de autorregistro
registro_admin.php     Panel: registrar manualmente a alguien
entrada.php            Panel: registrar/escanear entradas + quién está adentro/afuera
salida.php             Panel: registrar/escanear salidas + quién está adentro/afuera
historial.php          Panel: historial general de entradas y salidas
control.php            (en desuso) redirige a entrada.php por compatibilidad
asistentes.php         Panel: listado y búsqueda de todos los asistentes
registro.php            PÚBLICO: formulario que abre el QR de autorregistro
tarjeta.php              Tarjeta de un asistente (se ve dentro y fuera del panel)
```

## 4. Colores usados (paleta institucional SENA)

- Verde principal `#39A900`, verde oscuro `#007832` (botones, acentos, QR)
- Azul oscuro `#00304D` (barra superior)
- Oro `#FDC300` (detalle en la pastilla "Fuera")
- Fondo blanco `#FFFFFF` en todo el contenido, como pediste.

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

## 6. Cuando esto pase a la nube

Solo tendrías que:
1. Subir estos mismos archivos al hosting (por FTP o el panel del proveedor).
2. Crear la base de datos allá e importar `database.sql`.
3. Cambiar las 4 constantes de `config.php` por los datos que te dé el
   proveedor (host, usuario, contraseña, nombre de la base de datos).

Nada más del código cambia — por eso el proyecto ya está armado con PHP +
MySQL "de verdad" (sin nada dependiente de Claude ni de la nube de
Anthropic), tal como lo pediste.
