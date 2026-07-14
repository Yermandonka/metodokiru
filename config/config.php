<?php
/**
 * Configuración global — Método Kiru (MVP web · XAMPP)
 * Ajusta las credenciales si tu MySQL de XAMPP usa otras.
 */

// --- Base de datos (valores por defecto de XAMPP) ---
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP normalmente sin contraseña
define('DB_NAME', 'metodo_kiru');

// --- Aplicación ---
define('APP_NAME', 'Método Kiru');
// Ruta base (subcarpeta dentro de htdocs). Ej: '/metodokiru'
define('BASE_URL', '/metodokiru');

// --- IA (Fase 2, opcional) ---
// Pega tu clave de OpenAI para activar la generación de contenido académico.
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

// Errores visibles en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', '1');
