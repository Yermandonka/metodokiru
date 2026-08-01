<?php
/**
 * API de las tareas de inglés (mini-retos tipo Duolingo).
 *  - accion=nueva    → devuelve los ejercicios de una tarea según la configuración
 *                      del mentor (cantidad, temas, material PDF, tiempo) y la
 *                      destreza que evalúa la tarea. Se generan con Gemini
 *                      (diversos, sin repetir los ya vistos); si no hay clave o
 *                      la IA falla, se usa un banco local.
 *  - accion=terminar → suma los SkillCoins (según configuración, con tope diario),
 *                      guarda el resultado con la nota y el detalle de respuestas
 *                      (lo ven el alumno y el mentor) y avisa al mentor.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

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
$cfg = ingles_config($aid);

/** Banco local de ejercicios: se usa si no hay clave de IA o si Gemini falla. */
function ingles_banco(): array
{
    return [
        ['tipo' => 'eleccion', 'pregunta' => 'How do you say «perro» in English?', 'opciones' => ['dog', 'cat', 'horse', 'bird'], 'correcta' => 0,
         'leccion' => 'A dog is a pet. 🐶 Dogs say woof woof!'],
        ['tipo' => 'eleccion', 'pregunta' => 'How do you say «casa» in English?', 'opciones' => ['house', 'mouse', 'horse', 'hostel'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => 'What does "summer" mean?', 'opciones' => ['verano', 'invierno', 'sombrero', 'siempre'], 'correcta' => 0,
         'leccion' => 'Summer is hot. ☀️ In summer we go to the beach!'],
        ['tipo' => 'eleccion', 'pregunta' => 'How do you say «playa» in English?', 'opciones' => ['beach', 'peach', 'bench', 'branch'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => 'What does "ice cream" mean?', 'opciones' => ['helado', 'hielo', 'crema', 'iceberg'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => 'How do you say «amigo» in English?', 'opciones' => ['friend', 'family', 'fried', 'trend'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => 'What does "book" mean?', 'opciones' => ['libro', 'bota', 'banco', 'bosque'], 'correcta' => 0],
        ['tipo' => 'eleccion', 'pregunta' => 'How do you say «sol» in English?', 'opciones' => ['sun', 'son', 'soon', 'sand'], 'correcta' => 0],

        ['tipo' => 'hueco', 'frase' => 'I ___ eleven years old.', 'opciones' => ['am', 'is', 'are', 'be'], 'correcta' => 0,
         'leccion' => 'We use AM with I. 🧒 I am 11. You are 12.'],
        ['tipo' => 'hueco', 'frase' => 'She ___ tennis on Saturdays.', 'opciones' => ['plays', 'play', 'playing', 'played'], 'correcta' => 0,
         'leccion' => 'With he and she, the verb gets an S. 🎾 She plays. He plays.'],
        ['tipo' => 'hueco', 'frase' => 'We ___ to the beach in summer.', 'opciones' => ['go', 'goes', 'going', 'gone'], 'correcta' => 0],
        ['tipo' => 'hueco', 'frase' => 'There ___ a cat on the sofa.', 'opciones' => ['is', 'are', 'am', 'be'], 'correcta' => 0,
         'leccion' => 'One thing → there IS. Many things → there ARE. 🐱 There is a cat.'],
        ['tipo' => 'hueco', 'frase' => '___ you like pizza?', 'opciones' => ['Do', 'Does', 'Is', 'Are'], 'correcta' => 0,
         'leccion' => 'Questions with "like" start with DO. 🍕 Do you like pizza?'],
        ['tipo' => 'hueco', 'frase' => 'My brother ___ two dogs.', 'opciones' => ['has', 'have', 'is', 'do'], 'correcta' => 0],

        ['tipo' => 'ordenar', 'es' => 'Me gusta el chocolate', 'palabras' => ['I', 'like', 'chocolate'], 'solucion' => 'I like chocolate'],
        ['tipo' => 'ordenar', 'es' => 'El gato es negro', 'palabras' => ['The', 'cat', 'is', 'black'], 'solucion' => 'The cat is black'],
        ['tipo' => 'ordenar', 'es' => 'Yo juego al fútbol', 'palabras' => ['I', 'play', 'football'], 'solucion' => 'I play football'],
        ['tipo' => 'ordenar', 'es' => '¿Cuántos años tienes?', 'palabras' => ['How', 'old', 'are', 'you'], 'solucion' => 'How old are you',
         'leccion' => 'To ask the age we say: How old are you? 🎂'],
        ['tipo' => 'ordenar', 'es' => 'Hoy hace sol', 'palabras' => ['It', 'is', 'sunny', 'today'], 'solucion' => 'It is sunny today'],

        ['tipo' => 'parejas', 'pares' => [['es' => 'rojo', 'en' => 'red'], ['es' => 'azul', 'en' => 'blue'], ['es' => 'verde', 'en' => 'green'], ['es' => 'amarillo', 'en' => 'yellow']],
         'leccion' => 'Colors! ❤️ red, 💙 blue, 💚 green, 💛 yellow.'],
        ['tipo' => 'parejas', 'pares' => [['es' => 'gato', 'en' => 'cat'], ['es' => 'perro', 'en' => 'dog'], ['es' => 'pájaro', 'en' => 'bird'], ['es' => 'pez', 'en' => 'fish']]],
        ['tipo' => 'parejas', 'pares' => [['es' => 'manzana', 'en' => 'apple'], ['es' => 'pan', 'en' => 'bread'], ['es' => 'leche', 'en' => 'milk'], ['es' => 'queso', 'en' => 'cheese']]],
        ['tipo' => 'parejas', 'pares' => [['es' => 'madre', 'en' => 'mother'], ['es' => 'padre', 'en' => 'father'], ['es' => 'hermano', 'en' => 'brother'], ['es' => 'abuela', 'en' => 'grandmother']]],

        ['tipo' => 'escucha', 'texto' => 'apple', 'opciones' => ['apple', 'orange', 'animal', 'ankle'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'water', 'opciones' => ['water', 'winter', 'waiter', 'welcome'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'school', 'opciones' => ['school', 'cool', 'spoon', 'skirt'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'yellow', 'opciones' => ['yellow', 'hello', 'pillow', 'follow'], 'correcta' => 0],
        ['tipo' => 'escucha', 'texto' => 'birthday', 'opciones' => ['birthday', 'birds', 'Thursday', 'bakery'], 'correcta' => 0],

        ['tipo' => 'verdadero', 'afirmacion' => '"Cat" means «perro».', 'correcta' => false],
        ['tipo' => 'verdadero', 'afirmacion' => '"Blue" means «azul».', 'correcta' => true],
        ['tipo' => 'verdadero', 'afirmacion' => 'We say "good morning" at night.', 'correcta' => false],
        ['tipo' => 'verdadero', 'afirmacion' => '"Water" means «agua».', 'correcta' => true],

        ['tipo' => 'escribir', 'pregunta' => 'Write «gato» in English', 'solucion' => 'cat'],
        ['tipo' => 'escribir', 'pregunta' => 'Write «rojo» in English', 'solucion' => 'red'],
        ['tipo' => 'escribir', 'pregunta' => 'Write «uno» in English', 'solucion' => 'one'],
        ['tipo' => 'escribir', 'pregunta' => 'Write «leche» in English', 'solucion' => 'milk'],

        ['tipo' => 'emoji', 'emoji' => '🍎', 'opciones' => ['apple', 'bread', 'egg', 'cheese'], 'correcta' => 0],
        ['tipo' => 'emoji', 'emoji' => '🐟', 'opciones' => ['fish', 'dog', 'bird', 'horse'], 'correcta' => 0],
        ['tipo' => 'emoji', 'emoji' => '☀️', 'opciones' => ['sun', 'moon', 'star', 'rain'], 'correcta' => 0],
        ['tipo' => 'emoji', 'emoji' => '🏠', 'opciones' => ['house', 'school', 'car', 'tree'], 'correcta' => 0],
    ];
}

/** Valida un ejercicio (de la IA o del banco) y baraja sus opciones. Null si no es válido. */
function ingles_valido(array $e): ?array
{
    $tipo = $e['tipo'] ?? '';

    // Mini-lección opcional (texto sencillo en inglés que se muestra antes del reto).
    if (isset($e['leccion'])) {
        $leccion = is_string($e['leccion']) ? mb_substr(trim($e['leccion']), 0, 250) : '';
        if ($leccion === '') {
            unset($e['leccion']);
        } else {
            $e['leccion'] = $leccion;
        }
    }

    if (in_array($tipo, ['eleccion', 'hueco', 'escucha', 'emoji'], true)) {
        $ops = array_values(array_filter($e['opciones'] ?? [], fn($o) => is_string($o) && $o !== ''));
        $i = (int) ($e['correcta'] ?? -1);
        if (count($ops) < 3 || !isset($ops[$i])) {
            return null;
        }
        if ($tipo === 'eleccion' && empty($e['pregunta'])) return null;
        if ($tipo === 'hueco' && (empty($e['frase']) || !str_contains($e['frase'], '___'))) return null;
        if ($tipo === 'escucha' && empty($e['texto'])) return null;
        if ($tipo === 'emoji' && empty($e['emoji'])) return null;
        $correctaTxt = $ops[$i];
        shuffle($ops);
        $e['opciones'] = $ops;
        $e['correcta'] = array_search($correctaTxt, $ops, true);
        return $e;
    }

    if ($tipo === 'verdadero') {
        if (empty($e['afirmacion']) || !isset($e['correcta'])) {
            return null;
        }
        $e['correcta'] = filter_var($e['correcta'], FILTER_VALIDATE_BOOLEAN);
        return $e;
    }

    if ($tipo === 'escribir') {
        if (empty($e['pregunta']) || empty($e['solucion']) || !is_string($e['solucion'])) {
            return null;
        }
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
        'eleccion'  => $e['pregunta'],
        'hueco'     => $e['frase'],
        'ordenar'   => $e['solucion'],
        'escucha'   => $e['texto'],
        'parejas'   => implode(',', array_column($e['pares'], 'en')),
        'verdadero' => $e['afirmacion'],
        'escribir'  => 'w:' . $e['solucion'],
        'emoji'     => $e['emoji'],
        default     => json_encode($e),
    };
}

$accion = $_POST['accion'] ?? '';

// Tarea a la que pertenece la lección (siempre debe ser una tarea del alumno).
$tarea = null;
$tareaId = (int) ($_POST['tarea'] ?? 0);
if ($tareaId > 0) {
    ingles_tablas();
    $st = db()->prepare('SELECT * FROM ingles_tareas WHERE id = ? AND alumno_id = ?');
    $st->execute([$tareaId, $aid]);
    $tarea = $st->fetch() ?: null;
}
// Tipos de reto que evalúan la destreza de la tarea (vacío = todos).
$tiposTarea = $tarea ? ingles_tipos_de($tarea['destreza']) : [];

if ($accion === 'nueva') {
    $vistos = $_SESSION['ingles_vistos'] ?? [];
    $leccion = [];
    $conIA = false;
    $cantidad = $cfg['ejercicios'];

    if (GEMINI_API_KEY) {
        try {
            $material = ingles_material_activo($aid);
            foreach (ai_ingles((int) ($alumno['edad'] ?: 12), $cantidad, $vistos, $cfg['temas'], $material, $tiposTarea) as $e) {
                if (is_array($e) && (!$tiposTarea || in_array($e['tipo'] ?? '', $tiposTarea, true))
                    && ($ok = ingles_valido($e))) {
                    $leccion[] = $ok;
                }
            }
            $conIA = count($leccion) > 0;
        } catch (Throwable $ex) {
            // Sin conexión o error de la IA: se completa con el banco local.
        }
    }

    if (count($leccion) < $cantidad) {
        $banco = ingles_banco();
        if ($tiposTarea) {
            $banco = array_values(array_filter($banco, fn($e) => in_array($e['tipo'], $tiposTarea, true)));
        }
        shuffle($banco);
        // Primero los que el alumno no ha visto hace poco.
        usort($banco, fn($a, $b) => (int) in_array(ingles_clave($a), $vistos, true) <=> (int) in_array(ingles_clave($b), $vistos, true));
        foreach ($banco as $e) {
            if (count($leccion) >= $cantidad) {
                break;
            }
            if ($ok = ingles_valido($e)) {
                $leccion[] = $ok;
            }
        }
    }
    $leccion = array_slice($leccion, 0, $cantidad);
    shuffle($leccion);

    // Evita dos retos del mismo tipo seguidos (cuando hay variedad suficiente).
    for ($i = 1, $n = count($leccion); $i < $n; $i++) {
        if ($leccion[$i]['tipo'] === $leccion[$i - 1]['tipo']) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($leccion[$j]['tipo'] !== $leccion[$i - 1]['tipo']) {
                    [$leccion[$i], $leccion[$j]] = [$leccion[$j], $leccion[$i]];
                    break;
                }
            }
        }
    }

    foreach ($leccion as $e) {
        $vistos[] = ingles_clave($e);
    }
    $_SESSION['ingles_vistos'] = array_slice(array_values(array_unique($vistos)), -60);

    echo json_encode([
        'ejercicios'    => $leccion,
        'ia'            => $conIA,
        'tiempo'        => $cfg['tiempo_seg'],
        'coins_acierto' => $cfg['coins_acierto'],
    ]);

} elseif ($accion === 'terminar') {
    // Detalle de respuestas (lo revisan el alumno y el mentor):
    // [{tipo, clave, ok, pregunta, respuesta, solucion}, ...]
    $tiposValidos = ['eleccion', 'hueco', 'ordenar', 'parejas', 'escucha', 'verdadero', 'escribir', 'emoji'];
    $detalle = [];
    $crudo = json_decode((string) ($_POST['detalle'] ?? ''), true);
    if (is_array($crudo)) {
        foreach (array_slice($crudo, 0, max(1, $cfg['ejercicios'])) as $d) {
            if (is_array($d) && in_array($d['tipo'] ?? '', $tiposValidos, true)) {
                $detalle[] = [
                    'tipo'      => $d['tipo'],
                    'clave'     => limpiar_texto($d['clave'] ?? '', 150),
                    'ok'        => (bool) ($d['ok'] ?? false),
                    'pregunta'  => limpiar_texto($d['pregunta'] ?? '', 200),
                    'respuesta' => limpiar_texto($d['respuesta'] ?? '', 120),
                    'solucion'  => limpiar_texto($d['solucion'] ?? '', 120),
                ];
            }
        }
    }

    if ($detalle) {
        $total    = count($detalle);
        $aciertos = count(array_filter($detalle, fn($d) => $d['ok']));
    } else {
        $total    = $cfg['ejercicios'];
        $aciertos = max(0, min($total, (int) ($_POST['aciertos'] ?? 0)));
    }

    $st = db()->prepare("SELECT COALESCE(SUM(cantidad),0) FROM skillcoins_log
                         WHERE alumno_id = ? AND motivo LIKE 'Inglés%' AND DATE(fecha) = CURDATE()");
    $st->execute([$aid]);
    $hoy = (int) $st->fetchColumn();
    $coins = max(0, min($aciertos * $cfg['coins_acierto'], $cfg['tope_diario'] - $hoy));
    if ($coins > 0) {
        sc_add($aid, $coins, 'Inglés: ' . ($tarea ? $tarea['titulo'] : 'práctica'));
    }
    $nota = nota_sobre_10($aciertos, $total);

    // Guarda el resultado (nota y respuestas) para el alumno y el mentor.
    ingles_tablas();
    db()->prepare('INSERT INTO ingles_resultados (alumno_id, tarea_id, aciertos, total, coins, detalle) VALUES (?,?,?,?,?,?)')
        ->execute([$aid, $tarea ? (int) $tarea['id'] : null, $aciertos, $total, $coins,
            $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null]);

    // Avisa al mentor de la tarea terminada.
    if (!empty($alumno['mentor_id'])) {
        $que = $tarea ? 'la tarea de inglés «' . $tarea['titulo'] . '»' : 'una práctica de inglés';
        db()->prepare('INSERT INTO notificaciones (mentor_id, alumno_id, mensaje) VALUES (?,?,?)')
            ->execute([
                (int) $alumno['mentor_id'],
                $aid,
                '🇬🇧 ' . $user['nombre'] . " completó $que: $aciertos/$total · nota " . nota_txt($nota) . " (+$coins 🪙)",
            ]);
    }

    echo json_encode(['coins' => $coins, 'hoy' => $hoy + $coins, 'tope' => $cfg['tope_diario'], 'nota' => $nota]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'accion desconocida']);
}
