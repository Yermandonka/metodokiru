<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

function nav_admin(string $a): array
{
    $items = [
        ['Resumen', 'admin/index.php', 'index'],
        ['Usuarios', 'admin/usuarios.php', 'usuarios'],
        ['Niveles', 'admin/niveles.php', 'niveles'],
    ];
    return array_map(fn($i) => [$i[0], $i[1], $i[2] === $a], $items);
}
