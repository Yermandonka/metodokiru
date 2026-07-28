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
 * Devuelve la lista de ejercicios sin validar; lanza RuntimeException si falla.
 */
function ai_ingles(int $edad, int $cantidad, array $evitar = []): array
{
    $temas = ['los animales', 'la comida', 'la familia', 'el colegio', 'los deportes',
        'el verano y la playa', 'los viajes', 'la ropa', 'la casa', 'el cuerpo',
        'los colores y los números', 'las rutinas diarias', 'los hobbies', 'la ciudad',
        'el tiempo y las estaciones', 'las emociones', 'la naturaleza', 'las fiestas'];
    $tema = $temas[array_rand($temas)];

    $prompt = "Crea exactamente $cantidad ejercicios de inglés básico (nivel A1-A2, estilo Duolingo) "
        . "para un niño hispanohablante de $edad años. Tema principal: $tema (puedes mezclar con vocabulario general).\n"
        . "Hay 8 tipos de ejercicio; usa al menos 6 distintos en cada lección y mézclalos. Formato exacto:\n"
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
