<?php
/**
 * Envío de correos: la tarjeta con el código QR al registrarse, y los
 * avisos que se mandan desde Reportes (por ejemplo, a quien entró y no
 * registró su salida). Usa PHPMailer (en lib/PHPMailer, sin necesidad de
 * composer) para conectarse por SMTP — ver config.php para la
 * configuración de la cuenta que envía los correos (Gmail + contraseña de
 * aplicación).
 *
 * El código QR que se pone en el correo se genera llamando a un servicio
 * gratuito (api.qrserver.com) que devuelve directamente la imagen PNG a
 * partir del mismo texto que usa la tarjeta en pantalla — así el QR del
 * correo es idéntico al que se ve/escanea en la tarjeta impresa.
 */
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Descarga el PNG del código QR para un texto dado. Devuelve los bytes de
 * la imagen, o null si no se pudo generar (por ejemplo, sin internet).
 */
function generarQrPng($texto) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=' . urlencode($texto);
    $contexto = stream_context_create(['http' => ['timeout' => 8]]);
    $datos = @file_get_contents($url, false, $contexto);
    return $datos !== false ? $datos : null;
}

/**
 * PHPMailer ya configurado con la cuenta SMTP de config.php.
 */
function crearMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    // Si el servidor de correo no responde, que falle rápido en vez de
    // dejar la página colgada esperando indefinidamente.
    $mail->Timeout = 12;
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

/**
 * Incrusta el logo SENA en versión blanca (negativo) para el encabezado
 * verde del correo. Devuelve el cid para usarlo en el HTML, o null.
 */
function incrustarLogo(PHPMailer $mail) {
    $logo = __DIR__ . '/../img/sena-logo-blanco.png';
    if (!is_file($logo)) {
        return null;
    }
    $mail->addEmbeddedImage($logo, 'logo_sena', 'sena.png', 'base64', 'image/png');
    return 'logo_sena';
}

/**
 * Estructura común de los correos (con estilos en línea, porque muchos
 * clientes de correo ignoran las hojas de estilo): encabezado verde con el
 * logo, las filas de contenido que se reciben en $contenido, y pie azul.
 */
function plantillaCorreo($etiqueta, $evento, $contenido, $cidLogo) {
    $imgLogo = $cidLogo
        ? '<td width="52" style="padding-right:12px;"><img src="cid:' . $cidLogo . '" width="44" height="44" alt="SENA" style="display:block;"></td>'
        : '';

    return '
    <div style="font-family:\'Work Sans\',Calibri,Arial,Helvetica,sans-serif;background:#F6F6F6;padding:24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #DDE5D8;">
        <tr>
          <td style="background:#39A900;padding:16px 20px;color:#ffffff;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
              ' . $imgLogo . '
              <td>
                <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;font-weight:bold;">' . h($etiqueta) . '</div>
                <div style="font-size:18px;font-weight:bold;margin-top:2px;">' . h($evento) . '</div>
              </td>
            </tr></table>
          </td>
        </tr>
        ' . $contenido . '
        <tr>
          <td style="background:#00304D;padding:12px 20px;font-size:11.5px;color:#D5ECC8;text-align:center;">
            Servicio Nacional de Aprendizaje — SENA · <a href="https://www.sena.edu.co" style="color:#ffffff;text-decoration:none;font-weight:bold;">sena.edu.co</a>
          </td>
        </tr>
      </table>
    </div>';
}

/**
 * Correo con la tarjeta de ingreso (una versión sencilla de la tarjeta).
 */
