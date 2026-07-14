<?php
require_once __DIR__ . '/_common.php';
$user = require_login(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['nivel'] as $id => $datos) {
        db()->prepare('UPDATE niveles SET nombre=?, skillcoins_necesarias=?, mundo=? WHERE id=?')
            ->execute([
                trim($datos['nombre']),
                max(0, (int) $datos['skillcoins_necesarias']),
                $datos['mundo'],
                (int) $id,
            ]);
    }
    flash('Niveles actualizados.');
    redirect('admin/niveles.php');
}

$niveles = niveles_todos();
$mundos = ['bosque' => 'Bosque', 'ciudad' => 'Ciudad', 'montana' => 'Montaña', 'cielo' => 'Cielo'];

render_header('Niveles', 'panel', '', nav_admin('niveles'));
?>
<div class="page-head">
    <h1>Niveles y mundos</h1>
    <p class="muted">Define cuántas SkillCoins hacen falta para cada nivel. El fondo del alumno cambia solo al subir de nivel.</p>
</div>
<div class="card">
    <form method="post">
        <table class="tabla">
            <thead><tr><th>Orden</th><th>Nombre</th><th>SkillCoins necesarias</th><th>Mundo (fondo)</th></tr></thead>
            <tbody>
            <?php foreach ($niveles as $n): ?>
                <tr>
                    <td><?= (int)$n['orden'] ?></td>
                    <td><input type="text" name="nivel[<?= (int)$n['id'] ?>][nombre]" value="<?= e($n['nombre']) ?>"></td>
                    <td><input type="number" name="nivel[<?= (int)$n['id'] ?>][skillcoins_necesarias]" value="<?= (int)$n['skillcoins_necesarias'] ?>" min="0"></td>
                    <td>
                        <select name="nivel[<?= (int)$n['id'] ?>][mundo]">
                            <?php foreach ($mundos as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $n['mundo'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button class="btn" style="margin-top:16px">Guardar niveles</button>
    </form>
</div>
<?php render_footer(); ?>
