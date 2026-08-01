<?php
/**
 * Tareas de inglés del alumno: una lista de tareas creadas por el mentor
 * (una por destreza), cada una con su fecha de entrega y su estado. Al
 * completarla, el alumno ve su nota y puede revisar sus respuestas.
 */
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];

// SkillCoins ganadas hoy con inglés (informativo)
$st = db()->prepare("SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
                     WHERE alumno_id = ? AND motivo LIKE 'Inglés%' AND DATE(fecha) = CURDATE()");
$st->execute([$aid]);
$hoyIngles = (int) $st->fetchColumn();

$cfg       = ingles_config($aid);
$tareas    = ingles_tareas_de($aid);
$destrezas = ingles_destrezas();
$hoy       = date('Y-m-d');

render_header('Inglés', 'alumno', $mundo, nav_alumno('ingles'));
?>
<h1 class="page-title">Tareas de inglés 🇬🇧</h1>

<!-- Lista de tareas -->
<div id="pantallaInicio">
    <div class="card">
        <p class="muted" style="margin:0">
            Cada acierto vale <strong><?= (int)$cfg['coins_acierto'] ?> SkillCoin<?= $cfg['coins_acierto'] === 1 ? '' : 's' ?></strong>
            (máximo <?= (int)$cfg['tope_diario'] ?> al día).
            <?php if ($cfg['tiempo_seg'] > 0): ?>Tienes <strong><?= (int)$cfg['tiempo_seg'] ?> segundos</strong> por ejercicio. ⏱<?php endif; ?>
            Hoy llevas <strong><?= $hoyIngles ?></strong> 🪙 de inglés.
        </p>
    </div>

    <?php if (!$tareas): ?>
        <div class="card"><p class="muted">Tu mentor todavía no te ha puesto tareas de inglés. ¡Vuelve pronto!</p></div>
    <?php endif; ?>

    <?php foreach ($tareas as $t):
        $hecha    = $t['resultado_id'] !== null;
        $nota     = $hecha ? nota_sobre_10((int) $t['aciertos'], (int) $t['total']) : null;
        $atrasada = !$hecha && $t['fecha_entrega'] && $t['fecha_entrega'] < $hoy;
        $det      = $hecha ? (array) json_decode((string) $t['detalle'], true) : [];
    ?>
    <div class="card tarea <?= $hecha ? 'tarea-hecha' : '' ?>">
        <div class="tarea-cab">
            <div>
                <h3><?= e($t['titulo']) ?></h3>
                <div class="obj-tags">
                    <span class="tag"><?= e($destrezas[$t['destreza']] ?? ucfirst($t['destreza'])) ?></span>
                    <?php if ($t['fecha_entrega']): ?>
                        <span class="tag <?= $atrasada ? 'prio-alta' : '' ?>">📅 Entrega:
                            <?= e(date('d/m/Y', strtotime($t['fecha_entrega']))) ?><?= $atrasada ? ' · ¡atrasada!' : '' ?></span>
                    <?php endif; ?>
                    <?php if ($hecha): ?>
                        <span class="tag nivel">✅ Completada</span>
                        <span class="tag gold">Nota: <?= nota_txt($nota) ?>/10</span>
                    <?php else: ?>
                        <span class="tag prio-media">⬜ Pendiente</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tarea-acciones">
                <?php if ($hecha): ?>
                    <button class="btn-sm tarea-revisar" type="button" data-rev="rev<?= (int)$t['id'] ?>">Ver mis respuestas</button>
                <?php endif; ?>
                <button class="btn tarea-empezar" type="button" data-tarea="<?= (int)$t['id'] ?>">
                    <?= $hecha ? 'Repetir' : 'Empezar' ?>
                </button>
            </div>
        </div>

        <?php if ($hecha): ?>
        <div class="tarea-revision" id="rev<?= (int)$t['id'] ?>" hidden>
            <p class="muted"><?= (int)$t['aciertos'] ?> de <?= (int)$t['total'] ?> aciertos ·
                hecha el <?= e(date('d/m/Y H:i', strtotime($t['hecha_en']))) ?></p>
            <?php if ($det): ?>
            <ul class="revision-lista">
                <?php foreach ($det as $d): if (!is_array($d)) continue; $ok = !empty($d['ok']); ?>
                <li class="<?= $ok ? 'rev-ok' : 'rev-mal' ?>">
                    <span class="rev-ico"><?= $ok ? '✅' : '❌' ?></span>
                    <div>
                        <strong><?= e($d['pregunta'] ?? '') !== '' ? e($d['pregunta']) : e($d['clave'] ?? '') ?></strong>
                        <?php if (!empty($d['respuesta'])): ?>
                            <div class="muted">Tu respuesta: <?= e($d['respuesta']) ?></div>
                        <?php endif; ?>
                        <?php if (!$ok && !empty($d['solucion'])): ?>
                            <div class="rev-solucion">Correcta: <?= e($d['solucion']) ?></div>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p class="muted">No hay detalle guardado de esta tarea.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Ejercicios de la tarea -->
<div class="card" id="pantallaJuego" hidden>
    <div class="ingles-top">
        <div class="progress"><div class="progress-fill" id="inglesBarra" style="width:0%"></div></div>
        <span class="ingles-timer" id="inglesTimer" hidden></span>
        <span class="ingles-racha" id="inglesRacha" hidden></span>
        <span class="muted" id="inglesPaso"></span>
    </div>
    <div id="ejercicio"></div>
    <div class="ingles-pie" id="inglesPie" hidden>
        <div class="ingles-feedback" id="inglesFeedback" hidden></div>
        <button class="btn" id="inglesAccion" type="button" hidden>Continue</button>
    </div>
</div>

<!-- Resultado -->
<div class="card" id="pantallaFin" hidden>
    <div class="ingles-fin">
        <div class="ingles-fin-ico" id="finIco">🏆</div>
        <h3 id="finTitulo"></h3>
        <p class="muted" id="finDetalle"></p>
        <button class="btn" id="inglesOtra" type="button">Back to my tasks</button>
    </div>
</div>

<!-- Salir / volver al modo pantalla completa (solo visibles en móvil) -->
<button class="ingles-salir" id="inglesSalir" type="button" aria-label="Exit full screen">✕</button>
<button class="ingles-entrar" id="inglesEntrar" type="button" aria-label="Full screen">⛶</button>

<script>
    window.KIRU_INGLES = { api: <?= json_encode(url('alumno/ingles_api.php')) ?> };

    // Desplegar/ocultar la revisión de respuestas de cada tarea completada.
    document.querySelectorAll('.tarea-revisar').forEach(function (b) {
        b.addEventListener('click', function () {
            var rev = document.getElementById(b.dataset.rev);
            if (!rev) return;
            rev.hidden = !rev.hidden;
            b.textContent = rev.hidden ? 'Ver mis respuestas' : 'Ocultar respuestas';
        });
    });
</script>
<script src="<?= asset('assets/js/ingles.js') ?>"></script>
<?php render_footer(); ?>
