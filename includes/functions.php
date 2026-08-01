<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sesión de larga duración: la sesión sobrevive al cierre del navegador.
    $vida = 60 * 60 * 24 * REMEMBER_DIAS;
    ini_set('session.gc_maxlifetime', (string) $vida);
    session_set_cookie_params([
        'lifetime' => $vida,
        'path'     => (BASE_URL ?: '') . '/',
        'secure'   => es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/* ---------- Utilidades ---------- */

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Como url() pero añade ?v=<fecha del archivo> para romper la caché del navegador. */
function asset(string $path): string
{
    $full = __DIR__ . '/../' . ltrim($path, '/');
    $v = @filemtime($full) ?: 1;
    return url($path) . '?v=' . $v;
}

/* ---------- Saneado de entradas ---------- */

/** Limpia un texto recibido: recorta, quita caracteres de control y limita la longitud. */
function limpiar_texto(mixed $s, int $max = 255): string
{
    if (!is_scalar($s)) {
        return '';
    }
    $s = trim((string) $s);
    // Elimina caracteres de control (se conservan saltos de línea y tabulador).
    $s = preg_replace('/[^\P{C}\n\t]/u', '', $s) ?? '';
    return mb_substr($s, 0, $max);
}

/** Texto de $_POST ya saneado. */
function post_texto(string $clave, int $max = 255): string
{
    return limpiar_texto($_POST[$clave] ?? '', $max);
}

/** Entero de $_POST acotado a [$min, $max]; $def si no es numérico. */
function post_int(string $clave, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, int $def = 0): int
{
    $v = $_POST[$clave] ?? null;
    $n = (is_scalar($v) && is_numeric(trim((string) $v))) ? (int) $v : $def;
    return max($min, min($max, $n));
}

/** Valor de $_POST solo si está en la lista permitida; si no, $def. */
function post_opcion(string $clave, array $permitidos, string $def): string
{
    $v = $_POST[$clave] ?? '';
    return in_array($v, $permitidos, true) ? $v : $def;
}

/** Formato de nombre de usuario: 3-30 caracteres (letras, números, _ o .). */
function username_valido(string $u): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $u);
}

