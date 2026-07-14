<?php
/**
 * Punto de entrada: envía al panel si hay sesión, o a la pantalla de acceso.
 */
require_once __DIR__ . '/includes/functions.php';
redirect(current_user() ? 'dashboard.php' : 'login.php');
