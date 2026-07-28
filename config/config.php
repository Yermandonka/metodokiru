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

// --- Sesión persistente ("mantener sesión iniciada") ---
// Días que se recuerda al usuario sin volver a pedir contraseña.
define('REMEMBER_DIAS', 90);
define('REMEMBER_COOKIE', 'kiru_recuerdame');

// --- IA (Fase 2) · Google Gemini (tiene nivel gratuito) ---
// La clave se busca en 2 sitios (por este orden):
//   1) Variable de entorno GEMINI_API_KEY  (recomendado en Railway)
//   2) Archivo config/gemini_key.txt        (cómodo en local; está en .gitignore)
$geminiKey = getenv('GEMINI_API_KEY') ?: '';
if (!$geminiKey && is_file(__DIR__ . '/gemini_key.txt')) {
    $geminiKey = trim((string) file_get_contents(__DIR__ . '/gemini_key.txt'));
}
define('GEMINI_API_KEY', $geminiKey);
// Modelo gratuito por defecto (se puede cambiar con la variable GEMINI_MODEL).
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

error_reporting(E_ALL);
