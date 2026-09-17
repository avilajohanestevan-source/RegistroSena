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
 * Filas del correo con el resumen del cronograma (ver
 * cronogramaParaCorreo()): cada día con sus actividades y el enlace a la
 * vista completa. Devuelve '' si no hay cronograma.
 */
function bloqueCronogramaCorreo($cronograma) {
    if (!$cronograma) {
        return '';
    }
    $html = '
        <tr>
          <td style="padding:6px 20px 4px;">
            <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;font-weight:bold;color:#007832;border-top:1px solid #DDE5D8;padding-top:16px;">Cronograma</div>
          </td>
        </tr>';
    foreach ($cronograma['dias'] as $dia) {
        $filas = '';
        foreach ($dia['items'] as $item) {
            $detalle = array_filter([$item['descripcion'], $item['ubicacion'] !== '' ? 'Lugar: ' . $item['ubicacion'] : '', $item['responsable'] !== '' ? 'A cargo de: ' . $item['responsable'] : '']);
            $filas .= '
              <tr>
                <td valign="top" style="padding:6px 10px 6px 0;font-family:monospace;font-size:12.5px;color:#00304D;white-space:nowrap;">' . h(fmtHora12($item['hora_inicio'])) . '<br><span style="color:#5B6660;">' . h(fmtHora12($item['hora_fin'])) . '</span></td>
                <td valign="top" style="padding:6px 0;border-left:3px solid #39A900;padding-left:10px;">
                  <div style="font-size:14px;font-weight:bold;color:#1B1B1B;">' . h($item['titulo']) . '</div>
                  ' . ($detalle ? '<div style="font-size:12.5px;color:#5B6660;margin-top:2px;">' . h(implode(' · ', $detalle)) . '</div>' : '') . '
                </td>
              </tr>';
        }
        $html .= '
        <tr>
          <td style="padding:6px 20px 8px;">
            <div style="font-size:13px;font-weight:bold;color:#00304D;margin-bottom:4px;">' . h($dia['etiqueta']) . '</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $filas . '</table>
          </td>
        </tr>';
    }
    $mas = $cronograma['faltan'] > 0 ? ' (y ' . $cronograma['faltan'] . ' días más)' : '';
    $html .= '
        <tr>
          <td style="padding:6px 20px 20px;text-align:center;">
            <a href="' . h($cronograma['url']) . '" style="color:#007832;font-weight:bold;font-size:13.5px;">Ver el cronograma completo' . h($mas) . '</a>
            ' . (!empty($cronograma['pdf']) ? '<br><a href="' . h($cronograma['pdf']) . '" style="display:inline-block;margin-top:10px;background:#39A900;color:#ffffff;text-decoration:none;font-weight:bold;font-size:13.5px;padding:9px 16px;border-radius:8px;">Descargar el cronograma (PDF)</a>' : '') . '
          </td>
        </tr>';
    return $html;
}

/** El cronograma en texto plano, para la versión sin HTML del correo. */
function textoCronogramaCorreo($cronograma) {
    if (!$cronograma) {
        return '';
    }
    $texto = "\n\nCRONOGRAMA\n";
    foreach ($cronograma['dias'] as $dia) {
        $texto .= "\n" . $dia['etiqueta'] . "\n";
        foreach ($dia['items'] as $item) {
            $texto .= '- ' . fmtHora12($item['hora_inicio']) . ' a ' . fmtHora12($item['hora_fin']) . ': ' . $item['titulo']
                . ($item['ubicacion'] !== '' ? ' (' . $item['ubicacion'] . ')' : '') . "\n";
        }
    }
    return $texto . "\nCronograma completo: " . $cronograma['url'] . (!empty($cronograma['pdf']) ? "\nDescargar en PDF: " . $cronograma['pdf'] : '');
}

/**
 * Correo con la tarjeta de ingreso (una versión sencilla de la tarjeta).
 */
