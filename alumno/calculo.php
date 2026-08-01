<?php
/**
 * Cálculo: práctica de cálculo mental con Calculalo (calculalo.app),
 * incrustada dentro del campus como una pestaña más del alumno.
 */
require_once __DIR__ . '/../includes/alumno_ctx.php';
require_once __DIR__ . '/../includes/layout.php';

render_header('Cálculo', 'alumno', $mundo, nav_alumno('calculo'));
?>
<h1 class="page-title">Cálculo 🧮</h1>

<div class="card calculo-card">
    <iframe src="https://calculalo.app" title="Calculalo — práctica de cálculo mental"
            loading="lazy" allow="fullscreen"></iframe>
</div>
<p class="muted calculo-alt">¿No se ve bien?
    <a class="link" href="https://calculalo.app" target="_blank" rel="noopener">Abrir Calculalo en una pestaña nueva</a>
</p>
<?php render_footer(); ?>
