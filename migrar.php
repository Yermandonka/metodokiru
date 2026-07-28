<?php
/**
 * Migración única — elimina de la base de datos lo que quedaba del "tiempo de uso":
 *   - la tabla uso_diario
 *   - la columna alumnos.tiempo_maximo
 *
 * Ábrelo UNA vez en el navegador (en local y/o en producción):
 *   http://localhost/metodokiru/migrar.php  ·  https://TU-DOMINIO/migrar.php
 * Es idempotente (se puede repetir sin romper nada). Bórralo cuando termines.
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');
$log = [];

try {
    $pdo = db();

    $pdo->exec('DROP TABLE IF EXISTS uso_diario');
    $log[] = 'Tabla «uso_diario» eliminada (si existía).';

    $col = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = 'alumnos' AND column_name = 'tiempo_maximo'"
    );
    $col->execute([DB_NAME]);
    if ($col->fetchColumn()) {
        $pdo->exec('ALTER TABLE alumnos DROP COLUMN tiempo_maximo');
        $log[] = 'Columna «alumnos.tiempo_maximo» eliminada.';
    } else {
        $log[] = 'La columna «alumnos.tiempo_maximo» ya no existía.';
    }

    $ok = true;
} catch (Throwable $ex) {
    $ok = false;
    $error = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Migración · Método Kiru</title>
<style>
 body{font-family:system-ui,sans-serif;background:#f4efe6;color:#3a322a;max-width:640px;margin:40px auto;padding:0 20px}
 h1{color:#6b5b4a} .ok{color:#3c7a4a} .err{color:#b23b3b}
 ul{background:#fff;border:1px solid #e4d9c7;border-radius:12px;padding:16px 28px}
 li{margin:4px 0} a.btn{display:inline-block;margin-top:20px;background:#6b5b4a;color:#fff;
   text-decoration:none;padding:12px 22px;border-radius:10px} pre{white-space:pre-wrap}
 code{background:#efe7d8;padding:2px 6px;border-radius:5px}
</style></head><body>
<h1>Migración: adiós al tiempo de uso</h1>
<?php if (!empty($ok)): ?>
    <p class="ok"><strong>✔ Migración completada.</strong></p>
    <ul><?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul>
    <a class="btn" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Volver a la web →</a>
    <p style="margin-top:24px;color:#8a7c6a">Por seguridad, borra <code>migrar.php</code> cuando termines.</p>
<?php else: ?>
    <p class="err"><strong>✖ Error durante la migración:</strong></p>
    <pre class="err"><?= htmlspecialchars($error ?? 'desconocido') ?></pre>
    <p>Revisa que la base de datos esté accesible y vuelve a intentarlo.</p>
<?php endif; ?>
</body></html>
