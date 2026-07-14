<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
switch ($user['rol']) {
    case 'admin':  redirect('admin/resumen.php');
    case 'mentor': redirect('mentor/dashboard.php');
    default:       redirect('alumno/inicio.php');
}