function plantillaCorreoTarjeta(array $asistente, $evento, $cidQr, $cidLogo = null, $cronograma = null) {
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
        </tr>' . bloqueCronogramaCorreo($cronograma);

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
    $invitacion = ($codigo['rol'] ?? 'portero') === 'admin'
        ? 'Te invitaron a ser <strong>administrador</strong> del control de ingreso de <strong>' . h($evento) . '</strong>.'
        : 'Te invitaron a hacer parte del equipo de portería de <strong>' . h($evento) . '</strong>: vas a registrar las entradas y salidas desde tu celular.';
    $contenido = '
        <tr>
          <td style="padding:26px 24px 8px;font-size:15px;line-height:1.6;color:#1B1B1B;">
            Hola ' . h($codigo['nombre']) . ':<br><br>
            ' . $invitacion . '
            Para crear tu cuenta solo necesitas tu cédula, una contraseña y este código:
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
        $mail->Subject = (($codigo['rol'] ?? 'portero') === 'admin' ? 'Tu código de administrador · ' : 'Tu código de portería · ') . $evento;
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

    global $conn;
    // El mismo QR de siempre de la persona.
    $qrTexto = qrGuardado($conn, $asistente['cedula'], $asistente['nombre']);
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

        $cronograma = cronogramaParaCorreo($conn, eventoContextoFila($conn));
        $mail->Body = plantillaCorreoTarjeta($asistente, $evento, $qrPng ? $cidQr : null, $cidLogo, $cronograma);
        $mail->AltBody = "Hola " . $asistente['nombre'] . ",\n\n" .
            "Quedaste registrado para " . $evento . ".\n" .
            "Cedula: " . $asistente['cedula'] . "\n" .
            "Presenta el codigo QR de tu tarjeta en la entrada y la salida del evento." .
            textoCronogramaCorreo($cronograma);

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

/**
 * Tarjeta promocional del correo: la imagen del evento (incrustada) o, si no
 * hay imagen o es SVG, una tarjeta HTML con el logo, el evento y la fecha.
 */
function bloquePromocionCorreo(array $promo, $cidPromo, $cidLogo) {
    if ($cidPromo) {
        return '
        <tr>
          <td style="padding:0;">
            <img src="cid:' . $cidPromo . '" width="480" alt="' . h($promo['alt']) . '" style="display:block;width:100%;max-width:480px;height:auto;border:0;">
          </td>
        </tr>';
    }
    return '
        <tr>
          <td style="padding:20px 20px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#00304D;border-radius:12px;border-bottom:6px solid #39A900;">
              <tr><td style="padding:22px 20px;text-align:center;color:#ffffff;">
                ' . ($cidLogo ? '<img src="cid:' . $cidLogo . '" width="56" height="56" alt="SENA" style="display:block;margin:0 auto 10px;">' : '') . '
                <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#9BE06B;font-weight:bold;">Estás invitado</div>
                <div style="font-size:22px;font-weight:bold;margin-top:4px;">' . h($promo['evento']) . '</div>
                ' . ($promo['fecha'] !== '' ? '<div style="font-size:13.5px;color:#D5ECC8;margin-top:6px;">' . h($promo['fecha']) . '</div>' : '') . '
              </td></tr>
            </table>
          </td>
        </tr>';
}

/** La fecha para la frase "Te invitamos a participar en X el ...". */
function fraseFechaInvitacion($fecha) {
    if ($fecha === '') {
        return '';
    }
    $fecha = lcfirst($fecha);
    return str_starts_with($fecha, 'del ') ? ' ' . $fecha : ' el ' . $fecha;
}

/** Botón y texto de Facebook para los correos. */
function bloqueFacebookCorreo() {
    return '
        <tr>
          <td style="padding:6px 24px 22px;text-align:center;border-top:1px solid #DDE5D8;">
            <p style="font-size:13px;color:#5B6660;margin:16px 0 10px;">' . h(TEXTO_FACEBOOK) . '</p>
            <a href="' . h(FACEBOOK_SENA) . '" style="display:inline-block;background:#1877F2;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:10px 18px;border-radius:8px;">Síguenos en Facebook</a>
          </td>
        </tr>';
}

/**
 * Correo de invitación: tarjeta promocional, "Regístrate aquí" con el
 * enlace personal (token), cronograma y Facebook. A quien ya estuvo en otro
 * evento se le pide confirmar (su mismo QR le sirve); a una persona nueva,
 * registrarse.
 */
function plantillaCorreoInvitacion(array $invitacion, $evento, $horario, $url, $cidLogo = null, $cronograma = null, $promo = null, $cidPromo = null) {
    $promo = $promo ?? ['evento' => $evento, 'fecha' => $horario, 'alt' => $evento, 'cronograma' => urlDelSistema('cronograma_ver.php')];
    $anterior = (string) ($invitacion['cedula'] ?? '') !== '';
    $fecha = h(fraseFechaInvitacion($promo['fecha']));
    $contenido = bloquePromocionCorreo($promo, $cidPromo, $cidLogo) . '
        <tr>
          <td style="padding:24px 24px 6px;font-size:15px;line-height:1.6;color:#1B1B1B;">
            Hola ' . h($invitacion['nombre']) . ',<br><br>
            Te invitamos a participar en <strong>' . h($evento) . '</strong>' . $fecha . '.
            ' . ($anterior
                ? 'Como ya estuviste en un evento del SENA no tienes que registrarte de nuevo: confirma tu asistencia y tu <strong>mismo código QR</strong> te sirve para entrar y salir.'
                : 'Regístrate con tu enlace personal y recibirás tu código QR para entrar y salir del evento.') . '
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px 22px;text-align:center;">
            <a href="' . h($url) . '" style="display:inline-block;background:#39A900;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;padding:13px 26px;border-radius:9px;">' . ($anterior ? 'Confirma tu asistencia' : 'Regístrate aquí') . '</a>
            <p style="margin:14px 0 0;"><a href="' . h($promo['cronograma']) . '" style="color:#007832;font-weight:bold;font-size:14px;">Ver cronograma</a></p>
          </td>
        </tr>' . bloqueCronogramaCorreo($cronograma) . bloqueFacebookCorreo() . '
        <tr>
          <td style="padding:0 24px 22px;font-size:14px;color:#1B1B1B;">Gracias,<br><strong>Equipo organizador</strong></td>
        </tr>';
    return plantillaCorreo('Invitación', $evento, $contenido, $cidLogo);
}

