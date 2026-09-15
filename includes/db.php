<?php
require_once __DIR__ . '/../config.php';

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">' .
         '<title>Error de conexión</title></head><body style="font-family:system-ui,sans-serif;' .
         'max-width:640px;margin:60px auto;padding:24px;">' .
         '<div style="border:1px solid #e3b8b8;border-radius:12px;background:#fdf1f1;color:#7a1f1f;padding:20px 24px;">' .
         '<h2 style="margin-top:0;">No se pudo conectar a la base de datos</h2>' .
         '<p>Revisa que el servidor de MySQL esté encendido y que hayas importado <code>database.sql</code> ' .
         '(por ejemplo, en phpMyAdmin: pestaña "Importar").</p>' .
         '<p><strong>Detalle:</strong> ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8') . '</p>' .
         '</div></body></html>';
    exit;
}

$conn->set_charset('utf8mb4');
