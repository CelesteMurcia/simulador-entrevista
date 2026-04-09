<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// Solo admins
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int) ($body['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode([
        'error'        => 'Invalid id',
        'debug_body'   => file_get_contents('php://input'),
        'debug_post'   => $_POST,
        'debug_server' => $_SERVER['CONTENT_TYPE'] ?? 'no content-type',
    ]);
    exit;
}

try {
    // Verificar que exista antes de borrar
    $check = $pdo->prepare('SELECT id FROM interviews WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Interview not found']);
        exit;
    }

    // Los escenarios se borran en cascada por FK (ON DELETE CASCADE)
    $pdo->prepare('DELETE FROM interviews WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    // Devolver el mensaje real de error para facilitar debugging
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}