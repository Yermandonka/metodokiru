<?php
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';
$aid = (int) $alumno['id'];

// SkillCoins ganadas hoy jugando (informativo)
$st = db()->prepare("SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
                     WHERE alumno_id = ? AND motivo LIKE 'Inglés%' AND DATE(fecha) = CURDATE()");
$st->execute([$aid]);
$hoyIngles = (int) $st->fetchColumn();

$cfg = ingles_config($aid);

render_header('Inglés', 'alumno', $mundo, nav_alumno('ingles'));
?>
<h1 class="page-title">Summer English 🇬🇧</h1>

<!-- Portada -->
<div class="card" id="pantallaInicio">
    <div class="ingles-hero">
        <div class="ingles-ico">🎮</div>
        <div>
            <h3>Play mini-games and learn English!</h3>
            <p class="muted">Each lesson has <?= (int)$cfg['ejercicios'] ?> fun games: translate, complete, order,
                match and listen. One win = <strong><?= (int)$cfg['coins_acierto'] ?> SkillCoin<?= $cfg['coins_acierto'] === 1 ? '' : 's' ?></strong>
                (max <?= (int)$cfg['tope_diario'] ?> a day).
                <?php if ($cfg['tiempo_seg'] > 0): ?>You have <strong><?= (int)$cfg['tiempo_seg'] ?> seconds</strong> per game! ⏱<?php endif; ?>
                Every lesson is new!</p>
            <p class="muted">Today you have <strong><?= $hoyIngles ?></strong> 🪙 from English.</p>
        </div>
    </div>
    <button class="btn" id="inglesEmpezar" type="button">Start lesson</button>
</div>

<!-- Juego -->
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
        <button class="btn" id="inglesOtra" type="button">Play again</button>
    </div>
</div>

<!-- Salir / volver al modo pantalla completa (solo visibles en móvil jugando) -->
<button class="ingles-salir" id="inglesSalir" type="button" aria-label="Exit full screen">✕</button>
<button class="ingles-entrar" id="inglesEntrar" type="button" aria-label="Full screen">⛶</button>

<script>
    window.KIRU_INGLES = { api: <?= json_encode(url('alumno/ingles_api.php')) ?> };
</script>
<script src="<?= asset('assets/js/ingles.js') ?>"></script>
<?php render_footer(); ?>
