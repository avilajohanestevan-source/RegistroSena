<?php
/**
 * Promoción del evento: imagen promocional, tarjeta para el correo y la
 * página de registro, botón de Facebook y enlaces de invitación.
 *
 * La imagen (PNG, JPG o SVG) se guarda en uploads/eventos, que está
 * cerrado a la web, y se sirve con evento_imagen.php: así un SVG nunca se
 * abre como página del sistema (podría traer scripts).
 *
 * Acceso por correo vs. acceso directo:
 *   - Cada correo lleva registro.php?t=TOKEN. Con token la página muestra
 *     la versión personalizada ("Hola, Nombre") con la imagen, marca la
 *     invitación como abierta y, al registrarse, la marca como inscrita.
 *   - El enlace público es registro.php?origen=enlace: quien se registra
 *     por ahí queda como invitación con origen 'public_link'.
 *   - registro.php sin nada es el acceso directo (el QR en la sede).
 */

if (!defined('FACEBOOK_SENA')) {
    define('FACEBOOK_SENA', 'https://www.facebook.com/SENAVilletaOficial');
}
const TEXTO_FACEBOOK = 'En nuestro Facebook publicaremos contenido y actualizaciones del evento.';
const CARPETA_EVENTOS = __DIR__ . '/../uploads/eventos';

/** Cierra la carpeta uploads/ al acceso directo desde la web. */
function prepararCarpetaEventos() {
    if (!is_dir(CARPETA_EVENTOS)) {
        mkdir(CARPETA_EVENTOS, 0755, true);
    }
    $htaccess = dirname(CARPETA_EVENTOS) . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "# Archivos subidos al sistema: solo los usa el sistema.\nRequire all denied\n");
    }
}

/** ¿El SVG es seguro para mostrarse? (sin scripts, eventos ni contenido externo) */
function svgSeguro($contenido) {
    if (stripos($contenido, '<svg') === false) {
        return false;
    }
    $peligroso = '/<\s*(script|foreignObject|iframe|object|embed)\b|\bon[a-z]+\s*=|javascript\s*:|<!ENTITY|xlink:href\s*=\s*["\']\s*(?!#|data:image\/)/i';
    return !preg_match($peligroso, $contenido);
}

/**
 * Guarda la imagen promocional subida. PNG, JPG o SVG de hasta 5 MB.
 * Devuelve [nombre del archivo | null, mensaje de error].
 */
function guardarImagenEvento(array $archivo) {
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, ''];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return [null, 'No se pudo subir la imagen. Intenta de nuevo.'];
    }
    if ($archivo['size'] > 5 * 1024 * 1024) {
        return [null, 'La imagen pesa más de 5 MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $extension = null;
    if (in_array($mime, ['image/png', 'image/jpeg'], true) && getimagesize($archivo['tmp_name'])) {
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
    } elseif (in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'], true)
        && strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) === 'svg') {
        if (!svgSeguro(file_get_contents($archivo['tmp_name']))) {
            return [null, 'El SVG no es válido o trae contenido no permitido (scripts o enlaces externos).'];
        }
        $extension = 'svg';
    }
    if (!$extension) {
        return [null, 'La imagen debe ser PNG, JPG o SVG.'];
    }
    prepararCarpetaEventos();
    $nombre = 'promo_' . bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file($archivo['tmp_name'], CARPETA_EVENTOS . '/' . $nombre)) {
        return [null, 'No se pudo guardar la imagen en el servidor.'];
    }
    return [$nombre, ''];
}

function rutaImagenEvento($evento) {
    $nombre = basename((string) ($evento['imagen_promo'] ?? ''));
    $ruta = CARPETA_EVENTOS . '/' . $nombre;
    return $nombre !== '' && is_file($ruta) ? $ruta : null;
}

function borrarImagenEvento($nombre) {
    $ruta = CARPETA_EVENTOS . '/' . basename((string) $nombre);
    if ($nombre !== '' && is_file($ruta)) {
        unlink($ruta);
    }
}

