<?php
/**
 * Arranque de las pantallas públicas (portal, autorregistro, consulta de
 * tarjeta): conexión, funciones y evento activo como contexto. Todo lo
 * que se registre o se consulte ahí pertenece al evento activo.
 *
 * Deja listas dos variables para la página:
 *   $eventoActual -> el evento activo (o null si no hay ninguno)
 *   $evento       -> el nombre del evento, para los títulos
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$eventoActual = eventoActivo($conn);
fijarEventoContexto($eventoActual['id'] ?? 0);
$evento = nombreEvento($conn);
