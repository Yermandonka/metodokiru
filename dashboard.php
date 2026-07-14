<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
switch ($user['rol']) {
    case 'admin':  redirect('admin/index.php');
    case 'mentor': redirect('mentor/index.php');
    default:       redirect('alumno/index.php');
}