function plantillaCorreoTarjeta(array $asistente, $evento, $cidQr, $cidLogo = null) {
    $empresa = trim($asistente['empresa'] ?? '');
    $tipo = !empty($asistente['tipo']) ? tipoAsistente($asistente['tipo'], $asistente['tipo_otro'] ?? '') : '';

    $imgQr = $cidQr
        ? '<img src="cid:' . $cidQr . '" width="220" height="220" alt="Código QR" style="display:block;margin:0 auto;border-radius:8px;">'
        : '<p style="text-align:center;color:#5B6660;font-size:13px;">No se pudo generar la imagen del QR — presenta tu cédula en la entrada.</p>';

    $contenido = '
        <tr>
          <td style="padding:24px 20px;text-align:center;">
            ' . $imgQr . '
            ' . ($tipo !== '' ? '<div style="display:inline-block;margin-top:16px;padding:3px 10px;border-radius:4px;background:#E4F4DA;color:#007832;font-size:11.5px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">' . h($tipo) . '</div>' : '') . '
            <div style="font-size:18px;font-weight:bold;color:#00304D;margin-top:10px;">' . h($asistente['nombre']) . '</div>
            <div style="font-family:monospace;font-size:14px;color:#5B6660;margin-top:4px;">C.C. ' . h($asistente['cedula']) . '</div>
            ' . ($empresa !== '' ? '<div style="font-size:12.5px;color:#5B6660;margin-top:6px;">' . h($empresa) . '</div>' : '') . '
          </td>
        </tr>
        <tr>
          <td style="border-top:1px dashed #DDE5D8;padding:14px 20px;font-size:12px;color:#5B6660;text-align:center;">
            Presenta este código QR (o esta tarjeta impresa) en la entrada y la salida del evento.
          </td>
        </tr>';

    return plantillaCorreo('Tarjeta de ingreso', $evento, $contenido, $cidLogo);
}

/**
 * Correo de aviso: el mensaje (texto plano, ya personalizado) con saltos
 * de línea convertidos a HTML.
 */
function plantillaCorreoAviso($mensaje, $evento, $cidLogo = null) {
    $contenido = '
        <tr>
          <td style="padding:26px 24px;font-size:15px;line-height:1.6;color:#1B1B1B;">' . nl2br(h($mensaje)) . '</td>
        </tr>';
    return plantillaCorreo('Aviso del evento', $evento, $contenido, $cidLogo);
}

/**
 * Correo con el código de registro de portería y el enlace para crear la
 * cuenta (ver includes/codigos_porteria.php).
 */
function plantillaCorreoCodigo(array $codigo, $evento, $url, $cidLogo = null) {
    $restriccion = $codigo['cedula'] !== '' ? ' Solo funciona con la cédula ' . h($codigo['cedula']) . '.' : '';
    $contenido = '
        <tr>
          <td style="padding:26px 24px 8px;font-size:15px;line-height:1.6;color:#1B1B1B;">
            Hola ' . h($codigo['nombre']) . ':<br><br>
            Te invitaron a hacer parte del equipo de portería de <strong>' . h($evento) . '</strong>.
            Con este código creas tu cuenta para registrar las entradas y salidas del evento:
          </td>
        </tr>
        <tr>
          <td style="padding:12px 24px;text-align:center;">
            <div style="display:inline-block;padding:14px 26px;border:2px dashed #39A900;border-radius:10px;font-family:monospace;font-size:28px;font-weight:bold;letter-spacing:4px;color:#00304D;">' . h(formatoCodigo($codigo['codigo'])) . '</div>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 24px 26px;text-align:center;">
            <a href="' . h($url) . '" style="display:inline-block;background:#39A900;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:9px;">Crear mi cuenta</a>
            <p style="font-size:12.5px;color:#5B6660;margin:16px 0 0;">El código sirve para crear una sola cuenta.' . $restriccion . '</p>
          </td>
        </tr>';
    return plantillaCorreo('Código de portería', $evento, $contenido, $cidLogo);
}

/**
 * Envía a una persona su código de registro de portería.
 * Devuelve [ok(bool), detalle(string)] — nunca lanza excepciones hacia afuera.
 */