/** Devuelve la fecha si tiene formato AAAA-MM-DD real; si no, null. */
function fecha_o_null(mixed $f): ?string
{
    $f = is_scalar($f) ? trim((string) $f) : '';
    if ($f === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return ($d && $d->format('Y-m-d') === $f) ? $f : null;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/* ---------- Autenticación ---------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        remember_login_desde_cookie();
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/* ---------- Sesión persistente ("recuérdame") ----------
   Además de la sesión PHP, se guarda un token en una cookie de larga duración.
   La cookie lleva "selector:validador"; en la BD solo se guarda el hash del
   validador, así un volcado de la tabla no permite suplantar a nadie. */

function remember_tabla(): void
{
    static $creada = false;
    if ($creada) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS sesiones_recordadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        selector CHAR(24) NOT NULL UNIQUE,
        validador_hash CHAR(64) NOT NULL,
        expira DATETIME NOT NULL,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (usuario_id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $creada = true;
}

function remember_cookie(string $valor, int $expiraTs): void
{
    setcookie(REMEMBER_COOKIE, $valor, [
        'expires'  => $expiraTs,
        'path'     => (BASE_URL ?: '') . '/',
        'secure'   => es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Crea un token nuevo para el usuario y deja la cookie puesta. */
function remember_crear(int $usuarioId): void
{
    remember_tabla();
    $selector  = bin2hex(random_bytes(12));
    $validador = bin2hex(random_bytes(32));
    $expira    = time() + 60 * 60 * 24 * REMEMBER_DIAS;
    db()->prepare('INSERT INTO sesiones_recordadas (usuario_id, selector, validador_hash, expira) VALUES (?,?,?,?)')
        ->execute([$usuarioId, $selector, hash('sha256', $validador), date('Y-m-d H:i:s', $expira)]);
    remember_cookie($selector . ':' . $validador, $expira);
}

/** Si hay cookie válida, restaura la sesión (y rota el token). */
function remember_login_desde_cookie(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!$cookie || substr_count($cookie, ':') !== 1) {
        return;
    }
    [$selector, $validador] = explode(':', $cookie);
    remember_tabla();
    $stmt = db()->prepare('SELECT * FROM sesiones_recordadas WHERE selector = ? AND expira > NOW()');
    $stmt->execute([$selector]);
    $fila = $stmt->fetch();
    if (!$fila || !hash_equals($fila['validador_hash'], hash('sha256', $validador))) {
        // Cookie inválida o caducada: se limpia para no reintentar en cada página.
        remember_cookie('', time() - 3600);
        return;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $fila['usuario_id'];
    // Rotación: el token usado se sustituye por uno nuevo.
    db()->prepare('DELETE FROM sesiones_recordadas WHERE id = ?')->execute([$fila['id']]);
    remember_crear((int) $fila['usuario_id']);
}

/** Borra el token actual y la cookie (se usa al cerrar sesión). */
function remember_olvidar(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($cookie && substr_count($cookie, ':') === 1) {
        [$selector] = explode(':', $cookie);
        remember_tabla();
        db()->prepare('DELETE FROM sesiones_recordadas WHERE selector = ?')->execute([$selector]);
    }
    remember_cookie('', time() - 3600);
}

function require_login(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    if ($roles && !in_array($user['rol'], $roles, true)) {
        http_response_code(403);
        die('Acceso no autorizado para el rol: ' . e($user['rol']));
    }
    return $user;
}

/* ---------- Alumno ---------- */

function alumno_por_usuario(int $usuarioId): ?array
{
    $stmt = db()->prepare('SELECT * FROM alumnos WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?: null;
}

function alumno_por_id(int $alumnoId): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.nombre, u.username
         FROM alumnos a JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.id = ?'
    );
    $stmt->execute([$alumnoId]);
    return $stmt->fetch() ?: null;
}

/* ---------- SkillCoins ---------- */

function sc_total(int $alumnoId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log WHERE alumno_id = ?');
    $stmt->execute([$alumnoId]);
    return (int) $stmt->fetchColumn();
}

function sc_semana(int $alumnoId): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
         WHERE alumno_id = ? AND YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1)'
    );
    $stmt->execute([$alumnoId]);
    return (int) $stmt->fetchColumn();
}

function sc_mes(int $alumnoId): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
         WHERE alumno_id = ? AND YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())'
    );
    $stmt->execute([$alumnoId]);
    return (int) $stmt->fetchColumn();
}

function sc_add(int $alumnoId, int $cantidad, string $motivo): void
{
    $stmt = db()->prepare('INSERT INTO skillcoins_log (alumno_id, cantidad, motivo) VALUES (?,?,?)');
    $stmt->execute([$alumnoId, $cantidad, $motivo]);
}

/* ---------- Avatares ---------- */

/** Animales disponibles como avatar (clave => emoji). */
function avatares(): array
{
    return [
        'zorro' => '🦊', 'buho' => '🦉', 'lobo' => '🐺', 'dragon' => '🐉',
        'tigre' => '🐯', 'aguila' => '🦅', 'panda' => '🐼', 'gato' => '🐱',
    ];
}

function avatar_emoji(?string $clave): string
{
    return avatares()[$clave] ?? '🦊';
}

/* ---------- Niveles / Mundos ---------- */

function niveles_todos(): array
{
    return db()->query('SELECT * FROM niveles ORDER BY orden')->fetchAll();
}

/** Nivel actual del alumno: override manual o cálculo automático por SkillCoins. */
function nivel_actual(array $alumno): array
{
    $niveles = niveles_todos();
    if (!empty($alumno['nivel_id'])) {
        foreach ($niveles as $n) {
            if ((int) $n['id'] === (int) $alumno['nivel_id']) {
                return $n;
            }
        }
    }
    $total = sc_total((int) $alumno['id']);
    $actual = $niveles[0];
    foreach ($niveles as $n) {
        if ($total >= (int) $n['skillcoins_necesarias']) {
            $actual = $n;
        }
    }
    return $actual;
}

function nivel_siguiente(array $nivelActual): ?array
{
    foreach (niveles_todos() as $n) {
        if ((int) $n['orden'] === (int) $nivelActual['orden'] + 1) {
            return $n;
        }
    }
    return null;
}

/* ---------- Conexiones ---------- */

