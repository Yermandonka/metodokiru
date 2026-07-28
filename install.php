<?php
/**
 * Instalador — crea la base de datos, las tablas y datos de ejemplo.
 * Ábrelo una vez en el navegador: http://localhost/metodokiru/install.php
 * Añade ?reset=1 para borrar y recrear todo.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');
$reset = isset($_GET['reset']);
$log = [];

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($reset) {
        $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
        $log[] = 'Base de datos anterior eliminada.';
    }

    // En hosting compartido normalmente la BD ya existe y no se puede crear.
    try {
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $log[] = 'Base de datos «' . DB_NAME . '» lista.';
    } catch (PDOException $e) {
        $log[] = 'Nota: no se pudo crear la BD (normal en hosting compartido); se usará la existente.';
    }
    $pdo->exec('USE `' . DB_NAME . '`');

    $tables = [
        'niveles' => "CREATE TABLE IF NOT EXISTS niveles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL,
            orden INT NOT NULL,
            skillcoins_necesarias INT NOT NULL DEFAULT 0,
            mundo VARCHAR(30) NOT NULL
        ) ENGINE=InnoDB",

        'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            rol ENUM('alumno','mentor','admin') NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        'alumnos' => "CREATE TABLE IF NOT EXISTS alumnos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            edad INT,
            mentor_id INT NULL,
            nivel_id INT NULL,
            avatar VARCHAR(40) DEFAULT 'zorro',
            mensaje_diario TEXT,
            ultima_conexion DATETIME NULL,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        'skillcoins_log' => "CREATE TABLE IF NOT EXISTS skillcoins_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumno_id INT NOT NULL,
            cantidad INT NOT NULL,
            motivo VARCHAR(255),
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (alumno_id, fecha)
        ) ENGINE=InnoDB",

        'objetivos' => "CREATE TABLE IF NOT EXISTS objetivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumno_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT,
            prioridad ENUM('baja','media','alta') DEFAULT 'media',
            fecha DATE NULL,
            recompensa VARCHAR(255),
            skillcoins INT DEFAULT 0,
            completado TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB",

        'habitos' => "CREATE TABLE IF NOT EXISTS habitos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumno_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            completado TINYINT(1) DEFAULT 0,
            fecha DATE NULL
        ) ENGINE=InnoDB",

        'recompensas' => "CREATE TABLE IF NOT EXISTS recompensas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumno_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            costo INT NOT NULL,
            activa TINYINT(1) DEFAULT 1,
            conseguida TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB",

        'conexiones' => "CREATE TABLE IF NOT EXISTS conexiones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumno_id INT NOT NULL,
            fecha DATE NOT NULL,
            INDEX (alumno_id, fecha)
        ) ENGINE=InnoDB",

        'notificaciones' => "CREATE TABLE IF NOT EXISTS notificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mentor_id INT NOT NULL,
            alumno_id INT NULL,
            mensaje VARCHAR(255) NOT NULL,
            leida TINYINT(1) DEFAULT 0,
            creada_en DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        'materias' => "CREATE TABLE IF NOT EXISTS materias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumno_id INT NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            activa TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB",

        'sesiones_recordadas' => "CREATE TABLE IF NOT EXISTS sesiones_recordadas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            selector CHAR(24) NOT NULL UNIQUE,
            validador_hash CHAR(64) NOT NULL,
            expira DATETIME NOT NULL,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (usuario_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
    ];

    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        $log[] = "Tabla «{$name}» lista.";
    }

    /* ---------- Semilla (solo si no hay usuarios) ---------- */
    $hayUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if (!$hayUsuarios) {
        // Niveles / mundos
        $niveles = [
            ['Explorador', 1, 0,   'bosque'],
            ['Constructor', 2, 100, 'ciudad'],
            ['Líder', 3, 300, 'montana'],
            ['Maestro', 4, 600, 'cielo'],
        ];
        $stn = $pdo->prepare('INSERT INTO niveles (nombre, orden, skillcoins_necesarias, mundo) VALUES (?,?,?,?)');
        foreach ($niveles as $n) {
            $stn->execute($n);
        }

        $mkUser = function (PDO $pdo, string $u, string $p, string $rol, string $nombre): int {
            $st = $pdo->prepare('INSERT INTO usuarios (username, password_hash, rol, nombre) VALUES (?,?,?,?)');
            $st->execute([$u, password_hash($p, PASSWORD_DEFAULT), $rol, $nombre]);
            return (int) $pdo->lastInsertId();
        };

        $adminId  = $mkUser($pdo, 'admin', 'admin123', 'admin', 'Administrador');
        $mentorId = $mkUser($pdo, 'mentor', 'mentor123', 'mentor', 'Ana (Mentora)');

        // Alumnos de ejemplo
        $mkAlumno = function (PDO $pdo, int $uid, int $edad, int $mentorId, string $msg) {
            $st = $pdo->prepare('INSERT INTO alumnos (usuario_id, edad, mentor_id, mensaje_diario)
                                 VALUES (?,?,?,?)');
            $st->execute([$uid, $edad, $mentorId, $msg]);
            return (int) $pdo->lastInsertId();
        };

        $uLeo = $mkUser($pdo, 'alumno', 'alumno123', 'alumno', 'Leo');
        $aLeo = $mkAlumno($pdo, $uLeo, 12, $mentorId, 'Hoy recuerda preparar la mochila antes de dormir.');

        $uMar = $mkUser($pdo, 'mara', 'mara123', 'alumno', 'Mara');
        $aMar = $mkAlumno($pdo, $uMar, 11, $mentorId, 'Solo te faltan unas monedas para tu recompensa.');

        $uIzan = $mkUser($pdo, 'izan', 'izan123', 'alumno', 'Izan');
        $aIzan = $mkAlumno($pdo, $uIzan, 13, $mentorId, 'Esta semana tu objetivo es llegar puntual todos los días.');

        // SkillCoins de ejemplo repartidos en el tiempo
        $sc = $pdo->prepare('INSERT INTO skillcoins_log (alumno_id, cantidad, motivo, fecha) VALUES (?,?,?,?)');
        $siembraSC = function ($alumnoId, $pares) use ($sc) {
            foreach ($pares as [$dias, $cant, $motivo]) {
                $f = (new DateTime("-$dias days"))->format('Y-m-d H:i:s');
                $sc->execute([$alumnoId, $cant, $motivo, $f]);
            }
        };
        $siembraSC($aLeo, [
            [25, 30, 'Objetivo cumplido'], [18, 20, 'Hábito: leer'], [12, 25, 'Puntualidad'],
            [6, 15, 'Objetivo cumplido'], [3, 20, 'Racha'], [1, 15, 'Hábito'], [0, 10, 'Entrada diaria'],
        ]);
        $siembraSC($aMar, [[10, 40, 'Objetivos'], [4, 30, 'Hábitos'], [1, 15, 'Racha']]);
        $siembraSC($aIzan, [[20, 120, 'Reto mensual'], [9, 90, 'Objetivos'], [2, 130, 'Constancia']]);

        // Objetivos
        $ob = $pdo->prepare('INSERT INTO objetivos (alumno_id, titulo, descripcion, prioridad, fecha, recompensa, skillcoins, completado)
                             VALUES (?,?,?,?,?,?,?,?)');
        $ob->execute([$aLeo, 'Preparar mochila', 'Dejarla lista antes de dormir', 'alta', date('Y-m-d'), 'Tiempo de juego', 10, 1]);
        $ob->execute([$aLeo, 'Leer 15 minutos', 'Un capítulo del libro de aventuras', 'media', date('Y-m-d'), '', 15, 0]);
        $ob->execute([$aLeo, 'Revisar agenda', 'Comprobar deberes del día', 'baja', date('Y-m-d'), '', 5, 0]);

        // Hábitos
        $hb = $pdo->prepare('INSERT INTO habitos (alumno_id, titulo, completado, fecha) VALUES (?,?,?,?)');
        $hb->execute([$aLeo, 'Beber agua', 1, date('Y-m-d')]);
        $hb->execute([$aLeo, 'Hacer la cama', 0, date('Y-m-d')]);
        $hb->execute([$aLeo, 'Estudiar 20 min', 0, date('Y-m-d')]);

        // Recompensa activa
        $rc = $pdo->prepare('INSERT INTO recompensas (alumno_id, titulo, costo, activa, conseguida) VALUES (?,?,?,1,0)');
        $rc->execute([$aLeo, 'Ir al cine', 155]);
        $rc->execute([$aMar, 'Nueva libreta Kiru', 100]);
        $rc->execute([$aIzan, 'Excursión especial', 400]);

        // Conexiones (racha)
        $cx = $pdo->prepare('INSERT INTO conexiones (alumno_id, fecha) VALUES (?,?)');
        foreach ([0, 1, 2, 3, 4] as $d) {
            $f = (new DateTime("-$d days"))->format('Y-m-d');
            $cx->execute([$aLeo, $f]);
        }

        // Materias (Fase 2)
        $mt = $pdo->prepare('INSERT INTO materias (alumno_id, nombre, activa) VALUES (?,?,?)');
        foreach ([['Matemáticas', 1], ['Inglés', 1], ['Lengua', 0], ['Ciencias', 0]] as $m) {
            $mt->execute([$aLeo, $m[0], $m[1]]);
        }

        // Notificaciones para el mentor
        $nt = $pdo->prepare('INSERT INTO notificaciones (mentor_id, alumno_id, mensaje) VALUES (?,?,?)');
        $nt->execute([$mentorId, $aIzan, 'Izan no entra desde hace 5 días.']);
        $nt->execute([$mentorId, $aLeo, 'Leo ha superado un objetivo.']);

        $log[] = 'Datos de ejemplo insertados.';
    } else {
        $log[] = 'Ya había usuarios: no se vuelven a insertar datos de ejemplo.';
    }

    $ok = true;
} catch (Throwable $ex) {
    $ok = false;
    $error = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Instalación · Método Kiru</title>
<style>
 body{font-family:system-ui,sans-serif;background:#f4efe6;color:#3a322a;max-width:640px;margin:40px auto;padding:0 20px}
 h1{color:#6b5b4a} .ok{color:#3c7a4a} .err{color:#b23b3b}
 ul{background:#fff;border:1px solid #e4d9c7;border-radius:12px;padding:16px 28px}
 li{margin:4px 0} a.btn{display:inline-block;margin-top:20px;background:#6b5b4a;color:#fff;
   text-decoration:none;padding:12px 22px;border-radius:10px} pre{white-space:pre-wrap}
 .creds{background:#fff;border:1px solid #e4d9c7;border-radius:12px;padding:14px 20px;margin-top:16px}
 code{background:#efe7d8;padding:2px 6px;border-radius:5px}
</style></head><body>
<h1>Instalación de Método Kiru</h1>
<?php if (!empty($ok)): ?>
    <p class="ok"><strong>✔ Instalación completada.</strong></p>
    <ul><?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul>
    <div class="creds">
        <p><strong>Cuentas de acceso de ejemplo:</strong></p>
        <ul style="border:none;padding-left:18px">
            <li>Admin — <code>admin</code> / <code>admin123</code></li>
            <li>Mentor — <code>mentor</code> / <code>mentor123</code></li>
            <li>Alumno — <code>alumno</code> / <code>alumno123</code> (Leo)</li>
            <li>Alumno — <code>mara</code> / <code>mara123</code> · <code>izan</code> / <code>izan123</code></li>
        </ul>
    </div>
    <a class="btn" href="<?= htmlspecialchars(BASE_URL) ?>/index.php">Ir a iniciar sesión →</a>
    <p style="margin-top:24px;color:#8a7c6a">Por seguridad, borra <code>install.php</code> cuando termines.</p>
<?php else: ?>
    <p class="err"><strong>✖ Error durante la instalación:</strong></p>
    <pre class="err"><?= htmlspecialchars($error ?? 'desconocido') ?></pre>
    <p>Revisa que MySQL esté encendido en XAMPP y las credenciales en <code>config/config.php</code>.</p>
<?php endif; ?>
</body></html>
