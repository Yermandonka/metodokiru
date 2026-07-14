<?php
/**
 * Contexto compartido de las páginas del alumno.
 * Deja disponibles: $user, $alumno, $nivel, $mundo, $navAlumno, $usoHoy, $tiempoMax.
 * Registra la conexión y calcula el nivel/mundo actual.
 */
require_once __DIR__ . '/functions.php';

$user = require_login(['alumno']);

$alumno = alumno_por_usuario((int) $user['id']);
if (!$alumno) {
    die('Este usuario no tiene ficha de alumno. Avisa al administrador.');
}
$alumno['nombre']   = $user['nombre'];
$alumno['username'] = $user['username'];

registrar_conexion((int) $alumno['id']);

$nivel = nivel_actual($alumno);
$mundo = $nivel['mundo'];

$usoHoy = uso_hoy((int) $alumno['id']);
// Si el límite global está desactivado, max = 0 (sin límite) pase lo que pase en la BD.
$tiempoMax = (TIEMPO_LIMITE_ACTIVO ? (int) $alumno['tiempo_maximo'] : 0);

// Datos para el widget de tiempo de uso (lo pinta render_footer).
$GLOBALS['KIRU_USO'] = ['uso' => $usoHoy, 'max' => $tiempoMax];

function nav_alumno(string $activo): array
{
    $items = [
        ['Inicio', 'alumno/index.php', 'index'],
        ['Objetivos', 'alumno/objetivos.php', 'objetivos'],
        ['SkillCoins', 'alumno/skillcoins.php', 'skillcoins'],
        ['Estadísticas', 'alumno/estadisticas.php', 'estadisticas'],
        ['Recompensas', 'alumno/recompensas.php', 'recompensas'],
    ];
    return array_map(fn($i) => [$i[0], $i[1], $i[2] === $activo], $items);
}
