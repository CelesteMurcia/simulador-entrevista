<?php
// actions/login.php
session_start();
header('Content-Type: application/json');

require_once '../config/db.php';

// ── Solo POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Leer body JSON ────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

$email    = trim($body['email']    ?? '');
$password = $body['password']      ?? '';

// ── Validación básica ─────────────────────────────────────
if (!$email || !$password) {
    http_response_code(422);
    echo json_encode(['error' => 'Correo y contraseña son obligatorios']);
    exit;
}

// ── Buscar usuario ────────────────────────────────────────
try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Mensaje genérico intencionalmente — no revelar si el email existe
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Correo o contraseña incorrectos']);
        exit;
    }

    // ── Iniciar sesión ────────────────────────────────────
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al iniciar sesión. Intenta de nuevo.']);
}