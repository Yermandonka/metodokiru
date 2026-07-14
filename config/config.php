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
    /* ---------- PRODUCCIÓN (InfinityFree) ----------
       Rellena estos 4 valores con los de tu panel:
       vPanel → MySQL Databases. */
    define('DB_HOST', 'sqlXXX.infinityfree.com'); // "MySQL Host Name"
    define('DB_PORT', '3306');
    define('DB_USER', 'epiz_XXXXXXX');            // "MySQL User Name"
    define('DB_PASS', 'TU_CONTRASENA_BD');        // contraseña de tu cuenta
    define('DB_NAME', 'epiz_XXXXXXX_kiru');       // "MySQL Database Name"
    define('BASE_URL', '');                        // la app va en la raíz del dominio
    ini_set('display_errors', '0');               // no mostrar errores en público
}

// --- Aplicación ---
define('APP_NAME', 'Método Kiru');

// --- IA (Fase 2, opcional) ---
// Se lee de una variable de entorno para no dejar la clave en el código.
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

error_reporting(E_ALL);
