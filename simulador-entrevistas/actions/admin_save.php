<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// ── Solo admins ───────────────────────────────────────────
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$editId    = isset($body['id']) ? (int) $body['id'] : null;
$iv        = $body['interview'] ?? [];
$questions = $body['questions']  ?? [];

// ── Validar campos requeridos ────────────────────────────
$category = preg_replace('/[^a-z_]/', '', strtolower($iv['category'] ?? ''));
$slug     = preg_replace('/[^a-z0-9_\-]/', '', strtolower($iv['slug'] ?? ''));
$level    = in_array($iv['level'] ?? '', ['easy','medium','hard']) ? $iv['level'] : null;
$title    = trim($iv['title'] ?? '');
$style    = trim($iv['interviewer_style'] ?? '');

if (!$category || !$slug || !$level || !$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos requeridos']);
    exit;
}
if (empty($questions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Se necesita al menos una pregunta']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($editId) {
        // ── Actualizar entrevista ─────────────────────────
        $pdo->prepare('
            UPDATE interviews
            SET category = ?, slug = ?, level = ?, title = ?, interviewer_style = ?
            WHERE id = ?
        ')->execute([$category, $slug, $level, $title, $style, $editId]);

        // Borrar escenarios anteriores y reinsertar
        $pdo->prepare('DELETE FROM scenarios WHERE interview_id = ?')->execute([$editId]);
        $interviewId = $editId;

    } else {
        // ── Crear entrevista ──────────────────────────────
        // Verificar que no exista ya esa combinación
        $check = $pdo->prepare('
            SELECT id FROM interviews
            WHERE type = "technical" AND category = ? AND slug = ? AND level = ?
        ');
        $check->execute([$category, $slug, $level]);
        if ($check->fetch()) {
            $pdo->rollBack();
            echo json_encode(['error' => "Ya existe una entrevista $slug/$level en $category"]);
            exit;
        }

        $pdo->prepare('
            INSERT INTO interviews (type, category, slug, level, title, interviewer_style)
            VALUES ("technical", ?, ?, ?, ?, ?)
        ')->execute([$category, $slug, $level, $title, $style]);

        $interviewId = (int) $pdo->lastInsertId();
    }

    // ── Insertar escenarios ───────────────────────────────
    $stmtScenario = $pdo->prepare('
        INSERT INTO scenarios (interview_id, position, phase, question, evaluation_hint)
        VALUES (?, ?, ?, ?, ?)
    ');

    foreach ($questions as $q) {
        $pos   = (int)   ($q['position'] ?? 1);
        $phase = in_array($q['phase'] ?? '', ['intro','main']) ? $q['phase'] : 'main';
        $text  = trim($q['question']        ?? '');
        $hint  = trim($q['evaluation_hint'] ?? '');

        if (!$text) continue; // Saltar preguntas vacías

        $stmtScenario->execute([$interviewId, $pos, $phase, $text, $hint]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $interviewId]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}