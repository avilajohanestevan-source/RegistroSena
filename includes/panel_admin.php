<?php
/**
 * Arranque de las páginas que solo ve el administrador (evento, códigos,
 * estadísticas, exportes, asistentes, historial, reportes...). Un
 * portero que llegue aquí vuelve al control de acceso.
 */
require_once __DIR__ . '/panel.php';
requerirAdmin();
