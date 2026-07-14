<?php
require_once __DIR__ . '/config.php';

/**
 * Devuelve una conexión PDO única a la base de datos.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<h2>Error de conexión a la base de datos.</h2>'
                . '<p>¿Has ejecutado <a href="' . BASE_URL . '/install.php">install.php</a>? '
                . '¿MySQL está encendido en XAMPP?</p>'
                . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
        }
    }
    return $pdo;
}