/** Versión en texto plano del correo de invitación. */
function textoCorreoInvitacion(array $invitacion, $evento, array $promo, $url, $cronograma) {
    $anterior = (string) ($invitacion['cedula'] ?? '') !== '';
    return 'Hola ' . $invitacion['nombre'] . ",\n\n"
        . 'Te invitamos a participar en ' . $evento . fraseFechaInvitacion($promo['fecha']) . ".\n\n"
        . ($promo['imagen'] ?? null ? '[Imagen promocional: ' . $promo['alt'] . "]\n\n" : '')
        . ($anterior ? 'Confirma tu asistencia aquí: ' : 'Regístrate aquí: ') . $url . "\n"
        . 'Ver cronograma: ' . $promo['cronograma'] . "\n\n"
        . 'Síguenos en Facebook para actualizaciones: ' . FACEBOOK_SENA . "\n"
        . textoCronogramaCorreo($cronograma) . "\n\n"
        . "Gracias,\nEquipo organizador";
}

/**
 * Envía las invitaciones de un evento usando una sola conexión SMTP.
 * $cronograma: el del evento (cronogramaParaCorreo()) o null para no incluirlo.
 * $promo: la tarjeta promocional (promocionParaCorreo()); con 'imagen' null
 * se usa la tarjeta HTML.
 * Devuelve [id de la invitación => [ok(bool), detalle(string)]].
 */
function enviarInvitaciones(array $invitaciones, $evento, $horario, $cronograma = null, $promo = null) {
    $resultados = [];
    if (!EMAIL_HABILITADO) {
        foreach ($invitaciones as $inv) {
            $resultados[$inv['id']] = [false, 'El envío de correo no está configurado.'];
        }
        return $resultados;
    }

    @set_time_limit(300);
    $promo = $promo ?? ['evento' => $evento, 'fecha' => $horario, 'imagen' => null, 'alt' => $evento, 'cronograma' => urlDelSistema('cronograma_ver.php')];
    $mail = crearMailer();
    $mail->SMTPKeepAlive = true;
    $cidLogo = incrustarLogo($mail);
    $cidPromo = null;
    if (!empty($promo['imagen'])) {
        $cidPromo = 'promo_evento';
        $mail->addStringEmbeddedImage($promo['imagen']['bytes'], $cidPromo, 'evento.' . ($promo['imagen']['mime'] === 'image/png' ? 'png' : 'jpg'), 'base64', $promo['imagen']['mime']);
    }

    foreach ($invitaciones as $inv) {
        if (empty($inv['correo'])) {
            $resultados[$inv['id']] = [false, 'No tiene correo registrado.'];
            continue;
        }
        $mail->clearAddresses();
        try {
            $url = urlInvitacion($inv);
            $mail->addAddress($inv['correo'], $inv['nombre']);
            $mail->Subject = 'Estás invitado a ' . $evento . ' — Confirma tu asistencia';
            $mail->Body = plantillaCorreoInvitacion($inv, $evento, $horario, $url, $cidLogo, $cronograma, $promo, $cidPromo);
            $mail->AltBody = textoCorreoInvitacion($inv, $evento, $promo, $url, $cronograma);
            $mail->send();
            $resultados[$inv['id']] = [true, ''];
        } catch (Exception $e) {
            $resultados[$inv['id']] = [false, 'No se pudo enviar: ' . $mail->ErrorInfo];
        }
    }
    $mail->smtpClose();
    return $resultados;
}

