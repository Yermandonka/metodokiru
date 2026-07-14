<?php
/**
 * Suma minutos de uso del día. Lo llama app.js cada minuto.
 * Devuelve JSON con el uso acumulado y el máximo.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user || $user['rol'] !== 'alumno') {
    http_response_code(401);
    echo json_encode(['error' => 'no auth']);
    exit;
}
$alumno = alumno_por_usuario((int) $user['id']);
if (!$alumno) {
    http_response_code(404);
    echo json_encode(['error' => 'sin ficha']);
    exit;
}

$aid = (int) $alumno['id'];
$mins = max(0, min(5, (int) ($_POST['mins'] ?? 1))); // como mucho 5 min por latido

db()->prepare(
    'INSERT INTO uso_diario (alumno_id, fecha, minutos) VALUES (?, CURDATE(), ?)
     ON DUPLICATE KEY UPDATE minutos = minutos + VALUES(minutos)'
)->execute([$aid, $mins]);

echo json_encode([
    'uso' => uso_hoy($aid),
    'max' => (TIEMPO_LIMITE_ACTIVO ? (int) $alumno['tiempo_maximo'] : 0),
]);
