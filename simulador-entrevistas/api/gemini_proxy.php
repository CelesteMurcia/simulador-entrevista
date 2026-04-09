<?php
header('Content-Type: application/json');

require_once '../middleware/auth.php';
require_once '../config/db.php';
require_once '../config/gemini.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['question']) || empty($body['answer'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos question o answer']);
    exit;
}

$question       = trim($body['question']);
$answer         = trim($body['answer']);
$evaluationHint = trim($body['evaluation_hint'] ?? '');
$username       = trim($body['username'] ?? 'candidato');

// ── Límite de usos diarios ────────────────────────────────
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT role, gemini_uses_today, gemini_last_use FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }

    $isAdmin = $user['role'] === 'admin';
    $today   = date('Y-m-d');

    if (!$isAdmin) {
        // Resetear contador si es un día nuevo
        if ($user['gemini_last_use'] !== $today) {
            $pdo->prepare('UPDATE users SET gemini_uses_today = 0, gemini_last_use = ? WHERE id = ?')
                ->execute([$today, $currentUserId]);
            $uses = 0;
        } else {
            $uses = (int) $user['gemini_uses_today'];
        }

        if ($uses >= GEMINI_DAILY_LIMIT) {
            http_response_code(429);
            echo json_encode([
                'error'    => 'Límite diario de evaluaciones alcanzado.',
                'reset_en' => 'Mañana a las 00:00',
            ]);
            exit;
        }
    } else {
        // Admin: no se aplica límite, pero igual se lleva conteo
        if ($user['gemini_last_use'] !== $today) {
            $pdo->prepare('UPDATE users SET gemini_uses_today = 0, gemini_last_use = ? WHERE id = ?')
                ->execute([$today, $currentUserId]);
            $uses = 0;
        } else {
            $uses = (int) $user['gemini_uses_today'];
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
    exit;
}

// ── Prompt ────────────────────────────────────────────────
$promptBase = 'Eres un entrevistador tecnico sarcastico y directo. '
    . "El candidato se llama {$username}. "
    . 'Usa su nombre ocasionalmente en el feedback, especialmente cuando responde mal. '
    . 'Cuando el candidato responde bien lo reconoces pero sin exagerar. '
    . 'Cuando responde mal, haces un comentario ironico y breve. '
    . 'Nunca eres cruel, pero tampoco diplomatico en exceso.'
    . "\n\nEvalua la siguiente respuesta de entrevista tecnica."
    . "\n\nPregunta: \"{$question}\""
    . "\nRespuesta del candidato: \"{$answer}\"\n\n";

$promptCriteria = $evaluationHint
    ? "Criterio de evaluacion para esta pregunta: {$evaluationHint}\n\n"
    : '';

$promptFormat = "Evalua en escala 0 a 3:\n"
    . "- technical_accuracy: precision y correctitud tecnica\n"
    . "- clarity: claridad y estructura de la explicacion\n"
    . "- completeness: que tanto cubre lo importante de la pregunta\n\n"
    . "Responde UNICAMENTE con este JSON, sin texto adicional, sin backticks:\n"
    . '{"technical_accuracy":0,"clarity":0,"completeness":0,"feedback":"reaccion corta del entrevistador en espanol, max 12 palabras, con personalidad","quality":"good|neutral|bad"}'
    . "\n\nDonde quality es: good si total >= 7, neutral si total >= 4, bad si total < 4.";

$prompt  = $promptBase . $promptCriteria . $promptFormat;
$payload = json_encode([
    'contents'         => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1024],
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
     . GEMINI_MODEL . ':generateContent?key=' . GEMINI_KEY;

// ── Llamada a Gemini ──────────────────────────────────────
$response = false;
$httpCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        http_response_code(502);
        echo json_encode(['error' => 'Error de red: ' . $curlErr]);
        exit;
    }
} else {
    $context = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => $payload,
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $context);
    if (isset($http_response_header)) {
        preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
        $httpCode = isset($m[1]) ? (int) $m[1] : 0;
    }
    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'No se pudo conectar con Gemini']);
        exit;
    }
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Gemini respondió con código ' . $httpCode]);
    exit;
}

// ── Parsear respuesta ─────────────────────────────────────
$data   = json_decode($response, true);
$text   = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
$text   = trim(preg_replace('/```json|```/', '', $text));
$result = json_decode($text, true);

if (!$result || !isset($result['technical_accuracy'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Respuesta inesperada de Gemini', 'raw' => $text]);
    exit;
}

// ── Incrementar contador (siempre, incluso admins) ────────
try {
    $pdo->prepare('
        UPDATE users
        SET gemini_uses_today = gemini_uses_today + 1,
            gemini_uses       = gemini_uses + 1,
            gemini_last_use   = ?
        WHERE id = ?
    ')->execute([$today, $currentUserId]);
} catch (PDOException $e) {
    // No bloquear la respuesta si falla el contador
}

// Para admin: indicar ilimitado; para usuarios: usos restantes
$result['usos_restantes'] = $isAdmin ? -1 : GEMINI_DAILY_LIMIT - ($uses + 1);
$result['is_admin']       = $isAdmin;

echo json_encode($result);