/**
 * Correo con el certificado de asistencia adjunto en PDF.
 */
function plantillaCorreoCertificado(array $certificado, $evento, $url, $cidLogo = null) {
    $contenido = '
        <tr>
          <td style="padding:26px 24px 8px;font-size:15px;line-height:1.6;color:#1B1B1B;">
            Hola ' . h($certificado['nombre']) . ':<br><br>
            Gracias por participar en <strong>' . h($evento) . '</strong>. Adjunto a este correo encuentras tu
            <strong>certificado de asistencia</strong> en PDF.
          </td>
        </tr>
        <tr>
          <td style="padding:10px 24px 26px;text-align:center;">
            <div style="display:inline-block;padding:10px 18px;border:2px dashed #39A900;border-radius:10px;font-family:monospace;font-size:18px;font-weight:bold;letter-spacing:2px;color:#00304D;">' . h(formatoCodigoCertificado($certificado['codigo'])) . '</div>
            <p style="font-size:12.5px;color:#5B6660;margin:14px 0 0;">Con este código cualquier persona puede comprobar que el certificado es auténtico:<br>
            <a href="' . h($url) . '" style="color:#007832;font-weight:bold;">Verificar certificado</a></p>
          </td>
        </tr>';
    return plantillaCorreo('Certificado de asistencia', $evento, $contenido, $cidLogo);
}

/**
 * Envía los certificados por correo, cada uno con su PDF, usando una sola
 * conexión SMTP. $items: [[certificado, evento], ...].
 * Devuelve [id del certificado => [ok(bool), detalle(string)]].
 */
function enviarCertificados(array $plantilla, array $items) {
    $resultados = [];
    if (!EMAIL_HABILITADO) {
        foreach ($items as [$c]) {
            $resultados[$c['id']] = [false, 'El envío de correo no está configurado.'];
        }
        return $resultados;
    }

    @set_time_limit(600);
    $mail = crearMailer();
    $mail->SMTPKeepAlive = true;
    $cidLogo = incrustarLogo($mail);

    foreach ($items as [$c, $evento]) {
        if (empty($c['correo'])) {
            $resultados[$c['id']] = [false, 'No tiene correo registrado.'];
            continue;
        }
        $mail->clearAddresses();
        $mail->clearAttachments();
        try {
            $url = urlVerificacionCertificado($c['codigo']);
            $mail->addAddress($c['correo'], $c['nombre']);
            $mail->Subject = 'Tu certificado de asistencia · ' . $evento['nombre'];
            // clearAttachments() también quita el logo incrustado: se vuelve a poner.
            $cidLogo = incrustarLogo($mail);
            $mail->addStringAttachment(pdfCertificados($plantilla, [[$c, $evento]]), nombreArchivoCertificado($c), 'base64', 'application/pdf');
            $mail->Body = plantillaCorreoCertificado($c, $evento['nombre'], $url, $cidLogo);
            $mail->AltBody = 'Hola ' . $c['nombre'] . ":\n\n"
                . 'Adjunto encuentras tu certificado de asistencia a ' . $evento['nombre'] . ".\n"
                . 'Código de verificación: ' . formatoCodigoCertificado($c['codigo']) . "\n"
                . 'Verificar: ' . $url;
            $mail->send();
            $resultados[$c['id']] = [true, ''];
        } catch (Exception $e) {
            $resultados[$c['id']] = [false, 'No se pudo enviar: ' . $mail->ErrorInfo];
        }
    }
    $mail->smtpClose();
    return $resultados;
}