function registrar_conexion(int $alumnoId): void
{
    db()->prepare('UPDATE alumnos SET ultima_conexion = NOW() WHERE id = ?')->execute([$alumnoId]);
    $hoy = date('Y-m-d');
    $stmt = db()->prepare('SELECT COUNT(*) FROM conexiones WHERE alumno_id = ? AND fecha = ?');
    $stmt->execute([$alumnoId, $hoy]);
    if (!$stmt->fetchColumn()) {
        db()->prepare('INSERT INTO conexiones (alumno_id, fecha) VALUES (?,?)')->execute([$alumnoId, $hoy]);
    }
}

/* ---------- Inglés: configuración del mentor, materiales (PDF) y resultados ---------- */

function ingles_tablas(): void
{
    static $creadas = false;
    if ($creadas) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS ingles_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL UNIQUE,
        ejercicios INT NOT NULL DEFAULT 10,
        coins_acierto INT NOT NULL DEFAULT 1,
        tope_diario INT NOT NULL DEFAULT 50,
        tiempo_seg INT NOT NULL DEFAULT 0,
        temas VARCHAR(255) NOT NULL DEFAULT '',
        FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS ingles_material (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        titulo VARCHAR(100) NOT NULL,
        contenido MEDIUMTEXT NOT NULL,
        activo TINYINT(1) DEFAULT 1,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (alumno_id),
        FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS ingles_resultados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        tarea_id INT NULL,
        aciertos INT NOT NULL,
        total INT NOT NULL,
        coins INT NOT NULL DEFAULT 0,
        detalle TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (alumno_id, fecha),
        FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS ingles_tareas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        titulo VARCHAR(100) NOT NULL,
        destreza VARCHAR(20) NOT NULL,
        fecha_entrega DATE NULL,
        creada_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (alumno_id),
        FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    // Migración perezosa: instalaciones antiguas no tenían la columna tarea_id.
    if (!db()->query("SHOW COLUMNS FROM ingles_resultados LIKE 'tarea_id'")->fetch()) {
        db()->exec('ALTER TABLE ingles_resultados ADD COLUMN tarea_id INT NULL AFTER alumno_id');
    }
    $creadas = true;
}

/** Configuración del juego de inglés del alumno (valores por defecto si el mentor no la ha tocado). */
function ingles_config(int $alumnoId): array
{
    ingles_tablas();
    $st = db()->prepare('SELECT * FROM ingles_config WHERE alumno_id = ?');
    $st->execute([$alumnoId]);
    $cfg = $st->fetch() ?: [];
    return [
        'ejercicios'    => (int) ($cfg['ejercicios'] ?? 10),
        'coins_acierto' => (int) ($cfg['coins_acierto'] ?? 1),
        'tope_diario'   => (int) ($cfg['tope_diario'] ?? 50),
        'tiempo_seg'    => (int) ($cfg['tiempo_seg'] ?? 0),
        'temas'         => (string) ($cfg['temas'] ?? ''),
    ];
}

function ingles_config_guardar(int $alumnoId, array $cfg): void
{
    ingles_tablas();
    db()->prepare(
        'INSERT INTO ingles_config (alumno_id, ejercicios, coins_acierto, tope_diario, tiempo_seg, temas)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE ejercicios=VALUES(ejercicios), coins_acierto=VALUES(coins_acierto),
             tope_diario=VALUES(tope_diario), tiempo_seg=VALUES(tiempo_seg), temas=VALUES(temas)'
    )->execute([
        $alumnoId,
        $cfg['ejercicios'],
        $cfg['coins_acierto'],
        $cfg['tope_diario'],
        $cfg['tiempo_seg'],
        $cfg['temas'],
    ]);
}

/** Texto de los materiales (PDF) activos del alumno, recortado para caber en el prompt. */
function ingles_material_activo(int $alumnoId, int $max = 6000): string
{
    ingles_tablas();
    $st = db()->prepare('SELECT titulo, contenido FROM ingles_material WHERE alumno_id = ? AND activo = 1 ORDER BY id DESC LIMIT 3');
    $st->execute([$alumnoId]);
    $trozos = [];
    foreach ($st->fetchAll() as $m) {
        $trozos[] = '## ' . $m['titulo'] . "\n" . $m['contenido'];
    }
    return mb_substr(implode("\n\n", $trozos), 0, $max);
}

/* ---------- Inglés: tareas (las crea el mentor, una por destreza) ---------- */

/**
 * Tareas de inglés del alumno con su último resultado (si ya la ha hecho).
 * Primero las pendientes (por fecha de entrega), después las completadas.
 */
function ingles_tareas_de(int $alumnoId): array
{
    ingles_tablas();
    $st = db()->prepare(
        'SELECT t.*, r.id AS resultado_id, r.aciertos, r.total, r.detalle, r.fecha AS hecha_en
         FROM ingles_tareas t
         LEFT JOIN ingles_resultados r ON r.id = (
             SELECT r2.id FROM ingles_resultados r2
             WHERE r2.tarea_id = t.id ORDER BY r2.fecha DESC, r2.id DESC LIMIT 1
         )
         WHERE t.alumno_id = ?
         ORDER BY (r.id IS NOT NULL), t.fecha_entrega IS NULL, t.fecha_entrega, t.id'
    );
    $st->execute([$alumnoId]);
    return $st->fetchAll();
}

/** Nota sobre 10 (un decimal) a partir de aciertos/total. */
function nota_sobre_10(int $aciertos, int $total): float
{
    return $total > 0 ? round($aciertos / $total * 10, 1) : 0.0;
}

/** Nota en texto para mostrar: coma decimal y sin «,0» (8,5 · 10). */
function nota_txt(float $nota): string
{
    return rtrim(rtrim(number_format($nota, 1, ',', ''), '0'), ',');
}

/* ---------- Inglés: radar de destrezas (lo ven mentor y familias) ---------- */

/** Nombres de las destrezas del radar, por clave. */
function ingles_destrezas(): array
{
    return [
        'vocabulario' => '📚 Vocabulario',
        'gramatica'   => '🧩 Gramática',
        'lectura'     => '🧠 Comprensión',
        'escucha'     => '👂 Escucha',
        'escritura'   => '⌨️ Escritura',
    ];
}

/** Qué destrezas evalúa cada tipo de reto (y con qué peso). */
function ingles_tipo_destreza(): array
{
    return [
        'eleccion'  => ['vocabulario' => 1.0, 'lectura' => 0.5],
        'parejas'   => ['vocabulario' => 1.0],
        'emoji'     => ['vocabulario' => 1.0],
        'hueco'     => ['gramatica' => 1.0, 'lectura' => 0.5],
        'ordenar'   => ['gramatica' => 1.0, 'escritura' => 0.5],
        'verdadero' => ['lectura' => 1.0],
        'escucha'   => ['escucha' => 1.0],
        'escribir'  => ['escritura' => 1.0, 'vocabulario' => 0.5],
    ];
}

/** Tipos de reto que sirven para practicar una destreza concreta. */
function ingles_tipos_de(string $destreza): array
{
    $tipos = [];
    foreach (ingles_tipo_destreza() as $tipo => $pesos) {
        if (isset($pesos[$destreza])) {
            $tipos[] = $tipo;
        }
    }
    return $tipos;
}

/**
 * Radar de destrezas del alumno: cada tipo de reto puntúa en una o dos
 * destrezas del idioma. Las lecciones recientes pesan más, así el radar
 * refleja el nivel actual y se mueve con cada lección que juega.
 * Devuelve [clave => ['nombre', 'pct' (null = sin datos), 'n']].
 */
function ingles_radar(int $alumnoId): array
{
    $TIPO_DESTREZA = ingles_tipo_destreza();
    $radar = [];
    foreach (ingles_destrezas() as $k => $nombre) {
        $radar[$k] = ['nombre' => $nombre, 'ok' => 0.0, 'peso' => 0.0, 'n' => 0, 'pct' => null];
    }

    ingles_tablas();
    $st = db()->prepare('SELECT detalle FROM ingles_resultados WHERE alumno_id=? ORDER BY fecha DESC LIMIT 30');
    $st->execute([$alumnoId]);
    foreach ($st->fetchAll() as $i => $r) {
        $reciente = pow(0.93, $i); // de la más nueva a la más vieja
        foreach ((array) json_decode((string) $r['detalle'], true) as $d) {
            if (!is_array($d) || empty($d['tipo'])) {
                continue;
            }
            foreach ($TIPO_DESTREZA[$d['tipo']] ?? [] as $dk => $w) {
                $radar[$dk]['peso'] += $w * $reciente;
                $radar[$dk]['n']++;
                if (!empty($d['ok'])) {
                    $radar[$dk]['ok'] += $w * $reciente;
                }
            }
        }
    }
    foreach ($radar as $k => $v) {
        if ($v['peso'] > 0) {
            $radar[$k]['pct'] = (int) round($v['ok'] / $v['peso'] * 100);
        }
    }
    return $radar;
}

/** Dibuja el radar (SVG) con un eje por destreza; pct null = sin datos aún. */
function radar_svg(array $ejes): string
{
    $n = count($ejes);
    $cx = 240.0; $cy = 178.0; $R = 104.0;
    $ang = fn(int $i) => -M_PI / 2 + $i * 2 * M_PI / $n;
    $punto = function (int $i, float $r) use ($ang, $cx, $cy) {
        return [round($cx + $r * cos($ang($i)), 1), round($cy + $r * sin($ang($i)), 1)];
    };
    $poligono = function (float $r) use ($n, $punto) {
        $p = [];
        for ($i = 0; $i < $n; $i++) { [$x, $y] = $punto($i, $r); $p[] = "$x,$y"; }
        return implode(' ', $p);
    };

    $s = '<svg viewBox="0 0 480 336" role="img" aria-label="Radar de destrezas de inglés">';
    // Rejilla: anillos al 25/50/75/100 % y radios hasta cada vértice.
    foreach ([.25, .5, .75, 1] as $f) {
        $s .= '<polygon points="' . $poligono($R * $f) . '" fill="none" stroke="var(--linea)" stroke-width="1"/>';
    }
    for ($i = 0; $i < $n; $i++) {
        [$x, $y] = $punto($i, $R);
        $s .= "<line x1=\"$cx\" y1=\"$cy\" x2=\"$x\" y2=\"$y\" stroke=\"var(--linea)\" stroke-width=\"1\"/>";
    }
    foreach ([25, 50, 75, 100] as $v) {
        $s .= '<text x="' . ($cx + 5) . '" y="' . round($cy - $R * $v / 100 + 4, 1) . '" font-size="9" fill="var(--muted)">' . $v . '</text>';
    }
    // Polígono del alumno.
    $p = [];
    foreach (array_values($ejes) as $i => $e) {
        [$x, $y] = $punto($i, $R * max(0, min(100, (int) ($e['pct'] ?? 0))) / 100);
        $p[] = "$x,$y";
    }
    $s .= '<polygon points="' . implode(' ', $p) . '" fill="#7c8a5a" fill-opacity=".14" stroke="#7c8a5a" stroke-width="2" stroke-linejoin="round"/>';
    // Puntos (huecos si esa destreza aún no tiene datos) y etiquetas.
    foreach (array_values($ejes) as $i => $e) {
        $con = $e['pct'] !== null;
        [$x, $y] = $punto($i, $R * ($con ? max(0, min(100, (int) $e['pct'])) : 0) / 100);
        $s .= $con
            ? "<circle cx=\"$x\" cy=\"$y\" r=\"4.5\" fill=\"#7c8a5a\" stroke=\"var(--crema2)\" stroke-width=\"2\"/>"
            : "<circle cx=\"$x\" cy=\"$y\" r=\"4\" fill=\"var(--crema2)\" stroke=\"var(--muted)\" stroke-width=\"1.5\"/>";
        $cos = cos($ang($i)); $sin = sin($ang($i));
        [$lx, $ly] = $punto($i, $R + 22);
        $anchor = $cos > .15 ? 'start' : ($cos < -.15 ? 'end' : 'middle');
        $sube = $sin < -.3 ? -12 : ($sin > .3 ? 12 : 0); // separa la etiqueta del vértice
        $s .= '<text x="' . $lx . '" y="' . round($ly + $sube, 1) . '" text-anchor="' . $anchor . '" font-size="13" font-weight="700" fill="var(--texto)">' . e($e['nombre']) . '</text>';
        $s .= '<text x="' . $lx . '" y="' . round($ly + $sube + 15, 1) . '" text-anchor="' . $anchor . '" font-size="12" fill="var(--muted)">'
            . ($con ? (int) $e['pct'] . ' %' : 'sin datos') . '</text>';
    }
    return $s . '</svg>';
}

/* ---------- Familias (rol padre: solo visualización) ---------- */

/** Migración perezosa del rol padre: amplía el enum de roles y añade alumnos.padre_id. */
function padres_migracion(): void
{
    static $hecha = false;
    if ($hecha) {
        return;
    }
    $col = db()->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch();
    if ($col && !str_contains((string) $col['Type'], 'padre')) {
        db()->exec("ALTER TABLE usuarios MODIFY rol ENUM('alumno','mentor','admin','padre') NOT NULL");
    }
    if (!db()->query("SHOW COLUMNS FROM alumnos LIKE 'padre_id'")->fetch()) {
        db()->exec('ALTER TABLE alumnos ADD COLUMN padre_id INT NULL AFTER mentor_id');
    }
    $hecha = true;
}

/** Hijos vinculados a una cuenta de padre/madre. */
function hijos_de_padre(int $padreUsuarioId): array
{
    padres_migracion();
    $st = db()->prepare(
        'SELECT a.*, u.nombre, u.username
         FROM alumnos a JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.padre_id = ? ORDER BY u.nombre'
    );
    $st->execute([$padreUsuarioId]);
    return $st->fetchAll();
}

/* ---------- IA (Fase 2) ---------- */

/** POST a la API de Gemini; devuelve la respuesta decodificada o lanza RuntimeException. */
function gemini_request(array $payload): array
{
    if (!GEMINI_API_KEY) {
        throw new RuntimeException('No hay clave de Gemini configurada. Añádela en la variable GEMINI_API_KEY (Railway) o en config/gemini_key.txt (local).');
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_TIMEOUT => 45,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Error de conexión con Gemini: ' . $err);
    }
    curl_close($ch);
    $data = json_decode($resp, true);
    if (isset($data['error'])) {
        throw new RuntimeException('Gemini: ' . ($data['error']['message'] ?? 'error desconocido'));
    }
    return is_array($data) ? $data : [];
}

/**
 * Llama a la API de Google Gemini para generar contenido académico.
 * Devuelve el texto o lanza RuntimeException si algo falla / no hay clave.
 */
function ai_generar(string $tema, int $edad = 12): string
{
    $prompt = "Sobre el tema: \"$tema\", crea contenido educativo en español para un niño de $edad años, "
        . "claro y motivador, en formato Markdown con estas secciones exactas:\n"
        . "## Teoría (breve)\n## Ejemplos\n## Ejercicios (5, estilo Duolingo)\n## Soluciones\n"
        . "Adáptalo a 10-13 años.";

    $data = gemini_request([
        'system_instruction' => [
            'parts' => [['text' => 'Eres un tutor del Método Kiru para niños. Explicas de forma sencilla, amena y positiva.']],
        ],
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => ['temperature' => 0.7],
    ]);
    $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($texto === '') {
        throw new RuntimeException('Gemini no devolvió contenido (posible bloqueo de seguridad o modelo no disponible).');
    }
    return $texto;
}

/**
 * Genera una lección de inglés (ejercicios tipo Duolingo) con Gemini.
 * $evitar: contenidos ya usados que no deben repetirse.
 * $temas: temas preferidos que fija el mentor (separados por comas); vacío = variados.
 * $material: contenido extraído de los PDF del mentor; si existe, los ejercicios se basan en él.
 * $tipos: si la tarea evalúa una destreza concreta, solo se piden estos tipos de reto.
 * Devuelve la lista de ejercicios sin validar; lanza RuntimeException si falla.
 */
function ai_ingles(int $edad, int $cantidad, array $evitar = [], string $temas = '', string $material = '', array $tipos = []): array
{
    if ($temas !== '') {
        $lista = array_values(array_filter(array_map('trim', explode(',', $temas))));
    } else {
        $lista = ['los animales', 'la comida', 'la familia', 'el colegio', 'los deportes',
            'el verano y la playa', 'los viajes', 'la ropa', 'la casa', 'el cuerpo',
            'los colores y los números', 'las rutinas diarias', 'los hobbies', 'la ciudad',
            'el tiempo y las estaciones', 'las emociones', 'la naturaleza', 'las fiestas'];
    }
    $tema = $lista ? $lista[array_rand($lista)] : 'vocabulario general';

    $prompt = "Crea exactamente $cantidad ejercicios de inglés básico (nivel A1-A2, estilo Duolingo) "
        . "para un niño hispanohablante de $edad años. Tema principal: $tema (puedes mezclar con vocabulario general).\n";
    if ($material !== '') {
        $prompt .= "IMPORTANTE: basa el vocabulario y las estructuras de los ejercicios en este material "
            . "que ha preparado su mentor (los temas anteriores son secundarios):\n---\n$material\n---\n";
    }
    $prompt .= ($tipos
            ? 'IMPORTANTE: esta tarea evalúa una destreza concreta; usa SOLO estos tipos de ejercicio: '
                . implode(', ', $tipos) . ". Formato exacto:\n"
            : "Hay 8 tipos de ejercicio; usa al menos 6 distintos en cada lección y mézclalos. Formato exacto:\n")
        . '- {"tipo":"eleccion","pregunta":"How do you say «perro» in English?","opciones":["dog","cat","bird","fish"],"correcta":0}' . "\n"
        . '- {"tipo":"hueco","frase":"I ___ football on Sundays.","opciones":["play","plays","playing","played"],"correcta":0}' . "\n"
        . '- {"tipo":"ordenar","es":"Me gusta la pizza","palabras":["I","like","pizza"],"solucion":"I like pizza"}' . "\n"
        . '- {"tipo":"parejas","pares":[{"es":"rojo","en":"red"},{"es":"azul","en":"blue"},{"es":"verde","en":"green"},{"es":"negro","en":"black"}]}' . "\n"
        . '- {"tipo":"escucha","texto":"apple","opciones":["apple","orange","table","chair"],"correcta":0}' . "\n"
        . '- {"tipo":"verdadero","afirmacion":"\"Dog\" means «perro».","correcta":true}' . "\n"
        . '- {"tipo":"escribir","pregunta":"Write «gato» in English","solucion":"cat"}' . "\n"
        . '- {"tipo":"emoji","emoji":"🍎","opciones":["apple","bread","egg","cheese"],"correcta":0}' . "\n"
        . "Reglas: \"correcta\" es el índice (0-3) de la respuesta correcta dentro de \"opciones\"; "
        . "exactamente 4 opciones; frases cortas y vocabulario cotidiano; "
        . "en \"ordenar\" la solución usa todas las palabras; en \"parejas\" exactamente 4 pares; "
        . "en \"escucha\" el texto debe estar entre las opciones; "
        . "en \"verdadero\" mezcla afirmaciones verdaderas y falsas; "
        . "en \"escribir\" la solución es UNA palabra sencilla; "
        . "en \"emoji\" las opciones son palabras en inglés y una corresponde al emoji.\n"
        . "Los enunciados (\"pregunta\") van en inglés MUY sencillo (nivel A1).\n"
        . "Añade a unos 3 ejercicios un campo extra \"leccion\": 1-2 frases MUY cortas y sencillas "
        . "en inglés (nivel A1, con un emoji) que enseñen justo lo que practica ese ejercicio. "
        . 'Ejemplo: "We use AM with I. 🧒 I am 11 years old."' . "\n";
    if ($evitar) {
        $prompt .= 'NO repitas estos contenidos ya usados: ' . implode(' | ', array_slice($evitar, -40)) . "\n";
    }
    $prompt .= 'Devuelve SOLO este JSON: {"ejercicios":[...]}';

    $data = gemini_request([
        'system_instruction' => [
            'parts' => [['text' => 'Eres un profesor de inglés del Método Kiru para niños. Creas ejercicios variados, divertidos y correctos.']],
        ],
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => ['temperature' => 1.0, 'responseMimeType' => 'application/json'],
    ]);
    $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $json = json_decode($texto, true);
    if (!is_array($json) || empty($json['ejercicios']) || !is_array($json['ejercicios'])) {
        throw new RuntimeException('Gemini no devolvió ejercicios válidos.');
    }
    return $json['ejercicios'];
}

/**
 * Extrae con Gemini el contenido didáctico de un PDF (en base64) para poder
 * crear ejercicios de inglés a partir de él. Lanza RuntimeException si falla.
 */
function ai_pdf_extraer(string $pdfBase64): string
{
    $data = gemini_request([
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => 'application/pdf', 'data' => $pdfBase64]],
                ['text' => 'Extrae de este PDF el contenido útil para crear ejercicios de inglés '
                    . 'para un niño hispanohablante: listas de vocabulario (inglés — español), '
                    . 'estructuras gramaticales con un ejemplo, y los temas que trata. '
                    . 'Devuelve SOLO texto plano organizado en listas, breve (máximo 200 líneas), sin comentarios.'],
            ],
        ]],
        'generationConfig' => ['temperature' => 0.2],
    ]);
    $texto = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($texto === '') {
        throw new RuntimeException('Gemini no pudo leer el PDF (¿es un escaneo sin texto o demasiado grande?).');
    }
    return $texto;
}

