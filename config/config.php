<?php
/**
 * Configuración global — Método Kiru (MVP web)
 *
 * Detecta automáticamente el entorno:
 *   - LOCAL  (XAMPP): cuando entras por localhost / 127.0.0.1
 *   - PRODUCCIÓN (InfinityFree): cualquier otro dominio
 * Así el mismo archivo sirve para desarrollar y para el hosting.
 */

$host = $_SERVER['HTTP_HOST'] ?? 'cli';
$esLocal = ($host === 'cli')
    || str_contains($host, 'localhost')
    || str_starts_with($host, '127.')
    || str_starts_with($host, '192.168.');

if ($esLocal) {
    /* ---------- LOCAL (XAMPP) ---------- */
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_USER', 'root');
    define('DB_PASS', '');              // XAMPP normalmente sin contraseña
    define('DB_NAME', 'metodo_kiru');
    define('BASE_URL', '/metodokiru');  // subcarpeta dentro de htdocs
    ini_set('display_errors', '1');
} else {
    /* ---------- PRODUCCIÓN (Railway u otro host) ----------
       Las credenciales llegan por variables de entorno.
       Railway MySQL expone MYSQL_URL (mysql://usuario:pass@host:puerto/base). */
    $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';
    if ($url) {
        $p = parse_url($url);
        define('DB_HOST', $p['host'] ?? 'localhost');
        define('DB_PORT', (string) ($p['port'] ?? 3306));
        define('DB_USER', $p['user'] ?? 'root');
        define('DB_PASS', $p['pass'] ?? '');
        define('DB_NAME', ltrim($p['path'] ?? '/railway', '/'));
    } else {
        // Alternativa: variables sueltas (Railway también ofrece MYSQL*)
        define('DB_HOST', getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost');
        define('DB_PORT', getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306');
        define('DB_USER', getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root');
        define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
        define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway');
    }
    define('BASE_URL', '');            // la app va en la raíz del dominio
    ini_set('display_errors', '0');    // no mostrar errores en público
}

// --- Aplicación ---
define('APP_NAME', 'Método Kiru');

// Límite de tiempo diario del alumno.
// false = DESACTIVADO globalmente (nunca aparece "La sesión de hoy ha terminado").
// true  = se respeta el "tiempo máximo" de cada alumno (0 en ese alumno = sin límite).
define('TIEMPO_LIMITE_ACTIVO', false);

// --- IA (Fase 2, opcional) ---
// Se lee de una variable de entorno para no dejar la clave en el código.
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

error_reporting(E_ALL);
