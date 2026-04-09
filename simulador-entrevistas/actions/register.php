<?php
// actions/register.php
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

$username  = trim($body['username']  ?? '');
$email     = trim($body['email']     ?? '');
$password  = $body['password']       ?? '';

// ── Validación servidor ───────────────────────────────────
$errors = [];

if (!$username) {
    $errors[] = 'El nombre de usuario es obligatorio';
} elseif (strlen($username) < 3) {
    $errors[] = 'El nombre de usuario debe tener al menos 3 caracteres';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'El nombre de usuario solo puede tener letras, números y guión bajo';
}

if (!$email) {
    $errors[] = 'El correo es obligatorio';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El correo no es válido';
}

if (!$password) {
    $errors[] = 'La contraseña es obligatoria';
} elseif (strlen($password) < 8) {
    $errors[] = 'La contraseña debe tener al menos 8 caracteres';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => $errors[0]]);
    exit;
}

// ── Verificar que email y username no existan ─────────────
try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Verificar cuál de los dos ya existe para dar mensaje preciso
        $stmtEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmtEmail->execute([$email]);

        if ($stmtEmail->fetch()) {
            echo json_encode(['error' => 'Este correo ya está registrado']);
        } else {
            echo json_encode(['error' => 'Este nombre de usuario ya está en uso']);
        }
        exit;
    }

    // ── Crear usuario ─────────────────────────────────────
    $userId       = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (id, email, password_hash, username)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $email, $passwordHash, $username]);

    // ── Crear stats base ──────────────────────────────────
    // Se hace aquí una sola vez — nunca más hay que verificarlo
    $stmt = $pdo->prepare(
        'INSERT INTO user_game_stats (user_id) VALUES (?)'
    );
    $stmt->execute([$userId]);

    // ── Iniciar sesión automáticamente ────────────────────
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al crear la cuenta. Intenta de nuevo.']);
}