/* ---------- Markdown ---------- */

/** Convierte Markdown (el que devuelve la IA) en HTML seguro. */
function md_to_html(string $md): string
{
    $md = str_replace("\r\n", "\n", $md);
    $lines = explode("\n", $md);
    $html = '';
    $inList = null;   // 'ul' | 'ol'
    $inCode = false;
    $para = [];

    $flushPara = function () use (&$para, &$html) {
        if ($para) {
            $html .= '<p>' . md_inline(implode(' ', $para)) . "</p>\n";
            $para = [];
        }
    };
    $closeList = function () use (&$inList, &$html) {
        if ($inList) { $html .= "</$inList>\n"; $inList = null; }
    };

    foreach ($lines as $line) {
        $t = trim($line);

        if (preg_match('/^```/', $t)) {                       // bloque de código
            $flushPara(); $closeList();
            if (!$inCode) { $html .= '<pre class="md-pre"><code>'; $inCode = true; }
            else { $html .= "</code></pre>\n"; $inCode = false; }
            continue;
        }
        if ($inCode) { $html .= htmlspecialchars($line) . "\n"; continue; }

        if ($t === '') { $flushPara(); $closeList(); continue; }

        if (preg_match('/^(#{1,6})\s+(.*)$/', $t, $m)) {      // encabezado
            $flushPara(); $closeList();
            $lvl = strlen($m[1]);
            $html .= "<h$lvl>" . md_inline($m[2]) . "</h$lvl>\n";
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $t)) {     // regla horizontal
            $flushPara(); $closeList();
            $html .= "<hr>\n";
            continue;
        }
        if (preg_match('/^\d+[\.\)]\s+(.*)$/', $t, $m)) {     // lista ordenada
            $flushPara();
            if ($inList !== 'ol') { $closeList(); $html .= "<ol>\n"; $inList = 'ol'; }
            $html .= '<li>' . md_inline($m[1]) . "</li>\n";
            continue;
        }
        if (preg_match('/^[-*+]\s+(.*)$/', $t, $m)) {         // lista no ordenada
            $flushPara();
            if ($inList !== 'ul') { $closeList(); $html .= "<ul>\n"; $inList = 'ul'; }
            $html .= '<li>' . md_inline($m[1]) . "</li>\n";
            continue;
        }

        $closeList();                                         // párrafo
        $para[] = $t;
    }
    $flushPara(); $closeList();
    if ($inCode) { $html .= "</code></pre>\n"; }
    return $html;
}

/** Formato en línea: negrita, cursiva y código. Escapa el HTML primero. */
function md_inline(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $s);
    $s = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $s);
    return $s;
}

/** Racha de días consecutivos con conexión (hasta hoy). */
function racha_dias(int $alumnoId): int
{
    $stmt = db()->prepare('SELECT fecha FROM conexiones WHERE alumno_id = ? ORDER BY fecha DESC LIMIT 60');
    $stmt->execute([$alumnoId]);
    $fechas = array_column($stmt->fetchAll(), 'fecha');
    $racha = 0;
    $cursor = new DateTime('today');
    $set = array_flip($fechas);
    // Permite que la racha valga aunque hoy aún no haya entrado, empezando por ayer.
    if (!isset($set[$cursor->format('Y-m-d')])) {
        $cursor->modify('-1 day');
    }
    while (isset($set[$cursor->format('Y-m-d')])) {
        $racha++;
        $cursor->modify('-1 day');
    }
    return $racha;
}