function enviarCodigoPorteria(array $codigo, $evento, $url) {
    if (!EMAIL_HABILITADO) {
        return [false, 'El envío de correo no está configurado (falta SMTP_USER/SMTP_PASS en config.php).'];
    }
    if ($codigo['correo'] === '') {
        return [false, 'Este código no tiene correo.'];
    }
    try {
        $mail = crearMailer();
        $mail->addAddress($codigo['correo'], $codigo['nombre']);
        $mail->Subject = 'Tu código de portería · ' . $evento;
        $cidLogo = incrustarLogo($mail);
        $mail->Body = plantillaCorreoCodigo($codigo, $evento, $url, $cidLogo);
        $mail->AltBody = 'Hola ' . $codigo['nombre'] . ":\n\n" .
            'Tu código para crear la cuenta de portería de ' . $evento . ' es: ' . formatoCodigo($codigo['codigo']) . "\n\n" .
            'Crea tu cuenta aquí: ' . $url . "\n\n" .
            'El código sirve para crear una sola cuenta.';
        $mail->send();
        return [true, ''];
    } catch (Exception $e) {
        return [false, 'No se pudo enviar el correo: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage())];
    }
}

/**
 * Reemplaza {nombre}, {evento} y {hora_entrada} en el asunto o el mensaje
 * de un aviso con los datos de cada persona.
 */
function personalizarAviso($texto, array $persona, $evento) {
    return strtr($texto, [
        '{nombre}'       => $persona['nombre'],
        '{evento}'       => $evento,
        '{hora_entrada}' => fmtFecha($persona['hora_entrada'] ?? null),
    ]);
}

/**
 * Envía la tarjeta con QR al correo del asistente.
 * $asistente necesita al menos: nombre, cedula, correo, empresa.
 * Devuelve [ok(bool), mensaje(string)] — nunca lanza excepciones hacia
 * afuera, para que un fallo de correo no rompa el registro.
 */
function enviarCorreoTarjeta(array $asistente, $evento) {
    if (!EMAIL_HABILITADO) {
        return [false, 'El envío de correo no está configurado (falta SMTP_USER/SMTP_PASS en config.php).'];
    }
    if (empty($asistente['correo'])) {
        return [false, 'Este asistente no tiene un correo registrado.'];
    }

    $qrTexto = textoQR($asistente['cedula'], $asistente['nombre']);
    $qrPng = generarQrPng($qrTexto);

    try {
        $mail = crearMailer();
        $mail->addAddress($asistente['correo'], $asistente['nombre']);
        $mail->Subject = 'Tu tarjeta de ingreso · ' . $evento;

        $cidQr = 'qr_tarjeta';
        if ($qrPng) {
            $mail->addStringEmbeddedImage($qrPng, $cidQr, 'qr.png', 'base64', 'image/png');
        }
        $cidLogo = incrustarLogo($mail);

        $mail->Body = plantillaCorreoTarjeta($asistente, $evento, $qrPng ? $cidQr : null, $cidLogo);
        $mail->AltBody = "Hola " . $asistente['nombre'] . ",\n\n" .
            "Quedaste registrado para " . $evento . ".\n" .
            "Cedula: " . $asistente['cedula'] . "\n" .
            "Presenta el codigo QR de tu tarjeta en la entrada y la salida del evento.";

        $mail->send();
        return [true, ''];
    } catch (Exception $e) {
        return [false, 'No se pudo enviar el correo: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage())];
    }
}

/**
 * Envía un aviso a varias personas usando una sola conexión SMTP.
 * $personas: filas con cedula, nombre, correo y hora_entrada.
 * Devuelve [cedula => [ok(bool), detalle(string)]] — igual que la
 * función anterior, nunca lanza excepciones hacia afuera.
 */
function enviarAvisos(array $personas, $evento, $asunto, $mensaje) {
    $resultados = [];
    if (!EMAIL_HABILITADO) {
        foreach ($personas as $p) {
            $resultados[$p['cedula']] = [false, 'El envío de correo no está configurado.'];
        }
        return $resultados;
    }

    // Cada correo tarda unos segundos; con varias personas se necesita
    // más tiempo que el límite normal de PHP.
    @set_time_limit(300);

    $mail = crearMailer();
    $mail->SMTPKeepAlive = true;
    $cidLogo = incrustarLogo($mail);

    foreach ($personas as $p) {
        if (empty($p['correo'])) {
            $resultados[$p['cedula']] = [false, 'No tiene correo registrado.'];
            continue;
        }
        $mail->clearAddresses();
        try {
            $texto = personalizarAviso($mensaje, $p, $evento);
            $mail->addAddress($p['correo'], $p['nombre']);
            $mail->Subject = personalizarAviso($asunto, $p, $evento);
            $mail->Body = plantillaCorreoAviso($texto, $evento, $cidLogo);
            $mail->AltBody = $texto;
            $mail->send();
            $resultados[$p['cedula']] = [true, ''];
        } catch (Exception $e) {
            $resultados[$p['cedula']] = [false, 'No se pudo enviar: ' . $mail->ErrorInfo];
        }
    }
    $mail->smtpClose();
    return $resultados;
}
