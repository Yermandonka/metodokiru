<?php
require_once __DIR__ . '/includes/functions.php';
remember_olvidar();
$_SESSION = [];
session_destroy();
redirect('login.php');
