<?php
/**
 * API del juego «Inglés de verano» (mini-juegos tipo Duolingo).
 *  - accion=nueva    → devuelve una lección de ejercicios variados.
 *                      Se generan con Gemini (diversos, sin repetir los ya vistos);
 *                      si no hay clave o la IA falla, se usa un banco local.
 *  - accion=terminar → suma los SkillCoins de la lección (1 por acierto, con tope diario).
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

const INGLES_EJERCICIOS  = 10; // ejercicios por lección
const INGLES_TOPE_DIARIO = 50; // SkillCoins máximas al día jugando

$user = current_user();
if (!$user || $user['rol'] !== 'alumno') {
    http_response_code(401);
    echo json_encode(['error' => 'no auth']);
    exit;
}
$alumno = alumno_por_usuario((int) $user['id']);
if (!$alumno) {
    http_response_code(404);
    echo json_encode(['error' => 'sin ficha']);
    exit;
}
$aid = (int) $alumno['id'];

/** Banco local de ejercicios: se usa si no hay clave de IA o si Gemini falla. */
function ingles_banco(): array
{
    return [
        ['tipo' => 'eleccion', 'pregunta' => '¿Cómo se dice «perro» en inglés?', 'opciones' => ['dog', 'cat', 'horse', 'bird'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Cómo se dice «casa» en inglés?', 'opciones' => ['house', 'mouse', 'horse', 'hostel'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Qué significa "summer"?', 'opciones' => ['verano', 'invierno', 'sombrero', 'siempre'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Cómo se dice «playa» en inglés?', 'opciones' => ['beach', 'peach', 'bench', 'branch'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Qué significa "ice cream"?', 'opciones' => ['helado', 'hielo', 'crema', 'iceberg'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Cómo se dice «amigo» en inglés?', 'opciones' => ['friend', 'family', 'fried', 'trend'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Qué significa "book"?', 'opciones' => ['libro', 'bota', 'banco', 'bosque'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => '¿Cómo se dice «sol» en inglés?', 'opciones' => ['sun', 'son', 'soon', 'sand'], 'correcta' => 0],

        ['tipo' => 'hueco', 'frase' => 'I ___ eleven years old.', 'opciones' => ['am', 'is', 'are', 'be'], 'correcta' => 0],
        ['tipo' => 'hueco', 'frase' => 'She ___ tennis on Saturdays.', 'opciones' => ['plays', 'play', 'playing', 'played'], 'correcta' => 0],
        ['tipo' => 'hueco', 'frase' => 'We ___ to the beach in summer.', 'opciones' => ['go', 'goes', 'going', 'gone'], 'correcta' => 0],
        ['tipo' => 'hueco', 'frase' => 'There ___ a cat on the sofa.', 'opciones' => ['is', 'are', 'am', 'be'], 'correcta' => 0],
        ['tipo' => 'hueco', 'frase' => '___ you like pizza?', 'opciones' => ['Do', 'Does', 'Is', 'Are'], 'correcta' => 0],
        ['tipo' => 'hueco', 'frase' => 'My brother ___ two dogs.', 'opciones' => ['has', 'have', 'is', 'do'], 'correcta' => 0],

        ['tipo' => 'ordenar', 'es' => 'Me gusta el chocolate', 'palabras' => ['I', 'like', 'chocolate'], 'solucion' => 'I like chocolate'],
        ['tipo' => 'ordenar', 'es' => 'El gato es negro', 'palabras' => ['The', 'cat', 'is', 'black'], 'solucion' => 'The cat is black'],
        ['tipo' => 'ordenar', 'es' => 'Yo juego al fútbol', 'palabras' => ['I', 'play', 'football'], 'solucion' => 'I play football'],
        ['tipo' => 'ordenar', 'es' => '¿Cuántos años tienes?', 'palabras' => ['How', 'old', 'are', 'you'], 'solucion' => 'How old are you'],
        ['tipo' => 'ordenar', 'es' => 'Hoy hace sol', 'palabras' => ['It', 'is', 'sunny', 'today'], 'solucion' => 'It is sunny today'],

        ['tipo' => 'parejas', 'pares' => [['es' => 'rojo', 'en' => 'red'], ['es' => 'azul', 'en' => 'blue'], ['es' => 'verde', 'en' => 'green'], ['es' => 'amarillo', 'en' => 'yellow']]],
        ['tipo' => 'parejas', 'pares' => [['es' => 'gato', 'en' => 'cat'], ['es' => 'perro', 'en' => 'dog'], ['es' => 'pájaro', 'en' => 'bird'], ['es' => 'pez', 'en' => 'fish']]],
        ['tipo' => 'parejas', 'pares' => [['es' => 'manzana', 'en' => 'apple'], ['es' => 'pan', 'en' => 'bread'], ['es' => 'leche', 'en' => 'milk'], ['es' => 'queso', 'en' => 'cheese']]],
        ['tipo' => 'parejas', 'pares' => [['es' => 'madre', 'en' => 'mother'], ['es' => 'padre', 'en' => 'father'], ['es' => 'hermano', 'en' => 'brother'], ['es' => 'abuela', 'en' => 'grandmother']]],

        ['tipo' => 'escucha', 'texto' => 'apple', 'opciones' => ['apple', 'orange', 'animal', 'ankle'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'water', 'opciones' => ['water', 'winter', 'waiter', 'welcome'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'school', 'opciones' => ['school', 'cool', 'spoon', 'skirt'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'yellow', 'opciones' => ['yellow', 'hello', 'pillow', 'follow'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'birthday', 'opciones' => ['birthday', 'birds', 'Thursday', 'bakery'], 'correcta' => 0],
    ];
}

/** Valida un ejercicio (de la IA o del banco) y baraja sus opciones. Null si no es válido. */
function ingles_valido(array $e): ?array
{
    $tipo = $e['tipo'] ?? '';

    if (in_array($tipo, ['eleccion', 'hueco', 'escucha'], true)) {
        $ops = array_values(array_filter($e['opciones'] ?? [], fn($o) => is_string($o) && $o !== ''));
        $i = (int) ($e['correcta'] ?? -1);
        if (count($ops) < 3 || !isset($ops[$i])) {
            return null;
        }
        if ($tipo === 'eleccion' && empty($e['pregunta'])) return null;
        if ($tipo === 'hueco' && (empty($e['frase']) || !str_contains($e['frase'], '___'))) return null;
        if ($tipo === 'escucha' && empty($e['texto'])) return null;
        $correctaTxt = $ops[$i];
        shuffle($ops);
        $e['opciones'] = $ops;
        $e['correcta'] = array_search($correctaTxt, $ops, true);
        return $e;
    }

    if ($tipo === 'ordenar') {
        $palabras = array_values(array_filter($e['palabras'] ?? [], fn($p) => is_string($p) && $p !== ''));
        if (count($palabras) < 2 || empty($e['solucion']) || empty($e['es'])) {
            return null;
        }
        shuffle($palabras);
        $e['palabras'] = $palabras;
        return $e;
    }

    if ($tipo === 'parejas') {
        $pares = [];
        foreach (($e['pares'] ?? []) as $p) {
            if (is_array($p) && !empty($p['es']) && !empty($p['en'])) {
                $pares[] = ['es' => (string) $p['es'], 'en' => (string) $p['en']];
            }
        }
        if (count($pares) < 3) {
            return null;
        }
        $e['pares'] = array_slice($pares, 0, 4);
        return $e;
    }

    return null;
}

/** Clave del ejercicio para recordar lo ya visto y no repetirlo. */
function ingles_clave(array $e): string
{
    return match ($e['tipo']) {
        'eleccion' => $e['pregunta'],
        'hueco'    => $e['frase'],
        'ordenar'  => $e['solucion'],
        'escucha'  => $e['texto'],
        'parejas'  => implode(',', array_column($e['pares'], 'en')),
        default    => json_encode($e),
    };
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'nueva') {
    $vistos = $_SESSION['ingles_vistos'] ?? [];
    $leccion = [];
    $conIA = false;

    if (GEMINI_API_KEY) {
        try {
            foreach (ai_ingles((int) ($alumno['edad'] ?: 12), INGLES_EJERCICIOS, $vistos) as $e) {
                if (is_array($e) && ($ok = ingles_valido($e))) {
                    $leccion[] = $ok;
                }
            }
            $conIA = count($leccion) > 0;
        } catch (Throwable $ex) {
            // Sin conexión o error de la IA: se completa con el banco local.
        }
    }

    if (count($leccion) < INGLES_EJERCICIOS) {
        $banco = ingles_banco();
        shuffle($banco);
        // Primero los que el alumno no ha visto hace poco.
        usort($banco, fn($a, $b) => (int) in_array(ingles_clave($a), $vistos, true) <=> (int) in_array(ingles_clave($b), $vistos, true));
        foreach ($banco as $e) {
            if (count($leccion) >= INGLES_EJERCICIOS) {
                break;
            }
            if ($ok = ingles_valido($e)) {
                $leccion[] = $ok;
            }
        }
    }
    $leccion = array_slice($leccion, 0, INGLES_EJERCICIOS);
    shuffle($leccion);

    foreach ($leccion as $e) {
        $vistos[] = ingles_clave($e);
    }
    $_SESSION['ingles_vistos'] = array_slice(array_values(array_unique($vistos)), -60);

    echo json_encode(['ejercicios' => $leccion, 'ia' => $conIA]);

} elseif ($accion === 'terminar') {
    $aciertos = max(0, min(INGLES_EJERCICIOS, (int) ($_POST['aciertos'] ?? 0)));
    $st = db()->prepare("SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
                         WHERE alumno_id = ? AND motivo LIKE 'Inglés%' AND DATE(fecha) = CURDATE()");
    $st->execute([$aid]);
    $hoy = (int) $st->fetchColumn();
    $coins = max(0, min($aciertos, INGLES_TOPE_DIARIO - $hoy));
    if ($coins > 0) {
        sc_add($aid, $coins, 'Inglés: lección de verano');
    }
    echo json_encode(['coins' => $coins, 'hoy' => $hoy + $coins, 'tope' => INGLES_TOPE_DIARIO]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'accion desconocida']);
}
