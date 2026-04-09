<?php
require_once '../middleware/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json');

// Solo admins
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

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int) ($body['id'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid id']); exit; }

try {
    $pdo->prepare('UPDATE interviews SET active = NOT active WHERE id = ?')->execute([$id]);
    $stmt = $pdo->prepare('SELECT active FROM interviews WHERE id = ?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    echo json_encode(['ok' => true, 'active' => (int) $row['active']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}