/** Guarda la imagen, su texto alternativo y dónde se muestra. */
function guardarPromocionEvento(mysqli $conn, $eventoId, $imagen, $alt, $enPagina) {
    $eventoId = (int) $eventoId;
    $alt = mb_substr(trim((string) $alt), 0, 255);
    $enPagina = $enPagina ? 1 : 0;
    $stmt = $conn->prepare("UPDATE eventos SET imagen_promo = ?, imagen_alt = ?, imagen_en_pagina = ? WHERE id = ?");
    $stmt->bind_param('ssii', $imagen, $alt, $enPagina, $eventoId);
    $stmt->execute();
    $stmt->close();
}

/** URL relativa de la imagen (con versión para que el navegador no use una vieja). */
function srcImagenEvento($evento) {
    return rutaImagenEvento($evento)
        ? 'evento_imagen.php?id=' . (int) $evento['id'] . '&v=' . substr(md5($evento['imagen_promo']), 0, 8)
        : null;
}

function altImagenEvento($evento) {
    $alt = trim((string) ($evento['imagen_alt'] ?? ''));
    return $alt !== '' ? $alt : 'Imagen promocional de ' . $evento['nombre'];
}

/** ¿La página de registro muestra la tarjeta promocional? Con token (llegó por correo) siempre. */
function mostrarPromocionEnPagina($evento, $conToken) {
    return $evento && ($conToken || (int) ($evento['imagen_en_pagina'] ?? 1) === 1);
}

/**
 * La imagen lista para incrustar en el correo: PNG/JPG reducidos a 600 px
 * de ancho. Los SVG no se ven en la mayoría de correos, así que para ellos
 * (o si no hay imagen) se devuelve null y el correo usa la tarjeta HTML.
 * Devuelve ['bytes', 'mime'] o null.
 */
function imagenEventoParaCorreo($evento) {
    $ruta = rutaImagenEvento($evento);
    if (!$ruta || str_ends_with($ruta, '.svg')) {
        return null;
    }
    $info = getimagesize($ruta);
    if (!$info) {
        return null;
    }
    if ($info[0] <= 600) {
        return ['bytes' => file_get_contents($ruta), 'mime' => $info['mime']];
    }
    $origen = $info['mime'] === 'image/png' ? imagecreatefrompng($ruta) : imagecreatefromjpeg($ruta);
    $alto = (int) round($info[1] * 600 / $info[0]);
    $destino = imagecreatetruecolor(600, $alto);
    imagealphablending($destino, false);
    imagesavealpha($destino, true);
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, 600, $alto, $info[0], $info[1]);
    ob_start();
    $info['mime'] === 'image/png' ? imagepng($destino, null, 8) : imagejpeg($destino, null, 85);
    $bytes = ob_get_clean();
    imagedestroy($origen);
    imagedestroy($destino);
    return ['bytes' => $bytes, 'mime' => $info['mime']];
}

/**
 * Datos de la tarjeta promocional para el correo: evento, fecha, enlace al
 * cronograma y la imagen (o null para usar la tarjeta HTML).
 */
function promocionParaCorreo($evento, $incluirImagen = true) {
    $horario = horarioDeEvento($evento);
    return [
        'evento'     => $evento['nombre'],
        'fecha'      => horarioConfigurado($horario) ? textoHorario($horario) : '',
        'imagen'     => $incluirImagen ? imagenEventoParaCorreo($evento) : null,
        'alt'        => altImagenEvento($evento),
        'cronograma' => urlDelSistema('cronograma_ver.php'),
    ];
}

/** Enlace personal de la invitación (lleva el token). */
function urlInvitacion(array $invitacion) {
    return urlDelSistema('registro.php') . '?t=' . $invitacion['token'];
}

/** Enlace público para compartir: quien se registra por aquí queda con origen 'public_link'. */
function urlEnlacePublico() {
    return urlDelSistema('registro.php') . '?origen=enlace';
}

