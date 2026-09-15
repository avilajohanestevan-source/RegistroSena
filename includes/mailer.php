<?php
/**
 * Envío por correo de la tarjeta con el código QR de un asistente.
 * Usa PHPMailer (en lib/PHPMailer, sin necesidad de composer) para
 * conectarse por SMTP — ver config.php para la configuración de la
 * cuenta que envía los correos (Gmail + contraseña de aplicación).
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
 * Arma el HTML del correo (una versión sencilla de la tarjeta, con estilos
 * en línea porque muchos clientes de correo ignoran las hojas de estilo).
 */
function plantillaCorreoTarjeta(array $asistente, $evento, $cidQr) {
    $nombre  = h($asistente['nombre']);
    $cedula  = h($asistente['cedula']);
    $empresa = trim($asistente['empresa'] ?? '');
    $eventoH = h($evento);

    $imgQr = $cidQr
        ? '<img src="cid:' . $cidQr . '" width="220" height="220" alt="Código QR" style="display:block;margin:0 auto;border-radius:8px;">'
        : '<p style="text-align:center;color:#5C6B5C;font-size:13px;">No se pudo generar la imagen del QR — presenta tu cédula en la entrada.</p>';

    return '
    <div style="font-family:Arial,Helvetica,sans-serif;background:#F2F7EE;padding:24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:420px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #DCE6D3;">
        <tr>
          <td style="background:#007832;padding:16px 20px;color:#ffffff;">
            <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;font-weight:bold;opacity:.95;">SENA</div>
            <div style="font-size:16px;font-weight:bold;margin-top:2px;">' . $eventoH . '</div>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 20px;text-align:center;">
            ' . $imgQr . '
            <div style="font-size:18px;font-weight:bold;color:#14201A;margin-top:16px;">' . $nombre . '</div>
            <div style="font-family:monospace;font-size:14px;color:#5C6B5C;margin-top:4px;">C.C. ' . $cedula . '</div>
            ' . ($empresa !== '' ? '<div style="font-size:12.5px;color:#5C6B5C;margin-top:6px;">' . h($empresa) . '</div>' : '') . '
          </td>
        </tr>
        <tr>
          <td style="border-top:1px dashed #DCE6D3;padding:14px 20px;font-size:12px;color:#5C6B5C;text-align:center;">
            Presenta este código QR (o esta tarjeta impresa) en la entrada y la salida del evento.
          </td>
        </tr>
      </table>
      <p style="max-width:420px;margin:16px auto 0;text-align:center;font-size:11.5px;color:#9aa79a;">
        Servicio Nacional de Aprendizaje — SENA
      </p>
    </div>';
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

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        // Si el servidor de correo no responde, que falle rápido en vez de
        // dejar la página de registro colgada esperando indefinidamente.
        $mail->Timeout = 12;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($asistente['correo'], $asistente['nombre']);

        $mail->Subject = 'Tu tarjeta de ingreso · ' . $evento;

        $cidQr = 'qr_tarjeta';
        if ($qrPng) {
            $mail->addStringEmbeddedImage($qrPng, $cidQr, 'qr.png', 'base64', 'image/png');
        }

        $mail->isHTML(true);
        $mail->Body = plantillaCorreoTarjeta($asistente, $evento, $qrPng ? $cidQr : null);
        $mail->AltBody = "Hola " . $asistente['nombre'] . ",\n\n" .
            "Quedaste registrado para " . $evento . ".\n" .
            "Cedula: " . $asistente['cedula'] . "\n" .
            "Presenta el codigo QR de tu tarjeta en la entrada y la salida del evento.";

        $mail->send();
        return [true, ''];
    } catch (Exception $e) {
        return [false, 'No se pudo enviar el correo: ' . $mail->ErrorInfo];
    }
}
