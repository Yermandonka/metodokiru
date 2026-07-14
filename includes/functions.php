<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        redirect('index.php');
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

/* ---------- Uso diario / conexiones ---------- */

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

function uso_hoy(int $alumnoId): int
{
    $stmt = db()->prepare('SELECT minutos FROM uso_diario WHERE alumno_id = ? AND fecha = ?');
    $stmt->execute([$alumnoId, date('Y-m-d')]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/* ---------- IA (Fase 2) ---------- */

/**
 * Llama a la API de OpenAI para generar contenido académico.
 * Devuelve el texto o lanza RuntimeException si algo falla / no hay clave.
 */
function ai_generar(string $tema, int $edad = 12): string
{
    if (!OPENAI_API_KEY) {
        throw new RuntimeException('No hay clave de OpenAI configurada. Añádela en config/config.php (OPENAI_API_KEY).');
    }
    $prompt = "Eres un profesor del Método Kiru para niños de $edad años. "
        . "Sobre el tema: \"$tema\", crea contenido en español, claro y motivador, con este formato Markdown:\n"
        . "## Teoría (breve)\n## Ejemplos\n## Ejercicios (5, estilo Duolingo)\n## Soluciones\n"
        . "Adáptalo a 10-13 años.";

    $payload = json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Eres un tutor educativo para niños.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.7,
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_TIMEOUT => 45,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Error de conexión con OpenAI: ' . $err);
    }
    curl_close($ch);
    $data = json_decode($resp, true);
    if (isset($data['error'])) {
        throw new RuntimeException('OpenAI: ' . ($data['error']['message'] ?? 'error desconocido'));
    }
    return $data['choices'][0]['message']['content'] ?? '(Sin respuesta)';
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