/** Marca la primera vez que se abrió el enlace del correo. */
function marcarInvitacionAbierta(mysqli $conn, array $invitacion) {
    if (!empty($invitacion['abierto_en'])) {
        return;
    }
    $id = (int) $invitacion['id'];
    $stmt = $conn->prepare("UPDATE invitaciones SET abierto_en = NOW() WHERE id = ? AND abierto_en IS NULL");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Invitación por correo a alguien nuevo (sin cédula todavía). Si ya había
 * una para ese correo en el evento, la devuelve.
 */
function crearInvitacionCorreo(mysqli $conn, $eventoId, $nombre, $correo, $usuarioId) {
    $eventoId = (int) $eventoId;
    $stmt = $conn->prepare("SELECT * FROM invitaciones WHERE evento_id = ? AND correo = ? ORDER BY id LIMIT 1");
    $stmt->bind_param('is', $eventoId, $correo);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existente) {
        return $existente;
    }
    $token = bin2hex(random_bytes(16));
    $usuarioId = $usuarioId ? (int) $usuarioId : null;
    $stmt = $conn->prepare(
        "INSERT INTO invitaciones (evento_id, cedula, nombre, correo, token, origen, creado_por)
         VALUES (?, NULL, ?, ?, ?, 'email_link', ?)"
    );
    $stmt->bind_param('isssi', $eventoId, $nombre, $correo, $token, $usuarioId);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return invitacionPorId($conn, $id);
}

/**
 * Lee la lista pegada en "Invitar por correo": una persona por línea, con
 * el nombre y el correo separados por coma, punto y coma o tabulación (o
 * solo el correo). Devuelve [[nombre, correo], ...] y las líneas inválidas.
 */
function leerListaInvitados($texto) {
    $personas = [];
    $invalidas = [];
    foreach (preg_split('/\R/u', (string) $texto) as $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }
        $correo = null;
        $resto = [];
        foreach (preg_split('/[,;\t]+/u', $linea) as $parte) {
            $parte = trim($parte, " \t<>\"'");
            if (!$correo && filter_var($parte, FILTER_VALIDATE_EMAIL)) {
                $correo = strtolower($parte);
            } elseif ($parte !== '') {
                $resto[] = $parte;
            }
        }
        if (!$correo) {
            $invalidas[] = $linea;
            continue;
        }
        $nombre = trim(implode(' ', $resto));
        if ($nombre === '') {
            $nombre = ucwords(str_replace(['.', '_', '-'], ' ', strstr($correo, '@', true)));
        }
        if (!isset($personas[$correo])) {
            $personas[$correo] = [mb_substr($nombre, 0, 150), $correo];
        }
    }
    return [array_values(array_slice($personas, 0, 300)), $invalidas];
}

/**
 * Deja la invitación como inscrita cuando la persona se registra con su
 * enlace (token) o con el enlace público (se crea una con origen 'public_link').
 */
function registrarInvitacionAlInscribirse(mysqli $conn, $eventoId, array $datos, ?array $invitacion, $origen) {
    $eventoId = (int) $eventoId;
    if ($invitacion) {
        // Si la cédula ya tiene otra invitación en el evento, se deja la de ella tal cual.
        $stmt = $conn->prepare("SELECT id FROM invitaciones WHERE evento_id = ? AND cedula = ? AND id <> ?");
        $id = (int) $invitacion['id'];
        $stmt->bind_param('isi', $eventoId, $datos['cedula'], $id);
        $stmt->execute();
        $otra = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cedula = $otra ? $invitacion['cedula'] : $datos['cedula'];
        $stmt = $conn->prepare(
            "UPDATE invitaciones SET cedula = ?, nombre = ?, tipo = ?, tipo_otro = ?, correo = ?, telefono = ?, empresa = ?,
                    direccion = ?, estado = 'confirmado', inscrito = 1, respondido_en = NOW(), abierto_en = COALESCE(abierto_en, NOW())
             WHERE id = ?"
        );
        $stmt->bind_param('ssssssssi', $cedula, $datos['nombre'], $datos['tipo'], $datos['tipo_otro'], $datos['correo'],
            $datos['telefono'], $datos['empresa'], $datos['direccion'], $id);
        $stmt->execute();
        $stmt->close();
        return;
    }
    if ($origen !== 'public_link') {
        return;
    }
    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO invitaciones (evento_id, cedula, nombre, tipo, tipo_otro, correo, telefono, empresa, direccion,
                                          token, estado, inscrito, origen, respondido_en, abierto_en)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmado', 1, 'public_link', NOW(), NOW())"
    );
    $stmt->bind_param('isssssssss', $eventoId, $datos['cedula'], $datos['nombre'], $datos['tipo'], $datos['tipo_otro'],
        $datos['correo'], $datos['telefono'], $datos['empresa'], $datos['direccion'], $token);
    $stmt->execute();
    $stmt->close();
}
