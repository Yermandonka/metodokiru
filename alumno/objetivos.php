<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];

// Acciones: completar objetivo / hábito (otorga SkillCoins una sola vez)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'objetivo') {
        $id = post_int('id', 1);
        $st = db()->prepare('SELECT * FROM objetivos WHERE id = ? AND alumno_id = ?');
        $st->execute([$id, $aid]);
        if (($o = $st->fetch()) && !$o['completado']) {
            db()->prepare('UPDATE objetivos SET completado = 1 WHERE id = ?')->execute([$id]);
            if ((int) $o['skillcoins'] > 0) {
                sc_add($aid, (int) $o['skillcoins'], 'Objetivo: ' . $o['titulo']);
            }
            flash('¡Objetivo completado! +' . (int) $o['skillcoins'] . ' SkillCoins');
        }
    } elseif (($_POST['accion'] ?? '') === 'habito') {
        $id = post_int('id', 1);
        $st = db()->prepare('SELECT * FROM habitos WHERE id = ? AND alumno_id = ?');
        $st->execute([$id, $aid]);
        if (($h = $st->fetch()) && !$h['completado']) {
            db()->prepare('UPDATE habitos SET completado = 1 WHERE id = ?')->execute([$id]);
            sc_add($aid, 5, 'Hábito: ' . $h['titulo']);
            flash('¡Hábito completado! +5 SkillCoins');
        }
    }
    redirect('alumno/objetivos.php');
}

$objetivos = db()->prepare('SELECT * FROM objetivos WHERE alumno_id = ? ORDER BY completado, FIELD(prioridad,"alta","media","baja")');
$objetivos->execute([$aid]);
$objetivos = $objetivos->fetchAll();

$habitos = db()->prepare('SELECT * FROM habitos WHERE alumno_id = ? ORDER BY completado, id');
$habitos->execute([$aid]);
$habitos = $habitos->fetchAll();

render_header('Objetivos', 'alumno', $mundo, nav_alumno('objetivos'));
?>
<h1 class="page-title">Tus objetivos</h1>
<div class="grid-2">
    <div class="card">
        <h3>Objetivos</h3>
        <ul class="objlist">
            <?php foreach ($objetivos as $o): ?>
                <li class="<?= $o['completado'] ? 'done' : '' ?>">
                    <div class="obj-main">
                        <span class="check"><?= $o['completado'] ? '✅' : '⬜' ?></span>
                        <div>
                            <strong><?= e($o['titulo']) ?></strong>
                            <?php if ($o['descripcion']): ?><div class="muted"><?= e($o['descripcion']) ?></div><?php endif; ?>
                            <div class="obj-tags">
                                <span class="tag prio-<?= e($o['prioridad']) ?>"><?= e(ucfirst($o['prioridad'])) ?></span>
                                <?php if ($o['fecha']): ?><span class="tag">📅 <?= e($o['fecha']) ?></span><?php endif; ?>
                                <?php if ($o['skillcoins']): ?><span class="tag gold">+<?= (int)$o['skillcoins'] ?> 🪙</span><?php endif; ?>
                                <?php if ($o['recompensa']): ?><span class="tag">🎁 <?= e($o['recompensa']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!$o['completado']): ?>
                    <form method="post" class="inline">
                        <input type="hidden" name="accion" value="objetivo">
                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <button class="btn-sm">Completar</button>
                    </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$objetivos): ?><li class="muted">Sin objetivos por ahora.</li><?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h3>Hábitos de hoy</h3>
        <ul class="objlist">
            <?php foreach ($habitos as $h): ?>
                <li class="<?= $h['completado'] ? 'done' : '' ?>">
                    <div class="obj-main">
                        <span class="check"><?= $h['completado'] ? '✅' : '⬜' ?></span>
                        <strong><?= e($h['titulo']) ?></strong>
                    </div>
                    <?php if (!$h['completado']): ?>
                    <form method="post" class="inline">
                        <input type="hidden" name="accion" value="habito">
                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                        <button class="btn-sm">Hecho</button>
                    </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$habitos): ?><li class="muted">Sin hábitos asignados.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?php render_footer(); ?>
